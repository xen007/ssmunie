-- ============================================================
-- SSMUNIE — Migration 002 : authentification, rôles, permissions (B1 v2)
-- ============================================================
-- Cible : MariaDB 10.4+ (F1). Idempotent (IF NOT EXISTS / INSERT IGNORE).
-- Ajoute : email/last_login/created_by/session_version (users),
--          setup_completed (etablissement), sessions (A5), roles,
--          permissions, role_permissions, user_roles.
-- ============================================================

CREATE DATABASE IF NOT EXISTS ssmunie_meta
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE ssmunie_meta;

-- ---------------------------------------------------------------------------
-- 1. Extension users
-- ---------------------------------------------------------------------------
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS email VARCHAR(150) NULL,
  ADD COLUMN IF NOT EXISTS last_login TIMESTAMP NULL,
  ADD COLUMN IF NOT EXISTS created_by INT NULL,
  ADD COLUMN IF NOT EXISTS session_version INT DEFAULT 1;

ALTER TABLE users MODIFY email VARCHAR(150) NULL;
CREATE UNIQUE INDEX IF NOT EXISTS idx_users_email ON users(email);

-- ---------------------------------------------------------------------------
-- 2. Extension etablissement (setup wizard)
-- ---------------------------------------------------------------------------
ALTER TABLE etablissement
  ADD COLUMN IF NOT EXISTS setup_completed TINYINT DEFAULT 0,
  ADD COLUMN IF NOT EXISTS setup_completed_at TIMESTAMP NULL;

-- ---------------------------------------------------------------------------
-- 3. Nouvelles tables
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) UNIQUE NOT NULL,
  libelle_fr VARCHAR(100) NOT NULL,
  libelle_en VARCHAR(100) NOT NULL,
  description TEXT NULL,
  systeme TINYINT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS permissions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(100) UNIQUE NOT NULL,
  categorie VARCHAR(50) NOT NULL,
  libelle_fr VARCHAR(150) NOT NULL,
  libelle_en VARCHAR(150) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS role_permissions (
  role_id INT NOT NULL,
  permission_id INT NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
  FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS user_roles (
  user_id INT NOT NULL,
  role_id INT NOT NULL,
  PRIMARY KEY (user_id, role_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
);

-- Sessions en BDD (A5) : révocation ciblée, audit, portabilité WAN.
CREATE TABLE IF NOT EXISTS sessions (
  id VARCHAR(128) PRIMARY KEY,
  user_id INT NULL,
  payload MEDIUMTEXT NOT NULL,
  last_activity INT NOT NULL,
  created_at INT NOT NULL,
  ip VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  INDEX idx_sessions_activity (last_activity),
  INDEX idx_sessions_user (user_id)
);

-- ---------------------------------------------------------------------------
-- 4. Seed : 12 rôles
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO roles (code, libelle_fr, libelle_en, systeme) VALUES
  ('admin', 'Administrateur', 'Administrator', 1),
  ('directeur', 'Directeur / Directrice', 'Director', 1),
  ('censeur', 'Censeur', 'Vice-Principal', 1),
  ('surveillant_gen', 'Surveillant Général', 'Discipline Master', 1),
  ('econome', 'Économe', 'Bursar', 1),
  ('comptable', 'Comptable', 'Accountant', 1),
  ('secretaire', 'Secrétaire', 'Secretary', 1),
  ('enseignant', 'Enseignant', 'Teacher', 1),
  ('maitre', 'Maître / Maîtresse', 'Primary School Teacher', 1),
  ('surveillant', 'Surveillant', 'Supervisor', 1),
  ('gardien', 'Gardien', 'Guard', 1),
  ('parent', 'Parent', 'Parent', 1);

-- ---------------------------------------------------------------------------
-- 5. Seed : 15 permissions
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO permissions (code, categorie, libelle_fr, libelle_en) VALUES
  ('students.view', 'students', 'Voir les élèves', 'View students'),
  ('students.edit', 'students', 'Modifier les élèves', 'Edit students'),
  ('students.delete', 'students', 'Supprimer les élèves', 'Delete students'),
  ('grades.view', 'grades', 'Voir les notes', 'View grades'),
  ('grades.edit', 'grades', 'Saisir / modifier les notes', 'Enter / edit grades'),
  ('grades.publish', 'grades', 'Publier les bulletins', 'Publish reports'),
  ('finance.view', 'finance', 'Voir la scolarité', 'View finance'),
  ('finance.edit', 'finance', 'Enregistrer les paiements', 'Record payments'),
  ('finance.reports', 'finance', 'Rapports financiers', 'Financial reports'),
  ('hr.view', 'hr', 'Voir le personnel', 'View staff'),
  ('hr.edit', 'hr', 'Modifier le personnel', 'Edit staff'),
  ('settings.view', 'settings', 'Voir les réglages', 'View settings'),
  ('settings.edit', 'settings', 'Modifier les réglages', 'Edit settings'),
  ('accounts.manage', 'accounts', 'Gérer les comptes', 'Manage accounts'),
  ('roles.manage', 'roles', 'Gérer les rôles', 'Manage roles');

-- ---------------------------------------------------------------------------
-- 6. Seed : mapping rôle → permissions (F13)
--    admin = bypass total (['*'] en code, non représenté en base)
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p WHERE
  (r.code = 'directeur'      AND p.code NOT IN ('accounts.manage','roles.manage'))
  OR (r.code = 'censeur'     AND p.code IN ('students.view','students.edit','students.delete','grades.view','grades.edit','grades.publish','hr.view'))
  OR (r.code = 'surveillant_gen' AND p.code IN ('students.view','grades.view'))
  OR (r.code = 'econome'     AND p.code IN ('finance.view','finance.edit','finance.reports','students.view'))
  OR (r.code = 'comptable'   AND p.code IN ('finance.view','finance.reports'))
  OR (r.code = 'secretaire'  AND p.code IN ('students.view','students.edit','students.delete','finance.view'))
  OR (r.code = 'enseignant'  AND p.code IN ('grades.view','grades.edit','students.view'))
  OR (r.code = 'maitre'      AND p.code IN ('grades.view','grades.edit','students.view'))
  OR (r.code = 'surveillant' AND p.code IN ('students.view'));

-- ---------------------------------------------------------------------------
-- 7. Attribution du rôle admin à user id=1 (si présent et pas déjà attribué)
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO user_roles (user_id, role_id)
SELECT 1, id FROM roles WHERE code = 'admin' AND EXISTS (SELECT 1 FROM users WHERE id = 1);

-- ---------------------------------------------------------------------------
-- 8. Cohérence : année courante si nulle
-- ---------------------------------------------------------------------------
UPDATE etablissement
   SET annee_courante_id = (SELECT MIN(id) FROM annee_scolaire)
 WHERE annee_courante_id IS NULL
   AND EXISTS (SELECT 1 FROM annee_scolaire);

-- ---------------------------------------------------------------------------
-- 9. Version de schéma
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO schema_version (version) VALUES ('002');
