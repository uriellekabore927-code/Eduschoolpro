<?php

require_once __DIR__ . '/../models/Vacation.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../utils/jwt.php';
require_once __DIR__ . '/../utils/pdf_generator.php';
require_once __DIR__ . '/../utils/activity_log.php';

class VacationController
{
    private Vacation $model;

    public function __construct()
    {
        $this->model = new Vacation();
    }

    public function index(): void
    {
        $auth = requirePermission('vacations');
        $enseignantFilter = null;
        if (($auth['role'] ?? '') === 'enseignant') {
            $user = authenticatedUserProfile($auth);
            if (($user['type_lien'] ?? '') === 'enseignant' && !empty($user['id_lien'])) {
                $enseignantFilter = (int) $user['id_lien'];
            }
        }
        jsonResponse(true, 'Vacations récupérées.', [
            'summary' => $this->model->summary($enseignantFilter),
            'items' => $this->model->all($enseignantFilter),
        ]);
    }

    public function generate(array $data): void
    {
        $auth = requirePermission('vacations');
        $role = $auth['role'] ?? '';

        if ($role === 'enseignant') {
            $user = authenticatedUserProfile($auth);
            if (($user['type_lien'] ?? '') !== 'enseignant' || empty($user['id_lien'])) {
                jsonResponse(false, 'Votre compte n\'est pas lié à un enseignant.', null, 400);
            }
            $requestedEnseignant = (int) $user['id_lien'];
        } elseif ($role === 'administrateur') {
            $requestedEnseignant = isset($data['id_enseignant']) ? (int) $data['id_enseignant'] : 0;
            if ($requestedEnseignant === 0) {
                jsonResponse(false, 'L\'identifiant de l\'enseignant est requis.', null, 400);
            }
        } else {
            jsonResponse(false, 'Seul l\'administrateur ou l\'enseignant concerné peut générer une fiche de vacation.', null, 403);
        }

        $id = $this->model->generate($requestedEnseignant, (int) $data['mois'], (int) $data['annee']);
        logActivity((int) $auth['sub'], 'VACATION_GENERATE', [
            'vacation_id' => $id,
            'id_enseignant' => $requestedEnseignant,
            'mois' => $data['mois'] ?? null,
            'annee' => $data['annee'] ?? null,
        ]);
        jsonResponse(true, 'Vacation générée.', ['id' => $id], 201);
    }

    public function validate(int $id, array $data): void
    {
        $auth = requirePermission('vacations');
        if (!in_array($auth['role'] ?? '', ['administrateur', 'surveillant', 'comptable', 'enseignant'], true)) {
            jsonResponse(false, "Seuls le surveillant, le comptable, l'enseignant ou l'administrateur peuvent valider une vacation.", null, 403);
        }
        $data['id_validateur'] = $auth['sub'];
        if (empty($data['role_validateur'])) {
            if (($auth['role'] ?? '') === 'surveillant') {
                $data['role_validateur'] = 'surveillant';
            } elseif (($auth['role'] ?? '') === 'comptable') {
                $data['role_validateur'] = 'comptable';
            } elseif (($auth['role'] ?? '') === 'enseignant') {
                $data['role_validateur'] = 'enseignant';
            } else {
                $data['role_validateur'] = 'comptable';
            }
        }
        if (($data['role_validateur'] === 'enseignant') && ($auth['role'] ?? '') !== 'enseignant') {
            jsonResponse(false, "Seule la fiche de l'enseignant lié peut recevoir la signature enseignant.", null, 403);
        }
        $this->model->validate($id, $data);
        logActivity((int) $auth['sub'], 'VACATION_VALIDATE', [
            'vacation_id' => $id,
            'role_validateur' => $data['role_validateur'],
        ]);
        jsonResponse(true, 'Vacation validée.', ['id' => $id]);
    }

    public function pdf(int $id): void
    {
        $auth = requirePermission('vacations');
        $vacation = $this->model->find($id);
        if (!$vacation) {
            jsonResponse(false, 'Vacation introuvable.', null, 404);
        }
        $file = generateVacationPdf($vacation);
        logActivity((int) $auth['sub'], 'VACATION_EXPORT_PDF', ['vacation_id' => $id]);
        jsonResponse(true, 'Export prêt.', $file);
    }

    public function approuver(int $id, array $data): void
    {
        $auth = requirePermission('vacations');
        if (!in_array($auth['role'] ?? '', ['administrateur', 'comptable'], true)) {
            jsonResponse(false, 'Seul le responsable comptable ou administrateur peut approuver une vacation.', null, 403);
        }
        $db = \Database::getConnection();
        $stmt = $db->prepare('UPDATE vacations SET statut = "payee" WHERE id = :id AND statut = "validee"');
        $stmt->execute(['id' => $id]);
        if ($stmt->rowCount() === 0) {
            jsonResponse(false, 'La vacation doit être au statut "validée" avant d\'être marquée comme payée.', null, 400);
        }
        logActivity((int) $auth['sub'], 'VACATION_APPROVE', ['vacation_id' => $id]);
        jsonResponse(true, 'Vacation approuvée et marquée comme payée.', ['id' => $id]);
    }
}
