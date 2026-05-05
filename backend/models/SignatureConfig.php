<?php

require_once __DIR__ . '/BaseModel.php';

class SignatureConfig extends BaseModel
{
    public function all(): array
    {
        return $this->db->query('SELECT * FROM parametres_signatures ORDER BY document_type, ordre_validation')->fetchAll();
    }

    public function byDocument(string $documentType): array
    {
        $stmt = $this->db->prepare('
            SELECT * FROM parametres_signatures
            WHERE document_type = :document_type AND actif = 1
            ORDER BY ordre_validation
        ');
        $stmt->execute(['document_type' => $documentType]);
        return $stmt->fetchAll();
    }

    public function create(array $data): string
    {
        $stmt = $this->db->prepare('
            INSERT INTO parametres_signatures (document_type, role_signataire, ordre_validation, obligatoire, actif)
            VALUES (:document_type, :role_signataire, :ordre_validation, :obligatoire, :actif)
        ');
        $stmt->execute([
            'document_type' => $data['document_type'],
            'role_signataire' => $data['role_signataire'],
            'ordre_validation' => $data['ordre_validation'],
            'obligatoire' => $data['obligatoire'] ?? 1,
            'actif' => $data['actif'] ?? 1,
        ]);
        return (string) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare('
            UPDATE parametres_signatures
            SET document_type = :document_type, role_signataire = :role_signataire,
                ordre_validation = :ordre_validation, obligatoire = :obligatoire, actif = :actif
            WHERE id = :id
        ');
        return $stmt->execute([
            'id' => $id,
            'document_type' => $data['document_type'],
            'role_signataire' => $data['role_signataire'],
            'ordre_validation' => $data['ordre_validation'],
            'obligatoire' => $data['obligatoire'] ?? 1,
            'actif' => $data['actif'] ?? 1,
        ]);
    }

    public function delete(int $id): bool
    {
        return $this->db->prepare('DELETE FROM parametres_signatures WHERE id = :id')->execute(['id' => $id]);
    }
}
