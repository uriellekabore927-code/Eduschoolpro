<?php

require_once __DIR__ . '/../models/Utilisateur.php';
require_once __DIR__ . '/../models/Role.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../utils/jwt.php';
require_once __DIR__ . '/../utils/activity_log.php';

class UtilisateurController
{
    private Utilisateur $model;
    private Role $roles;

    public function __construct()
    {
        $this->model = new Utilisateur();
        $this->roles = new Role();
    }

    public function index(): void
    {
        requirePermission('utilisateurs');
        jsonResponse(true, 'Utilisateurs récupérés.', [
            'items' => $this->model->all(),
            'stats' => $this->model->statsByRole(),
        ]);
    }

    public function store(array $data): void
    {
        $auth = requirePermission('utilisateurs');
        if (empty($data['email']) || empty($data['role']) || empty($data['nom']) || empty($data['prenom']) || empty($data['mot_de_passe'])) {
            jsonResponse(false, 'Nom, prénom, email, rôle et mot de passe sont requis.', null, 400);
        }
        if (!$this->roles->findByCode((string) $data['role'])) {
            jsonResponse(false, 'Le rôle sélectionné est introuvable.', null, 400);
        }
        $id = $this->model->create($data);
        logActivity((int) $auth['sub'], 'USER_CREATE', [
            'utilisateur_id' => $id,
            'email' => $data['email'],
            'role' => $data['role'],
        ]);
        jsonResponse(true, 'Utilisateur créé avec succès.', ['id' => $id], 201);
    }

    public function update(int $id, array $data): void
    {
        $auth = requirePermission('utilisateurs');
        if (empty($data['email']) || empty($data['role']) || empty($data['nom']) || empty($data['prenom'])) {
            jsonResponse(false, 'Nom, prénom, email et rôle sont requis.', null, 400);
        }
        if (!$this->roles->findByCode((string) $data['role'])) {
            jsonResponse(false, 'Le rôle sélectionné est introuvable.', null, 400);
        }
        $this->model->update($id, $data);
        logActivity((int) $auth['sub'], 'USER_UPDATE', [
            'utilisateur_id' => $id,
            'email' => $data['email'],
            'role' => $data['role'],
        ]);
        jsonResponse(true, 'Utilisateur mis à jour.', ['id' => $id]);
    }

    public function destroy(int $id): void
    {
        $auth = requirePermission('utilisateurs');
        $this->model->delete($id);
        logActivity((int) $auth['sub'], 'USER_DELETE', ['utilisateur_id' => $id]);
        jsonResponse(true, 'Utilisateur supprimé.', ['id' => $id]);
    }
}
