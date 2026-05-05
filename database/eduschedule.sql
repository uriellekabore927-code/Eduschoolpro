CREATE DATABASE IF NOT EXISTS campusflow_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE campusflow_db;

DROP TABLE IF EXISTS logs_activite;
DROP TABLE IF EXISTS parametres_signatures;
DROP TABLE IF EXISTS validations;
DROP TABLE IF EXISTS vacation_lignes;
DROP TABLE IF EXISTS vacations;
DROP TABLE IF EXISTS signatures;
DROP TABLE IF EXISTS cahiers_texte;
DROP TABLE IF EXISTS pointages;
DROP TABLE IF EXISTS creneaux;
DROP TABLE IF EXISTS emploi_temps;
DROP TABLE IF EXISTS utilisateurs;
DROP TABLE IF EXISTS roles;
DROP TABLE IF EXISTS salles;
DROP TABLE IF EXISTS enseignants;
DROP TABLE IF EXISTS classe_matieres;
DROP TABLE IF EXISTS matieres;
DROP TABLE IF EXISTS classes;
DROP TABLE IF EXISTS annees_academiques;

CREATE TABLE annees_academiques (
  id INT AUTO_INCREMENT PRIMARY KEY,
  libelle VARCHAR(20) NOT NULL UNIQUE,
  date_debut DATE NOT NULL,
  date_fin DATE NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 0
);

CREATE TABLE classes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NOT NULL UNIQUE,
  libelle VARCHAR(120) NOT NULL,
  niveau VARCHAR(50) NOT NULL,
  id_annee_academique INT NOT NULL,
  actif TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_classes_annee FOREIGN KEY (id_annee_academique) REFERENCES annees_academiques(id)
);

CREATE TABLE matieres (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NOT NULL UNIQUE,
  libelle VARCHAR(150) NOT NULL,
  volume_horaire_total DECIMAL(10,2) NOT NULL DEFAULT 0,
  coefficient DECIMAL(5,2) NOT NULL DEFAULT 1,
  actif TINYINT(1) NOT NULL DEFAULT 1
);

CREATE TABLE classe_matieres (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_classe INT NOT NULL,
  id_matiere INT NOT NULL,
  UNIQUE KEY uk_classe_matiere (id_classe, id_matiere),
  CONSTRAINT fk_cm_classe FOREIGN KEY (id_classe) REFERENCES classes(id) ON DELETE CASCADE,
  CONSTRAINT fk_cm_matiere FOREIGN KEY (id_matiere) REFERENCES matieres(id) ON DELETE CASCADE
);

CREATE TABLE enseignants (
  id INT AUTO_INCREMENT PRIMARY KEY,
  matricule VARCHAR(30) NOT NULL UNIQUE,
  nom VARCHAR(120) NOT NULL,
  prenom VARCHAR(120) NOT NULL,
  email VARCHAR(150) UNIQUE NULL,
  telephone VARCHAR(30) NULL,
  specialite VARCHAR(150) NULL,
  statut ENUM('permanent', 'vacataire') NOT NULL DEFAULT 'vacataire',
  taux_horaire DECIMAL(12,2) NOT NULL DEFAULT 5000,
  actif TINYINT(1) NOT NULL DEFAULT 1
);

CREATE TABLE salles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NOT NULL UNIQUE,
  libelle VARCHAR(120) NOT NULL,
  capacite INT NOT NULL DEFAULT 0,
  batiment VARCHAR(100) NULL,
  equipements TEXT NULL,
  actif TINYINT(1) NOT NULL DEFAULT 1
);

CREATE TABLE roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) NOT NULL UNIQUE,
  libelle VARCHAR(120) NOT NULL,
  permissions_json JSON NOT NULL,
  actif TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE utilisateurs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(120) NOT NULL,
  prenom VARCHAR(120) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  mot_de_passe_hash VARCHAR(255) NOT NULL,
  role VARCHAR(50) NOT NULL,
  id_lien INT NULL,
  type_lien ENUM('enseignant', 'classe', 'surveillant', 'comptable', 'aucun') NOT NULL DEFAULT 'aucun',
  permissions_json JSON NULL,
  actif TINYINT(1) NOT NULL DEFAULT 1,
  derniere_connexion DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_utilisateur_role FOREIGN KEY (role) REFERENCES roles(code) ON UPDATE CASCADE
);

CREATE TABLE emploi_temps (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_classe INT NOT NULL,
  semaine_debut DATE NOT NULL,
  statut_publication ENUM('brouillon', 'publie', 'archive') NOT NULL DEFAULT 'brouillon',
  cree_par INT NOT NULL,
  date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_emploi_temps_classe FOREIGN KEY (id_classe) REFERENCES classes(id),
  CONSTRAINT fk_emploi_temps_createur FOREIGN KEY (cree_par) REFERENCES utilisateurs(id)
);

CREATE TABLE creneaux (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_emploi_temps INT NOT NULL,
  id_matiere INT NOT NULL,
  id_enseignant INT NOT NULL,
  id_salle INT NOT NULL,
  jour ENUM('lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi') NOT NULL,
  heure_debut TIME NOT NULL,
  heure_fin TIME NOT NULL,
  type_seance ENUM('cours_magistral', 'td', 'tp', 'devoir') NOT NULL DEFAULT 'cours_magistral',
  devoir_prevu VARCHAR(255) NULL,
  devoir_date DATE NULL,
  qr_token VARCHAR(64) NOT NULL UNIQUE,
  qr_expire DATETIME NOT NULL,
  statut ENUM('planifie', 'confirme', 'annule') NOT NULL DEFAULT 'planifie',
  CONSTRAINT fk_creneau_emploi FOREIGN KEY (id_emploi_temps) REFERENCES emploi_temps(id) ON DELETE CASCADE,
  CONSTRAINT fk_creneau_matiere FOREIGN KEY (id_matiere) REFERENCES matieres(id),
  CONSTRAINT fk_creneau_enseignant FOREIGN KEY (id_enseignant) REFERENCES enseignants(id),
  CONSTRAINT fk_creneau_salle FOREIGN KEY (id_salle) REFERENCES salles(id),
  INDEX idx_creneaux_enseignant_jour (id_enseignant, jour, heure_debut, heure_fin),
  INDEX idx_creneaux_salle_jour (id_salle, jour, heure_debut, heure_fin)
);

CREATE TABLE pointages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_creneau INT NOT NULL,
  id_enseignant INT NOT NULL,
  heure_pointage_reelle DATETIME NOT NULL,
  ip_source VARCHAR(50) NULL,
  token_utilise VARCHAR(64) NOT NULL UNIQUE,
  statut ENUM('a_l_heure', 'retard', 'refuse') NOT NULL DEFAULT 'a_l_heure',
  CONSTRAINT fk_pointage_creneau FOREIGN KEY (id_creneau) REFERENCES creneaux(id),
  CONSTRAINT fk_pointage_enseignant FOREIGN KEY (id_enseignant) REFERENCES enseignants(id)
);

CREATE TABLE cahiers_texte (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_creneau INT NOT NULL,
  id_delegue INT NULL,
  titre_cours VARCHAR(255) NOT NULL,
  points_abordes TEXT NOT NULL,
  niveau_avancement VARCHAR(100) NULL,
  travaux_demandes TEXT NULL,
  observations TEXT NULL,
  heure_fin_reelle DATETIME NULL,
  statut ENUM('brouillon', 'signe', 'cloture') NOT NULL DEFAULT 'brouillon',
  date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_cahier_creneau FOREIGN KEY (id_creneau) REFERENCES creneaux(id),
  CONSTRAINT fk_cahier_delegue FOREIGN KEY (id_delegue) REFERENCES utilisateurs(id)
);

CREATE TABLE signatures (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_cahier INT NOT NULL,
  type_signataire ENUM('enseignant', 'delegue', 'surveillant', 'administrateur') NOT NULL,
  id_utilisateur INT NOT NULL,
  signature_base64 LONGTEXT NOT NULL,
  horodatage DATETIME NOT NULL,
  CONSTRAINT fk_signature_cahier FOREIGN KEY (id_cahier) REFERENCES cahiers_texte(id) ON DELETE CASCADE,
  CONSTRAINT fk_signature_user FOREIGN KEY (id_utilisateur) REFERENCES utilisateurs(id)
);

CREATE TABLE parametres_signatures (
  id INT AUTO_INCREMENT PRIMARY KEY,
  document_type ENUM('cahier', 'vacation') NOT NULL,
  role_signataire ENUM('enseignant', 'delegue', 'surveillant', 'administrateur', 'comptable') NOT NULL,
  ordre_validation TINYINT NOT NULL DEFAULT 1,
  obligatoire TINYINT(1) NOT NULL DEFAULT 1,
  actif TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE vacations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_enseignant INT NOT NULL,
  mois TINYINT NOT NULL,
  annee SMALLINT NOT NULL,
  total_heures DECIMAL(10,2) NOT NULL DEFAULT 0,
  montant_brut DECIMAL(12,2) NOT NULL DEFAULT 0,
  retenues DECIMAL(12,2) NOT NULL DEFAULT 0,
  montant_net DECIMAL(12,2) NOT NULL DEFAULT 0,
  statut ENUM('generee', 'controlee', 'validee', 'payee', 'rejetee') NOT NULL DEFAULT 'generee',
  date_generation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_vacation_enseignant FOREIGN KEY (id_enseignant) REFERENCES enseignants(id)
);

CREATE TABLE vacation_lignes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_vacation INT NOT NULL,
  id_creneau INT NOT NULL,
  duree_heures DECIMAL(8,2) NOT NULL,
  taux DECIMAL(12,2) NOT NULL,
  montant DECIMAL(12,2) NOT NULL,
  CONSTRAINT fk_vl_vacation FOREIGN KEY (id_vacation) REFERENCES vacations(id) ON DELETE CASCADE,
  CONSTRAINT fk_vl_creneau FOREIGN KEY (id_creneau) REFERENCES creneaux(id)
);

CREATE TABLE validations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_vacation INT NOT NULL,
  id_validateur INT NOT NULL,
  role_validateur ENUM('administrateur', 'enseignant', 'delegue', 'surveillant', 'comptable') NOT NULL,
  visa_base64 LONGTEXT NULL,
  commentaire TEXT NULL,
  date_validation DATETIME NOT NULL,
  CONSTRAINT fk_validation_vacation FOREIGN KEY (id_vacation) REFERENCES vacations(id) ON DELETE CASCADE,
  CONSTRAINT fk_validation_user FOREIGN KEY (id_validateur) REFERENCES utilisateurs(id)
);

CREATE TABLE logs_activite (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_utilisateur INT NULL,
  action VARCHAR(150) NOT NULL,
  details TEXT NULL,
  ip VARCHAR(50) NULL,
  date_heure TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_log_user FOREIGN KEY (id_utilisateur) REFERENCES utilisateurs(id)
);

INSERT INTO annees_academiques (id, libelle, date_debut, date_fin, active) VALUES
(1, '2025-2026', '2025-10-01', '2026-07-31', 1);

INSERT INTO classes (id, code, libelle, niveau, id_annee_academique, actif) VALUES
(1, 'L1-RST', 'Licence 1 Réseaux et Systèmes', 'L1', 1, 1),
(2, 'L2-INFO', 'Licence 2 Informatique', 'L2', 1, 1),
(3, 'L3-TEL', 'Licence 3 Télécoms', 'L3', 1, 1);

INSERT INTO matieres (id, code, libelle, volume_horaire_total, coefficient, actif) VALUES
(1, 'BD101', 'Base de données', 45, 3, 1),
(2, 'RES201', 'Réseaux', 60, 4, 1),
(3, 'WEB301', 'Développement Web', 40, 3, 1),
(4, 'ALG102', 'Algorithmique', 50, 3, 1),
(5, 'SYS202', 'Systèmes et architecture', 55, 4, 1);

INSERT INTO classe_matieres (id_classe, id_matiere) VALUES
(1, 4), (1, 5), (2, 1), (2, 2), (2, 4), (3, 2), (3, 3), (3, 5);

INSERT INTO enseignants (id, matricule, nom, prenom, email, telephone, specialite, statut, taux_horaire, actif) VALUES
(1, 'ENS001', 'OUEDRAOGO', 'Paul', 'paul.ouedraogo@isge.edu', '70000001', 'Base de données', 'vacataire', 5000, 1),
(2, 'ENS002', 'KABORE', 'Mariam', 'mariam.kabore@isge.edu', '70000002', 'Réseaux', 'permanent', 6000, 1),
(3, 'ENS003', 'TRAORE', 'Awa', 'awa.traore@isge.edu', '70000003', 'Développement Web', 'vacataire', 5500, 1),
(4, 'ENS004', 'ZONGO', 'Ibrahim', 'ibrahim.zongo@isge.edu', '70000004', 'Algorithmique', 'permanent', 6500, 1),
(5, 'ENS005', 'SAWADOGO', 'Clarisse', 'clarisse.sawadogo@isge.edu', '70000005', 'Systèmes', 'vacataire', 5200, 1);

INSERT INTO salles (id, code, libelle, capacite, batiment, equipements, actif) VALUES
(1, 'S101', 'Salle 101', 40, 'Bloc A', 'Projecteur, climatisation', 1),
(2, 'S102', 'Salle 102', 35, 'Bloc A', 'Projecteur', 1),
(3, 'LAB1', 'Laboratoire 1', 25, 'Bloc B', 'PC, switchs, vidéoprojecteur', 1);

INSERT INTO roles (id, code, libelle, permissions_json, actif) VALUES
(1, 'administrateur', 'Administrateur', JSON_ARRAY('dashboard','parametres','utilisateurs','emploi_temps','pointage','cahiers','vacations','rapports'), 1),
(2, 'enseignant', 'Enseignant', JSON_ARRAY('dashboard','pointage','cahiers','vacations','emploi_temps'), 1),
(3, 'delegue', 'Délégué', JSON_ARRAY('dashboard','cahiers','emploi_temps'), 1),
(4, 'surveillant', 'Surveillant', JSON_ARRAY('dashboard','pointage','cahiers','vacations','rapports'), 1),
(5, 'comptable', 'Comptable', JSON_ARRAY('dashboard','vacations','rapports'), 1),
(6, 'etudiant', 'Étudiant', JSON_ARRAY('emploi_temps'), 1);

INSERT INTO utilisateurs (id, nom, prenom, email, mot_de_passe_hash, role, id_lien, type_lien, permissions_json, actif, derniere_connexion) VALUES
(1, 'Admin', 'CampusFlow', 'admin@campusflow.local', '$2y$10$A3T96jiZRuwKqNxlgLBZx.ByKuDUAwv2Fy2tcJw8dvul5pyeB72HS', 'administrateur', NULL, 'aucun', NULL, 1, NULL),
(2, 'OUEDRAOGO', 'Paul', 'enseignant@campusflow.local', '$2y$10$A3T96jiZRuwKqNxlgLBZx.ByKuDUAwv2Fy2tcJw8dvul5pyeB72HS', 'enseignant', 1, 'enseignant', NULL, 1, NULL),
(3, 'Comptable', 'Service', 'comptable@campusflow.local', '$2y$10$A3T96jiZRuwKqNxlgLBZx.ByKuDUAwv2Fy2tcJw8dvul5pyeB72HS', 'comptable', NULL, 'comptable', NULL, 1, NULL),
(4, 'KABORE', 'Mariam', 'mariam@campusflow.local', '$2y$10$A3T96jiZRuwKqNxlgLBZx.ByKuDUAwv2Fy2tcJw8dvul5pyeB72HS', 'enseignant', 2, 'enseignant', NULL, 1, NULL),
(5, 'BARRY', 'Aïssata', 'delegue@campusflow.local', '$2y$10$A3T96jiZRuwKqNxlgLBZx.ByKuDUAwv2Fy2tcJw8dvul5pyeB72HS', 'delegue', 2, 'classe', NULL, 1, NULL),
(6, 'NANA', 'Georges', 'surveillant@campusflow.local', '$2y$10$A3T96jiZRuwKqNxlgLBZx.ByKuDUAwv2Fy2tcJw8dvul5pyeB72HS', 'surveillant', NULL, 'surveillant', NULL, 1, NULL);

INSERT INTO parametres_signatures (document_type, role_signataire, ordre_validation, obligatoire, actif) VALUES
('cahier', 'delegue', 1, 1, 1),
('cahier', 'enseignant', 2, 1, 1),
('vacation', 'enseignant', 1, 1, 1),
('vacation', 'surveillant', 2, 1, 1),
('vacation', 'comptable', 3, 1, 1);

INSERT INTO emploi_temps (id, id_classe, semaine_debut, statut_publication, cree_par) VALUES
(1, 2, '2026-04-27', 'publie', 1);

INSERT INTO creneaux (id, id_emploi_temps, id_matiere, id_enseignant, id_salle, jour, heure_debut, heure_fin, qr_token, qr_expire, statut) VALUES
(1, 1, 1, 1, 2, 'mardi', '10:00:00', '12:00:00', 'demoqr001', '2026-12-31 23:59:59', 'confirme'),
(2, 1, 2, 2, 3, 'jeudi', '08:00:00', '10:00:00', 'demoqr002', '2026-12-31 23:59:59', 'planifie'),
(3, 1, 4, 4, 1, 'lundi', '08:00:00', '10:00:00', 'demoqr003', '2026-12-31 23:59:59', 'confirme'),
(4, 1, 5, 5, 3, 'mercredi', '14:00:00', '16:00:00', 'demoqr004', '2026-12-31 23:59:59', 'planifie');

INSERT INTO pointages (id_creneau, id_enseignant, heure_pointage_reelle, ip_source, token_utilise, statut) VALUES
(1, 1, '2026-04-28 09:58:00', '127.0.0.1', 'demo-pointage-001', 'a_l_heure');

INSERT INTO cahiers_texte (id, id_creneau, id_delegue, titre_cours, points_abordes, niveau_avancement, travaux_demandes, observations, heure_fin_reelle, statut) VALUES
(1, 1, NULL, 'Modèle relationnel', 'Normalisation, clés primaires, dépendances fonctionnelles', '80%', 'Exercices 4 à 7', 'Bonne séance', '2026-04-28 12:05:00', 'cloture');
