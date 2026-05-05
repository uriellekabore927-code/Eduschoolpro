<?php

require_once __DIR__ . '/BaseModel.php';

class Classe extends BaseModel
{
    public function all(): array
    {
        $sql = 'SELECT c.*, a.libelle AS annee_libelle
                FROM classes c
                LEFT JOIN annees_academiques a ON a.id = c.id_annee_academique
                ORDER BY c.libelle';
        return $this->db->query($sql)->fetchAll();
    }

    public function create(array $data): string
    {
        $stmt = $this->db->prepare('
            INSERT INTO classes (code, libelle, niveau, id_annee_academique, actif)
            VALUES (:code, :libelle, :niveau, :id_annee_academique, :actif)
        ');
        $stmt->execute([
            'code' => $data['code'],
            'libelle' => $data['libelle'],
            'niveau' => $data['niveau'],
            'id_annee_academique' => $data['id_annee_academique'],
            'actif' => $data['actif'] ?? 1,
        ]);

        return (string) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare('
            UPDATE classes
            SET code = :code, libelle = :libelle, niveau = :niveau, id_annee_academique = :id_annee_academique, actif = :actif
            WHERE id = :id
        ');
        return $stmt->execute([
            'id' => $id,
            'code' => $data['code'],
            'libelle' => $data['libelle'],
            'niveau' => $data['niveau'],
            'id_annee_academique' => $data['id_annee_academique'],
            'actif' => $data['actif'] ?? 1,
        ]);
    }

    public function delete(int $id): bool
    {
        return $this->db->prepare('DELETE FROM classes WHERE id = :id')->execute(['id' => $id]);
    }
}
