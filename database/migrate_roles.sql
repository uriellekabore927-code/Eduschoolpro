USE campusflow_db;

CREATE TABLE IF NOT EXISTS roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) NOT NULL UNIQUE,
  libelle VARCHAR(120) NOT NULL,
  permissions_json JSON NOT NULL,
  actif TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO roles (code, libelle, permissions_json, actif) VALUES
('administrateur', 'Administrateur', JSON_ARRAY('dashboard','parametres','utilisateurs','emploi_temps','pointage','cahiers','vacations','rapports'), 1),
('enseignant', 'Enseignant', JSON_ARRAY('dashboard','pointage','cahiers','vacations','emploi_temps'), 1),
('delegue', 'Délégué', JSON_ARRAY('dashboard','cahiers','emploi_temps'), 1),
('surveillant', 'Surveillant', JSON_ARRAY('dashboard','pointage','cahiers','vacations','rapports'), 1),
('comptable', 'Comptable', JSON_ARRAY('dashboard','vacations','rapports'), 1),
('etudiant', 'Étudiant', JSON_ARRAY('emploi_temps'), 1)
ON DUPLICATE KEY UPDATE
  libelle = VALUES(libelle),
  permissions_json = VALUES(permissions_json),
  actif = VALUES(actif);

ALTER TABLE utilisateurs
  MODIFY COLUMN role VARCHAR(50) NOT NULL;

SET @fk_exists := (
  SELECT COUNT(*)
  FROM information_schema.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'utilisateurs'
    AND CONSTRAINT_NAME = 'fk_utilisateur_role'
);

SET @fk_sql := IF(
  @fk_exists = 0,
  'ALTER TABLE utilisateurs ADD CONSTRAINT fk_utilisateur_role FOREIGN KEY (role) REFERENCES roles(code) ON UPDATE CASCADE',
  'SELECT 1'
);

PREPARE stmt_fk FROM @fk_sql;
EXECUTE stmt_fk;
DEALLOCATE PREPARE stmt_fk;
