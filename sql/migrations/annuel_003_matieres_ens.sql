-- ============================================================
-- SSMUNIE — Base annuelle : matières, évaluations, enseignants,
-- habilitations (annuel_003)
-- ============================================================
-- Exécuté à CHAQUE création d'année scolaire, DANS la base
-- <initiale>-<YYYY>. Idempotent.
-- niveau_id / section_id de seed : 0 = « tous niveaux / toutes sections »
-- (les NULL ne dédupliquent pas dans un index UNIQUE MySQL).
-- annee_id = 0 : renseigné après création (ssmunie_create_annee_db).
-- AUCUNE FK inter-bases.
-- ============================================================

-- ---------------------------------------------------------------------------
-- 1. Matières
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS matiere (
  id_matiere INT AUTO_INCREMENT PRIMARY KEY,
  code_matiere VARCHAR(20) NOT NULL,
  nom_matiere VARCHAR(150) NOT NULL,
  description_mat TEXT NULL,
  section_id INT NOT NULL,
  filiere_id INT NOT NULL,
  niveau_id INT NULL,
  coefficient DECIMAL(4,2) DEFAULT 1.00,
  type ENUM('generale','technique','optionnelle','competence') DEFAULT 'generale',
  actif TINYINT DEFAULT 1,
  annee_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_matiere_code (code_matiere, section_id, filiere_id, niveau_id),
  INDEX idx_matiere_section (section_id),
  INDEX idx_matiere_niveau (niveau_id)
);

-- ---------------------------------------------------------------------------
-- 2. Évaluations (types d'évaluation par défaut)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS evaluation (
  id_eval INT AUTO_INCREMENT PRIMARY KEY,
  nom_evaluation VARCHAR(150) NOT NULL,
  bareme DECIMAL(5,2) NOT NULL DEFAULT 20.00,
  section_id INT NULL,
  niveau_id INT NULL,
  type ENUM('interrogation','devoir','composition','examen','oral','tp','projet') DEFAULT 'devoir',
  ordre INT DEFAULT 0,
  actif TINYINT DEFAULT 1,
  annee_id INT NOT NULL,
  UNIQUE KEY uk_eval_nom (nom_evaluation, section_id, niveau_id)
);

-- ---------------------------------------------------------------------------
-- 3. Enseignants
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS enseignant (
  id_enseignant INT AUTO_INCREMENT PRIMARY KEY,
  matricule_ens VARCHAR(50) UNIQUE NOT NULL,
  civilite ENUM('M','Mme','Mlle') NULL,
  nom VARCHAR(100) NOT NULL,
  prenom VARCHAR(150) NOT NULL,
  date_naissance DATE NULL,
  lieu_naissance VARCHAR(150) NULL,
  genre ENUM('M','F') NULL,
  nationalite VARCHAR(80) DEFAULT 'Camerounaise',
  adresse VARCHAR(255) NULL,
  telephone VARCHAR(30) NULL,
  email VARCHAR(150) NULL,
  statut_emploi ENUM('permanent','contractuel','vacataire','stagiaire') DEFAULT 'contractuel',
  grade VARCHAR(50) NULL,
  specialite VARCHAR(150) NULL,
  date_embauche DATE NULL,
  debut_contrat DATE NULL,
  fin_contrat DATE NULL,
  salaire_base DECIMAL(12,2) NULL,
  langues VARCHAR(100) NULL,
  photo_path VARCHAR(255) NULL,
  photo_updated_at TIMESTAMP NULL,
  cni_path VARCHAR(255) NULL,
  contrat_path VARCHAR(255) NULL,
  cv_path VARCHAR(255) NULL,
  diplome_path VARCHAR(255) NULL,
  statut ENUM('actif','inactif','suspendu','parti') DEFAULT 'actif',
  motif_statut VARCHAR(255) NULL,
  date_enregistrement TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  created_by INT NULL,
  updated_by INT NULL,
  deleted_at TIMESTAMP NULL,
  annee_id INT NOT NULL,
  INDEX idx_ens_nom (nom, prenom),
  INDEX idx_ens_statut (statut),
  INDEX idx_ens_matricule (matricule_ens)
);

-- ---------------------------------------------------------------------------
-- 4. Habilitations (enseignant ↔ matière ↔ classe)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS enseignant_matiere (
  id INT AUTO_INCREMENT PRIMARY KEY,
  enseignant_id INT NOT NULL,
  matiere_id INT NOT NULL,
  classe_id INT NULL,
  annee_id INT NOT NULL,
  date_attribution TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  attribue_par INT NULL,
  UNIQUE KEY uk_habil (enseignant_id, matiere_id, classe_id, annee_id),
  INDEX idx_habil_ens (enseignant_id),
  INDEX idx_habil_mat (matiere_id)
);

-- ---------------------------------------------------------------------------
-- 5. Documents enseignant (métadonnées — fichiers stockés hors BDD)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS document_enseignant (
  id INT AUTO_INCREMENT PRIMARY KEY,
  enseignant_id INT NOT NULL,
  type ENUM('cni','contrat','cv','diplome','attestation','autre') NOT NULL,
  chemin VARCHAR(255) NOT NULL,
  nom_original VARCHAR(255) NULL,
  hash_sha256 VARCHAR(64) NULL,
  taille_octets INT NULL,
  mime_type VARCHAR(100) NULL,
  uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  uploaded_by INT NULL,
  INDEX idx_doc_ens (enseignant_id)
);

-- ============================================================================
-- SEED — Matières
-- ============================================================================

-- PRIMAIRE FR (section 2, filière 1)
INSERT IGNORE INTO matiere (code_matiere, nom_matiere, section_id, filiere_id, niveau_id, type, actif, annee_id) VALUES
  ('FRANCAIS',        'Français',            2, 1, 0, 'generale', 1, 0),
  ('ANGLAIS',         'Anglais',             2, 1, 0, 'generale', 1, 0),
  ('MATHEMATIQUES',   'Mathématiques',       2, 1, 0, 'generale', 1, 0),
  ('SCIENCES',        'Sciences',            2, 1, 0, 'generale', 1, 0),
  ('HISTOIRE',        'Histoire',            2, 1, 0, 'generale', 1, 0),
  ('GEOGRAPHIE',      'Géographie',          2, 1, 0, 'generale', 1, 0),
  ('EDUCATION_CIVIQUE','Éducation civique',  2, 1, 0, 'generale', 1, 0),
  ('EPS',             'EPS',                 2, 1, 0, 'generale', 1, 0),
  ('ARTS',            'Arts',                2, 1, 0, 'generale', 1, 0),
  ('CHANT',           'Chant',               2, 1, 0, 'generale', 1, 0);

-- PRIMAIRE EN (section 2, filière 2)
INSERT IGNORE INTO matiere (code_matiere, nom_matiere, section_id, filiere_id, niveau_id, type, actif, annee_id) VALUES
  ('ENGLISH',         'English Language',    2, 2, 0, 'generale', 1, 0),
  ('FRENCH',          'French',              2, 2, 0, 'generale', 1, 0),
  ('MATHEMATICS',     'Mathematics',         2, 2, 0, 'generale', 1, 0),
  ('SCIENCE',         'Science',             2, 2, 0, 'generale', 1, 0),
  ('SOCIAL_STUDIES',  'Social Studies',      2, 2, 0, 'generale', 1, 0),
  ('CITIZENSHIP',     'Citizenship',         2, 2, 0, 'generale', 1, 0),
  ('PE',              'Physical Education',  2, 2, 0, 'generale', 1, 0),
  ('ARTS',            'Arts',                2, 2, 0, 'generale', 1, 0),
  ('MUSIC',           'Music',               2, 2, 0, 'generale', 1, 0),
  ('HANDWRITING',     'Handwriting',         2, 2, 0, 'generale', 1, 0);

-- COLLÈGE GÉNÉRAL FR (section 3, filière 1)
INSERT IGNORE INTO matiere (code_matiere, nom_matiere, section_id, filiere_id, niveau_id, type, actif, annee_id) VALUES
  ('FRANCAIS',       'Français',                 3, 1, 0, 'generale', 1, 0),
  ('ANGLAIS',        'Anglais',                  3, 1, 0, 'generale', 1, 0),
  ('MATHEMATIQUES',  'Mathématiques',            3, 1, 0, 'generale', 1, 0),
  ('SVT',            'SVT',                      3, 1, 0, 'generale', 1, 0),
  ('PCT',            'PCT',                      3, 1, 0, 'generale', 1, 0),
  ('HISTOIRE_GEO',   'Histoire-Géographie',      3, 1, 0, 'generale', 1, 0),
  ('ECM',            'ECM',                      3, 1, 0, 'generale', 1, 0),
  ('INFORMATIQUE',   'Informatique',             3, 1, 0, 'generale', 1, 0),
  ('EPS',            'EPS',                      3, 1, 0, 'generale', 1, 0),
  ('ALLEMAND',       'Allemand',                 3, 1, 0, 'generale', 1, 0),
  ('ESPAGNOL',       'Espagnol',                 3, 1, 0, 'generale', 1, 0),
  ('ARABE',          'Arabe',                    3, 1, 0, 'generale', 1, 0),
  ('PHILOSOPHIE',    'Philosophie',              3, 1, 0, 'generale', 1, 0);

-- COLLÈGE GÉNÉRAL EN (section 3, filière 2)
INSERT IGNORE INTO matiere (code_matiere, nom_matiere, section_id, filiere_id, niveau_id, type, actif, annee_id) VALUES
  ('ENGLISH_LANG',    'English Language',       3, 2, 0, 'generale', 1, 0),
  ('FRENCH',          'French',                 3, 2, 0, 'generale', 1, 0),
  ('MATHEMATICS',     'Mathematics',            3, 2, 0, 'generale', 1, 0),
  ('PURE_MATH',       'Pure Mathematics',       3, 2, 0, 'generale', 1, 0),
  ('FURTHER_MATH',    'Further Mathematics',    3, 2, 0, 'generale', 1, 0),
  ('BIOLOGY',         'Biology',                3, 2, 0, 'generale', 1, 0),
  ('CHEMISTRY',       'Chemistry',              3, 2, 0, 'generale', 1, 0),
  ('PHYSICS',         'Physics',                3, 2, 0, 'generale', 1, 0),
  ('HISTORY',         'History',                3, 2, 0, 'generale', 1, 0),
  ('GEOGRAPHY',       'Geography',              3, 2, 0, 'generale', 1, 0),
  ('CITIZENSHIP',     'Citizenship',            3, 2, 0, 'generale', 1, 0),
  ('ICT',             'ICT',                    3, 2, 0, 'generale', 1, 0),
  ('PE',              'Physical Education',     3, 2, 0, 'generale', 1, 0),
  ('RELIGIOUS_STUDIES','Religious Studies',     3, 2, 0, 'generale', 1, 0),
  ('LITERATURE',      'Literature',             3, 2, 0, 'generale', 1, 0),
  ('ECONOMICS',       'Economics',              3, 2, 0, 'generale', 1, 0),
  ('COMMERCE',        'Commerce',               3, 2, 0, 'generale', 1, 0),
  ('ACCOUNTING',      'Accounting',             3, 2, 0, 'generale', 1, 0);

-- TECHNIQUE (section 4, filière 1) — catalogue de 40 matières « prêtes à activer » (actif = 0)
INSERT IGNORE INTO matiere (code_matiere, nom_matiere, section_id, filiere_id, niveau_id, type, actif, annee_id) VALUES
  ('FRANCAIS',             'Français',                         4, 1, 0, 'technique', 0, 0),
  ('ANGLAIS',              'Anglais',                          4, 1, 0, 'technique', 0, 0),
  ('MATHEMATIQUES',        'Mathématiques',                    4, 1, 0, 'technique', 0, 0),
  ('PHYSIQUE',             'Physique',                         4, 1, 0, 'technique', 0, 0),
  ('CHIMIE',               'Chimie',                           4, 1, 0, 'technique', 0, 0),
  ('HISTOIRE_GEO',         'Histoire-Géographie',              4, 1, 0, 'technique', 0, 0),
  ('ECM',                  'ECM',                              4, 1, 0, 'technique', 0, 0),
  ('EPS',                  'EPS',                              4, 1, 0, 'technique', 0, 0),
  ('INFORMATIQUE',         'Informatique',                     4, 1, 0, 'technique', 0, 0),
  ('DESSIN_TECHNIQUE',     'Dessin technique',                 4, 1, 0, 'technique', 0, 0),
  ('TECHNOLOGIE',          'Technologie',                      4, 1, 0, 'technique', 0, 0),
  ('LEGISLATION_TRAVAIL',  'Législation du travail',           4, 1, 0, 'technique', 0, 0),
  ('ELECTRICITE',          'Électricité',                      4, 1, 0, 'technique', 0, 0),
  ('ELECTRONIQUE',         'Électronique',                     4, 1, 0, 'technique', 0, 0),
  ('ELECTROTECHNIQUE',     'Électrotechnique',                 4, 1, 0, 'technique', 0, 0),
  ('MECANIQUE_GENERALE',   'Mécanique générale',               4, 1, 0, 'technique', 0, 0),
  ('MECANIQUE_AUTO',       'Mécanique automobile',             4, 1, 0, 'technique', 0, 0),
  ('MECANIQUE_FABRICATION','Mécanique de fabrication',         4, 1, 0, 'technique', 0, 0),
  ('FROID_CLIM',           'Froid et climatisation',           4, 1, 0, 'technique', 0, 0),
  ('CHAUDRONNERIE',        'Chaudronnerie',                    4, 1, 0, 'technique', 0, 0),
  ('SOUDURE',              'Soudure',                          4, 1, 0, 'technique', 0, 0),
  ('CONSTRUCTION_METALLIQUE','Construction métallique',        4, 1, 0, 'technique', 0, 0),
  ('MENUISERIE',           'Menuiserie',                       4, 1, 0, 'technique', 0, 0),
  ('EBENISTERIE',          'Ébénisterie',                      4, 1, 0, 'technique', 0, 0),
  ('MACONNERIE',           'Maçonnerie',                       4, 1, 0, 'technique', 0, 0),
  ('GENIE_CIVIL',          'Génie civil',                      4, 1, 0, 'technique', 0, 0),
  ('TOPOGRAPHIE',          'Topographie',                      4, 1, 0, 'technique', 0, 0),
  ('DESSIN_BATIMENT',      'Dessin bâtiment',                  4, 1, 0, 'technique', 0, 0),
  ('PLOMBERIE',            'Plomberie',                        4, 1, 0, 'technique', 0, 0),
  ('INSTALLATION_SANITAIRE','Installation sanitaire',          4, 1, 0, 'technique', 0, 0),
  ('COMPTABILITE',         'Comptabilité',                     4, 1, 0, 'technique', 0, 0),
  ('GESTION',              'Gestion',                          4, 1, 0, 'technique', 0, 0),
  ('SECRETARIAT',          'Secrétariat',                      4, 1, 0, 'technique', 0, 0),
  ('BUREAUTIQUE',          'Bureautique',                      4, 1, 0, 'technique', 0, 0),
  ('COMMERCE',             'Commerce',                         4, 1, 0, 'technique', 0, 0),
  ('VENTE',                'Vente',                            4, 1, 0, 'technique', 0, 0),
  ('ECONOMIE',             'Économie',                         4, 1, 0, 'technique', 0, 0),
  ('DROIT',                'Droit',                            4, 1, 0, 'technique', 0, 0),
  ('COUTURE',              'Couture',                          4, 1, 0, 'technique', 0, 0),
  ('CUISINE',              'Cuisine',                          4, 1, 0, 'technique', 0, 0);

-- ============================================================================
-- SEED — Évaluations (6 types par défaut, section_id/niveau_id = 0 = global)
-- ============================================================================
INSERT IGNORE INTO evaluation (nom_evaluation, bareme, section_id, niveau_id, type, ordre, actif, annee_id) VALUES
  ('Interrogation',    20.00, 0, 0, 'interrogation', 1, 1, 0),
  ('Devoir surveillé', 20.00, 0, 0, 'devoir',        2, 1, 0),
  ('Composition',      20.00, 0, 0, 'composition',   3, 1, 0),
  ('Examen',           20.00, 0, 0, 'examen',        4, 1, 0),
  ('Oral',             20.00, 0, 0, 'oral',          5, 1, 0),
  ('TP',               20.00, 0, 0, 'tp',            6, 1, 0);

INSERT IGNORE INTO schema_version (version) VALUES ('annuel_003');
