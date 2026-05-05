<?php

require_once __DIR__ . '/../models/CahierTexte.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../utils/jwt.php';
require_once __DIR__ . '/../utils/activity_log.php';

class CahierTexteController
{
    private CahierTexte $model;

    public function __construct()
    {
        $this->model = new CahierTexte();
    }

    public function index(): void
    {
        $auth = requirePermission('cahiers');
        $user = authenticatedUserProfile($auth);
        jsonResponse(true, 'Cahiers de texte récupérés.', $this->model->all($user));
    }

    public function store(array $data): void
    {
        $auth = requirePermission('cahiers');
        $user = authenticatedUserProfile($auth);
        if (!in_array($user['role'] ?? '', ['delegue', 'enseignant'], true)) {
            jsonResponse(false, 'Seuls le délégué et l’enseignant peuvent enregistrer un cahier.', null, 403);
        }
        if (empty($data['id_creneau']) || !$this->model->userCanAccessCreneau((int) $data['id_creneau'], $user)) {
            jsonResponse(false, 'Accès non autorisé à cette séance.', null, 403);
        }
        if (($user['role'] ?? '') === 'delegue') {
            $data['id_delegue'] = $user['sub'] ?? $user['id'] ?? null;
        }
        $id = $this->model->create($data);
        logActivity((int) $auth['sub'], 'CAHIER_CREATE', [
            'cahier_id' => $id,
            'id_creneau' => $data['id_creneau'] ?? null,
            'statut' => $data['statut'] ?? 'brouillon',
        ]);
        jsonResponse(true, 'Cahier créé.', ['id' => $id], 201);
    }

    public function update(int $id, array $data): void
    {
        $auth = requirePermission('cahiers');
        $user = authenticatedUserProfile($auth);
        if (!in_array($user['role'] ?? '', ['delegue', 'enseignant'], true)) {
            jsonResponse(false, 'Seuls le délégué et l’enseignant peuvent modifier un cahier.', null, 403);
        }
        if (!$this->model->userCanAccessCahier($id, $user)) {
            jsonResponse(false, 'Accès non autorisé à ce cahier.', null, 403);
        }
        $this->model->update($id, $data);
        logActivity((int) $auth['sub'], 'CAHIER_UPDATE', [
            'cahier_id' => $id,
            'statut' => $data['statut'] ?? 'brouillon',
        ]);
        jsonResponse(true, 'Cahier mis à jour.', ['id' => $id]);
    }

    public function sign(int $id, array $data): void
    {
        $auth = requirePermission('cahiers');
        $user = authenticatedUserProfile($auth);
        if (!in_array($user['role'] ?? '', ['enseignant', 'delegue'], true)) {
            jsonResponse(false, 'Seuls le délégué et l’enseignant peuvent signer ce cahier.', null, 403);
        }
        if (!$this->model->userCanAccessCahier($id, $user)) {
            jsonResponse(false, 'Accès non autorisé à ce cahier.', null, 403);
        }
        $data['id_utilisateur'] = $auth['sub'];
        $data['type_signataire'] = ($user['role'] ?? '') === 'delegue' ? 'delegue' : 'enseignant';
        $savedSignature = $this->model->sign($id, $data);
        logActivity((int) $auth['sub'], 'CAHIER_SIGN', [
            'cahier_id' => $id,
            'type_signataire' => $data['type_signataire'],
        ]);
        jsonResponse(true, 'Signature enregistrée.', [
            'id' => $id,
            'signature' => $savedSignature,
        ]);
    }

    public function close(int $id): void
    {
        $auth = requirePermission('cahiers');
        $user = authenticatedUserProfile($auth);
        if (!in_array($user['role'] ?? '', ['administrateur', 'admin', 'enseignant'], true)) {
            jsonResponse(false, 'Seul l’enseignant ou l’administrateur peut clôturer ce cahier.', null, 403);
        }
        if (!$this->model->userCanAccessCahier($id, $user)) {
            jsonResponse(false, 'Accès non autorisé à ce cahier.', null, 403);
        }
        $this->model->close($id);
        logActivity((int) $auth['sub'], 'CAHIER_CLOSE', ['cahier_id' => $id]);
        jsonResponse(true, 'Cahier clôturé.', ['id' => $id]);
    }

    public function signatures(int $id): void
    {
        $auth = requirePermission('cahiers');
        $user = authenticatedUserProfile($auth);
        if (!$this->model->userCanAccessCahier($id, $user)) {
            jsonResponse(false, 'Accès non autorisé à ce cahier.', null, 403);
        }
        jsonResponse(true, 'Signatures récupérées.', $this->model->signaturesByCahier($id));
    }

    public function signaturesByCreneau(int $creneauId): void
    {
        $auth = requirePermission('cahiers');
        $user = authenticatedUserProfile($auth);
        if (!$this->model->userCanAccessCreneau($creneauId, $user)) {
            jsonResponse(false, 'Accès non autorisé à cette séance.', null, 403);
        }
        jsonResponse(true, 'Signatures récupérées (créneau).', $this->model->signaturesByCreneau($creneauId));
    }
}
