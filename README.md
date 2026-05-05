# EduSchedule Pro

Système Intégré de Gestion de l'Emploi du Temps et de Suivi Pédagogique des Séances de Cours — ISGE · RST · 2025-2026.

- Frontend : `HTML5`, `CSS3`, `Bootstrap 5`, `JavaScript vanilla`
- Backend : API REST `PHP 8+`
- Base de données : `MySQL 8`

## Arborescence

```text
.
├── assets/
│   ├── css/          # Styles (style.css + theme.css)
│   ├── js/           # Scripts (app.js, api.js, auth.js)
│   └── vendor/       # Bootstrap, Bootstrap Icons
├── backend/
│   ├── config/       # Connexion BDD, CORS
│   ├── controllers/  # Logique métier PHP
│   ├── models/       # Accès données
│   ├── routes/       # Point d'entrée API (api.php)
│   └── utils/        # JWT, PDF, QR, logs
├── database/
│   └── eduschedule.sql   # Script de création + données de démonstration
├── index.html        # Page de connexion
└── .env.example      # Variables d'environnement (modèle)
```

## Prérequis

- PHP 8.1+
- MySQL 8+
- Serveur web Apache ou `php -S`

## Installation

1. Démarrer MySQL :

```bash
sudo service mysql start
```

2. Importer la base de données :

```bash
mysql -u root -p < database/eduschedule.sql
```

3. Copier et configurer les variables d'environnement :

```bash
cp .env.example .env
# Éditer .env avec vos identifiants MySQL
```

4. Lancer le serveur de développement :

```bash
php -S localhost:8000
```

5. Accéder à l'application : `http://localhost:8000`

## Comptes de démonstration

| Rôle                  | Email                    | Mot de passe  |
|-----------------------|--------------------------|---------------|
| Administrateur        | admin@campusflow.local   | Campus@2026   |
| Enseignant            | enseignant@test.com      | Campus@2026   |
| Délégué de classe     | delegue@test.com         | Campus@2026   |
| Surveillant général   | surveillant@test.com     | Campus@2026   |
| Responsable comptable | comptable@test.com       | Campus@2026   |
| Étudiant              | etudiant@test.com        | Campus@2026   |

## Principaux endpoints API

| Méthode | Endpoint                          | Description                        |
|---------|-----------------------------------|------------------------------------|
| POST    | /api/auth/login                   | Connexion — retourne JWT           |
| GET     | /api/classes                      | Liste des classes                  |
| GET     | /api/enseignants                  | Liste des enseignants              |
| GET     | /api/emploi-temps                 | Emploi du temps                    |
| GET     | /api/creneaux/{id}/qr             | QR-Code d'un créneau (SVG)         |
| POST    | /api/pointages/scan               | Valider le scan QR d'un enseignant |
| GET     | /api/cahiers                      | Cahiers de texte                   |
| POST    | /api/cahiers/{id}/signer          | Apposer une signature numérique    |
| POST    | /api/cahiers/{id}/cloturer        | Clôturer une séance                |
| GET     | /api/vacations                    | Fiches de vacation                 |
| POST    | /api/vacations/generer            | Générer une fiche mensuelle        |
| POST    | /api/vacations/{id}/valider       | Visa du surveillant                |
| POST    | /api/vacations/{id}/approuver     | Approbation comptable finale       |
| GET     | /api/vacations/{id}/pdf           | Télécharger la fiche en PDF        |
| GET     | /api/dashboard/stats              | Statistiques du tableau de bord    |
| GET     | /api/reports/summary              | Synthèse des rapports              |
| GET     | /api/logs                         | Journal d'activité (admin)         |

## Notes techniques

- Toutes les réponses API sont en JSON `{ success, message, data }`.
- Les mots de passe sont hashés avec `password_hash` (bcrypt).
- L'authentification utilise des tokens JWT (TTL configurable).
- La détection de conflits d'emploi du temps est effectuée côté serveur à chaque création/modification de créneau.
- Les QR-Codes sont générés en SVG côté serveur (tokens HMAC-SHA256, usage unique, fenêtre ±15 min).
- Les signatures numériques sont capturées via canvas HTML5 et stockées en base64.
