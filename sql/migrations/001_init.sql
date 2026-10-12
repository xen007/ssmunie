-- ============================================================
-- SSMUNIE — Migration 001 : base "meta" (B0)
-- Idempotent (IF NOT EXISTS partout).
-- Base annuelle (une par école) : créée en B1.
-- ============================================================

CREATE DATABASE IF NOT EXISTS ssmunie_meta
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE ssmunie_meta;

CREATE TABLE IF NOT EXISTS etablissement (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(255) NOT NULL,
  initiale VARCHAR(20),
  logo VARCHAR(255),
  ministere VARCHAR(50),
  contact VARCHAR(100),
  email VARCHAR(150),
  adresse VARCHAR(255),
  devise VARCHAR(255),
  annee_courante_id INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS annee_scolaire (
  id INT AUTO_INCREMENT PRIMARY KEY,
  libellee VARCHAR(50) NOT NULL,
  date_debut DATE NOT NULL,
  date_fin DATE NOT NULL,
  statut TINYINT DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_matricule VARCHAR(50),
  username VARCHAR(100) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  role VARCHAR(50) NOT NULL DEFAULT 'admin',
  signature VARCHAR(255),
  poste VARCHAR(100),
  statut TINYINT DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS schema_version (
  version VARCHAR(20) PRIMARY KEY,
  applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT IGNORE INTO schema_version (version) VALUES ('001');

INSERT IGNORE INTO etablissement (id, nom, initiale, ministere, devise)
  VALUES (1, 'SSMUNIE', 'SSM', 'MINEDUB', 'Grace-Excellence-Reussite');

INSERT IGNORE INTO annee_scolaire (id, libellee, date_debut, date_fin, statut)
  VALUES (1, '2026-2027', '2026-08-01', '2027-07-31', 1);

INSERT IGNORE INTO users (id, username, password, role, statut)
  VALUES (1, 'admin', '$2y$10$placeholder', 'admin', 1);
