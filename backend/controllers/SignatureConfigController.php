<?php

require_once __DIR__ . '/../models/SignatureConfig.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../utils/jwt.php';

class SignatureConfigController
{
    private SignatureConfig $model;

    public function __construct()
    {
        $this->model = new SignatureConfig();
    }

    public function index(): void
    {
        requireAnyPermission(['parametres', 'cahiers', 'vacations']);
        jsonResponse(true, 'Paramètres de signature récupérés.', $this->model->all());
    }

    public function store(array $data): void
    {
        requirePermission('parametres');
        $id = $this->model->create($data);
        jsonResponse(true, 'Règle de signature créée.', ['id' => $id], 201);
    }

    public function update(int $id, array $data): void
    {
        requirePermission('parametres');
        $this->model->update($id, $data);
        jsonResponse(true, 'Règle de signature mise à jour.', ['id' => $id]);
    }

    public function destroy(int $id): void
    {
        requirePermission('parametres');
        $this->model->delete($id);
        jsonResponse(true, 'Règle de signature supprimée.', ['id' => $id]);
    }
}
