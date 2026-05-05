<?php

require_once __DIR__ . '/BaseModel.php';

class EmploiTemps extends BaseModel
{
    public function all(?int $classId = null, ?string $week = null, ?int $teacherId = null): array
    {
        $sql = 'SELECT e.*, c.libelle AS classe_libelle, c.code AS classe_code
                FROM emploi_temps e
                INNER JOIN classes c ON c.id = e.id_classe
                WHERE 1=1';
        $params = [];

        if ($classId) {
            $sql .= ' AND e.id_classe = :id_classe';
            $params['id_classe'] = $classId;
        }

        if ($week) {
            $sql .= ' AND e.semaine_debut = :semaine_debut';
            $params['semaine_debut'] = $week;
        }

        if ($teacherId) {
            $sql .= ' AND EXISTS (
                SELECT 1
                FROM creneaux c2
                WHERE c2.id_emploi_temps = e.id
                  AND c2.id_enseignant = :id_enseignant
            )';
            $params['id_enseignant'] = $teacherId;
        }

        $sql .= ' ORDER BY e.semaine_debut DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $emplois = $stmt->fetchAll();

        foreach ($emplois as &$emploi) {
            $emploi['creneaux'] = $this->getCreneaux((int) $emploi['id'], $teacherId);
        }

        return $emplois;
    }

    public function create(array $data): string
    {
        $stmt = $this->db->prepare('
            INSERT INTO emploi_temps (id_classe, semaine_debut, statut_publication, cree_par)
            VALUES (:id_classe, :semaine_debut, :statut_publication, :cree_par)
        ');
        $stmt->execute([
            'id_classe' => $data['id_classe'],
            'semaine_debut' => $data['semaine_debut'],
            'statut_publication' => $data['statut_publication'] ?? 'brouillon',
            'cree_par' => $data['cree_par'],
        ]);
        return (string) $this->db->lastInsertId();
    }

    public function publish(int $id): bool
    {
        return $this->db->prepare('UPDATE emploi_temps SET statut_publication = "publie" WHERE id = :id')->execute(['id' => $id]);
    }

    public function getClasseLabel(int $classId): ?string
    {
        $stmt = $this->db->prepare('SELECT libelle FROM classes WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $classId]);
        $label = $stmt->fetchColumn();
        return $label !== false ? (string) $label : null;
    }

    public function getCreneaux(int $emploiTempsId, ?int $teacherId = null): array
    {
        $sql = '
            SELECT c.*, m.libelle AS matiere_libelle, m.code AS matiere_code,
                   CONCAT(e.nom, " ", e.prenom) AS enseignant_nom,
                   s.libelle AS salle_libelle, s.code AS salle_code
            FROM creneaux c
            INNER JOIN matieres m ON m.id = c.id_matiere
            INNER JOIN enseignants e ON e.id = c.id_enseignant
            INNER JOIN salles s ON s.id = c.id_salle
            WHERE c.id_emploi_temps = :id_emploi_temps
        ';
        $params = ['id_emploi_temps' => $emploiTempsId];

        if ($teacherId) {
            $sql .= ' AND c.id_enseignant = :id_enseignant';
            $params['id_enseignant'] = $teacherId;
        }

        $sql .= ' ORDER BY FIELD(c.jour, "lundi", "mardi", "mercredi", "jeudi", "vendredi", "samedi"), c.heure_debut';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
