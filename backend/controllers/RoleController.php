<?php

require_once __DIR__ . '/../models/Role.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../utils/jwt.php';

class RoleController
{
    private Role $model;

    public function __construct()
    {
        $this->model = new Role();
    }

    private function existingRoleByCode(string $code): ?array
    {
        return $this->model->findByCode(trim($code));
    }

    public function index(): void
    {
        requirePermission('parametres');
        jsonResponse(true, 'Rôles récupérés.', $this->model->all());
    }

    public function store(array $data): void
    {
        requirePermission('parametres');
        if (empty($data['code']) || empty($data['libelle'])) {
            jsonResponse(false, 'Code et libellé du rôle sont requis.', null, 400);
        }
        if ($this->existingRoleByCode((string) $data['code'])) {
            jsonResponse(false, 'Un rôle existe déjà avec ce code.', null, 400);
        }

        $id = $this->model->create($data);
        jsonResponse(true, 'Rôle créé avec succès.', ['id' => $id], 201);
    }

    public function update(int $id, array $data): void
    {
        requirePermission('parametres');
        if (empty($data['code']) || empty($data['libelle'])) {
            jsonResponse(false, 'Code et libellé du rôle sont requis.', null, 400);
        }
        $existing = $this->existingRoleByCode((string) $data['code']);
        if ($existing && (int) $existing['id'] !== $id) {
            jsonResponse(false, 'Un autre rôle utilise déjà ce code.', null, 400);
        }

        $this->model->update($id, $data);
        jsonResponse(true, 'Rôle mis à jour.', ['id' => $id]);
    }

    public function destroy(int $id): void
    {
        requirePermission('parametres');

        try {
            $this->model->delete($id);
            jsonResponse(true, 'Rôle supprimé.', ['id' => $id]);
        } catch (PDOException $exception) {
            jsonResponse(false, 'Ce rôle est encore attribué à un ou plusieurs utilisateurs.', null, 400);
        }
    }
}
