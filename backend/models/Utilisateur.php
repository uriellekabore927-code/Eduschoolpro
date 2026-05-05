<?php

require_once __DIR__ . '/BaseModel.php';

class Utilisateur extends BaseModel
{
    public function all(): array
    {
        return $this->db->query('
            SELECT u.id, u.nom, u.prenom, u.email, u.role, u.id_lien, u.type_lien, u.permissions_json,
                   u.actif, u.derniere_connexion, u.created_at,
                   r.libelle AS role_libelle, r.permissions_json AS role_permissions_json
            FROM utilisateurs u
            LEFT JOIN roles r ON r.code = u.role
            ORDER BY u.created_at DESC, u.nom ASC, u.prenom ASC
        ')->fetchAll();
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('
            SELECT u.*, r.libelle AS role_libelle, r.permissions_json AS role_permissions_json
            FROM utilisateurs u
            LEFT JOIN roles r ON r.code = u.role
            WHERE u.email = :email
            LIMIT 1
        ');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('
            SELECT u.id, u.nom, u.prenom, u.email, u.role, u.id_lien, u.type_lien, u.permissions_json,
                   u.actif, u.derniere_connexion, u.created_at,
                   r.libelle AS role_libelle, r.permissions_json AS role_permissions_json
            FROM utilisateurs u
            LEFT JOIN roles r ON r.code = u.role
            WHERE u.id = :id
            LIMIT 1
        ');
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function create(array $data): string
    {
        $stmt = $this->db->prepare('
            INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe_hash, role, id_lien, type_lien, permissions_json, actif)
            VALUES (:nom, :prenom, :email, :mot_de_passe_hash, :role, :id_lien, :type_lien, :permissions_json, :actif)
        ');
        $stmt->execute([
            'nom' => $data['nom'],
            'prenom' => $data['prenom'],
            'email' => $data['email'],
            'mot_de_passe_hash' => password_hash($data['mot_de_passe'], PASSWORD_DEFAULT),
            'role' => $data['role'],
            'id_lien' => $data['id_lien'] ?: null,
            'type_lien' => $data['type_lien'] ?? 'aucun',
            'permissions_json' => null,
            'actif' => $data['actif'] ?? 1,
        ]);
        return (string) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $fields = [
            'nom = :nom',
            'prenom = :prenom',
            'email = :email',
            'role = :role',
            'id_lien = :id_lien',
            'type_lien = :type_lien',
            'actif = :actif',
        ];

        if (!empty($data['mot_de_passe'])) {
            $fields[] = 'mot_de_passe_hash = :mot_de_passe_hash';
        }

        $sql = 'UPDATE utilisateurs SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $params = [
            'id' => $id,
            'nom' => $data['nom'],
            'prenom' => $data['prenom'],
            'email' => $data['email'],
            'role' => $data['role'],
            'id_lien' => $data['id_lien'] ?: null,
            'type_lien' => $data['type_lien'] ?? 'aucun',
            'actif' => $data['actif'] ?? 1,
        ];
        if (!empty($data['mot_de_passe'])) {
            $params['mot_de_passe_hash'] = password_hash($data['mot_de_passe'], PASSWORD_DEFAULT);
        }

        return $this->db->prepare($sql)->execute($params);
    }

    public function delete(int $id): bool
    {
        return $this->db->prepare('DELETE FROM utilisateurs WHERE id = :id')->execute(['id' => $id]);
    }

    public function touchLastLogin(int $id): void
    {
        $this->db->prepare('UPDATE utilisateurs SET derniere_connexion = NOW() WHERE id = :id')->execute(['id' => $id]);
    }

    public function statsByRole(): array
    {
        return $this->db->query('
            SELECT u.role, COALESCE(r.libelle, u.role) AS role_libelle, COUNT(*) AS total
            FROM utilisateurs u
            LEFT JOIN roles r ON r.code = u.role
            GROUP BY u.role, r.libelle
            ORDER BY role_libelle ASC
        ')->fetchAll();
    }
}
