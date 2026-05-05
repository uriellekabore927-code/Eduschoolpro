<?php

require_once __DIR__ . '/BaseModel.php';
require_once __DIR__ . '/../utils/activity_log.php';

class Pointage extends BaseModel
{
    public function scan(string $token, int $enseignantId): array
    {
        $stmt = $this->db->prepare('
            SELECT c.*, m.libelle AS matiere_libelle, s.libelle AS salle_libelle,
                   cl.libelle AS classe_libelle, CONCAT(e.nom, " ", e.prenom) AS enseignant_nom,
                   et.semaine_debut
            FROM creneaux c
            INNER JOIN matieres m ON m.id = c.id_matiere
            INNER JOIN salles s ON s.id = c.id_salle
            INNER JOIN emploi_temps et ON et.id = c.id_emploi_temps
            INNER JOIN classes cl ON cl.id = et.id_classe
            INNER JOIN enseignants e ON e.id = c.id_enseignant
            WHERE c.qr_token = :qr_token
            LIMIT 1
        ');
        $stmt->execute(['qr_token' => $token]);
        $creneau = $stmt->fetch();

        if (!$creneau) {
            logActivity($enseignantId ?: null, 'POINTAGE_QR_FAILED', ['reason' => 'token_not_found', 'token' => $token]);
            jsonResponse(false, 'QR Code introuvable.', null, 404);
        }

        $now = new DateTimeImmutable('now');
        $plannedStart = $this->plannedStartAt($creneau);
        $allowedStart = $plannedStart->modify('-15 minutes');
        $allowedEnd = $plannedStart->modify('+30 minutes');

        if ($now < $allowedStart || $now > $allowedEnd || strtotime($creneau['qr_expire']) < $now->getTimestamp()) {
            logActivity($enseignantId ?: null, 'POINTAGE_QR_FAILED', [
                'reason' => 'outside_window',
                'creneau_id' => (int) $creneau['id'],
                'token' => $token,
            ]);
            jsonResponse(false, 'QR Code hors fenêtre autorisée.', null, 400);
        }

        $existing = $this->db->prepare('SELECT id FROM pointages WHERE token_utilise = :token LIMIT 1');
        $existing->execute(['token' => $token]);
        if ($existing->fetch()) {
            logActivity($enseignantId ?: null, 'POINTAGE_QR_FAILED', [
                'reason' => 'token_already_used',
                'creneau_id' => (int) $creneau['id'],
                'token' => $token,
            ]);
            jsonResponse(false, 'Ce QR Code a déjà été utilisé.', null, 400);
        }

        $status = ($now <= $plannedStart) ? 'a_l_heure' : 'retard';

        $insert = $this->db->prepare('
            INSERT INTO pointages (id_creneau, id_enseignant, heure_pointage_reelle, ip_source, token_utilise, statut)
            VALUES (:id_creneau, :id_enseignant, NOW(), :ip_source, :token_utilise, :statut)
        ');
        $insert->execute([
            'id_creneau' => $creneau['id'],
            'id_enseignant' => $enseignantId,
            'ip_source' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            'token_utilise' => $token,
            'statut' => $status,
        ]);

        logActivity($enseignantId ?: null, 'POINTAGE_QR_SUCCESS', [
            'creneau_id' => (int) $creneau['id'],
            'token' => $token,
            'statut' => $status,
        ]);

        return [
            'pointage_id' => (int) $this->db->lastInsertId(),
            'status' => $status,
            'creneau' => [
                'id' => (int) $creneau['id'],
                'matiere' => $creneau['matiere_libelle'],
                'classe' => $creneau['classe_libelle'],
                'salle' => $creneau['salle_libelle'],
                'enseignant' => $creneau['enseignant_nom'],
                'jour' => $creneau['jour'],
                'heure_debut' => $creneau['heure_debut'],
                'heure_fin' => $creneau['heure_fin'],
            ],
        ];
    }

    private function plannedStartAt(array $creneau): DateTimeImmutable
    {
        $offsets = [
            'lundi' => 0,
            'mardi' => 1,
            'mercredi' => 2,
            'jeudi' => 3,
            'vendredi' => 4,
            'samedi' => 5,
        ];

        $weekStart = $creneau['semaine_debut'] ?? date('Y-m-d');
        $base = new DateTimeImmutable(sprintf('%s %s', $weekStart, $creneau['heure_debut']));
        $offset = $offsets[$creneau['jour']] ?? 0;

        return $base->modify(sprintf('+%d day', $offset));
    }
}
