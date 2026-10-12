-- ============================================================
-- SSMUNIE — Base annuelle : notes & bulletins (annuel_004)
-- ============================================================
-- Traduction fidèle de la logique legacy ssm/ssmcol :
--   primaire  = notes_evaluation (élève × matière × évaluation) → m1..m9
--   collège   = notes_sequence   (élève × matière)               → m1..m6
-- Mapping séquences→trimestres (donnée, table sequence_scolaire) :
--   primaire : T1=1,2,3  T2=4,5,6  T3=7,8,9
--   collège  : T1=1,2    T2=3,4    T3=5,6
-- Idempotent. UUID partout. Soft delete. AUCUN annee_id (une base = une année).
-- ============================================================
-- ---------------------------------------------------------------------------
-- 1. Notes primaire (par évaluation) — m1..m9 (9 séquences)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notes_evaluation (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_uuid VARCHAR(36) NOT NULL,
  eleve_id INT NOT NULL,
  matiere_id INT NOT NULL,
  eval_id INT NOT NULL,
  classe_id INT NOT NULL,
  m1 DECIMAL(5,2) NULL, m2 DECIMAL(5,2) NULL, m3 DECIMAL(5,2) NULL,
  m4 DECIMAL(5,2) NULL, m5 DECIMAL(5,2) NULL, m6 DECIMAL(5,2) NULL,
  m7 DECIMAL(5,2) NULL, m8 DECIMAL(5,2) NULL, m9 DECIMAL(5,2) NULL,
  bareme DECIMAL(5,2) DEFAULT 20.00,
  deleted_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_note_eval (eleve_id, matiere_id, eval_id, classe_id),
  INDEX idx_ne_eleve (eleve_id),
  INDEX idx_ne_classe (classe_id),
  INDEX idx_ne_matiere (matiere_id)
);
-- ---------------------------------------------------------------------------
-- 2. Notes collège (par matière) — m1..m6 (6 séquences)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notes_sequence (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_uuid VARCHAR(36) NOT NULL,
  eleve_id INT NOT NULL,
  matiere_id INT NOT NULL,
  classe_id INT NOT NULL,
  m1 DECIMAL(5,2) NULL, m2 DECIMAL(5,2) NULL, m3 DECIMAL(5,2) NULL,
  m4 DECIMAL(5,2) NULL, m5 DECIMAL(5,2) NULL, m6 DECIMAL(5,2) NULL,
  deleted_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_note_seq (eleve_id, matiere_id, classe_id),
  INDEX idx_ns_eleve (eleve_id),
  INDEX idx_ns_classe (classe_id),
  INDEX idx_ns_matiere (matiere_id)
);
-- ---------------------------------------------------------------------------
-- 3. Gabarits de bulletin (par section × variante FR/EN/BIL)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS bulletin_template (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_uuid VARCHAR(36) NOT NULL,
  code VARCHAR(50) NOT NULL,
  section_id INT NOT NULL,
  variante ENUM('fr','en','bil') NOT NULL DEFAULT 'fr',
  libelle VARCHAR(150) NOT NULL,
  contenu JSON NULL,
  actif TINYINT DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_tpl (code, section_id, variante)
);
-- ---------------------------------------------------------------------------
-- 4. Paramètres par niveau (clé/valeur, flexibles — décision, seuils…)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS parametres_niveau (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_uuid VARCHAR(36) NOT NULL,
  section_id INT NOT NULL,
  cle VARCHAR(50) NOT NULL,
  valeur VARCHAR(255) NULL,
  type ENUM('number','boolean','string','json') DEFAULT 'string',
  actif TINYINT DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_param_niveau (section_id, cle)
);
-- ---------------------------------------------------------------------------
-- 5. Décisions de passage (admis / redouble / … + rollback par run_id)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS decisions_passage (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_uuid VARCHAR(36) NOT NULL,
  eleve_id INT NOT NULL,
  classe_id INT NOT NULL,
  decision ENUM('admis','admis_conditionnel','redouble','exclu','transfere') NOT NULL,
  recommandation_auto VARCHAR(50) NULL,
  motif TEXT NULL,
  details JSON NULL,
  run_id INT NULL,
  decided_by INT NULL,
  decided_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL,
  UNIQUE KEY uk_decision (eleve_id, classe_id),
  INDEX idx_dec_classe (classe_id),
  INDEX idx_dec_run (run_id)
);
-- ---------------------------------------------------------------------------
-- 6. Avis pré-remplis de bulletin (appréciations) + lignes manuscrites
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS appreciations_bulletin (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_uuid VARCHAR(36) NOT NULL,
  code VARCHAR(50) NOT NULL,
  section_id INT NOT NULL DEFAULT 0,
  libelle_fr VARCHAR(255) NOT NULL,
  libelle_en VARCHAR(255) NOT NULL,
  ordre INT DEFAULT 0,
  actif TINYINT DEFAULT 1,
  UNIQUE KEY uk_appr (code, section_id)
);
-- ---------------------------------------------------------------------------
-- 7. Référentiel APC (compétences par matière × niveau) — configurable
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS competences_apc (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_uuid VARCHAR(36) NOT NULL,
  matiere_id INT NOT NULL DEFAULT 0,
  niveau_id INT NOT NULL DEFAULT 0,
  code VARCHAR(20) NOT NULL,
  libelle_fr VARCHAR(150) NOT NULL,
  libelle_en VARCHAR(150) NOT NULL,
  ordre INT DEFAULT 0,
  actif TINYINT DEFAULT 1,
  UNIQUE KEY uk_apc (matiere_id, niveau_id, code)
);
-- ---------------------------------------------------------------------------
-- 8. Grades GCE (O-Level / A-Level) — paramétrables
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS parametres_grades (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_uuid VARCHAR(36) NOT NULL,
  examen ENUM('O_LEVEL','A_LEVEL') NOT NULL,
  grade VARCHAR(5) NOT NULL,
  note_min DECIMAL(5,2) NOT NULL,
  note_max DECIMAL(5,2) NOT NULL,
  est_valide TINYINT DEFAULT 1,
  actif TINYINT DEFAULT 1,
  UNIQUE KEY uk_grade (examen, grade)
);
-- ---------------------------------------------------------------------------
-- 9. Séquences scolaires (mapping séquence → trimestre)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sequence_scolaire (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_uuid VARCHAR(36) NOT NULL,
  libellee_sequence VARCHAR(50) NOT NULL,
  trimestre INT NOT NULL,
  ordre INT NOT NULL,
  actif TINYINT DEFAULT 1,
  UNIQUE KEY uk_seq (ordre)
);
-- ---------------------------------------------------------------------------
-- 10. Échelles d'appréciation (par section) — Expert/Acquis/ECA/… /20
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS echelles_appreciation (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_uuid VARCHAR(36) NOT NULL,
  code VARCHAR(50) NOT NULL,
  section_id INT NOT NULL,
  libelle_fr VARCHAR(100) NOT NULL,
  libelle_en VARCHAR(100) NOT NULL,
  note_min DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  ordre INT DEFAULT 0,
  actif TINYINT DEFAULT 1,
  UNIQUE KEY uk_echelle (code, section_id)
);
-- ============================================================================
-- SEEDS
-- ============================================================================
-- Paramètres par section (défauts = v9 / legacy, modifiables)
INSERT IGNORE INTO parametres_niveau (id_uuid, section_id, cle, valeur, type) VALUES
  (UUID(),1,'mode_decision','conseil','string'),
  (UUID(),1,'nb_competences_min','4','number'),
  (UUID(),1,'absences_max_justifiees','30','number'),
  (UUID(),1,'absences_max_injustifiees','15','number'),
  (UUID(),1,'override_possible','true','boolean'),
  (UUID(),1,'lignes_manuscrites','2','number'),
  (UUID(),2,'mode_decision','conseil','string'),
  (UUID(),2,'seuil_admission','10','number'),
  (UUID(),2,'note_min_matiere','6','number'),
  (UUID(),2,'nb_competences_min','4','number'),
  (UUID(),2,'absences_max_justifiees','30','number'),
  (UUID(),2,'absences_max_injustifiees','15','number'),
  (UUID(),2,'override_possible','true','boolean'),
  (UUID(),2,'lignes_manuscrites','3','number'),
  (UUID(),3,'mode_decision','auto','string'),
  (UUID(),3,'seuil_admission','10','number'),
  (UUID(),3,'note_min_matiere','6','number'),
  (UUID(),3,'absences_max_justifiees','40','number'),
  (UUID(),3,'absences_max_injustifiees','25','number'),
  (UUID(),3,'override_possible','true','boolean'),
  (UUID(),3,'lignes_manuscrites','1','number'),
  (UUID(),4,'mode_decision','auto','string'),
  (UUID(),4,'seuil_admission','5','number'),
  (UUID(),4,'note_min_matiere','6','number'),
  (UUID(),4,'absences_max_justifiees','40','number'),
  (UUID(),4,'absences_max_injustifiees','25','number'),
  (UUID(),4,'override_possible','true','boolean'),
  (UUID(),4,'lignes_manuscrites','1','number');
-- Échelles d'appréciation (primaire APC + collège)
INSERT IGNORE INTO echelles_appreciation (id_uuid, code, section_id, libelle_fr, libelle_en, note_min, ordre) VALUES
  (UUID(),'expert',1,'Expert','Expert',18,1),
  (UUID(),'acquis',1,'Acquis','Acquired',15,2),
  (UUID(),'eca',1,'En cours d''acquisition','In progress',10,3),
  (UUID(),'non_acquis',1,'Non acquis','Not acquired',0,4),
  (UUID(),'expert',2,'Expert','Expert',18,1),
  (UUID(),'acquis',2,'Acquis','Acquired',15,2),
  (UUID(),'eca',2,'En cours d''acquisition','In progress',10,3),
  (UUID(),'non_acquis',2,'Non acquis','Not acquired',0,4),
  (UUID(),'excellent',3,'Excellent','Excellent',18,1),
  (UUID(),'tres_bien',3,'Très bien','Very good',15,2),
  (UUID(),'bien',3,'Bien','Good',12,3),
  (UUID(),'passable',3,'Passable','Fair',10,4),
  (UUID(),'insuffisant',3,'Insuffisant','Insufficient',8,5),
  (UUID(),'faible',3,'Faible','Weak',0,6),
  (UUID(),'excellent',4,'Excellent','Excellent',18,1),
  (UUID(),'tres_bien',4,'Très bien','Very good',15,2),
  (UUID(),'bien',4,'Bien','Good',12,3),
  (UUID(),'passable',4,'Passable','Fair',10,4),
  (UUID(),'insuffisant',4,'Insuffisant','Insufficient',8,5),
  (UUID(),'faible',4,'Faible','Weak',0,6);
-- Grades GCE (défauts raisonnables /20 — à ajuster par école)
INSERT IGNORE INTO parametres_grades (id_uuid, examen, grade, note_min, note_max, est_valide) VALUES
  (UUID(),'O_LEVEL','A',16.00,20.00,1),
  (UUID(),'O_LEVEL','B',14.00,15.99,1),
  (UUID(),'O_LEVEL','C',12.00,13.99,1),
  (UUID(),'O_LEVEL','D',10.00,11.99,1),
  (UUID(),'O_LEVEL','E',8.00,9.99,1),
  (UUID(),'O_LEVEL','U',0.00,7.99,0),
  (UUID(),'A_LEVEL','A',16.00,20.00,1),
  (UUID(),'A_LEVEL','B',14.00,15.99,1),
  (UUID(),'A_LEVEL','C',12.00,13.99,1),
  (UUID(),'A_LEVEL','D',10.00,11.99,1),
  (UUID(),'A_LEVEL','E',8.00,9.99,1),
  (UUID(),'A_LEVEL','U',0.00,7.99,0);
-- Séquences (9 = primaire ; le collège utilise 1..6)
INSERT IGNORE INTO sequence_scolaire (id_uuid, libellee_sequence, trimestre, ordre) VALUES
  (UUID(),'Sequence 1',1,1),(UUID(),'Sequence 2',1,2),(UUID(),'Sequence 3',1,3),
  (UUID(),'Sequence 4',2,4),(UUID(),'Sequence 5',2,5),(UUID(),'Sequence 6',2,6),
  (UUID(),'Sequence 7',3,7),(UUID(),'Sequence 8',3,8),(UUID(),'Sequence 9',3,9);
-- Compétences APC transversales (seed générique, importable ensuite)
INSERT IGNORE INTO competences_apc (id_uuid, matiere_id, niveau_id, code, libelle_fr, libelle_en, ordre) VALUES
  (UUID(),0,0,'LECTURE','Lecture','Reading',1),
  (UUID(),0,0,'ECRITURE','Écriture','Writing',2),
  (UUID(),0,0,'CALCUL','Calcul','Numeracy',3),
  (UUID(),0,0,'SCIENCES','Sciences','Science',4),
  (UUID(),0,0,'HISTOIRE_GEO','Histoire-Géo','Social Studies',5),
  (UUID(),0,0,'LANGUES','Langues','Languages',6),
  (UUID(),0,0,'EPS','EPS','Physical Education',7),
  (UUID(),0,0,'ARTS','Arts','Arts',8);
-- Avis pré-remplis (appréciations de bulletin)
INSERT IGNORE INTO appreciations_bulletin (id_uuid, code, section_id, libelle_fr, libelle_en, ordre) VALUES
  (UUID(),'excellent',0,'Excellent travail, félicitations.','Excellent work, congratulations.',1),
  (UUID(),'tres_bien',0,'Très bon travail, continue ainsi.','Very good work, keep it up.',2),
  (UUID(),'bien',0,'Bon travail.','Good work.',3),
  (UUID(),'passable',0,'Peut mieux faire.','Can do better.',4),
  (UUID(),'insuffisant',0,'Doit fournir plus d''efforts.','Must work harder.',5),
  (UUID(),'faible',0,'En difficulté, soutien nécessaire.','Struggling, support needed.',6);
INSERT IGNORE INTO schema_version (version) VALUES ('annuel_004');