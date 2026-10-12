-- ============================================================
-- SSMUNIE — Base annuelle : élèves & classes (annuel_002)
-- ============================================================
-- Exécuté à CHAQUE création d'année scolaire, DANS la base
-- <initiale>-<YYYY> (ex. lyc-2026). Idempotent.
-- AUCUNE FK inter-bases. Soft-delete uniquement (statut / actif).
-- categorie_id / enseignant_principal_id : colonnes de liaison,
-- la table enseignant arrive en annuel_003 (pas de FK déclarée).
-- ============================================================

-- ---------------------------------------------------------------------------
-- 1. Classes
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS classe (
  id_classe INT AUTO_INCREMENT PRIMARY KEY,
  code_classe VARCHAR(30) UNIQUE NOT NULL,
  libellee_classe VARCHAR(150) NOT NULL,
  niveau_id INT NOT NULL,
  section_id INT NOT NULL,
  filiere_id INT NOT NULL,
  categorie_id INT NULL,
  enseignant_principal_id INT NULL,
  capacite_accueil INT DEFAULT 60,
  annee_id INT NOT NULL,
  actif TINYINT DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_classe_niveau (niveau_id),
  INDEX idx_classe_section (section_id),
  INDEX idx_classe_annee (annee_id)
);

-- ---------------------------------------------------------------------------
-- 2. Élèves
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS eleve (
  id_eleve INT AUTO_INCREMENT PRIMARY KEY,
  matricule VARCHAR(50) UNIQUE NOT NULL,
  matricule_mode ENUM('standard','section','classe') NOT NULL,
  nom VARCHAR(100) NOT NULL,
  prenom VARCHAR(150) NOT NULL,
  date_naissance DATE NULL,
  lieu_naissance VARCHAR(150) NULL,
  genre ENUM('M','F') NULL,
  nationalite VARCHAR(80) DEFAULT 'Camerounaise',
  adresse VARCHAR(255) NULL,
  nom_tuteur VARCHAR(200) NULL,
  telephone_tuteur VARCHAR(30) NULL,
  profession_tuteur VARCHAR(100) NULL,
  nom_mere VARCHAR(200) NULL,
  telephone_mere VARCHAR(30) NULL,
  profession_mere VARCHAR(100) NULL,
  email_parent VARCHAR(150) NULL,
  classe_id INT NULL,
  niveau_id INT NULL,
  section_id INT NOT NULL,
  filiere_id INT NOT NULL,
  acte_naissance VARCHAR(100) NULL,
  code_parent VARCHAR(50) NULL,
  statut ENUM('actif','inactif','diplome','transfere','decede') DEFAULT 'actif',
  motif_statut VARCHAR(255) NULL,
  redoublant TINYINT DEFAULT 0,
  anciennete VARCHAR(50) NULL,
  photo_path VARCHAR(255) NULL,
  photo_updated_at TIMESTAMP NULL,
  sante TEXT NULL,
  malade TINYINT DEFAULT 0,
  ethnie VARCHAR(80) NULL,
  infos_supplementaire TEXT NULL,
  date_enregistrement TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  created_by INT NULL,
  updated_by INT NULL,
  deleted_at TIMESTAMP NULL,
  annee_id INT NOT NULL,
  INDEX idx_eleve_classe (classe_id),
  INDEX idx_eleve_niveau (niveau_id),
  INDEX idx_eleve_section (section_id),
  INDEX idx_eleve_filiere (filiere_id),
  INDEX idx_eleve_statut (statut),
  INDEX idx_eleve_nom (nom, prenom),
  INDEX idx_eleve_code_parent (code_parent)
);

-- ---------------------------------------------------------------------------
-- 3. Photos d'élèves (une par année scolaire)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS photo_eleve (
  id INT AUTO_INCREMENT PRIMARY KEY,
  eleve_id INT NOT NULL,
  annee_id INT NOT NULL,
  chemin VARCHAR(255) NOT NULL,
  miniature VARCHAR(255) NULL,
  hash_sha256 VARCHAR(64) NULL,
  taille_octets INT NULL,
  uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  uploaded_by INT NULL,
  UNIQUE KEY uk_photo_eleve_annee (eleve_id, annee_id)
);

-- ---------------------------------------------------------------------------
-- 4. Compteurs de matricules (race-safe : SELECT ... FOR UPDATE + UPDATE)
--    section_id / classe_id : 0 = « non applicable » (les NULL ne dédupliquent
--    pas dans un index UNIQUE MySQL).
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS matricule_counter (
  id INT AUTO_INCREMENT PRIMARY KEY,
  annee_id INT NOT NULL,
  mode ENUM('standard','section','classe','enseignant') NOT NULL,
  section_id INT NULL,
  classe_id INT NULL,
  compteur INT DEFAULT 0,
  UNIQUE KEY uk_counter (annee_id, mode, section_id, classe_id)
);

INSERT IGNORE INTO schema_version (version) VALUES ('annuel_002');
