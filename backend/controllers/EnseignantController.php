<?php

require_once __DIR__ . '/BaseCrudController.php';
require_once __DIR__ . '/../models/Enseignant.php';
require_once __DIR__ . '/../models/Utilisateur.php';

class EnseignantController extends BaseCrudController
{
    protected function model(): object
    {
        return new Enseignant();
    }

    public function store(array $data, array $roles = ['administrateur']): void
    {
        requirePermission($this->permission());

        $enseignantId = $this->model()->create($data);

        // Crée automatiquement un compte utilisateur si l'email est fourni
        if (!empty($data['email'])) {
            $userModel = new Utilisateur();
            $existing = $userModel->findByEmail((string) $data['email']);
            if (!$existing) {
                $userModel->create([
                    'nom'        => $data['nom'],
                    'prenom'     => $data['prenom'],
                    'email'      => $data['email'],
                    'mot_de_passe' => 'enseignant@2026',
                    'role'       => 'enseignant',
                    'id_lien'    => $enseignantId,
                    'type_lien'  => 'enseignant',
                    'actif'      => 1,
                ]);
            }
        }

        jsonResponse(true, 'Enseignant créé avec succès.', ['id' => $enseignantId], 201);
    }
}
