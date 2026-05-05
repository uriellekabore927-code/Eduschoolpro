<?php

require_once __DIR__ . '/BaseModel.php';

class Salle extends BaseModel
{
    public function all(): array
    {
        return $this->db->query('SELECT * FROM salles ORDER BY batiment, libelle')->fetchAll();
    }

    public function create(array $data): string
    {
        $stmt = $this->db->prepare('
            INSERT INTO salles (code, libelle, capacite, batiment, equipements, actif)
            VALUES (:code, :libelle, :capacite, :batiment, :equipements, :actif)
        ');
        $stmt->execute([
            'code' => $data['code'],
            'libelle' => $data['libelle'],
            'capacite' => $data['capacite'],
            'batiment' => $data['batiment'] ?? null,
            'equipements' => $data['equipements'] ?? null,
            'actif' => $data['actif'] ?? 1,
        ]);
        return (string) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare('
            UPDATE salles
            SET code = :code, libelle = :libelle, capacite = :capacite, batiment = :batiment, equipements = :equipements, actif = :actif
            WHERE id = :id
        ');
        return $stmt->execute([
            'id' => $id,
            'code' => $data['code'],
            'libelle' => $data['libelle'],
            'capacite' => $data['capacite'],
            'batiment' => $data['batiment'] ?? null,
            'equipements' => $data['equipements'] ?? null,
            'actif' => $data['actif'] ?? 1,
        ]);
    }

    public function delete(int $id): bool
    {
        return $this->db->prepare('DELETE FROM salles WHERE id = :id')->execute(['id' => $id]);
    }
}
