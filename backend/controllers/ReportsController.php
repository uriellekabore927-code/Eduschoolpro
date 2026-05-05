<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../utils/jwt.php';
require_once __DIR__ . '/../utils/pdf_generator.php';
require_once __DIR__ . '/../utils/activity_log.php';
require_once __DIR__ . '/../models/EmploiTemps.php';
require_once __DIR__ . '/../models/Vacation.php';

class ReportsController
{
    public function summary(): void
    {
        requirePermission('rapports');
        $db = Database::getConnection();

        $activeUsers = $this->scalar($db, 'SELECT COUNT(*) FROM utilisateurs WHERE actif = 1');
        $classCount = $this->scalar($db, 'SELECT COUNT(*) FROM classes');
        $creneauxCount = $this->scalar($db, 'SELECT COUNT(*) FROM creneaux');
        $closedCahiers = $this->scalar($db, 'SELECT COUNT(*) FROM cahiers_texte WHERE statut = "cloture"');
        $vacationCount = $this->scalar($db, 'SELECT COUNT(*) FROM vacations');

        $payload = [
            'kpis' => [
                ['icon' => 'ph-tree-structure', 'tone' => 'primary', 'label' => 'Classes configurées', 'value' => $classCount, 'trend' => ''],
                ['icon' => 'ph-calendar', 'tone' => 'success', 'label' => 'Séances planifiées', 'value' => $creneauxCount, 'trend' => ''],
                ['icon' => 'ph-notebook', 'tone' => 'warning', 'label' => 'Cahiers clôturés', 'value' => $closedCahiers, 'trend' => ''],
                ['icon' => 'ph-users', 'tone' => 'primary', 'label' => 'Utilisateurs actifs', 'value' => $activeUsers, 'trend' => ''],
            ],
            'line' => $this->sessionsByDay($db),
            'donut' => $this->vacationsByStatus($db),
            'bars' => $this->sessionsByClass($db),
            'meta' => [
                'vacations_total' => $vacationCount,
            ],
        ];

        jsonResponse(true, 'Synthèse des rapports récupérée.', $payload);
    }

    public function exportSessionsPdf(): void
    {
        $auth = requirePermission('rapports');
        $weekStart = $_GET['semaine'] ?? (new DateTime('monday this week'))->format('Y-m-d');
        $model = new EmploiTemps();
        $emplois = $model->all(null, $weekStart);
        $file = generateTimetablePdfExport($emplois, $weekStart, 'all', null);
        logActivity((int) $auth['sub'], 'REPORT_EXPORT_SESSIONS_PDF', ['semaine' => $weekStart]);
        jsonResponse(true, 'Rapport des séances généré.', $file);
    }

    public function exportVacationsPdf(): void
    {
        $auth = requirePermission('rapports');
        $model = new Vacation();
        $items = $model->all();
        $periodLabel = (string) ($_GET['periode'] ?? 'Période courante');
        $file = generateVacationsReportPdf($items, $periodLabel);
        logActivity((int) $auth['sub'], 'REPORT_EXPORT_VACATIONS_PDF', ['periode' => $periodLabel]);
        jsonResponse(true, 'Rapport des vacations généré.', $file);
    }

    public function exportReferentialsPdf(): void
    {
        $auth = requirePermission('rapports');
        $db = Database::getConnection();
        $file = generateReferentialsReportPdf([
            'classes' => $this->fetchAll($db, 'SELECT code, libelle, niveau FROM classes ORDER BY libelle'),
            'matieres' => $this->fetchAll($db, 'SELECT code, libelle, volume_horaire_total, coefficient FROM matieres ORDER BY libelle'),
            'enseignants' => $this->fetchAll($db, 'SELECT matricule, nom, prenom, specialite, statut FROM enseignants ORDER BY nom, prenom'),
            'salles' => $this->fetchAll($db, 'SELECT code, libelle, capacite, batiment FROM salles ORDER BY libelle'),
        ]);
        logActivity((int) $auth['sub'], 'REPORT_EXPORT_REFERENTIALS_PDF');
        jsonResponse(true, 'Rapport des référentiels généré.', $file);
    }

    public function exportExcel(): void
    {
        $auth = requirePermission('rapports');
        $db = Database::getConnection();
        $file = generateReferentialsExcelExport([
            'classes' => $this->fetchAll($db, 'SELECT code, libelle, niveau FROM classes ORDER BY libelle'),
            'matieres' => $this->fetchAll($db, 'SELECT code, libelle, volume_horaire_total, coefficient FROM matieres ORDER BY libelle'),
            'enseignants' => $this->fetchAll($db, 'SELECT matricule, nom, prenom, specialite, statut FROM enseignants ORDER BY nom, prenom'),
            'salles' => $this->fetchAll($db, 'SELECT code, libelle, capacite, batiment FROM salles ORDER BY libelle'),
        ]);
        logActivity((int) $auth['sub'], 'REPORT_EXPORT_EXCEL');
        jsonResponse(true, 'Export Excel généré.', $file);
    }

    private function sessionsByDay(PDO $db): array
    {
        $weekStart = (new DateTime('monday this week'))->format('Y-m-d');
        $stmt = $db->prepare('
            SELECT c.jour, COUNT(*) AS total
            FROM creneaux c
            INNER JOIN emploi_temps et ON et.id = c.id_emploi_temps
            WHERE et.semaine_debut = :week
            GROUP BY c.jour
        ');
        $stmt->execute(['week' => $weekStart]);
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $days = [
            'lundi' => 'Lun.',
            'mardi' => 'Mar.',
            'mercredi' => 'Mer.',
            'jeudi' => 'Jeu.',
            'vendredi' => 'Ven.',
            'samedi' => 'Sam.',
        ];

        $items = [];
        foreach ($days as $key => $label) {
            $items[] = ['label' => $label, 'value' => (int) ($rows[$key] ?? 0)];
        }
        return $items;
    }

    private function vacationsByStatus(PDO $db): array
    {
        $stmt = $db->query('SELECT statut, COUNT(*) AS total FROM vacations GROUP BY statut');
        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $rows[$row['statut']] = (int) $row['total'];
        }

        return [
            ['label' => 'Générées', 'value' => (int) ($rows['generee'] ?? 0), 'color' => '#2563eb'],
            ['label' => 'Contrôlées', 'value' => (int) ($rows['controlee'] ?? 0), 'color' => '#22c55e'],
            ['label' => 'Validées', 'value' => (int) ($rows['validee'] ?? 0), 'color' => '#fb923c'],
            ['label' => 'Payées', 'value' => (int) ($rows['payee'] ?? 0), 'color' => '#8b5cf6'],
        ];
    }

    private function sessionsByClass(PDO $db): array
    {
        $stmt = $db->query('
            SELECT cl.libelle AS label, COUNT(c.id) AS total
            FROM classes cl
            LEFT JOIN emploi_temps et ON et.id_classe = cl.id
            LEFT JOIN creneaux c ON c.id_emploi_temps = et.id
            GROUP BY cl.id, cl.libelle
            ORDER BY total DESC, cl.libelle ASC
            LIMIT 6
        ');

        $items = [];
        foreach ($stmt->fetchAll() as $row) {
            $items[] = [
                'label' => $row['label'],
                'value' => (int) $row['total'],
                'color' => '#2563eb',
            ];
        }

        return $items;
    }

    private function scalar(PDO $db, string $sql, array $params = []): int
    {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    private function fetchAll(PDO $db, string $sql, array $params = []): array
    {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
