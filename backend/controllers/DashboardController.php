<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../utils/jwt.php';
require_once __DIR__ . '/../models/Utilisateur.php';

class DashboardController
{
    public function stats(): void
    {
        $auth = requirePermission('dashboard');
        $db = Database::getConnection();
        $users = new Utilisateur();
        $user = $users->findById((int) ($auth['sub'] ?? 0)) ?: [];
        $role = $auth['role'] ?? 'administrateur';
        $linkedId = isset($user['id_lien']) ? (int) $user['id_lien'] : 0;

        $payload = match ($role) {
            'enseignant' => $this->teacherDashboard($db, $linkedId),
            'delegue' => $this->delegateDashboard($db, $linkedId),
            'surveillant' => $this->supervisorDashboard($db),
            'comptable' => $this->accountingDashboard($db),
            default => $this->adminDashboard($db),
        };

        $payload['role'] = $role;
        jsonResponse(true, 'Dashboard chargé.', $payload);
    }

    private function adminDashboard(PDO $db): array
    {
        $today = $this->currentDayLabel();
        $weekStart = $this->currentWeekStart();
        $requiredSignatures = $this->requiredSignatureCount($db, 'cahier');

        $sessionsToday = $this->scalar($db, '
            SELECT COUNT(*)
            FROM creneaux c
            INNER JOIN emploi_temps et ON et.id = c.id_emploi_temps
            WHERE et.semaine_debut = :week_start AND c.jour = :jour
        ', ['week_start' => $weekStart, 'jour' => $today]);

        $presentToday = $this->scalar($db, '
            SELECT COUNT(DISTINCT p.id_creneau)
            FROM pointages p
            INNER JOIN creneaux c ON c.id = p.id_creneau
            INNER JOIN emploi_temps et ON et.id = c.id_emploi_temps
            WHERE et.semaine_debut = :week_start AND c.jour = :jour
        ', ['week_start' => $weekStart, 'jour' => $today]);

        $lateToday = $this->scalar($db, '
            SELECT COUNT(*)
            FROM pointages p
            INNER JOIN creneaux c ON c.id = p.id_creneau
            INNER JOIN emploi_temps et ON et.id = c.id_emploi_temps
            WHERE et.semaine_debut = :week_start AND c.jour = :jour AND p.statut = "retard"
        ', ['week_start' => $weekStart, 'jour' => $today]);

        $unsignedCahiers = $this->scalar($db, '
            SELECT COUNT(*)
            FROM (
                SELECT ct.id, COUNT(s.id) AS total_signatures
                FROM cahiers_texte ct
                LEFT JOIN signatures s ON s.id_cahier = ct.id
                GROUP BY ct.id
            ) temp
            WHERE temp.total_signatures < :required_count
        ', ['required_count' => $requiredSignatures]);

        $validatedHours = $this->scalar($db, '
            SELECT COALESCE(SUM(TIME_TO_SEC(TIMEDIFF(c.heure_fin, c.heure_debut)) / 3600), 0)
            FROM creneaux c
            INNER JOIN cahiers_texte ct ON ct.id_creneau = c.id
            WHERE ct.statut = "cloture"
        ');

        $classHours = $this->fetchAll($db, '
            SELECT cl.libelle AS label,
                   ROUND(COALESCE(SUM(TIME_TO_SEC(TIMEDIFF(c.heure_fin, c.heure_debut)) / 3600), 0), 1) AS planned,
                   ROUND(COALESCE(SUM(CASE WHEN ct.statut = "cloture" THEN TIME_TO_SEC(TIMEDIFF(c.heure_fin, c.heure_debut)) / 3600 ELSE 0 END), 0), 1) AS realized
            FROM classes cl
            LEFT JOIN emploi_temps et ON et.id_classe = cl.id AND et.semaine_debut = :week_start
            LEFT JOIN creneaux c ON c.id_emploi_temps = et.id
            LEFT JOIN cahiers_texte ct ON ct.id_creneau = c.id
            GROUP BY cl.id, cl.libelle
            ORDER BY cl.libelle
            LIMIT 6
        ', ['week_start' => $weekStart]);

        $programProgress = $this->fetchAll($db, '
            SELECT CONCAT(cl.code, " / ", m.libelle) AS label,
                   ROUND(AVG(CAST(REPLACE(NULLIF(ct.niveau_avancement, ""), "%", "") AS DECIMAL(5,2))), 0) AS value
            FROM cahiers_texte ct
            INNER JOIN creneaux c ON c.id = ct.id_creneau
            INNER JOIN emploi_temps et ON et.id = c.id_emploi_temps
            INNER JOIN classes cl ON cl.id = et.id_classe
            INNER JOIN matieres m ON m.id = c.id_matiere
            GROUP BY cl.id, m.id
            ORDER BY value DESC, label ASC
            LIMIT 6
        ');

        $alerts = array_merge(
            $this->fetchAll($db, '
                SELECT "Séance non pointée" AS label,
                       CONCAT(cl.libelle, " · ", m.libelle, " · ", c.heure_debut) AS detail,
                       "danger" AS tone
                FROM creneaux c
                INNER JOIN emploi_temps et ON et.id = c.id_emploi_temps
                INNER JOIN classes cl ON cl.id = et.id_classe
                INNER JOIN matieres m ON m.id = c.id_matiere
                LEFT JOIN pointages p ON p.id_creneau = c.id
                WHERE et.semaine_debut = :week_start AND c.jour = :jour AND p.id IS NULL
                LIMIT 4
            ', ['week_start' => $weekStart, 'jour' => $today]),
            $this->fetchAll($db, '
                SELECT "Cahier non rempli" AS label,
                       CONCAT(cl.libelle, " · ", m.libelle) AS detail,
                       "warning" AS tone
                FROM creneaux c
                INNER JOIN emploi_temps et ON et.id = c.id_emploi_temps
                INNER JOIN classes cl ON cl.id = et.id_classe
                INNER JOIN matieres m ON m.id = c.id_matiere
                LEFT JOIN cahiers_texte ct ON ct.id_creneau = c.id
                WHERE et.semaine_debut = :week_start AND ct.id IS NULL
                LIMIT 4
            ', ['week_start' => $weekStart]),
            $this->fetchAll($db, '
                SELECT "Retard enseignant" AS label,
                       CONCAT(e.nom, " ", e.prenom, " · ", m.libelle) AS detail,
                       "warning" AS tone
                FROM pointages p
                INNER JOIN creneaux c ON c.id = p.id_creneau
                INNER JOIN emploi_temps et ON et.id = c.id_emploi_temps
                INNER JOIN enseignants e ON e.id = p.id_enseignant
                INNER JOIN matieres m ON m.id = c.id_matiere
                WHERE et.semaine_debut = :week_start AND c.jour = :jour AND p.statut = "retard"
                LIMIT 4
            ', ['week_start' => $weekStart, 'jour' => $today])
        );

        $activity = $this->fetchAll($db, '
            SELECT activity_time, event_label, reference_label, status_tone FROM (
                SELECT DATE_FORMAT(p.heure_pointage_reelle, "%H:%i") AS activity_time,
                       "Pointage QR effectué" AS event_label,
                       CONCAT(cl.libelle, " • ", m.libelle) AS reference_label,
                       CASE WHEN p.statut = "retard" THEN "warning" ELSE "success" END AS status_tone,
                       p.heure_pointage_reelle AS sort_at
                FROM pointages p
                INNER JOIN creneaux c ON c.id = p.id_creneau
                INNER JOIN emploi_temps et ON et.id = c.id_emploi_temps
                INNER JOIN classes cl ON cl.id = et.id_classe
                INNER JOIN matieres m ON m.id = c.id_matiere

                UNION ALL

                SELECT DATE_FORMAT(ct.date_creation, "%H:%i") AS activity_time,
                       CASE WHEN ct.statut = "cloture" THEN "Cahier de texte validé" ELSE "Cahier enregistré" END AS event_label,
                       CONCAT(cl.libelle, " • ", m.libelle) AS reference_label,
                       CASE WHEN ct.statut = "cloture" THEN "success" ELSE "primary" END AS status_tone,
                       ct.date_creation AS sort_at
                FROM cahiers_texte ct
                INNER JOIN creneaux c ON c.id = ct.id_creneau
                INNER JOIN emploi_temps et ON et.id = c.id_emploi_temps
                INNER JOIN classes cl ON cl.id = et.id_classe
                INNER JOIN matieres m ON m.id = c.id_matiere
            ) logs
            ORDER BY sort_at DESC
            LIMIT 6
        ');

        $delayDistribution = $this->fetchAll($db, '
            SELECT cl.libelle AS label, COUNT(*) AS value
            FROM pointages p
            INNER JOIN creneaux c ON c.id = p.id_creneau
            INNER JOIN emploi_temps et ON et.id = c.id_emploi_temps
            INNER JOIN classes cl ON cl.id = et.id_classe
            WHERE p.statut = "retard"
            GROUP BY cl.id, cl.libelle
            ORDER BY value DESC, cl.libelle ASC
            LIMIT 4
        ');

        $progressValues = array_map(fn ($row) => (int) ($row['value'] ?? 0), $programProgress);
        $programAverage = count($progressValues) ? round(array_sum($progressValues) / count($progressValues)) : 0;
        $progressSummary = [
            ['label' => 'Terminés', 'value' => count(array_filter($progressValues, fn ($v) => $v >= 80)), 'color' => '#48c78e'],
            ['label' => 'En cours', 'value' => count(array_filter($progressValues, fn ($v) => $v > 0 && $v < 80)), 'color' => '#5b9cf6'],
            ['label' => 'Non commencés', 'value' => count(array_filter($progressValues, fn ($v) => $v <= 0)), 'color' => '#d7dde7'],
        ];

        return [
            'headline' => [
                'title' => 'Vue globale du système',
                'subtitle' => 'Pilotage des séances, présences, cahiers et alertes du jour.',
            ],
            'kpis' => [
                ['label' => 'Séances du jour', 'value' => $sessionsToday, 'tone' => 'primary', 'hint' => 'Séances planifiées aujourd’hui'],
                ['label' => 'Taux de présence', 'value' => $sessionsToday > 0 ? round(($presentToday / $sessionsToday) * 100) . '%' : '0%', 'tone' => 'success', 'hint' => 'Enseignants effectivement pointés'],
                ['label' => 'Retards / absences', 'value' => $lateToday + max($sessionsToday - $presentToday, 0), 'tone' => 'warning', 'hint' => 'Retards et séances non pointées'],
                ['label' => 'Cahiers non signés', 'value' => $unsignedCahiers, 'tone' => 'danger', 'hint' => 'Signatures requises manquantes'],
                ['label' => 'Heures validées', 'value' => round($validatedHours) . 'h', 'tone' => 'primary', 'hint' => 'Vacations prêtes'],
            ],
            'sections' => [
                [
                    'title' => 'Heures planifiées vs réalisées',
                    'subtitle' => 'Comparaison rapide par classe sur la semaine en cours.',
                    'type' => 'comparison',
                    'items' => $classHours,
                ],
                [
                    'title' => 'Avancement des programmes',
                    'subtitle' => 'Progression moyenne par matière et par classe.',
                    'type' => 'progress',
                    'items' => array_map(fn ($row) => ['label' => $row['label'], 'value' => (int) ($row['value'] ?? 0)], $programProgress),
                ],
            ],
            'alerts' => array_slice($alerts, 0, 8),
            'activity' => $activity,
            'priority_actions' => array_slice($alerts, 0, 4),
            'program_summary' => $progressSummary,
            'program_average' => $programAverage,
            'delay_distribution' => $delayDistribution,
            'quick_actions' => [
                ['label' => 'Créer un emploi du temps', 'href' => 'emploi-temps.html', 'variant' => 'primary'],
                ['label' => 'Ajouter enseignant', 'href' => 'parametres.html#tab-enseignants', 'variant' => 'outline-primary'],
                ['label' => 'Générer rapport', 'href' => 'rapports.html', 'variant' => 'outline-primary'],
            ],
        ];
    }

    private function teacherDashboard(PDO $db, int $teacherId): array
    {
        $weekStart = $this->currentWeekStart();

        $sessions = $this->fetchAll($db, '
            SELECT c.id, c.jour, c.heure_debut, c.heure_fin, m.libelle AS matiere, cl.libelle AS classe,
                   p.id AS pointage_id, ct.id AS cahier_id, ct.statut AS cahier_statut
            FROM creneaux c
            INNER JOIN emploi_temps et ON et.id = c.id_emploi_temps
            INNER JOIN classes cl ON cl.id = et.id_classe
            INNER JOIN matieres m ON m.id = c.id_matiere
            LEFT JOIN pointages p ON p.id_creneau = c.id
            LEFT JOIN cahiers_texte ct ON ct.id_creneau = c.id
            WHERE c.id_enseignant = :teacher_id AND et.semaine_debut = :week_start
            ORDER BY FIELD(c.jour, "lundi", "mardi", "mercredi", "jeudi", "vendredi", "samedi"), c.heure_debut
        ', ['teacher_id' => $teacherId, 'week_start' => $weekStart]);

        $coming = 0;
        $pointed = 0;
        $closed = 0;
        foreach ($sessions as $session) {
            if (!empty($session['cahier_id']) && $session['cahier_statut'] === 'cloture') {
                $closed++;
            } elseif (!empty($session['pointage_id'])) {
                $pointed++;
            } else {
                $coming++;
            }
        }

        $vacSummary = $this->fetchOne($db, '
            SELECT COUNT(*) AS total,
                   SUM(CASE WHEN statut IN ("generee", "controlee") THEN 1 ELSE 0 END) AS en_cours,
                   SUM(CASE WHEN statut = "validee" THEN 1 ELSE 0 END) AS validees,
                   COALESCE(SUM(montant_net), 0) AS montant_total
            FROM vacations
            WHERE id_enseignant = :teacher_id
        ', ['teacher_id' => $teacherId]) ?: [];

        $history = $this->fetchAll($db, '
            SELECT c.jour, c.heure_debut, m.libelle AS matiere, cl.libelle AS classe, COALESCE(ct.statut, "a_faire") AS statut
            FROM creneaux c
            INNER JOIN emploi_temps et ON et.id = c.id_emploi_temps
            INNER JOIN classes cl ON cl.id = et.id_classe
            INNER JOIN matieres m ON m.id = c.id_matiere
            LEFT JOIN cahiers_texte ct ON ct.id_creneau = c.id
            WHERE c.id_enseignant = :teacher_id
            ORDER BY et.semaine_debut DESC, FIELD(c.jour, "lundi", "mardi", "mercredi", "jeudi", "vendredi", "samedi"), c.heure_debut DESC
            LIMIT 8
        ', ['teacher_id' => $teacherId]);

        return [
            'headline' => [
                'title' => 'Vue personnelle de l’enseignant',
                'subtitle' => 'Séances de la semaine, vacations et historique récent.',
            ],
            'kpis' => [
                ['label' => 'Séances semaine', 'value' => count($sessions), 'tone' => 'primary', 'hint' => 'Volume de la semaine en cours'],
                ['label' => 'À venir', 'value' => $coming, 'tone' => 'warning', 'hint' => 'Séances non encore pointées'],
                ['label' => 'Pointées', 'value' => $pointed, 'tone' => 'success', 'hint' => 'Présence enregistrée'],
                ['label' => 'Clôturées', 'value' => $closed, 'tone' => 'primary', 'hint' => 'Cahiers finalisés'],
            ],
            'sections' => [
                [
                    'title' => 'Mes vacations',
                    'subtitle' => 'État actuel des fiches et du montant cumulé.',
                    'type' => 'progress',
                    'items' => [
                        ['label' => 'Fiches en cours', 'value' => (int) ($vacSummary['en_cours'] ?? 0)],
                        ['label' => 'Fiches validées', 'value' => (int) ($vacSummary['validees'] ?? 0)],
                        ['label' => 'Montant total', 'value' => (int) ($vacSummary['montant_total'] ?? 0), 'format' => 'money'],
                    ],
                ],
                [
                    'title' => 'Historique des séances',
                    'subtitle' => 'Dernières séances passées et statut pédagogique.',
                    'type' => 'list',
                    'items' => array_map(fn ($row) => [
                        'label' => $row['matiere'],
                        'detail' => $row['classe'] . ' · ' . $row['jour'] . ' · ' . substr($row['heure_debut'], 0, 5),
                        'status' => $row['statut'],
                    ], $history),
                ],
            ],
            'alerts' => array_map(fn ($row) => [
                'label' => $row['matiere'],
                'detail' => $row['classe'] . ' · ' . $row['jour'] . ' · ' . substr($row['heure_debut'], 0, 5),
                'tone' => $row['cahier_statut'] === 'cloture' ? 'success' : (!empty($row['pointage_id']) ? 'primary' : 'warning'),
            ], array_slice($sessions, 0, 6)),
            'quick_actions' => [
                ['label' => 'Pointer une séance', 'href' => 'pointage-qr.html', 'variant' => 'primary'],
                ['label' => 'Ouvrir mon cahier', 'href' => 'cahier-texte.html', 'variant' => 'outline-primary'],
                ['label' => 'Voir mes vacations', 'href' => 'vacations.html', 'variant' => 'outline-primary'],
            ],
        ];
    }

    private function delegateDashboard(PDO $db, int $classId): array
    {
        $weekStart = $this->currentWeekStart();

        $sessions = $this->fetchAll($db, '
            SELECT c.id, c.jour, c.heure_debut, m.libelle AS matiere, COALESCE(ct.statut, "a_faire") AS statut
            FROM emploi_temps et
            INNER JOIN creneaux c ON c.id_emploi_temps = et.id
            INNER JOIN matieres m ON m.id = c.id_matiere
            LEFT JOIN cahiers_texte ct ON ct.id_creneau = c.id
            WHERE et.id_classe = :class_id AND et.semaine_debut = :week_start
            ORDER BY FIELD(c.jour, "lundi", "mardi", "mercredi", "jeudi", "vendredi", "samedi"), c.heure_debut
        ', ['class_id' => $classId, 'week_start' => $weekStart]);

        $toFill = count(array_filter($sessions, fn ($row) => $row['statut'] === 'a_faire'));
        $pending = count(array_filter($sessions, fn ($row) => in_array($row['statut'], ['brouillon', 'en_attente'], true)));
        $validated = count(array_filter($sessions, fn ($row) => $row['statut'] === 'cloture'));

        $signedHistory = $this->fetchAll($db, '
            SELECT m.libelle AS matiere, c.jour, c.heure_debut, ct.statut
            FROM cahiers_texte ct
            INNER JOIN creneaux c ON c.id = ct.id_creneau
            INNER JOIN emploi_temps et ON et.id = c.id_emploi_temps
            INNER JOIN matieres m ON m.id = c.id_matiere
            WHERE et.id_classe = :class_id
            ORDER BY ct.date_creation DESC
            LIMIT 8
        ', ['class_id' => $classId]);

        return [
            'headline' => [
                'title' => 'Suivi pédagogique de la classe',
                'subtitle' => 'Planning courant et suivi des cahiers à documenter.',
            ],
            'kpis' => [
                ['label' => 'Planning semaine', 'value' => count($sessions), 'tone' => 'primary', 'hint' => 'Séances prévues cette semaine'],
                ['label' => 'À remplir', 'value' => $toFill, 'tone' => 'warning', 'hint' => 'Séances sans cahier'],
                ['label' => 'En attente', 'value' => $pending, 'tone' => 'danger', 'hint' => 'Cahiers non finalisés'],
                ['label' => 'Validées', 'value' => $validated, 'tone' => 'success', 'hint' => 'Cahiers clôturés'],
            ],
            'sections' => [
                [
                    'title' => 'Emploi du temps de la semaine',
                    'subtitle' => 'Vue condensée des séances de la classe.',
                    'type' => 'list',
                    'items' => array_map(fn ($row) => [
                        'label' => $row['matiere'],
                        'detail' => $row['jour'] . ' · ' . substr($row['heure_debut'], 0, 5),
                        'status' => $row['statut'],
                    ], $sessions),
                ],
                [
                    'title' => 'Historique des cahiers signés',
                    'subtitle' => 'Dernières séances déjà documentées.',
                    'type' => 'list',
                    'items' => array_map(fn ($row) => [
                        'label' => $row['matiere'],
                        'detail' => $row['jour'] . ' · ' . substr($row['heure_debut'], 0, 5),
                        'status' => $row['statut'],
                    ], $signedHistory),
                ],
            ],
            'alerts' => array_map(fn ($row) => [
                'label' => $row['matiere'],
                'detail' => $row['jour'] . ' · ' . substr($row['heure_debut'], 0, 5),
                'tone' => $row['statut'] === 'a_faire' ? 'warning' : ($row['statut'] === 'cloture' ? 'success' : 'danger'),
            ], $sessions),
            'quick_actions' => [
                ['label' => 'Ouvrir le cahier', 'href' => 'cahier-texte.html', 'variant' => 'primary'],
                ['label' => 'Consulter le planning', 'href' => 'emploi-temps.html', 'variant' => 'outline-primary'],
            ],
        ];
    }

    private function supervisorDashboard(PDO $db): array
    {
        $requiredSignatures = $this->requiredSignatureCount($db, 'cahier');

        $pendingVacations = $this->scalar($db, 'SELECT COUNT(*) FROM vacations WHERE statut = "generee"');
        $incoherentCahiers = $this->scalar($db, '
            SELECT COUNT(*) FROM cahiers_texte WHERE statut IN ("brouillon", "en_attente")
        ');
        $sessionsWithoutQr = $this->scalar($db, 'SELECT COUNT(*) FROM creneaux WHERE qr_token IS NULL OR qr_token = ""');
        $unsignedCahiers = $this->scalar($db, '
            SELECT COUNT(*) FROM (
                SELECT ct.id, COUNT(s.id) AS total_signatures
                FROM cahiers_texte ct
                LEFT JOIN signatures s ON s.id_cahier = ct.id
                GROUP BY ct.id
            ) temp
            WHERE temp.total_signatures < :required_count
        ', ['required_count' => $requiredSignatures]);

        $vacationList = $this->fetchAll($db, '
            SELECT CONCAT(e.nom, " ", e.prenom) AS label,
                   CONCAT("Vacation ", v.mois, "/", v.annee) AS detail,
                   v.statut AS status
            FROM vacations v
            INNER JOIN enseignants e ON e.id = v.id_enseignant
            WHERE v.statut = "generee"
            ORDER BY v.annee DESC, v.mois DESC
            LIMIT 6
        ');

        $cahierProblems = $this->fetchAll($db, '
            SELECT m.libelle AS label,
                   CONCAT(cl.libelle, " · ", ct.statut) AS detail,
                   ct.statut AS status
            FROM cahiers_texte ct
            INNER JOIN creneaux c ON c.id = ct.id_creneau
            INNER JOIN emploi_temps et ON et.id = c.id_emploi_temps
            INNER JOIN classes cl ON cl.id = et.id_classe
            INNER JOIN matieres m ON m.id = c.id_matiere
            WHERE ct.statut IN ("brouillon", "en_attente")
            ORDER BY ct.date_creation DESC
            LIMIT 6
        ');

        return [
            'headline' => [
                'title' => 'Contrôle qualité',
                'subtitle' => 'Fiches à vérifier, cahiers incohérents et alertes de conformité.',
            ],
            'kpis' => [
                ['label' => 'Fiches à valider', 'value' => $pendingVacations, 'tone' => 'primary', 'hint' => 'Vacations en attente de contrôle'],
                ['label' => 'Cahiers incohérents', 'value' => $incoherentCahiers, 'tone' => 'danger', 'hint' => 'Cahiers à revoir'],
                ['label' => 'Séances sans QR', 'value' => $sessionsWithoutQr, 'tone' => 'warning', 'hint' => 'Tokens QR manquants'],
                ['label' => 'Cahiers non signés', 'value' => $unsignedCahiers, 'tone' => 'warning', 'hint' => 'Signatures obligatoires manquantes'],
            ],
            'sections' => [
                [
                    'title' => 'Fiches de vacation à vérifier',
                    'subtitle' => 'Éléments en attente de validation surveillant.',
                    'type' => 'list',
                    'items' => $vacationList,
                ],
                [
                    'title' => 'Cahiers incohérents',
                    'subtitle' => 'Anomalies détectées dans le flux pédagogique.',
                    'type' => 'list',
                    'items' => $cahierProblems,
                ],
            ],
            'alerts' => array_map(fn ($row) => [
                'label' => $row['label'],
                'detail' => $row['detail'],
                'tone' => 'danger',
            ], array_merge($vacationList, $cahierProblems)),
            'quick_actions' => [
                ['label' => 'Contrôler les vacations', 'href' => 'vacations.html', 'variant' => 'primary'],
                ['label' => 'Contrôler les cahiers', 'href' => 'cahier-texte.html', 'variant' => 'outline-primary'],
            ],
        ];
    }

    private function accountingDashboard(PDO $db): array
    {
        $readyToPay = $this->scalar($db, 'SELECT COUNT(*) FROM vacations WHERE statut = "controlee"');
        $validated = $this->scalar($db, 'SELECT COUNT(*) FROM vacations WHERE statut = "validee"');
        $amountReady = $this->scalar($db, 'SELECT COALESCE(SUM(montant_net), 0) FROM vacations WHERE statut IN ("controlee", "validee")');

        $paymentItems = $this->fetchAll($db, '
            SELECT CONCAT(e.nom, " ", e.prenom) AS label,
                   CONCAT("Montant net ", FORMAT(v.montant_net, 0), " FCFA") AS detail,
                   v.statut AS status
            FROM vacations v
            INNER JOIN enseignants e ON e.id = v.id_enseignant
            WHERE v.statut IN ("controlee", "validee")
            ORDER BY v.annee DESC, v.mois DESC
            LIMIT 8
        ');

        return [
            'headline' => [
                'title' => 'Paiement et validation finale',
                'subtitle' => 'Fiches contrôlées, montants à payer et actions comptables.',
            ],
            'kpis' => [
                ['label' => 'Fiches validées', 'value' => $validated, 'tone' => 'success', 'hint' => 'Déjà validées comptablement'],
                ['label' => 'À payer', 'value' => $readyToPay, 'tone' => 'warning', 'hint' => 'Fiches prêtes au paiement'],
                ['label' => 'Montants à payer', 'value' => $amountReady, 'tone' => 'primary', 'hint' => 'Cumul net à régler', 'format' => 'money'],
            ],
            'sections' => [
                [
                    'title' => 'Dossiers de paiement',
                    'subtitle' => 'Fiches déjà contrôlées ou validées.',
                    'type' => 'list',
                    'items' => $paymentItems,
                ],
            ],
            'alerts' => array_map(fn ($row) => [
                'label' => $row['label'],
                'detail' => $row['detail'],
                'tone' => $row['status'] === 'controlee' ? 'warning' : 'success',
            ], $paymentItems),
            'quick_actions' => [
                ['label' => 'Valider paiement', 'href' => 'vacations.html', 'variant' => 'primary'],
                ['label' => 'Télécharger PDF', 'href' => 'vacations.html', 'variant' => 'outline-primary'],
            ],
        ];
    }

    private function scalar(PDO $db, string $sql, array $params = []): int|float
    {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return (float) $stmt->fetchColumn();
    }

    private function fetchAll(PDO $db, string $sql, array $params = []): array
    {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private function fetchOne(PDO $db, string $sql, array $params = []): ?array
    {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch() ?: null;
    }

    private function currentWeekStart(): string
    {
        return date('Y-m-d', strtotime('monday this week'));
    }

    private function currentDayLabel(): string
    {
        return match ((int) date('N')) {
            1 => 'lundi',
            2 => 'mardi',
            3 => 'mercredi',
            4 => 'jeudi',
            5 => 'vendredi',
            6 => 'samedi',
            default => 'lundi',
        };
    }

    private function requiredSignatureCount(PDO $db, string $documentType): int
    {
        return (int) $this->scalar($db, '
            SELECT COUNT(*)
            FROM parametres_signatures
            WHERE document_type = :document_type AND obligatoire = 1 AND actif = 1
        ', ['document_type' => $documentType]);
    }
}
