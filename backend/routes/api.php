<?php

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/AnneeController.php';
require_once __DIR__ . '/../controllers/ClasseController.php';
require_once __DIR__ . '/../controllers/MatiereController.php';
require_once __DIR__ . '/../controllers/EnseignantController.php';
require_once __DIR__ . '/../controllers/SalleController.php';
require_once __DIR__ . '/../controllers/EmploiTempsController.php';
require_once __DIR__ . '/../controllers/CreneauController.php';
require_once __DIR__ . '/../controllers/PointageController.php';
require_once __DIR__ . '/../controllers/CahierTexteController.php';
require_once __DIR__ . '/../controllers/VacationController.php';
require_once __DIR__ . '/../controllers/DashboardController.php';
require_once __DIR__ . '/../controllers/ReportsController.php';
require_once __DIR__ . '/../controllers/UtilisateurController.php';
require_once __DIR__ . '/../controllers/RoleController.php';
require_once __DIR__ . '/../controllers/SignatureConfigController.php';

$method = $_SERVER['REQUEST_METHOD'];
$uriPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = preg_replace('#^/backend/routes/api\.php#', '', $uriPath);
$segments = array_values(array_filter(explode('/', trim($path, '/'))));
$input = getJsonInput();

if (($segments[0] ?? '') !== 'api') {
    jsonResponse(false, 'Route API introuvable.', null, 404);
}

array_shift($segments);
$resource = $segments[0] ?? '';
$id = isset($segments[1]) && is_numeric($segments[1]) ? (int) $segments[1] : null;
$action = $segments[2] ?? null;

switch ($resource) {
    case 'auth':
        $controller = new AuthController();
        if ($method === 'POST' && ($segments[1] ?? '') === 'login') {
            $controller->login($input);
        }
        if ($method === 'POST' && ($segments[1] ?? '') === 'logout') {
            $controller->logout();
        }
        if ($method === 'GET' && ($segments[1] ?? '') === 'me') {
            $controller->me();
        }
        break;

    case 'annees':
        $controller = new AnneeController();
        if ($method === 'GET') {
            $controller->index();
        }
        if ($method === 'POST') {
            $controller->store($input);
        }
        if ($method === 'PUT' && $id) {
            $controller->update($id, $input);
        }
        break;

    case 'classes':
        $controller = new ClasseController();
        if ($method === 'GET') {
            $controller->index();
        }
        if ($method === 'POST') {
            $controller->store($input);
        }
        if ($method === 'PUT' && $id) {
            $controller->update($id, $input);
        }
        if ($method === 'DELETE' && $id) {
            $controller->destroy($id);
        }
        break;

    case 'matieres':
        $controller = new MatiereController();
        if ($method === 'GET') {
            $controller->index();
        }
        if ($method === 'POST') {
            $controller->store($input);
        }
        if ($method === 'PUT' && $id) {
            $controller->update($id, $input);
        }
        if ($method === 'DELETE' && $id) {
            $controller->destroy($id);
        }
        break;

    case 'enseignants':
        $controller = new EnseignantController();
        if ($method === 'GET') {
            $controller->index();
        }
        if ($method === 'POST') {
            $controller->store($input);
        }
        if ($method === 'PUT' && $id) {
            $controller->update($id, $input);
        }
        if ($method === 'DELETE' && $id) {
            $controller->destroy($id);
        }
        break;

    case 'salles':
        $controller = new SalleController();
        if ($method === 'GET') {
            $controller->index();
        }
        if ($method === 'POST') {
            $controller->store($input);
        }
        if ($method === 'PUT' && $id) {
            $controller->update($id, $input);
        }
        if ($method === 'DELETE' && $id) {
            $controller->destroy($id);
        }
        break;

    case 'emploi-temps':
        $controller = new EmploiTempsController();
        if ($method === 'GET') {
            if (($segments[1] ?? '') === 'export') {
                $controller->export();
            }
            $controller->index();
        }
        if ($method === 'POST' && !$id) {
            $controller->store($input);
        }
        if ($method === 'PUT' && $id && $action === 'publier') {
            $controller->publish($id);
        }
        break;

    case 'creneaux':
        $controller = new CreneauController();
        if ($method === 'POST' && !$id) {
            $controller->store($input);
        }
        if ($method === 'PUT' && $id) {
            $controller->update($id, $input);
        }
        if ($method === 'DELETE' && $id) {
            $controller->destroy($id);
        }
        if ($method === 'GET' && $id && $action === 'qr') {
            $controller->qr($id);
        }
        break;

    case 'pointages':
        $controller = new PointageController();
        if ($method === 'POST' && ($segments[1] ?? '') === 'scan') {
            $controller->scan($input);
        }
        break;

    case 'cahiers':
        $controller = new CahierTexteController();
        if ($method === 'GET' && ($segments[1] ?? '') === 'creneau' && isset($segments[2]) && is_numeric($segments[2]) && ($segments[3] ?? '') === 'signatures') {
            $controller->signaturesByCreneau((int) $segments[2]);
        }
        if ($method === 'GET') {
            $controller->index();
        }
        if ($method === 'POST' && !$id) {
            $controller->store($input);
        }
        if ($method === 'PUT' && $id) {
            $controller->update($id, $input);
        }
        if ($method === 'POST' && $id && $action === 'signer') {
            $controller->sign($id, $input);
        }
        if ($method === 'POST' && $id && $action === 'cloturer') {
            $controller->close($id);
        }
        if ($method === 'GET' && $id && $action === 'signatures') {
            $controller->signatures($id);
        }
        break;

    case 'vacations':
        $controller = new VacationController();
        if ($method === 'GET' && !$id) {
            $controller->index();
        }
        if ($method === 'POST' && ($segments[1] ?? '') === 'generer') {
            $controller->generate($input);
        }
        if ($method === 'POST' && $id && $action === 'valider') {
            $controller->validate($id, $input);
        }
        if ($method === 'POST' && $id && $action === 'approuver') {
            $controller->approuver($id, $input);
        }
        if ($method === 'GET' && $id && $action === 'pdf') {
            $controller->pdf($id);
        }
        break;

    case 'dashboard':
        $controller = new DashboardController();
        if ($method === 'GET' && ($segments[1] ?? '') === 'stats') {
            $controller->stats();
        }
        break;

    case 'reports':
        $controller = new ReportsController();
        if ($method === 'GET' && ($segments[1] ?? '') === 'summary') {
            $controller->summary();
        }
        if ($method === 'GET' && ($segments[1] ?? '') === 'export' && $action === 'sessions') {
            $controller->exportSessionsPdf();
        }
        if ($method === 'GET' && ($segments[1] ?? '') === 'export' && $action === 'vacations') {
            $controller->exportVacationsPdf();
        }
        if ($method === 'GET' && ($segments[1] ?? '') === 'export' && $action === 'referentials') {
            $controller->exportReferentialsPdf();
        }
        if ($method === 'GET' && ($segments[1] ?? '') === 'export' && $action === 'excel') {
            $controller->exportExcel();
        }
        break;

    case 'utilisateurs':
        $controller = new UtilisateurController();
        if ($method === 'GET') {
            $controller->index();
        }
        if ($method === 'POST') {
            $controller->store($input);
        }
        if ($method === 'PUT' && $id) {
            $controller->update($id, $input);
        }
        if ($method === 'DELETE' && $id) {
            $controller->destroy($id);
        }
        break;

    case 'roles':
        $controller = new RoleController();
        if ($method === 'GET') {
            $controller->index();
        }
        if ($method === 'POST') {
            $controller->store($input);
        }
        if ($method === 'PUT' && $id) {
            $controller->update($id, $input);
        }
        if ($method === 'DELETE' && $id) {
            $controller->destroy($id);
        }
        break;

    case 'parametres-signatures':
        $controller = new SignatureConfigController();
        if ($method === 'GET') {
            $controller->index();
        }
        if ($method === 'POST') {
            $controller->store($input);
        }
        if ($method === 'PUT' && $id) {
            $controller->update($id, $input);
        }
        if ($method === 'DELETE' && $id) {
            $controller->destroy($id);
        }
        break;

    case 'logs':
        requirePermission('parametres');
        $db = Database::getConnection();
        $where = [];
        $params = [];
        if (!empty($_GET['action'])) {
            $where[] = 'action = :action';
            $params['action'] = $_GET['action'];
        }
        if (!empty($_GET['date_debut'])) {
            $where[] = 'date_heure >= :date_debut';
            $params['date_debut'] = $_GET['date_debut'];
        }
        if (!empty($_GET['date_fin'])) {
            $where[] = 'date_heure <= :date_fin';
            $params['date_fin'] = $_GET['date_fin'] . ' 23:59:59';
        }
        $sql = 'SELECT l.*, u.email FROM logs_activite l LEFT JOIN utilisateurs u ON u.id = l.id_utilisateur'
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
            . ' ORDER BY l.date_heure DESC LIMIT 500';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        jsonResponse(true, 'Journal récupéré.', $stmt->fetchAll());
        break;
}

jsonResponse(false, 'Endpoint introuvable ou méthode non autorisée.', null, 404);
