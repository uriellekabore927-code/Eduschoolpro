USE campusflow_db;

SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE vacation_lignes;
TRUNCATE validations;
TRUNCATE vacations;
TRUNCATE signatures;
TRUNCATE cahiers_texte;
TRUNCATE pointages;
TRUNCATE creneaux;
TRUNCATE emploi_temps;
TRUNCATE classe_matieres;
TRUNCATE matieres;
TRUNCATE classes;
TRUNCATE annees_academiques;
TRUNCATE enseignants;
TRUNCATE logs_activite;

DELETE FROM utilisateurs WHERE id <> 1;

SET FOREIGN_KEY_CHECKS = 1;
