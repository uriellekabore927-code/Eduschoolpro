<?php

require_once __DIR__ . '/BaseModel.php';

class Creneau extends BaseModel
{
    public function create(array $data): string
    {
        $this->assertNoConflict($data);

        $stmt = $this->db->prepare('
            INSERT INTO creneaux (id_emploi_temps, id_matiere, id_enseignant, id_salle, jour, heure_debut, heure_fin, type_seance, devoir_prevu, devoir_date, qr_token, qr_expire, statut)
            VALUES (:id_emploi_temps, :id_matiere, :id_enseignant, :id_salle, :jour, :heure_debut, :heure_fin, :type_seance, :devoir_prevu, :devoir_date, :qr_token, :qr_expire, :statut)
        ');
        $stmt->execute([
            'id_emploi_temps' => $data['id_emploi_temps'],
            'id_matiere' => $data['id_matiere'],
            'id_enseignant' => $data['id_enseignant'],
            'id_salle' => $data['id_salle'],
            'jour' => $data['jour'],
            'heure_debut' => $data['heure_debut'],
            'heure_fin' => $data['heure_fin'],
            'type_seance' => $data['type_seance'] ?? 'cours_magistral',
            'devoir_prevu' => $data['devoir_prevu'] ?? null,
            'devoir_date' => $data['devoir_date'] ?? null,
            'qr_token' => bin2hex(random_bytes(16)),
            'qr_expire' => $data['qr_expire'],
            'statut' => $data['statut'] ?? 'planifie',
        ]);

        return (string) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $this->assertNoConflict($data, $id);

        $stmt = $this->db->prepare('
            UPDATE creneaux
            SET id_matiere = :id_matiere, id_enseignant = :id_enseignant, id_salle = :id_salle,
                jour = :jour, heure_debut = :heure_debut, heure_fin = :heure_fin, type_seance = :type_seance,
                devoir_prevu = :devoir_prevu, devoir_date = :devoir_date, qr_expire = :qr_expire, statut = :statut
            WHERE id = :id
        ');

        return $stmt->execute([
            'id' => $id,
            'id_matiere' => $data['id_matiere'],
            'id_enseignant' => $data['id_enseignant'],
            'id_salle' => $data['id_salle'],
            'jour' => $data['jour'],
            'heure_debut' => $data['heure_debut'],
            'heure_fin' => $data['heure_fin'],
            'type_seance' => $data['type_seance'] ?? 'cours_magistral',
            'devoir_prevu' => $data['devoir_prevu'] ?? null,
            'devoir_date' => $data['devoir_date'] ?? null,
            'qr_expire' => $data['qr_expire'],
            'statut' => $data['statut'] ?? 'planifie',
        ]);
    }

    public function delete(int $id): bool
    {
        return $this->db->prepare('DELETE FROM creneaux WHERE id = :id')->execute(['id' => $id]);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('
            SELECT c.*, et.id_classe, m.libelle AS matiere, s.libelle AS salle, e.nom, e.prenom
            FROM creneaux c
            INNER JOIN emploi_temps et ON et.id = c.id_emploi_temps
            INNER JOIN matieres m ON m.id = c.id_matiere
            INNER JOIN salles s ON s.id = c.id_salle
            INNER JOIN enseignants e ON e.id = c.id_enseignant
            WHERE c.id = :id
        ');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function isAccessibleToUser(int $id, array $user): bool
    {
        if (in_array($user['role'] ?? '', ['administrateur', 'surveillant'], true)) {
            return true;
        }

        $creneau = $this->find($id);
        if (!$creneau) {
            return false;
        }

        if (($user['role'] ?? '') === 'enseignant') {
            return ($user['type_lien'] ?? '') === 'enseignant'
                && (int) ($user['id_lien'] ?? 0) === (int) $creneau['id_enseignant'];
        }

        if (($user['role'] ?? '') === 'delegue') {
            return ($user['type_lien'] ?? '') === 'classe'
                && (int) ($user['id_lien'] ?? 0) === (int) $creneau['id_classe'];
        }

        return false;
    }

    private function assertNoConflict(array $data, ?int $excludeId = null): void
    {
        $baseSql = '
            SELECT c.id, e.id_classe
            FROM creneaux c
            INNER JOIN emploi_temps e ON e.id = c.id_emploi_temps
            WHERE c.jour = :jour
              AND (:heure_debut < c.heure_fin AND :heure_fin > c.heure_debut)
        ';
        $suffix = $excludeId ? ' AND c.id != :exclude_id' : '';

        $checks = [
            ['sql' => $baseSql . ' AND c.id_enseignant = :resource_id' . $suffix, 'message' => 'Conflit : enseignant déjà occupé sur ce créneau.'],
            ['sql' => $baseSql . ' AND c.id_salle = :resource_id' . $suffix, 'message' => 'Conflit : salle déjà utilisée sur ce créneau.'],
            ['sql' => $baseSql . ' AND e.id_classe = :resource_id' . $suffix, 'message' => 'Conflit : classe déjà occupée sur ce créneau.'],
        ];

        $resourceMap = [
            $data['id_enseignant'],
            $data['id_salle'],
            $data['id_classe'] ?? $this->resolveClasseId((int) $data['id_emploi_temps']),
        ];

        foreach ($checks as $index => $check) {
            $stmt = $this->db->prepare($check['sql']);
            $params = [
                'jour' => $data['jour'],
                'heure_debut' => $data['heure_debut'],
                'heure_fin' => $data['heure_fin'],
                'resource_id' => $resourceMap[$index],
            ];
            if ($excludeId) {
                $params['exclude_id'] = $excludeId;
            }
            $stmt->execute($params);
            if ($stmt->fetch()) {
                jsonResponse(false, $check['message'], null, 400);
            }
        }
    }

    private function resolveClasseId(int $emploiTempsId): int
    {
        $stmt = $this->db->prepare('SELECT id_classe FROM emploi_temps WHERE id = :id');
        $stmt->execute(['id' => $emploiTempsId]);
        return (int) $stmt->fetchColumn();
    }
}
