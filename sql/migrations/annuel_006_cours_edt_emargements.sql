-- ============================================================
-- SSMUNIE — Cours + Emploi du temps + Émargements (annuel_006, B5)
-- ============================================================
-- Décisions validées :
--   - id_uuid partout (VARCHAR(36), convention B2-B4b) ; SANS annee_id (Q44).
--   - Cours = (matière, classe, enseignant) ; unicité matière×classe.
--   - Soft delete par actif=0 (jamais de DELETE SQL).
--   - Tranches horaires : 12 créneaux de cours + 1 marqueur fin, 2 pauses.
--   - Emargements : cycle en_attente -> valide (censeur) / refuse (motif).
--   - Jour « Samedi » ajouté (ordre 6) — utilisé seulement si autoriser_samedi=1.
-- Idempotent.
-- ============================================================

-- ---------------------------------------------------------------------------
-- 1. Cours : lien matière × classe × enseignant + coefficient + volume
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cours (
  id_cours INT AUTO_INCREMENT PRIMARY KEY,
  id_uuid VARCHAR(36) UNIQUE NOT NULL,
  matiere_id INT NOT NULL,
  classe_id INT NOT NULL,
  enseignant_id INT NULL,
  coef DECIMAL(4,2) NOT NULL DEFAULT 1.00,
  volume_horaire INT NOT NULL DEFAULT 0,
  credit DECIMAL(4,2) DEFAULT 0,
  semestre ENUM('annuel','S1','S2') DEFAULT 'annuel',
  duree_max_seance INT DEFAULT 2,
  actif TINYINT DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_cours_matiere (matiere_id),
  INDEX idx_cours_classe (classe_id),
  INDEX idx_cours_ens (enseignant_id),
  UNIQUE KEY uk_cours (matiere_id, classe_id)
);

-- ---------------------------------------------------------------------------
-- 2. Tranches horaires (créneaux de base)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tranches_horaires (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_uuid VARCHAR(36) UNIQUE NOT NULL,
  heure_debut TIME NOT NULL,
  heure_fin TIME NOT NULL,
  est_pause TINYINT DEFAULT 0,
  ordre INT NOT NULL,
  libelle VARCHAR(50) NULL,
  actif TINYINT DEFAULT 1,
  UNIQUE KEY uk_tranche_ordre (ordre)
);

-- ---------------------------------------------------------------------------
-- 3. Emploi du temps (créneaux affectés)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS emploi_temps (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_uuid VARCHAR(36) UNIQUE NOT NULL,
  cours_id INT NOT NULL,
  tranche_id INT NOT NULL,
  jour_id INT NOT NULL,
  salle VARCHAR(50) NULL,
  origine ENUM('auto','manuel') DEFAULT 'manuel',
  actif TINYINT DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_edt_cours (cours_id),
  INDEX idx_edt_jour (jour_id),
  INDEX idx_edt_tranche (tranche_id),
  UNIQUE KEY uk_edt_creneau (cours_id, jour_id, tranche_id)
);

-- ---------------------------------------------------------------------------
-- 4. Indisponibilités (contraintes profs)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS indisponibilite (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_uuid VARCHAR(36) UNIQUE NOT NULL,
  enseignant_id INT NOT NULL,
  jour_id INT NOT NULL,
  tranche_id INT NOT NULL,
  motif VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_indispo_ens (enseignant_id),
  INDEX idx_indispo_jour (jour_id),
  UNIQUE KEY uk_indispo (enseignant_id, jour_id, tranche_id)
);

-- ---------------------------------------------------------------------------
-- 5. Paramètres de génération EDT
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS parametres_emploi (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_uuid VARCHAR(36) UNIQUE NOT NULL,
  max_heures_jour_prof INT DEFAULT 6,
  max_seances_matiere_jour INT DEFAULT 2,
  duree_max_bloc INT DEFAULT 3,
  autoriser_samedi TINYINT DEFAULT 0,
  autoriser_chevauchement_pause TINYINT DEFAULT 0,
  mode_prioritaire ENUM('volume','equilibre','profs') DEFAULT 'volume',
  actif TINYINT DEFAULT 1,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ---------------------------------------------------------------------------
-- 6. Émargements (cahier de textes)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS emargements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_uuid VARCHAR(36) UNIQUE NOT NULL,
  emploi_temps_id INT NULL,
  cours_id INT NOT NULL,
  enseignant_id INT NOT NULL,
  date_cours DATE NOT NULL,
  heure_debut_reelle TIME NULL,
  heure_fin_reelle TIME NULL,
  duree_minutes INT NULL,
  contenu TEXT NULL,
  observation TEXT NULL,
  statut ENUM('fait','annule','reporte','absent') DEFAULT 'fait',
  valide TINYINT DEFAULT 0,
  motif_refus TEXT NULL,
  valide_par INT NULL,
  date_validation TIMESTAMP NULL,
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_emarg_cours (cours_id),
  INDEX idx_emarg_ens (enseignant_id),
  INDEX idx_emarg_date (date_cours),
  INDEX idx_emarg_statut (statut),
  INDEX idx_emarg_valide (valide)
);

-- ============================================================================
-- SEEDS
-- ============================================================================

-- Jour « Samedi » (ordre 6) — utilisé seulement si autoriser_samedi=1.
-- Idempotent : INSERT IGNORE (le libellé est UNIQUE).
INSERT IGNORE INTO jour (libellee_jour, ordre, actif) VALUES ('Samedi', 6, 1);

-- Tranches horaires (13) : 07:30 -> 16:40, 2 pauses (10:00-10:10 et 12:40-13:30).
-- Le marqueur de fin (ordre 13, 16:40-16:40) n'est PAS un créneau de cours.
-- ⚠ Correction : la pause « ordre 9 (13:10-13:50) » du legacy N'EST PAS reprise.
INSERT IGNORE INTO tranches_horaires (id_uuid, heure_debut, heure_fin, est_pause, ordre, libelle, actif) VALUES
  (UUID(), '07:30:00', '08:25:00', 0, 1,  'T1', 1),
  (UUID(), '08:25:00', '09:20:00', 0, 2,  'T2', 1),
  (UUID(), '09:20:00', '10:00:00', 0, 3,  'T3', 1),
  (UUID(), '10:00:00', '10:10:00', 1, 4,  'Pause', 1),
  (UUID(), '10:10:00', '11:05:00', 0, 5,  'T5', 1),
  (UUID(), '11:05:00', '12:00:00', 0, 6,  'T6', 1),
  (UUID(), '12:00:00', '12:40:00', 0, 7,  'T7', 1),
  (UUID(), '12:40:00', '13:30:00', 1, 8,  'Pause midi', 1),
  (UUID(), '13:30:00', '14:25:00', 0, 9,  'T9', 1),
  (UUID(), '14:25:00', '15:20:00', 0, 10, 'T10', 1),
  (UUID(), '15:20:00', '16:05:00', 0, 11, 'T11', 1),
  (UUID(), '16:05:00', '16:40:00', 0, 12, 'T12', 1),
  (UUID(), '16:40:00', '16:40:00', 0, 13, 'Fin', 1);

-- Paramètres de génération (1 ligne par défaut)
INSERT IGNORE INTO parametres_emploi (id_uuid, max_heures_jour_prof, max_seances_matiere_jour, duree_max_bloc, autoriser_samedi, autoriser_chevauchement_pause, mode_prioritaire, actif) VALUES
  (UUID(), 6, 2, 3, 0, 0, 'volume', 1);

INSERT IGNORE INTO schema_version (version) VALUES ('annuel_006');
