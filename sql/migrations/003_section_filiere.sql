-- ============================================================
-- SSMUNIE — Migration 003 : référentiels canoniques (meta)
-- ============================================================
-- `section` REMPLACE l'ancien `cycle` (4 valeurs, jamais modifiées après init).
-- `filiere` : FR | EN | BIL (3 valeurs).
-- Idempotent (IF NOT EXISTS / INSERT IGNORE).
-- ============================================================

CREATE DATABASE IF NOT EXISTS ssmunie_meta
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE ssmunie_meta;

-- ---------------------------------------------------------------------------
-- 1. Section (canonique)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS section (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(10) UNIQUE NOT NULL,
  libelle_fr VARCHAR(100) NOT NULL,
  libelle_en VARCHAR(100) NOT NULL,
  ministere VARCHAR(20) NOT NULL,
  examen_final VARCHAR(60) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT IGNORE INTO section (id, code, libelle_fr, libelle_en, ministere, examen_final) VALUES
  (1, 'MAT',  'Maternelle',        'Nursery',                 'MINEDUB', NULL),
  (2, 'PRIM', 'Primaire',          'Primary',                 'MINEDUB', 'CEP / FSLC'),
  (3, 'GEN',  'Collège Général',   'General College',         'MINESEC', 'BEPC / GCE O Level'),
  (4, 'TECH', 'Collège Technique', 'Technical College',       'MINESEC', 'CAP / GCE O Level Technical');

-- ---------------------------------------------------------------------------
-- 2. Filière
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS filiere (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(10) UNIQUE NOT NULL,
  libelle_fr VARCHAR(50) NOT NULL,
  libelle_en VARCHAR(50) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT IGNORE INTO filiere (id, code, libelle_fr, libelle_en) VALUES
  (1, 'FR',  'Francophone', 'Francophone'),
  (2, 'EN',  'Anglophone',  'Anglophone'),
  (3, 'BIL', 'Bilingue',    'Bilingual');

-- ---------------------------------------------------------------------------
-- 3. Version de schéma
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO schema_version (version) VALUES ('003');
