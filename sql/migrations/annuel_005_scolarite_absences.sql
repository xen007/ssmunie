-- ============================================================
-- SSMUNIE — Scolarité + Absences + Sanctions (annuel_005, B4b)
-- ============================================================
-- Décisions validées :
--   - UUID (id_uuid) partout (D18, option A) ; SANS annee_id (Q44).
--   - types_reductions.application ENUM('inscription','scolarite','total').
--   - Réductions : base brute parallèle, JAMAIS de cascade d'une base sur l'autre.
--   - Un code de paiement unique partagé par les 2 volets (split atomique, I4).
--   - Annulation = nouvelle ligne négative (append-only) + motif_annulation OBLIGATOIRE.
--   - Ledger scolarite/inscription immuable + scolarite_logs (audit).
-- Idempotent.
-- ============================================================
-- ---------------------------------------------------------------------------
-- 1. Catégories de classe (frais + tranches)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categorie_classe (
  id_categorie INT AUTO_INCREMENT PRIMARY KEY,
  id_uuid VARCHAR(36) NOT NULL,
  code_cat VARCHAR(20) NOT NULL,
  libellee_cat VARCHAR(150) NOT NULL,
  description TEXT NULL,
  frais_ins DECIMAL(12,2) DEFAULT 0,
  frais_sco DECIMAL(12,2) NOT NULL,
  tranche1 DECIMAL(12,2) DEFAULT 0,
  tranche2 DECIMAL(12,2) DEFAULT 0,
  tranche3 DECIMAL(12,2) DEFAULT 0,
  tranche4 DECIMAL(12,2) DEFAULT 0,
  actif TINYINT DEFAULT 1,
  deleted_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_cat_code (code_cat)
);
-- ---------------------------------------------------------------------------
-- 2. Types de réduction (application = base de calcul)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS types_reductions (
  id_type INT AUTO_INCREMENT PRIMARY KEY,
  id_uuid VARCHAR(36) NOT NULL,
  code_type VARCHAR(30) NOT NULL,
  libelle VARCHAR(150) NOT NULL,
  description TEXT NULL,
  application ENUM('inscription','scolarite','total') NOT NULL DEFAULT 'scolarite',
  taux DECIMAL(5,2) NULL,
  montant DECIMAL(12,2) NULL,
  statut TINYINT DEFAULT 1,
  deleted_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_type_code (code_type)
);
-- ---------------------------------------------------------------------------
-- 3. Réduction appliquée à un élève (montant calculé, non saisi)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS eleve_reduction (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_uuid VARCHAR(36) NOT NULL,
  eleve_id INT NOT NULL,
  type_id INT NOT NULL,
  montant_applique DECIMAL(12,2) NOT NULL,
  motif TEXT NULL,
  date_attribution TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  valide_par INT NULL,
  actif TINYINT DEFAULT 1,
  deleted_at TIMESTAMP NULL,
  INDEX idx_er_eleve (eleve_id)
);
-- ---------------------------------------------------------------------------
-- 4. Inscription (volet inscription d'un paiement)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS inscription (
  id_ins INT AUTO_INCREMENT PRIMARY KEY,
  id_uuid VARCHAR(36) NOT NULL,
  eleve_id INT NOT NULL,
  categorie_id INT NULL,
  montant DECIMAL(12,2) NOT NULL,
  frais_total DECIMAL(12,2) NOT NULL,
  code_versement VARCHAR(50) NOT NULL,
  date_inscription DATE NOT NULL,
  valide TINYINT DEFAULT 1,
  motif_annulation TEXT NULL,
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_ins_code (code_versement),
  INDEX idx_ins_eleve (eleve_id)
);
-- ---------------------------------------------------------------------------
-- 5. Scolarité (volet scolarité d'un paiement) — ledger append-only
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS scolarite (
  id_sco INT AUTO_INCREMENT PRIMARY KEY,
  id_uuid VARCHAR(36) NOT NULL,
  eleve_id INT NOT NULL,
  inscription_id INT NULL,
  montant DECIMAL(12,2) NOT NULL,
  code_versement VARCHAR(50) NOT NULL,
  code_ref VARCHAR(50) NULL,
  type_action ENUM('versement','annulation','correction') DEFAULT 'versement',
  motif_annulation TEXT NULL,
  observation TEXT NULL,
  date_paye DATE NOT NULL,
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_sco_code (code_versement),
  INDEX idx_sco_eleve (eleve_id),
  INDEX idx_sco_date (date_paye),
  INDEX idx_sco_ref (code_ref)
);
-- ---------------------------------------------------------------------------
-- 6. Journal d'audit financier (append-only, upgrade)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS scolarite_logs (
  id_log INT AUTO_INCREMENT PRIMARY KEY,
  id_uuid VARCHAR(36) NOT NULL,
  eleve_id INT NOT NULL,
  code_versement VARCHAR(50) NULL,
  action ENUM('versement','annulation','correction','reduction_attribuee','reduction_retiree') NOT NULL,
  detail JSON NULL,
  user_id INT NULL,
  user_role VARCHAR(50) NULL,
  date_action TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_slog_eleve (eleve_id),
  INDEX idx_slog_code (code_versement)
);
-- ---------------------------------------------------------------------------
-- 7. Statuts de scolarité (tranches types / dates limites)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS statut_scolarite (
  id_statut INT AUTO_INCREMENT PRIMARY KEY,
  id_uuid VARCHAR(36) NOT NULL,
  code_statut VARCHAR(30) NOT NULL,
  libelle VARCHAR(150) NOT NULL,
  date_tranche DATE NOT NULL,
  actif TINYINT DEFAULT 1,
  UNIQUE KEY uk_statut_code (code_statut)
);
-- ---------------------------------------------------------------------------
-- 8. Absences (par mois scolaire 1..9, en HEURES)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS absences (
  id_abs INT AUTO_INCREMENT PRIMARY KEY,
  id_uuid VARCHAR(36) NOT NULL,
  eleve_id INT NOT NULL,
  mois_id INT NOT NULL,
  abs_total INT DEFAULT 0,
  abs_justifies INT DEFAULT 0,
  observation TEXT NULL,
  deleted_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_abs_eleve_mois (eleve_id, mois_id),
  INDEX idx_abs_mois (mois_id)
);
-- ---------------------------------------------------------------------------
-- 9. Logs d'absences (audit append-only)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS absences_logs (
  id_log INT AUTO_INCREMENT PRIMARY KEY,
  id_uuid VARCHAR(36) NOT NULL,
  eleve_id INT NOT NULL,
  mois_id INT NOT NULL,
  ancien_total INT NULL,
  ancien_justifies INT NULL,
  nouveau_total INT NULL,
  nouveau_justifies INT NULL,
  action ENUM('creation','modification','justification','annulation') NOT NULL,
  motif TEXT NULL,
  user_id INT NULL,
  user_role VARCHAR(50) NULL,
  date_action TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_log_eleve (eleve_id)
);
-- ---------------------------------------------------------------------------
-- 10. Paramètres de sanction (7 paliers d'heures non justifiées)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS parametres_absence (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_uuid VARCHAR(36) NOT NULL,
  palier SMALLINT NOT NULL,
  heures_min SMALLINT NOT NULL,
  heures_max SMALLINT NULL,
  sanction VARCHAR(150) NOT NULL,
  duree_jours SMALLINT DEFAULT 0,
  exclusion_definitive TINYINT DEFAULT 0,
  convocation_parents TINYINT DEFAULT 0,
  description TEXT NULL,
  actif TINYINT DEFAULT 1,
  UNIQUE KEY uk_param_palier (palier)
);
-- ---------------------------------------------------------------------------
-- 11. Compteur de versement (race-safe) : ajouter le mode 'versement'
-- ---------------------------------------------------------------------------
ALTER TABLE matricule_counter MODIFY COLUMN mode ENUM('standard','section','classe','enseignant','versement') NOT NULL DEFAULT 'standard';
-- ============================================================================
-- SEEDS
-- ============================================================================
-- Catégories (12) — frais_ins = 0 partout (conforme legacy, configurable)
INSERT IGNORE INTO categorie_classe (id_uuid, code_cat, libellee_cat, frais_ins, frais_sco, tranche1, tranche2, tranche3, tranche4) VALUES
  (UUID(),'C1','6ème→4ème / Form I-IV',0,110000,60000,20000,15000,15000),
  (UUID(),'C2','3ème',0,135000,80000,30000,25000,0),
  (UUID(),'C3','Form V Arts',0,140000,90000,30000,20000,0),
  (UUID(),'C4','Form V Science',0,150000,90000,30000,30000,0),
  (UUID(),'C5','2nde',0,140000,80000,20000,20000,20000),
  (UUID(),'C6','1ère',0,145000,90000,30000,25000,0),
  (UUID(),'C7','Terminale',0,150000,90000,35000,25000,0),
  (UUID(),'C8','1ère/Tle TI',0,180000,120000,40000,20000,0),
  (UUID(),'C9','Lower 6th Arts',0,160000,90000,30000,20000,20000),
  (UUID(),'C10','Lower 6th Sci',0,170000,100000,30000,20000,20000),
  (UUID(),'C11','Upper 6th Arts',0,175000,110000,40000,25000,0),
  (UUID(),'C12','Upper 6th Sci',0,180000,115000,40000,25000,0);
-- Types de réduction (5) — application = base de calcul
INSERT IGNORE INTO types_reductions (id_uuid, code_type, libelle, application, taux, montant, statut) VALUES
  (UUID(),'enseignant_fils','Enfant d''enseignant','scolarite',25,NULL,1),
  (UUID(),'ancien','Ancien élève','scolarite',10,NULL,1),
  (UUID(),'scolarite_totale','Paiement total anticipé','total',5,NULL,0),
  (UUID(),'bourse','Bourse','scolarite',NULL,10000,0),
  (UUID(),'enfant_admin','Enfant d''administrateur','scolarite',30,NULL,1);
-- Paliers de sanction (7) — heures non justifiées, ordre croissant
INSERT IGNORE INTO parametres_absence (id_uuid, palier, heures_min, heures_max, sanction, duree_jours, exclusion_definitive, convocation_parents) VALUES
  (UUID(),1,3,5,'Avertissement conduite',0,0,0),
  (UUID(),2,6,8,'Avertissement écrit',0,0,0),
  (UUID(),3,9,11,'Blâme',0,0,0),
  (UUID(),4,12,26,'Exclusion temporaire',2,0,0),
  (UUID(),5,27,29,'Exclusion temporaire',3,0,0),
  (UUID(),6,30,40,'Exclusion temporaire',8,0,0),
  (UUID(),7,41,NULL,'Exclusion définitive',0,1,1);
-- Statuts de scolarité (tranches types) — dates limites (mois/jour) ref. année scolaire
INSERT IGNORE INTO statut_scolarite (id_uuid, code_statut, libelle, date_tranche) VALUES
  (UUID(),'tranche1','Tranche 1','2026-09-30'),
  (UUID(),'tranche2','Tranche 2','2026-11-30'),
  (UUID(),'tranche3','Tranche 3','2027-01-31'),
  (UUID(),'tranche4','Tranche 4','2027-03-31');
INSERT IGNORE INTO schema_version (version) VALUES ('annuel_005');