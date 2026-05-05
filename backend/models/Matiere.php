<?php

require_once __DIR__ . '/BaseModel.php';

class Matiere extends BaseModel
{
    public function all(): array
    {
        $sql = 'SELECT m.*, GROUP_CONCAT(cm.id_classe) AS classes_associees
                FROM matieres m
                LEFT JOIN classe_matieres cm ON cm.id_matiere = m.id
                GROUP BY m.id
                ORDER BY m.libelle';
        return $this->db->query($sql)->fetchAll();
    }

    public function create(array $data): string
    {
        $stmt = $this->db->prepare('
            INSERT INTO matieres (code, libelle, volume_horaire_total, coefficient, actif)
            VALUES (:code, :libelle, :volume_horaire_total, :coefficient, :actif)
        ');
        $stmt->execute([
            'code' => $data['code'],
            'libelle' => $data['libelle'],
            'volume_horaire_total' => $data['volume_horaire_total'],
            'coefficient' => $data['coefficient'],
            'actif' => $data['actif'] ?? 1,
        ]);
        $id = (string) $this->db->lastInsertId();
        $this->syncClasses((int) $id, $data['classes'] ?? []);
        return $id;
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare('
            UPDATE matieres
            SET code = :code, libelle = :libelle, volume_horaire_total = :volume_horaire_total, coefficient = :coefficient, actif = :actif
            WHERE id = :id
        ');
        $updated = $stmt->execute([
            'id' => $id,
            'code' => $data['code'],
            'libelle' => $data['libelle'],
            'volume_horaire_total' => $data['volume_horaire_total'],
            'coefficient' => $data['coefficient'],
            'actif' => $data['actif'] ?? 1,
        ]);
        $this->syncClasses($id, $data['classes'] ?? []);
        return $updated;
    }

    public function delete(int $id): bool
    {
        $this->db->prepare('DELETE FROM classe_matieres WHERE id_matiere = :id')->execute(['id' => $id]);
        return $this->db->prepare('DELETE FROM matieres WHERE id = :id')->execute(['id' => $id]);
    }

    private function syncClasses(int $matiereId, array $classIds): void
    {
        $this->db->prepare('DELETE FROM classe_matieres WHERE id_matiere = :id_matiere')->execute(['id_matiere' => $matiereId]);
        $stmt = $this->db->prepare('INSERT INTO classe_matieres (id_classe, id_matiere) VALUES (:id_classe, :id_matiere)');
        foreach ($classIds as $classId) {
            $stmt->execute(['id_classe' => $classId, 'id_matiere' => $matiereId]);
        }
    }
}
