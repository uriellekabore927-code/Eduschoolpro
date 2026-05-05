<?php

require_once __DIR__ . '/../models/EmploiTemps.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../utils/jwt.php';
require_once __DIR__ . '/../utils/pdf_generator.php';
require_once __DIR__ . '/../utils/activity_log.php';

class EmploiTempsController
{
    private EmploiTemps $model;

    public function __construct()
    {
        $this->model = new EmploiTemps();
    }

    public function index(): void
    {
        $auth = requireAuth();
        $user = authenticatedUserProfile($auth);
        $classId = isset($_GET['id_classe']) ? (int) $_GET['id_classe'] : null;
        $week = $_GET['semaine'] ?? null;
        $teacherId = null;

        if (($user['role'] ?? '') === 'delegue') {
            $linkedClassId = linkedAccessId($user, 'classe');
            if (!$linkedClassId) {
                jsonResponse(true, 'Emplois du temps récupérés.', []);
            }
            $classId = $linkedClassId;
        }

        if (($user['role'] ?? '') === 'enseignant') {
            $teacherId = linkedAccessId($user, 'enseignant');
            if (!$teacherId) {
                jsonResponse(true, 'Emplois du temps récupérés.', []);
            }
        }

        jsonResponse(true, 'Emplois du temps récupérés.', $this->model->all($classId, $week, $teacherId));
    }

    public function store(array $data): void
    {
        $auth = requirePermission('emploi_temps');
        if (($auth['role'] ?? '') !== 'administrateur') {
            jsonResponse(false, 'Seul l’administrateur peut créer un emploi du temps.', null, 403);
        }
        $data['cree_par'] = $auth['sub'];
        $id = $this->model->create($data);
        logActivity((int) $auth['sub'], 'EMPLOI_TEMPS_CREATE', [
            'emploi_temps_id' => $id,
            'id_classe' => $data['id_classe'] ?? null,
            'semaine_debut' => $data['semaine_debut'] ?? null,
        ]);
        jsonResponse(true, 'Emploi du temps créé.', ['id' => $id], 201);
    }

    public function publish(int $id): void
    {
        $auth = requirePermission('emploi_temps');
        if (($auth['role'] ?? '') !== 'administrateur') {
            jsonResponse(false, 'Seul l’administrateur peut publier un emploi du temps.', null, 403);
        }
        $this->model->publish($id);
        logActivity((int) $auth['sub'], 'EMPLOI_TEMPS_PUBLISH', ['emploi_temps_id' => $id]);
        jsonResponse(true, 'Emploi du temps publié.', ['id' => $id]);
    }

    public function export(): void
    {
        $auth = requirePermission('emploi_temps');
        $user = authenticatedUserProfile($auth);
        $classId = isset($_GET['id_classe']) ? (int) $_GET['id_classe'] : null;
        $week = $_GET['semaine'] ?? null;
        $scope = $_GET['scope'] ?? 'single';
        $teacherId = null;

        if (!$week) {
            jsonResponse(false, 'La semaine est requise pour l’export.', null, 400);
        }

        if (($user['role'] ?? '') === 'delegue') {
            $linkedClassId = linkedAccessId($user, 'classe');
            if (!$linkedClassId) {
                jsonResponse(false, 'Aucune classe n’est liée à ce compte délégué.', null, 403);
            }
            $classId = $linkedClassId;
            $scope = 'single';
        }

        if (($user['role'] ?? '') === 'enseignant') {
            $teacherId = linkedAccessId($user, 'enseignant');
            if (!$teacherId) {
                jsonResponse(false, 'Aucun enseignant n’est lié à ce compte.', null, 403);
            }
        }

        if ($scope === 'single' && !$classId) {
            jsonResponse(false, 'La classe est requise pour l’export unitaire.', null, 400);
        }

        $emplois = $this->model->all($scope === 'single' ? $classId : null, $week, $teacherId);
        $classLabel = $scope === 'single'
            ? ($emplois[0]['classe_libelle'] ?? ($classId ? $this->model->getClasseLabel($classId) : null))
            : null;
        $file = generateTimetablePdfExport($emplois, $week, $scope, $classLabel);
        logActivity((int) $auth['sub'], 'EMPLOI_TEMPS_EXPORT_PDF', [
            'scope' => $scope,
            'id_classe' => $classId,
            'semaine' => $week,
        ]);
        jsonResponse(true, 'Export généré.', $file);
    }
}
