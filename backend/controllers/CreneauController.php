<?php

require_once __DIR__ . '/../models/Creneau.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../utils/jwt.php';
require_once __DIR__ . '/../utils/activity_log.php';

class CreneauController
{
    private Creneau $model;

    public function __construct()
    {
        $this->model = new Creneau();
    }

    public function store(array $data): void
    {
        $auth = requirePermission('emploi_temps');
        if (($auth['role'] ?? '') !== 'administrateur') {
            jsonResponse(false, 'Seul l’administrateur peut ajouter des créneaux.', null, 403);
        }
        $id = $this->model->create($data);
        logActivity((int) $auth['sub'], 'CRENEAU_CREATE', [
            'creneau_id' => $id,
            'id_emploi_temps' => $data['id_emploi_temps'] ?? null,
            'id_matiere' => $data['id_matiere'] ?? null,
            'jour' => $data['jour'] ?? null,
            'heure_debut' => $data['heure_debut'] ?? null,
            'heure_fin' => $data['heure_fin'] ?? null,
        ]);
        jsonResponse(true, 'Créneau ajouté avec succès.', ['id' => $id], 201);
    }

    public function update(int $id, array $data): void
    {
        $auth = requirePermission('emploi_temps');
        if (($auth['role'] ?? '') !== 'administrateur') {
            jsonResponse(false, 'Seul l’administrateur peut modifier des créneaux.', null, 403);
        }
        $this->model->update($id, $data);
        logActivity((int) $auth['sub'], 'CRENEAU_UPDATE', [
            'creneau_id' => $id,
            'id_matiere' => $data['id_matiere'] ?? null,
            'jour' => $data['jour'] ?? null,
            'heure_debut' => $data['heure_debut'] ?? null,
            'heure_fin' => $data['heure_fin'] ?? null,
        ]);
        jsonResponse(true, 'Créneau mis à jour.', ['id' => $id]);
    }

    public function destroy(int $id): void
    {
        $auth = requirePermission('emploi_temps');
        if (($auth['role'] ?? '') !== 'administrateur') {
            jsonResponse(false, 'Seul l’administrateur peut supprimer des créneaux.', null, 403);
        }
        $this->model->delete($id);
        logActivity((int) $auth['sub'], 'CRENEAU_DELETE', ['creneau_id' => $id]);
        jsonResponse(true, 'Créneau supprimé.', ['id' => $id]);
    }

    public function qr(int $id): void
    {
        $auth = requireAnyPermission(['emploi_temps', 'pointage']);
        $user = authenticatedUserProfile($auth);
        $creneau = $this->model->find($id);
        if (!$creneau) {
            jsonResponse(false, 'Créneau introuvable.', null, 404);
        }
        if (!$this->model->isAccessibleToUser($id, $user)) {
            jsonResponse(false, 'Accès non autorisé à ce QR code.', null, 403);
        }
        jsonResponse(true, 'QR du créneau récupéré.', [
            'id' => $creneau['id'],
            'qr_token' => $creneau['qr_token'],
            'qr_expire' => $creneau['qr_expire'],
            'matiere' => $creneau['matiere'],
            'enseignant' => trim($creneau['prenom'] . ' ' . $creneau['nom']),
        ]);
    }
}
