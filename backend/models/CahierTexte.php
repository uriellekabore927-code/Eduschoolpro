<?php

require_once __DIR__ . '/BaseModel.php';
require_once __DIR__ . '/SignatureConfig.php';

class CahierTexte extends BaseModel
{
    public function all(?array $user = null): array
    {
        $sql = '
            SELECT ct.*, m.libelle AS matiere, cl.libelle AS classe, c.jour, c.heure_debut, c.heure_fin
            FROM cahiers_texte ct
            INNER JOIN creneaux c ON c.id = ct.id_creneau
            INNER JOIN emploi_temps et ON et.id = c.id_emploi_temps
            INNER JOIN classes cl ON cl.id = et.id_classe
            INNER JOIN matieres m ON m.id = c.id_matiere
            WHERE 1=1
        ';
        $params = [];

        if (($user['role'] ?? '') === 'enseignant') {
            $teacherId = ($user['type_lien'] ?? '') === 'enseignant' ? (int) ($user['id_lien'] ?? 0) : 0;
            if (!$teacherId) {
                return [];
            }
            $sql .= ' AND c.id_enseignant = :id_enseignant';
            $params['id_enseignant'] = $teacherId;
        }

        if (($user['role'] ?? '') === 'delegue') {
            $classId = ($user['type_lien'] ?? '') === 'classe' ? (int) ($user['id_lien'] ?? 0) : 0;
            if (!$classId) {
                return [];
            }
            $sql .= ' AND et.id_classe = :id_classe';
            $params['id_classe'] = $classId;
        }

        $sql .= ' ORDER BY ct.date_creation DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function create(array $data): string
    {
        $existingId = $this->findIdByCreneau((int) $data['id_creneau']);
        if ($existingId) {
            $this->update($existingId, $data);
            return (string) $existingId;
        }

        $stmt = $this->db->prepare('
            INSERT INTO cahiers_texte (id_creneau, id_delegue, titre_cours, points_abordes, niveau_avancement, travaux_demandes, observations, statut)
            VALUES (:id_creneau, :id_delegue, :titre_cours, :points_abordes, :niveau_avancement, :travaux_demandes, :observations, :statut)
        ');
        $stmt->execute([
            'id_creneau' => $data['id_creneau'],
            'id_delegue' => $data['id_delegue'] ?? null,
            'titre_cours' => $data['titre_cours'],
            'points_abordes' => $data['points_abordes'],
            'niveau_avancement' => $data['niveau_avancement'] ?? null,
            'travaux_demandes' => $data['travaux_demandes'] ?? null,
            'observations' => $data['observations'] ?? null,
            'statut' => $data['statut'] ?? 'brouillon',
        ]);
        return (string) $this->db->lastInsertId();
    }

    private function findIdByCreneau(int $creneauId): ?int
    {
        $stmt = $this->db->prepare('
            SELECT id
            FROM cahiers_texte
            WHERE id_creneau = :id_creneau
            ORDER BY date_creation DESC, id DESC
            LIMIT 1
        ');
        $stmt->execute(['id_creneau' => $creneauId]);
        $id = $stmt->fetchColumn();
        return $id ? (int) $id : null;
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare('
            UPDATE cahiers_texte
            SET titre_cours = :titre_cours, points_abordes = :points_abordes, niveau_avancement = :niveau_avancement,
                travaux_demandes = :travaux_demandes, observations = :observations, statut = :statut
            WHERE id = :id
        ');
        return $stmt->execute([
            'id' => $id,
            'titre_cours' => $data['titre_cours'],
            'points_abordes' => $data['points_abordes'],
            'niveau_avancement' => $data['niveau_avancement'] ?? null,
            'travaux_demandes' => $data['travaux_demandes'] ?? null,
            'observations' => $data['observations'] ?? null,
            'statut' => $data['statut'] ?? 'brouillon',
        ]);
    }

    public function sign(int $id, array $data): array
    {
        $typeSignataire = (string) ($data['type_signataire'] ?? '');
        $signatureBase64 = trim((string) ($data['signature_base64'] ?? ''));
        $userId = (int) ($data['id_utilisateur'] ?? 0);

        if (!$userId || !$typeSignataire || $signatureBase64 === '') {
            jsonResponse(false, 'Les informations de signature sont incomplètes.', null, 422);
        }

        $existing = $this->db->prepare('SELECT id FROM signatures WHERE id_cahier = :id_cahier AND type_signataire = :type_signataire LIMIT 1');
        $existing->execute([
            'id_cahier' => $id,
            'type_signataire' => $typeSignataire,
        ]);

        if ($existing->fetch()) {
            $this->db->prepare('
                UPDATE signatures
                SET id_utilisateur = :id_utilisateur, signature_base64 = :signature_base64, horodatage = NOW()
                WHERE id_cahier = :id_cahier AND type_signataire = :type_signataire
            ')->execute([
                'id_cahier' => $id,
                'type_signataire' => $typeSignataire,
                'id_utilisateur' => $userId,
                'signature_base64' => $signatureBase64,
            ]);
        } else {
            $stmt = $this->db->prepare('
                INSERT INTO signatures (id_cahier, type_signataire, id_utilisateur, signature_base64, horodatage)
                VALUES (:id_cahier, :type_signataire, :id_utilisateur, :signature_base64, NOW())
            ');
            $stmt->execute([
                'id_cahier' => $id,
                'type_signataire' => $typeSignataire,
                'id_utilisateur' => $userId,
                'signature_base64' => $signatureBase64,
            ]);
        }

        // Dès qu'une signature est apposée, le cahier n'est plus seulement en brouillon.
        $this->db->prepare('UPDATE cahiers_texte SET statut = "signe" WHERE id = :id AND statut = "brouillon"')
            ->execute(['id' => $id]);

        $saved = $this->db->prepare('
            SELECT s.*,
                   COALESCE(NULLIF(CONCAT(u.prenom, " ", u.nom), " "), CONCAT("Utilisateur #", s.id_utilisateur)) AS signataire_nom
            FROM signatures s
            LEFT JOIN utilisateurs u ON u.id = s.id_utilisateur
            WHERE s.id_cahier = :id_cahier AND s.type_signataire = :type_signataire
            ORDER BY s.horodatage DESC
            LIMIT 1
        ');
        $saved->execute([
            'id_cahier' => $id,
            'type_signataire' => $typeSignataire,
        ]);
        $row = $saved->fetch();

        if (!$row) {
            jsonResponse(false, 'La signature n’a pas pu être confirmée après enregistrement.', null, 500);
        }

        return $row;
    }

    public function close(int $id): bool
    {
        $signatureConfig = new SignatureConfig();
        $requiredRoles = array_values(array_unique(array_column(
            array_filter(
                $signatureConfig->byDocument('cahier'),
                static fn (array $row): bool => (int) ($row['obligatoire'] ?? 1) === 1
            ),
            'role_signataire'
        )));

        $creneauStmt = $this->db->prepare('SELECT id_creneau FROM cahiers_texte WHERE id = :id LIMIT 1');
        $creneauStmt->execute(['id' => $id]);
        $creneauId = (int) ($creneauStmt->fetchColumn() ?: 0);
        if (!$creneauId) {
            jsonResponse(false, 'Séance introuvable pour ce cahier.', null, 404);
        }

        // Vérifier sur l'ensemble de la séance (créneau) pour gérer l'historique
        // où les signatures peuvent être liées à des versions précédentes du cahier.
        $existingRoles = array_map(
            static fn (array $row): string => (string) ($row['type_signataire'] ?? ''),
            $this->signaturesByCreneau($creneauId)
        );

        foreach ($requiredRoles as $role) {
            if (!in_array($role, $existingRoles, true)) {
                jsonResponse(false, 'Toutes les signatures requises ne sont pas encore apposées sur ce cahier.', null, 400);
            }
        }

        $stmt = $this->db->prepare('UPDATE cahiers_texte SET statut = "cloture", heure_fin_reelle = NOW() WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    public function signaturesByCahier(int $id): array
    {
        $stmt = $this->db->prepare('
            SELECT s.*,
                   COALESCE(NULLIF(CONCAT(u.prenom, " ", u.nom), " "), CONCAT("Utilisateur #", s.id_utilisateur)) AS signataire_nom
            FROM signatures s
            LEFT JOIN utilisateurs u ON u.id = s.id_utilisateur
            WHERE s.id_cahier = :id_cahier
            ORDER BY s.horodatage
        ');
        $stmt->execute(['id_cahier' => $id]);
        $rows = $stmt->fetchAll();
        if (!empty($rows)) {
            return $rows;
        }

        // Fallback: certaines anciennes données peuvent stocker les signatures
        // sur une fiche antérieure de la même séance (même créneau).
        $creneauStmt = $this->db->prepare('SELECT id_creneau FROM cahiers_texte WHERE id = :id LIMIT 1');
        $creneauStmt->execute(['id' => $id]);
        $creneauId = (int) ($creneauStmt->fetchColumn() ?: 0);
        if (!$creneauId) {
            return [];
        }

        $legacyStmt = $this->db->prepare('
            SELECT s.*,
                   COALESCE(NULLIF(CONCAT(u.prenom, " ", u.nom), " "), CONCAT("Utilisateur #", s.id_utilisateur)) AS signataire_nom
            FROM signatures s
            INNER JOIN cahiers_texte ct ON ct.id = s.id_cahier
            LEFT JOIN utilisateurs u ON u.id = s.id_utilisateur
            WHERE ct.id_creneau = :id_creneau
            ORDER BY s.horodatage DESC
        ');
        $legacyStmt->execute(['id_creneau' => $creneauId]);
        $legacyRows = $legacyStmt->fetchAll();
        if (empty($legacyRows)) {
            return [];
        }

        $latestByRole = [];
        foreach ($legacyRows as $row) {
            $role = (string) ($row['type_signataire'] ?? '');
            if (!$role || isset($latestByRole[$role])) {
                continue;
            }
            $latestByRole[$role] = $row;
        }

        return array_values($latestByRole);
    }

    public function signaturesByCreneau(int $creneauId): array
    {
        $stmt = $this->db->prepare('
            SELECT s.*,
                   COALESCE(NULLIF(CONCAT(u.prenom, " ", u.nom), " "), CONCAT("Utilisateur #", s.id_utilisateur)) AS signataire_nom
            FROM signatures s
            INNER JOIN cahiers_texte ct ON ct.id = s.id_cahier
            LEFT JOIN utilisateurs u ON u.id = s.id_utilisateur
            WHERE ct.id_creneau = :id_creneau
            ORDER BY s.horodatage DESC
        ');
        $stmt->execute(['id_creneau' => $creneauId]);
        $rows = $stmt->fetchAll();
        if (empty($rows)) {
            return [];
        }

        $latestByRole = [];
        foreach ($rows as $row) {
            $role = (string) ($row['type_signataire'] ?? '');
            if (!$role || isset($latestByRole[$role])) {
                continue;
            }
            $latestByRole[$role] = $row;
        }

        return array_values($latestByRole);
    }

    public function userCanAccessCreneau(int $creneauId, array $user): bool
    {
        if (in_array($user['role'] ?? '', ['administrateur', 'admin', 'surveillant'], true)) {
            return true;
        }

        $stmt = $this->db->prepare('
            SELECT c.id_enseignant, et.id_classe
            FROM creneaux c
            INNER JOIN emploi_temps et ON et.id = c.id_emploi_temps
            WHERE c.id = :id
            LIMIT 1
        ');
        $stmt->execute(['id' => $creneauId]);
        $row = $stmt->fetch();
        if (!$row) {
            return false;
        }

        if (($user['role'] ?? '') === 'enseignant') {
            return ($user['type_lien'] ?? '') === 'enseignant'
                && (int) ($user['id_lien'] ?? 0) === (int) $row['id_enseignant'];
        }

        if (($user['role'] ?? '') === 'delegue') {
            return ($user['type_lien'] ?? '') === 'classe'
                && (int) ($user['id_lien'] ?? 0) === (int) $row['id_classe'];
        }

        return false;
    }

    public function userCanAccessCahier(int $cahierId, array $user): bool
    {
        if (in_array($user['role'] ?? '', ['administrateur', 'admin', 'surveillant'], true)) {
            return true;
        }

        $stmt = $this->db->prepare('SELECT id_creneau FROM cahiers_texte WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $cahierId]);
        $creneauId = $stmt->fetchColumn();
        return $creneauId ? $this->userCanAccessCreneau((int) $creneauId, $user) : false;
    }
}
