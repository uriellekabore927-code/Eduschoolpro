ALTER TABLE creneaux
  ADD COLUMN IF NOT EXISTS type_seance ENUM('cours_magistral', 'td', 'tp', 'devoir') NOT NULL DEFAULT 'cours_magistral' AFTER heure_fin,
  ADD COLUMN IF NOT EXISTS devoir_prevu VARCHAR(255) NULL AFTER type_seance,
  ADD COLUMN IF NOT EXISTS devoir_date DATE NULL AFTER devoir_prevu;
