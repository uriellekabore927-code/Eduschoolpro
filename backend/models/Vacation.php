<?php

require_once __DIR__ . '/BaseModel.php';
require_once __DIR__ . '/SignatureConfig.php';

class Vacation extends BaseModel
{
    public function all(?int $enseignantId = null): array
    {
        $where = $enseignantId ? 'WHERE v.id_enseignant = :id_enseignant' : '';
        $sql = "SELECT v.*, CONCAT(e.nom, ' ', e.prenom) AS enseignant, e.taux_horaire,
                       GROUP_CONCAT(DISTINCT cl.libelle ORDER BY cl.libelle SEPARATOR ', ') AS classes,
                       GROUP_CONCAT(DISTINCT m.libelle ORDER BY m.libelle SEPARATOR ', ') AS matieres
                FROM vacations v
                INNER JOIN enseignants e ON e.id = v.id_enseignant
                LEFT JOIN vacation_lignes vl ON vl.id_vacation = v.id
                LEFT JOIN creneaux c ON c.id = vl.id_creneau
                LEFT JOIN emploi_temps et ON et.id = c.id_emploi_temps
                LEFT JOIN classes cl ON cl.id = et.id_classe
                LEFT JOIN matieres m ON m.id = c.id_matiere
                $where
                GROUP BY v.id
                ORDER BY v.annee DESC, v.mois DESC";
        $stmt = $this->db->prepare($sql);
        if ($enseignantId) {
            $stmt->execute(['id_enseignant' => $enseignantId]);
        } else {
            $stmt->execute();
        }
        $items = $stmt->fetchAll();

        foreach ($items as &$item) {
            $item['lignes'] = $this->lines((int) $item['id']);
            $item['validations'] = $this->validations((int) $item['id']);
        }

        return $items;
    }

    public function summary(?int $enseignantId = null): array
    {
        $where = $enseignantId ? 'WHERE id_enseignant = :id_enseignant' : '';
        $sql = "SELECT
                COALESCE(SUM(total_heures), 0) AS total_heures,
                COALESCE(SUM(montant_brut), 0) AS montant_brut,
                COALESCE(SUM(retenues), 0) AS retenues,
                COALESCE(SUM(montant_net), 0) AS montant_net
            FROM vacations $where";
        $stmt = $this->db->prepare($sql);
        if ($enseignantId) {
            $stmt->execute(['id_enseignant' => $enseignantId]);
        } else {
            $stmt->execute();
        }
        return $stmt->fetch() ?: [
            'total_heures' => 0,
            'montant_brut' => 0,
            'retenues' => 0,
            'montant_net' => 0,
        ];
    }

    public function generate(int $enseignantId, int $mois, int $annee): string
    {
        $this->assertAllSessionsClosed($enseignantId, $mois, $annee);

        $sql = '
            SELECT c.id, TIME_TO_SEC(TIMEDIFF(c.heure_fin, c.heure_debut)) / 3600 AS duree_heures, e.taux_horaire
            FROM creneaux c
            INNER JOIN enseignants e ON e.id = c.id_enseignant
            INNER JOIN cahiers_texte ct ON ct.id_creneau = c.id
            INNER JOIN pointages p ON p.id_creneau = c.id
            WHERE c.id_enseignant = :id_enseignant
              AND MONTH(ct.date_creation) = :mois
              AND YEAR(ct.date_creation) = :annee
              AND ct.statut = "cloture"
        ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id_enseignant' => $enseignantId,
            'mois' => $mois,
            'annee' => $annee,
        ]);

        $rows = $stmt->fetchAll();
        $totalHeures = 0;
        $montantBrut = 0;

        foreach ($rows as $row) {
            $totalHeures += (float) $row['duree_heures'];
            $montantBrut += ((float) $row['duree_heures']) * ((float) $row['taux_horaire']);
        }

        $retenues = round($montantBrut * 0.1, 2);
        $montantNet = $montantBrut - $retenues;

        $insert = $this->db->prepare('
            INSERT INTO vacations (id_enseignant, mois, annee, total_heures, montant_brut, retenues, montant_net, statut)
            VALUES (:id_enseignant, :mois, :annee, :total_heures, :montant_brut, :retenues, :montant_net, "generee")
        ');
        $insert->execute([
            'id_enseignant' => $enseignantId,
            'mois' => $mois,
            'annee' => $annee,
            'total_heures' => $totalHeures,
            'montant_brut' => $montantBrut,
            'retenues' => $retenues,
            'montant_net' => $montantNet,
        ]);

        $vacationId = (int) $this->db->lastInsertId();
        $lineStmt = $this->db->prepare('
            INSERT INTO vacation_lignes (id_vacation, id_creneau, duree_heures, taux, montant)
            VALUES (:id_vacation, :id_creneau, :duree_heures, :taux, :montant)
        ');

        foreach ($rows as $row) {
            $lineStmt->execute([
                'id_vacation' => $vacationId,
                'id_creneau' => $row['id'],
                'duree_heures' => $row['duree_heures'],
                'taux' => $row['taux_horaire'],
                'montant' => ((float) $row['duree_heures']) * ((float) $row['taux_horaire']),
            ]);
        }

        return (string) $vacationId;
    }

    public function validate(int $vacationId, array $data): bool
    {
        $roleValidateur = $data['role_validateur'] ?? '';
        $duplicateCheck = $this->db->prepare('SELECT COUNT(*) FROM validations WHERE id_vacation = :id_vacation AND role_validateur = :role_validateur');
        $duplicateCheck->execute([
            'id_vacation' => $vacationId,
            'role_validateur' => $roleValidateur,
        ]);
        if ((int) $duplicateCheck->fetchColumn() > 0) {
            jsonResponse(false, 'Cette validation a déjà été enregistrée.', null, 400);
        }

        if ($roleValidateur === 'enseignant') {
            $stmt = $this->db->prepare('SELECT v.id_enseignant FROM vacations v WHERE v.id = :id');
            $stmt->execute(['id' => $vacationId]);
            $vacation = $stmt->fetch();
            if (!$vacation) {
                jsonResponse(false, 'Vacation introuvable.', null, 404);
            }

            $userStmt = $this->db->prepare('SELECT id_lien, type_lien FROM utilisateurs WHERE id = :id');
            $userStmt->execute(['id' => $data['id_validateur']]);
            $user = $userStmt->fetch();
            if (!$user || $user['type_lien'] !== 'enseignant' || (int) $user['id_lien'] !== (int) $vacation['id_enseignant']) {
                jsonResponse(false, 'Seul l’enseignant lié à la vacation peut signer cette fiche.', null, 403);
            }
        }

        if ($roleValidateur === 'surveillant') {
            $check = $this->db->prepare('SELECT COUNT(*) FROM validations WHERE id_vacation = :id_vacation AND role_validateur = "enseignant"');
            $check->execute(['id_vacation' => $vacationId]);
            if ((int) $check->fetchColumn() === 0) {
                jsonResponse(false, 'La signature de l\'enseignant est requise avant le contrôle du surveillant.', null, 400);
            }
        }

        if ($roleValidateur === 'comptable') {
            $check = $this->db->prepare('SELECT COUNT(*) FROM validations WHERE id_vacation = :id_vacation AND role_validateur = "surveillant"');
            $check->execute(['id_vacation' => $vacationId]);
            if ((int) $check->fetchColumn() === 0) {
                jsonResponse(false, 'Le visa du surveillant est requis avant la validation comptable.', null, 400);
            }
        }

        $stmt = $this->db->prepare('
            INSERT INTO validations (id_vacation, id_validateur, role_validateur, visa_base64, commentaire, date_validation)
            VALUES (:id_vacation, :id_validateur, :role_validateur, :visa_base64, :commentaire, NOW())
        ');
        $stmt->execute([
            'id_vacation' => $vacationId,
            'id_validateur' => $data['id_validateur'],
            'role_validateur' => $roleValidateur,
            'visa_base64' => $data['visa_base64'] ?? null,
            'commentaire' => $data['commentaire'] ?? null,
        ]);

        if ($roleValidateur === 'surveillant') {
            return $this->db->prepare('UPDATE vacations SET statut = :statut WHERE id = :id')->execute([
                'id' => $vacationId,
                'statut' => 'controlee',
            ]);
        }

        if ($roleValidateur === 'comptable') {
            return $this->db->prepare('UPDATE vacations SET statut = :statut WHERE id = :id')->execute([
                'id' => $vacationId,
                'statut' => 'validee',
            ]);
        }

        return true;
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('
            SELECT v.*, CONCAT(e.nom, " ", e.prenom) AS enseignant, e.taux_horaire,
                   GROUP_CONCAT(DISTINCT cl.libelle ORDER BY cl.libelle SEPARATOR ", ") AS classes,
                   GROUP_CONCAT(DISTINCT m.libelle ORDER BY m.libelle SEPARATOR ", ") AS matieres
            FROM vacations v
            INNER JOIN enseignants e ON e.id = v.id_enseignant
            LEFT JOIN vacation_lignes vl ON vl.id_vacation = v.id
            LEFT JOIN creneaux c ON c.id = vl.id_creneau
            LEFT JOIN emploi_temps et ON et.id = c.id_emploi_temps
            LEFT JOIN classes cl ON cl.id = et.id_classe
            LEFT JOIN matieres m ON m.id = c.id_matiere
            WHERE v.id = :id
            GROUP BY v.id
        ');
        $stmt->execute(['id' => $id]);
        $item = $stmt->fetch() ?: null;
        if ($item) {
            $item['lignes'] = $this->lines((int) $item['id']);
            $item['validations'] = $this->validations((int) $item['id']);
        }
        return $item;
    }

    private function lines(int $vacationId): array
    {
        $stmt = $this->db->prepare('
            SELECT vl.*, c.jour, c.heure_debut, c.heure_fin, et.semaine_debut,
                   cl.libelle AS classe, m.libelle AS matiere
            FROM vacation_lignes vl
            INNER JOIN creneaux c ON c.id = vl.id_creneau
            INNER JOIN emploi_temps et ON et.id = c.id_emploi_temps
            INNER JOIN classes cl ON cl.id = et.id_classe
            INNER JOIN matieres m ON m.id = c.id_matiere
            WHERE vl.id_vacation = :id_vacation
            ORDER BY et.semaine_debut, FIELD(c.jour, "lundi", "mardi", "mercredi", "jeudi", "vendredi", "samedi"), c.heure_debut
        ');
        $stmt->execute(['id_vacation' => $vacationId]);
        return $stmt->fetchAll();
    }

    private function validations(int $vacationId): array
    {
        $stmt = $this->db->prepare('
            SELECT v.*, CONCAT(u.prenom, " ", u.nom) AS validateur_nom
            FROM validations v
            INNER JOIN utilisateurs u ON u.id = v.id_validateur
            WHERE v.id_vacation = :id_vacation
            ORDER BY v.date_validation
        ');
        $stmt->execute(['id_vacation' => $vacationId]);
        return $stmt->fetchAll();
    }

    private function assertAllSessionsClosed(int $enseignantId, int $mois, int $annee): void
    {
        $stmt = $this->db->prepare('
            SELECT COUNT(*) FROM creneaux c
            LEFT JOIN cahiers_texte ct ON ct.id_creneau = c.id
            LEFT JOIN pointages p ON p.id_creneau = c.id
            WHERE c.id_enseignant = :id_enseignant
              AND MONTH(ct.date_creation) = :mois
              AND YEAR(ct.date_creation) = :annee
              AND (ct.id IS NULL OR ct.statut != "cloture" OR p.id IS NULL)
        ');
        $stmt->execute([
            'id_enseignant' => $enseignantId,
            'mois' => $mois,
            'annee' => $annee,
        ]);

        if ((int) $stmt->fetchColumn() > 0) {
            jsonResponse(false, 'Toutes les séances doivent être pointées et clôturées avant génération de la vacation.', null, 400);
        }
    }
}
