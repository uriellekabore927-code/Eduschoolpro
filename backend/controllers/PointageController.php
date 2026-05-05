<?php

require_once __DIR__ . '/../models/Pointage.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../utils/jwt.php';

class PointageController
{
    private Pointage $model;

    public function __construct()
    {
        $this->model = new Pointage();
    }

    public function scan(array $data): void
    {
        $auth = requirePermission('pointage');
        if (empty($data['token'])) {
            jsonResponse(false, 'Token QR requis.', null, 400);
        }

        $result = $this->model->scan($data['token'], (int) ($data['id_enseignant'] ?? $auth['sub']));
        jsonResponse(true, 'Pointage enregistré avec succès.', $result, 201);
    }
}
