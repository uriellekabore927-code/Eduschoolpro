<?php

require_once __DIR__ . '/BaseModel.php';

class Role extends BaseModel
{
    public function all(): array
    {
        return $this->db->query('
            SELECT r.id, r.code, r.libelle, r.permissions_json, r.actif, r.created_at,
                   COUNT(u.id) AS users_count
            FROM roles r
            LEFT JOIN utilisateurs u ON u.role = r.code
            GROUP BY r.id, r.code, r.libelle, r.permissions_json, r.actif, r.created_at
            ORDER BY r.libelle ASC, r.code ASC
        ')->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT id, code, libelle, permissions_json, actif, created_at FROM roles WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $role = $stmt->fetch();
        return $role ?: null;
    }

    public function findByCode(string $code): ?array
    {
        $stmt = $this->db->prepare('SELECT id, code, libelle, permissions_json, actif, created_at FROM roles WHERE code = :code LIMIT 1');
        $stmt->execute(['code' => $code]);
        $role = $stmt->fetch();
        return $role ?: null;
    }

    public function create(array $data): string
    {
        $stmt = $this->db->prepare('
            INSERT INTO roles (code, libelle, permissions_json, actif)
            VALUES (:code, :libelle, :permissions_json, :actif)
        ');
        $stmt->execute([
            'code' => trim((string) ($data['code'] ?? '')),
            'libelle' => trim((string) ($data['libelle'] ?? '')),
            'permissions_json' => json_encode(array_values($data['permissions'] ?? [])),
            'actif' => $data['actif'] ?? 1,
        ]);

        return (string) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare('
            UPDATE roles
            SET code = :code,
                libelle = :libelle,
                permissions_json = :permissions_json,
                actif = :actif
            WHERE id = :id
        ');

        return $stmt->execute([
            'id' => $id,
            'code' => trim((string) ($data['code'] ?? '')),
            'libelle' => trim((string) ($data['libelle'] ?? '')),
            'permissions_json' => json_encode(array_values($data['permissions'] ?? [])),
            'actif' => $data['actif'] ?? 1,
        ]);
    }

    public function delete(int $id): bool
    {
        return $this->db->prepare('DELETE FROM roles WHERE id = :id')->execute(['id' => $id]);
    }
}
