<?php

require_once __DIR__ . '/BaseModel.php';

class AnneeAcademique extends BaseModel
{
    public function all(): array
    {
        return $this->db->query('SELECT * FROM annees_academiques ORDER BY date_debut DESC')->fetchAll();
    }

    public function create(array $data): string
    {
        if (!empty($data['active'])) {
            $this->db->exec('UPDATE annees_academiques SET active = 0');
        }

        $stmt = $this->db->prepare('
            INSERT INTO annees_academiques (libelle, date_debut, date_fin, active)
            VALUES (:libelle, :date_debut, :date_fin, :active)
        ');
        $stmt->execute([
            'libelle' => $data['libelle'],
            'date_debut' => $data['date_debut'],
            'date_fin' => $data['date_fin'],
            'active' => !empty($data['active']) ? 1 : 0,
        ]);

        return (string) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        if (!empty($data['active'])) {
            $this->db->exec('UPDATE annees_academiques SET active = 0');
        }

        $stmt = $this->db->prepare('
            UPDATE annees_academiques
            SET libelle = :libelle, date_debut = :date_debut, date_fin = :date_fin, active = :active
            WHERE id = :id
        ');

        return $stmt->execute([
            'id' => $id,
            'libelle' => $data['libelle'],
            'date_debut' => $data['date_debut'],
            'date_fin' => $data['date_fin'],
            'active' => !empty($data['active']) ? 1 : 0,
        ]);
    }
}
