-- ============================================================
-- SSMUNIE — Base annuelle : référentiels (annuel_001)
-- ============================================================
-- Exécuté à CHAQUE création d'année scolaire, DANS la base
-- `<initiale>-<YYYY>` (ex. njike-2026).
-- Idempotent : CREATE TABLE IF NOT EXISTS + INSERT IGNORE
-- (clés UNIQUE sur les codes naturels).
-- AUCUNE FK inter-bases : section/filiere sont COPIÉES localement.
-- ============================================================

-- ---------------------------------------------------------------------------
-- 1. Copies locales (jointures) de meta.section / meta.filiere
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS section (
  id_section INT PRIMARY KEY,
  code VARCHAR(10) NOT NULL,
  libelle_fr VARCHAR(100) NOT NULL,
  libelle_en VARCHAR(100) NOT NULL,
  ministere VARCHAR(20) NOT NULL,
  examen_final VARCHAR(60) NULL
);

CREATE TABLE IF NOT EXISTS filiere (
  id_filiere INT PRIMARY KEY,
  code VARCHAR(10) NOT NULL,
  libelle_fr VARCHAR(50) NOT NULL,
  libelle_en VARCHAR(50) NOT NULL
);

INSERT IGNORE INTO section (id_section, code, libelle_fr, libelle_en, ministere, examen_final) VALUES
  (1, 'MAT',  'Maternelle',        'Nursery',           'MINEDUB', NULL),
  (2, 'PRIM', 'Primaire',          'Primary',           'MINEDUB', 'CEP / FSLC'),
  (3, 'GEN',  'Collège Général',   'General College',   'MINESEC', 'BEPC / GCE O Level'),
  (4, 'TECH', 'Collège Technique', 'Technical College', 'MINESEC', 'CAP / GCE O Level Technical');

INSERT IGNORE INTO filiere (id_filiere, code, libelle_fr, libelle_en) VALUES
  (1, 'FR',  'Francophone', 'Francophone'),
  (2, 'EN',  'Anglophone',  'Anglophone'),
  (3, 'BIL', 'Bilingue',    'Bilingual');

-- ---------------------------------------------------------------------------
-- 2. Niveaux
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS niveau (
  id_niveau INT AUTO_INCREMENT PRIMARY KEY,
  section_id INT NOT NULL,
  filiere_id INT NOT NULL,
  libellee_niveau VARCHAR(100) NOT NULL,
  code_niveau VARCHAR(50) NOT NULL,
  ordre INT NOT NULL,
  annee_id INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_niv (section_id, filiere_id, code_niveau),
  INDEX idx_niv_section (section_id),
  INDEX idx_niv_filiere (filiere_id)
);

-- MATERNELLE (section 1, filiere 3 BIL)
INSERT IGNORE INTO niveau (section_id, filiere_id, code_niveau, libellee_niveau, ordre) VALUES
  (1, 3, 'PN', 'Pre Nursery',      1),
  (1, 3, 'N1', 'Nursery 1',        2),
  (1, 3, 'N2', 'Nursery 2',        3),
  (1, 3, 'PS', 'Petite Section',   4),
  (1, 3, 'MS', 'Moyenne Section',  5),
  (1, 3, 'GS', 'Grande Section',   6);

-- PRIMAIRE FR (section 2, filiere 1)
INSERT IGNORE INTO niveau (section_id, filiere_id, code_niveau, libellee_niveau, ordre) VALUES
  (2, 1, 'SIL', 'SIL',    1),
  (2, 1, 'CP',  'CP',     2),
  (2, 1, 'CE1', 'CE1',    3),
  (2, 1, 'CE2', 'CE2',    4),
  (2, 1, 'CM1', 'CM1',    5),
  (2, 1, 'CM2', 'CM2',    6);

-- PRIMAIRE EN (section 2, filiere 2)
INSERT IGNORE INTO niveau (section_id, filiere_id, code_niveau, libellee_niveau, ordre) VALUES
  (2, 2, 'C1', 'Class 1', 1),
  (2, 2, 'C2', 'Class 2', 2),
  (2, 2, 'C3', 'Class 3', 3),
  (2, 2, 'C4', 'Class 4', 4),
  (2, 2, 'C5', 'Class 5', 5),
  (2, 2, 'C6', 'Class 6', 6);

-- PRIMAIRE BIL (section 2, filiere 3)
INSERT IGNORE INTO niveau (section_id, filiere_id, code_niveau, libellee_niveau, ordre) VALUES
  (2, 3, 'SILB', 'SIL-BIL', 1),
  (2, 3, 'CPB',  'CP-BIL',  2),
  (2, 3, 'CE1B', 'CE1-BIL', 3),
  (2, 3, 'CE2B', 'CE2-BIL', 4),
  (2, 3, 'CM1B', 'CM1-BIL', 5),
  (2, 3, 'CM2B', 'CM2-BIL', 6),
  (2, 3, 'C1B',  'Class 1-BIL', 7),
  (2, 3, 'C2B',  'Class 2-BIL', 8),
  (2, 3, 'C3B',  'Class 3-BIL', 9),
  (2, 3, 'C4B',  'Class 4-BIL', 10),
  (2, 3, 'C5B',  'Class 5-BIL', 11),
  (2, 3, 'C6B',  'Class 6-BIL', 12);

-- COLLÈGE GÉNÉRAL FR (section 3, filiere 1)
INSERT IGNORE INTO niveau (section_id, filiere_id, code_niveau, libellee_niveau, ordre) VALUES
  (3, 1, '6E',   'Sixième',    1),
  (3, 1, '5E',   'Cinquième',  2),
  (3, 1, '4E',   'Quatrième',  3),
  (3, 1, '3E',   'Troisième',  4),
  (3, 1, '2ND',  'Seconde',    5),
  (3, 1, '1ERE', 'Première',   6),
  (3, 1, 'TLE',  'Terminale',  7);

-- COLLÈGE GÉNÉRAL EN (section 3, filiere 2)
INSERT IGNORE INTO niveau (section_id, filiere_id, code_niveau, libellee_niveau, ordre) VALUES
  (3, 2, 'F1',  'Form 1',      1),
  (3, 2, 'F2',  'Form 2',      2),
  (3, 2, 'F3',  'Form 3',      3),
  (3, 2, 'F4',  'Form 4',      4),
  (3, 2, 'F5',  'Form 5',      5),
  (3, 2, 'L6',  'Lower Sixth', 6),
  (3, 2, 'U6',  'Upper Sixth', 7);

-- COLLÈGE TECHNIQUE FR 1er cycle (section 4, filiere 1)
INSERT IGNORE INTO niveau (section_id, filiere_id, code_niveau, libellee_niveau, ordre) VALUES
  (4, 1, 'T1', '1ère Année', 1),
  (4, 1, 'T2', '2ème Année', 2),
  (4, 1, 'T3', '3ème Année', 3),
  (4, 1, 'T4', '4ème Année', 4);

-- COLLÈGE TECHNIQUE FR 2nd cycle (section 4, filiere 1)
INSERT IGNORE INTO niveau (section_id, filiere_id, code_niveau, libellee_niveau, ordre) VALUES
  (4, 1, '2T', 'Seconde Tech',   5),
  (4, 1, '1T', 'Première Tech',  6),
  (4, 1, 'TT', 'Terminale Tech', 7);

-- COLLÈGE TECHNIQUE EN (section 4, filiere 2)
INSERT IGNORE INTO niveau (section_id, filiere_id, code_niveau, libellee_niveau, ordre) VALUES
  (4, 2, 'F1T', 'Form 1 T',      1),
  (4, 2, 'F2T', 'Form 2 T',      2),
  (4, 2, 'F3T', 'Form 3 T',      3),
  (4, 2, 'F4T', 'Form 4 T',      4),
  (4, 2, 'F5T', 'Form 5 T',      5),
  (4, 2, 'L6T', 'Lower Sixth T', 6),
  (4, 2, 'U6T', 'Upper Sixth T', 7);

-- ---------------------------------------------------------------------------
-- 3. Séries
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS serie (
  id_serie INT AUTO_INCREMENT PRIMARY KEY,
  section_id INT NOT NULL,
  filiere_id INT NOT NULL,
  code_serie VARCHAR(20) NOT NULL,
  libellee_serie VARCHAR(150) NOT NULL,
  cycle INT NOT NULL,
  annee_id INT NOT NULL DEFAULT 0,
  UNIQUE KEY uq_serie (section_id, filiere_id, code_serie, cycle),
  INDEX idx_serie_section (section_id)
);

-- Collège général FR (cycle 2)
INSERT IGNORE INTO serie (section_id, filiere_id, code_serie, libellee_serie, cycle) VALUES
  (3, 1, 'A1',  'A1 - Lettres',            2),
  (3, 1, 'A2',  'A2 - Lettres',            2),
  (3, 1, 'A3',  'A3 - Lettres',            2),
  (3, 1, 'A4',  'A4 - Lettres',            2),
  (3, 1, 'ABI', 'Arabe Bilingue',          2),
  (3, 1, 'C',   'C - Sciences',            2),
  (3, 1, 'D',   'D - Sciences',            2),
  (3, 1, 'E',   'E - Mathématiques',       2),
  (3, 1, 'TI',  'Technologie de l''information', 2);

-- Collège général EN (cycle 2)
INSERT IGNORE INTO serie (section_id, filiere_id, code_serie, libellee_serie, cycle) VALUES
  (3, 2, 'ARTS',      'Arts',      2),
  (3, 2, 'SCIENCE',   'Science',   2),
  (3, 2, 'COMMERCIAL','Commercial',2);

-- Technique industriel 2nd cycle (F1→F8)
INSERT IGNORE INTO serie (section_id, filiere_id, code_serie, libellee_serie, cycle) VALUES
  (4, 1, 'F1',    'Fabrication mécanique',                        2),
  (4, 1, 'F2',    'Électronique',                                 2),
  (4, 1, 'F3',    'Électrotechnique',                             2),
  (4, 1, 'F4/BA', 'Génie civil option Bâtiment',                  2),
  (4, 1, 'F4/BE', 'Génie civil option Bureau d''études',          2),
  (4, 1, 'F4/TP', 'Génie civil option Travaux publics',           2),
  (4, 1, 'F5',    'Froid et climatisation',                       2),
  (4, 1, 'F6',    'Génie chimique',                               2),
  (4, 1, 'F7',    'Sciences et techniques biologiques et médico-sanitaires', 2),
  (4, 1, 'F8',    'Sciences et technologies de la santé et du social',       2);

-- Technique commercial 1er cycle (CAP commercial)
INSERT IGNORE INTO serie (section_id, filiere_id, code_serie, libellee_serie, cycle) VALUES
  (4, 1, 'ESCOM', 'Employé des services comptables', 1),
  (4, 1, 'ESFI',  'Employé des services financiers', 1),
  (4, 1, 'SEBU',  'Secrétariat bureautique',        1),
  (4, 1, 'SEME',  'Secrétariat médical',            1),
  (4, 1, 'VE',    'Vente',                          1),
  (4, 1, 'ESF',   'Économie sociale et familiale',  1),
  (4, 1, 'RE',    'Restauration',                   1);

-- Technique commercial 2nd cycle
INSERT IGNORE INTO serie (section_id, filiere_id, code_serie, libellee_serie, cycle) VALUES
  (4, 1, 'ACA', 'Action et communication administratives',   2),
  (4, 1, 'ACC', 'Action et communication commerciales',      2),
  (4, 1, 'CG',  'Comptabilité et gestion',                   2),
  (4, 1, 'FIG', 'Fiscalité et informatique de gestion',      2),
  (4, 1, 'SES', 'Sciences économiques et sociales',          2),
  (4, 1, 'G1',  'Techniques administratives',                2),
  (4, 1, 'G2',  'Techniques quantitatives de gestion',       2),
  (4, 1, 'G3',  'Techniques commerciales',                   2);

-- Technique EN — GCE O Level (cycle 1)
INSERT IGNORE INTO serie (section_id, filiere_id, code_serie, libellee_serie, cycle) VALUES
  (4, 2, 'EPS',       'Electrical Power System',       1),
  (4, 2, 'BC',        'Building Construction',         1),
  (4, 2, 'CJ',        'Carpentry and Joinery',         1),
  (4, 2, 'WCM',       'Wood-Cabinet Making',           1),
  (4, 2, 'ARM',       'Automobile Repair Mechanics',   1),
  (4, 2, 'MM',        'Mechanical Manufacturing',      1),
  (4, 2, 'ACCOUNTING','Accounting',                    1),
  (4, 2, 'OFFICE',    'Office Automation',             1),
  (4, 2, 'HOMEECO',   'Home Economics',                1),
  (4, 2, 'FASHION',   'Fashion & Clothing',            1);

-- Technique EN — GCE A Level (cycle 2)
INSERT IGNORE INTO serie (section_id, filiere_id, code_serie, libellee_serie, cycle) VALUES
  (4, 2, 'ELEC',   'Electrical Engineering', 2),
  (4, 2, 'CIVIL',  'Civil Engineering',      2),
  (4, 2, 'MECH',   'Mechanical Engineering', 2),
  (4, 2, 'COMP',   'Computer Engineering',   2);

-- ---------------------------------------------------------------------------
-- 4. Options (spécialités du collège technique)
--    NOTE (décision B2) : `option.serie_id` est NULL-able et la table porte
--    section_id/filiere_id en plus, car le CAP industriel n'a pas de série
--    (la spécialité EST l'option) — cf. §2 du prompt.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `option` (
  id_option INT AUTO_INCREMENT PRIMARY KEY,
  serie_id INT NULL,
  section_id INT NOT NULL,
  filiere_id INT NOT NULL,
  code_option VARCHAR(20) NOT NULL,
  libellee_option VARCHAR(150) NOT NULL,
  secteur ENUM('industriel','commercial','tertiaire','artistique','autres') DEFAULT 'autres',
  domaine VARCHAR(100) NULL,
  annee_id INT NOT NULL DEFAULT 0,
  UNIQUE KEY uq_option (code_option),
  INDEX idx_opt_section (section_id),
  INDEX idx_opt_secteur (secteur)
);

-- Industriel (secteur industriel + domaine)
INSERT IGNORE INTO `option` (serie_id, section_id, filiere_id, code_option, libellee_option, secteur, domaine) VALUES
  (NULL, 4, 1, 'ELEQ', 'Électricité d''équipement',              'industriel', 'Génie électrique'),
  (NULL, 4, 1, 'ELNI', 'Électronique',                            'industriel', 'Génie électrique'),
  (NULL, 4, 1, 'ELME', 'Électromécanique',                        'industriel', 'Génie électrique'),
  (NULL, 4, 1, 'FRCL', 'Froid et climatisation',                  'industriel', 'Génie électrique'),
  (NULL, 4, 1, 'MACO', 'Maçonnerie',                              'industriel', 'Génie civil'),
  (NULL, 4, 1, 'MENU', 'Menuiserie',                              'industriel', 'Génie civil / Bois'),
  (NULL, 4, 1, 'MEB',  'Menuiserie-ébénisterie',                  'industriel', 'Génie civil / Bois'),
  (NULL, 4, 1, 'MEFA', 'Mécanique de fabrication',                'industriel', 'Génie mécanique'),
  (NULL, 4, 1, 'MARE', 'Mécanique automobile de réparation',      'industriel', 'Génie mécanique'),
  (NULL, 4, 1, 'MECAN','Mécanique générale',                      'industriel', 'Génie mécanique'),
  (NULL, 4, 1, 'COOM', 'Construction et ouvrages métalliques',    'industriel', 'Génie mécanique'),
  (NULL, 4, 1, 'MEFE', 'Métaux en feuilles',                      'industriel', 'Génie mécanique'),
  (NULL, 4, 1, 'CHAU', 'Chaudronnerie',                           'industriel', 'Génie mécanique'),
  (NULL, 4, 1, 'INSA', 'Installation sanitaire',                  'industriel', 'Bâtiment'),
  (NULL, 4, 1, 'AICI', 'Aide chimiste industrielle',              'industriel', 'Chimie'),
  (NULL, 4, 1, 'AIBC', 'Aide biochimiste',                        'industriel', 'Chimie'),
  (NULL, 4, 1, 'IH',   'Industrie de l''habillement',             'industriel', 'Textile'),
  (NULL, 4, 1, 'COUT', 'Couture sur mesure',                      'industriel', 'Textile'),
  (NULL, 4, 1, 'AF1',  'Artistique option céramique',             'artistique', 'Arts'),
  (NULL, 4, 1, 'AF2',  'Artistique option peinture',              'artistique', 'Arts'),
  (NULL, 4, 1, 'ESCO', 'Esthétique coiffure',                     'autres',     'Services');

-- Commercial / tertiaire
INSERT IGNORE INTO `option` (serie_id, section_id, filiere_id, code_option, libellee_option, secteur) VALUES
  (NULL, 4, 1, 'ESCOM', 'Employé des services comptables',   'commercial'),
  (NULL, 4, 1, 'ESFI',  'Employé des services financiers',   'commercial'),
  (NULL, 4, 1, 'SEBU',  'Secrétariat bureautique',           'commercial'),
  (NULL, 4, 1, 'SEME',  'Secrétariat médical',               'commercial'),
  (NULL, 4, 1, 'VE',    'Vente',                             'commercial'),
  (NULL, 4, 1, 'ESF',   'Économie sociale et familiale',     'commercial'),
  (NULL, 4, 1, 'RE',    'Restauration',                      'commercial'),
  (NULL, 4, 1, 'ACA',   'Action et communication administratives', 'commercial'),
  (NULL, 4, 1, 'ACC',   'Action et communication commerciales',    'commercial'),
  (NULL, 4, 1, 'CG',    'Comptabilité et gestion',                 'commercial'),
  (NULL, 4, 1, 'FIG',   'Fiscalité et informatique de gestion',    'commercial'),
  (NULL, 4, 1, 'SES',   'Sciences économiques et sociales',        'commercial');

-- ---------------------------------------------------------------------------
-- 5. Mois (ordre scolaire : Septembre=1 … Mai=9)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS mois (
  id_mois INT AUTO_INCREMENT PRIMARY KEY,
  libellee_mois VARCHAR(50) NOT NULL,
  ordre_scolaire INT NOT NULL,
  UNIQUE KEY uq_mois (libellee_mois)
);

INSERT IGNORE INTO mois (libellee_mois, ordre_scolaire) VALUES
  ('Septembre', 1),
  ('Octobre',   2),
  ('Novembre',  3),
  ('Décembre',  4),
  ('Janvier',   5),
  ('Février',   6),
  ('Mars',      7),
  ('Avril',     8),
  ('Mai',       9);

-- ---------------------------------------------------------------------------
-- 6. Jours
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS jour (
  id_jour INT AUTO_INCREMENT PRIMARY KEY,
  libellee_jour VARCHAR(50) NOT NULL,
  ordre INT NOT NULL,
  actif TINYINT DEFAULT 1,
  UNIQUE KEY uq_jour (libellee_jour)
);

INSERT IGNORE INTO jour (libellee_jour, ordre, actif) VALUES
  ('Lundi',    1, 1),
  ('Mardi',    2, 1),
  ('Mercredi', 3, 1),
  ('Jeudi',    4, 1),
  ('Vendredi', 5, 1);

-- ---------------------------------------------------------------------------
-- 7. Version de schéma annuel
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS schema_version (
  version VARCHAR(20) PRIMARY KEY,
  applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT IGNORE INTO schema_version (version) VALUES ('annuel_001');
