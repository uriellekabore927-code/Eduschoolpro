<?php

require_once __DIR__ . '/BaseModel.php';

class Enseignant extends BaseModel
{
    public function all(): array
    {
        return $this->db->query('SELECT * FROM enseignants ORDER BY nom, prenom')->fetchAll();
    }

    public function create(array $data): string
    {
        $stmt = $this->db->prepare('
            INSERT INTO enseignants (matricule, nom, prenom, email, telephone, specialite, statut, taux_horaire, actif)
            VALUES (:matricule, :nom, :prenom, :email, :telephone, :specialite, :statut, :taux_horaire, :actif)
        ');
        $stmt->execute([
            'matricule' => $data['matricule'],
            'nom' => $data['nom'],
            'prenom' => $data['prenom'],
            'email' => $data['email'] ?? null,
            'telephone' => $data['telephone'] ?? null,
            'specialite' => $data['specialite'] ?? null,
            'statut' => $data['statut'],
            'taux_horaire' => $data['taux_horaire'],
            'actif' => $data['actif'] ?? 1,
        ]);
        return (string) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare('
            UPDATE enseignants
            SET matricule = :matricule, nom = :nom, prenom = :prenom, email = :email, telephone = :telephone,
                specialite = :specialite, statut = :statut, taux_horaire = :taux_horaire, actif = :actif
            WHERE id = :id
        ');
        return $stmt->execute([
            'id' => $id,
            'matricule' => $data['matricule'],
            'nom' => $data['nom'],
            'prenom' => $data['prenom'],
            'email' => $data['email'] ?? null,
            'telephone' => $data['telephone'] ?? null,
            'specialite' => $data['specialite'] ?? null,
            'statut' => $data['statut'],
            'taux_horaire' => $data['taux_horaire'],
            'actif' => $data['actif'] ?? 1,
        ]);
    }

    public function delete(int $id): bool
    {
        return $this->db->prepare('DELETE FROM enseignants WHERE id = :id')->execute(['id' => $id]);
    }
}
