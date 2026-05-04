-- =============================================================================
-- Giudmunna — database completo (schema + esempi + riferimento query PHP)
-- =============================================================================
-- ATTENZIONE: questo script ESEGUE DROP TABLE su tutte le tabelle dell'app e
-- ricrea tutto da zero. Salva un backup se hai dati da conservare.
--
-- Uso:
--   mysql -u root < giudmunna_completo.sql
-- Oppure phpMyAdmin → Importa → questo file.
--
-- Installazioni vecchie con tipi FK diversi: vedi note in fondo e install_righe_ordine.sql
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------------
-- 1) Database
-- ---------------------------------------------------------------------------
CREATE DATABASE IF NOT EXISTS giudmunna
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE giudmunna;

-- ---------------------------------------------------------------------------
-- 2) Tabelle (ordine: dipendenze FK)
-- ---------------------------------------------------------------------------

DROP TABLE IF EXISTS righe_ordine;
DROP TABLE IF EXISTS ordini;
DROP TABLE IF EXISTS clienti;
DROP TABLE IF EXISTS prodotti;
DROP TABLE IF EXISTS gradi_estetici;
DROP TABLE IF EXISTS colori;
DROP TABLE IF EXISTS capacita;
DROP TABLE IF EXISTS modelli;
DROP TABLE IF EXISTS utenti;

CREATE TABLE utenti (
  id_utente INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username  VARCHAR(64) NOT NULL,
  email     VARCHAR(255) NOT NULL,
  password  VARCHAR(255) NOT NULL,
  ruolo     VARCHAR(32) NOT NULL DEFAULT 'utente',
  PRIMARY KEY (id_utente),
  UNIQUE KEY uk_utenti_username (username),
  UNIQUE KEY uk_utenti_email (email),
  KEY idx_utenti_ruolo (ruolo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE clienti (
  id_cliente INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_utente  INT UNSIGNED NOT NULL,
  nome       VARCHAR(100) NOT NULL,
  cognome    VARCHAR(100) NOT NULL,
  indirizzo  VARCHAR(255) NOT NULL,
  citta      VARCHAR(100) NOT NULL,
  cap        VARCHAR(10) NOT NULL,
  telefono   VARCHAR(30) NOT NULL,
  PRIMARY KEY (id_cliente),
  UNIQUE KEY uk_clienti_utente (id_utente),
  CONSTRAINT fk_clienti_utente
    FOREIGN KEY (id_utente) REFERENCES utenti (id_utente)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE modelli (
  id_modello INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome VARCHAR(120) NOT NULL,
  PRIMARY KEY (id_modello),
  UNIQUE KEY uk_modelli_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE capacita (
  id_capacita INT UNSIGNED NOT NULL AUTO_INCREMENT,
  valore VARCHAR(32) NOT NULL,
  PRIMARY KEY (id_capacita),
  UNIQUE KEY uk_capacita_valore (valore)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE colori (
  id_colore INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome VARCHAR(64) NOT NULL,
  PRIMARY KEY (id_colore),
  UNIQUE KEY uk_colori_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE gradi_estetici (
  id_grado_estetico INT UNSIGNED NOT NULL AUTO_INCREMENT,
  descrizione VARCHAR(64) NOT NULL,
  PRIMARY KEY (id_grado_estetico),
  UNIQUE KEY uk_gradi_descrizione (descrizione)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE prodotti (
  id_prodotto INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_modello INT UNSIGNED NOT NULL,
  id_capacita INT UNSIGNED NOT NULL,
  id_colore INT UNSIGNED NOT NULL,
  id_grado_estetico INT UNSIGNED NOT NULL,
  path VARCHAR(255) NOT NULL DEFAULT '',
  prezzo DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (id_prodotto),
  UNIQUE KEY uk_prodotto_variante (id_modello, id_capacita, id_colore, id_grado_estetico),
  KEY idx_prodotti_modello (id_modello),
  CONSTRAINT fk_prodotti_modelli FOREIGN KEY (id_modello) REFERENCES modelli (id_modello),
  CONSTRAINT fk_prodotti_capacita FOREIGN KEY (id_capacita) REFERENCES capacita (id_capacita),
  CONSTRAINT fk_prodotti_colori FOREIGN KEY (id_colore) REFERENCES colori (id_colore),
  CONSTRAINT fk_prodotti_gradi FOREIGN KEY (id_grado_estetico) REFERENCES gradi_estetici (id_grado_estetico)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ordini (
  id_ordine   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_cliente  INT UNSIGNED NOT NULL,
  data_ordine DATETIME NOT NULL,
  stato       VARCHAR(64) NOT NULL DEFAULT 'In lavorazione',
  totale      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (id_ordine),
  KEY idx_ordini_cliente (id_cliente),
  CONSTRAINT fk_ordini_cliente
    FOREIGN KEY (id_cliente) REFERENCES clienti (id_cliente)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE righe_ordine (
  id_riga         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_ordine       INT UNSIGNED NOT NULL,
  id_prodotto     INT UNSIGNED NOT NULL,
  quantita        INT UNSIGNED NOT NULL DEFAULT 1,
  prezzo_unitario DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (id_riga),
  KEY idx_righe_ordine (id_ordine),
  KEY idx_righe_prodotto (id_prodotto),
  CONSTRAINT fk_righe_ordine
    FOREIGN KEY (id_ordine) REFERENCES ordini (id_ordine)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_righe_prodotto
    FOREIGN KEY (id_prodotto) REFERENCES prodotti (id_prodotto)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------------
-- 3) Dati di esempio (opzionali — commentare la sezione se non servono)
-- ---------------------------------------------------------------------------

-- Utenti demo:
-- NOTA: qui le password sono in chiaro solo per semplicità di import.
-- L'app ora usa password_hash/password_verify e al primo login corretto
-- effettua automaticamente la migrazione salvando l'hash nel database.
INSERT INTO utenti (username, email, password, ruolo) VALUES
  ('demo', 'demo@esempio.it', 'demo123', 'utente'),
  ('admin', 'admin@giudmunna.it', 'admin123', 'admin'),
  ('ordini', 'ordini@giudmunna.it', 'ordini123', 'utente');

SET @uid_demo = LAST_INSERT_ID();

INSERT INTO clienti (id_utente, nome, cognome, indirizzo, citta, cap, telefono) VALUES
  (@uid_demo, 'Mario', 'Rossi', 'Via Esempio 1', 'Milano', '20100', '3331234567');

-- Cliente per l'utente "ordini" (serve per poter effettuare e visualizzare ordini)
INSERT INTO clienti (id_utente, nome, cognome, indirizzo, citta, cap, telefono) VALUES
  ((SELECT id_utente FROM utenti WHERE username = 'ordini'), 'Luca', 'Bianchi', 'Via Ordini 10', 'Roma', '00100', '3330001111');

-- Lookup per la normalizzazione dei prodotti
INSERT INTO modelli (nome) VALUES
  ('iPhone 13'),
  ('iPhone 14'),
  ('iPhone 15'),
  ('iPhone 15 Pro');

INSERT INTO capacita (valore) VALUES
  ('128 GB'),
  ('256 GB'),
  ('512 GB');

INSERT INTO colori (nome) VALUES
  ('Nero'),
  ('Blu'),
  ('Verde'),
  ('Rosa'),
  ('Giallo'),
  ('Bianco'),
  ('Viola'),
  ('Titanio Nero'),
  ('Titanio Naturale'),
  ('Titanio Bianco'),
  ('Titanio Blu');

INSERT INTO gradi_estetici (descrizione) VALUES
  ('Buono'),
  ('Ottimo'),
  ('Eccellente');

-- Varianti iPhone con schema normalizzato (FK) + path immagine dinamico.
-- Prezzo finale = base modello + delta capacità + delta grado estetico.
INSERT INTO prodotti (id_modello, id_capacita, id_colore, id_grado_estetico, path, prezzo)
SELECT
  mo.id_modello,
  ca.id_capacita,
  co.id_colore,
  ge.id_grado_estetico,
  CONCAT(
    'https://assets.swappie.com/cdn-cgi/image/width=600,height=600,fit=contain,format=auto/swappie-',
    ml.modello_slug,
    '-',
    mcl.colore_slug,
    '.png?v=e87bf771'
  ) AS path,
  (ml.prezzo_base + cap.delta_capacita + gr.delta_grado) AS prezzo
FROM (
  SELECT 'iPhone 13' AS modello, 'iphone-13' AS modello_slug, 429.00 AS prezzo_base
  UNION ALL SELECT 'iPhone 14', 'iphone-14', 539.00
  UNION ALL SELECT 'iPhone 15', 'iphone-15', 679.00
  UNION ALL SELECT 'iPhone 15 Pro', 'iphone-15-pro', 899.00
) AS ml
JOIN modelli mo ON mo.nome = ml.modello
JOIN (
  SELECT '128 GB' AS capacita, 0.00 AS delta_capacita
  UNION ALL SELECT '256 GB', 90.00
  UNION ALL SELECT '512 GB', 220.00
) AS cap
JOIN capacita ca ON ca.valore = cap.capacita
JOIN (
  -- Colori disponibili per modello (evita combinazioni non reali).
  SELECT 'iPhone 13' AS modello, 'Nero' AS colore, 'black' AS colore_slug
  UNION ALL SELECT 'iPhone 13', 'Blu', 'blue'
  UNION ALL SELECT 'iPhone 13', 'Verde', 'green'
  UNION ALL SELECT 'iPhone 13', 'Rosa', 'pink'
  UNION ALL SELECT 'iPhone 13', 'Bianco', 'starlight'
  UNION ALL SELECT 'iPhone 14', 'Nero', 'midnight'
  UNION ALL SELECT 'iPhone 14', 'Blu', 'blue'
  UNION ALL SELECT 'iPhone 14', 'Viola', 'purple'
  UNION ALL SELECT 'iPhone 14', 'Rosa', 'pink'
  UNION ALL SELECT 'iPhone 14', 'Giallo', 'yellow'
  UNION ALL SELECT 'iPhone 15', 'Nero', 'black'
  UNION ALL SELECT 'iPhone 15', 'Blu', 'blue'
  UNION ALL SELECT 'iPhone 15', 'Verde', 'green'
  UNION ALL SELECT 'iPhone 15', 'Rosa', 'pink'
  UNION ALL SELECT 'iPhone 15', 'Giallo', 'yellow'
  UNION ALL SELECT 'iPhone 15 Pro', 'Titanio Nero', 'black-titanium'
  UNION ALL SELECT 'iPhone 15 Pro', 'Titanio Naturale', 'natural-titanium'
  UNION ALL SELECT 'iPhone 15 Pro', 'Titanio Bianco', 'white-titanium'
  UNION ALL SELECT 'iPhone 15 Pro', 'Titanio Blu', 'blue-titanium'
) AS mcl
  ON mcl.modello = ml.modello
JOIN colori co ON co.nome = mcl.colore
JOIN (
  SELECT 'Buono' AS grado_estetico, -70.00 AS delta_grado
  UNION ALL SELECT 'Ottimo', 0.00
  UNION ALL SELECT 'Eccellente', 70.00
) AS gr
JOIN gradi_estetici ge ON ge.descrizione = gr.grado_estetico;

-- ---------------------------------------------------------------------------
-- 4) Aggiornamenti per database GIÀ ESISTENTI (solo se serve)
-- ---------------------------------------------------------------------------
-- Se `prodotti` esiste ma manca la colonna immagine:
--   ALTER TABLE prodotti ADD COLUMN path VARCHAR(255) NOT NULL DEFAULT '' AFTER grado_estetico;
--
-- Se `ordini` ha `id_ordine` INT signed e la FK su `righe_ordine` fallisce,
-- crea `righe_ordine` con id_ordine INT NOT NULL (senza UNSIGNED) per allinearti.
-- Script dedicato storico: install_righe_ordine.sql
-- ---------------------------------------------------------------------------

-- ---------------------------------------------------------------------------
-- 5) Riferimento: query usate dai file PHP (documentazione, non eseguire in blocco)
-- ---------------------------------------------------------------------------
/*
  config.php
    SHOW COLUMNS FROM prodotti LIKE 'path'

  index.php
    SELECT p.id_prodotto, m.nome AS modello, c.valore AS capacita, co.nome AS colore,
           g.descrizione AS grado_estetico, p.path, p.prezzo
      FROM prodotti p
      JOIN modelli m ON p.id_modello = m.id_modello
      JOIN capacita c ON p.id_capacita = c.id_capacita
      JOIN colori co ON p.id_colore = co.id_colore
      JOIN gradi_estetici g ON p.id_grado_estetico = g.id_grado_estetico
      ORDER BY p.id_prodotto ASC LIMIT 3

  catalogo.php
    SELECT p.id_prodotto, m.nome AS modello, c.valore AS capacita, co.nome AS colore,
           g.descrizione AS grado_estetico, p.path, p.prezzo
      FROM prodotti p
      JOIN modelli m ON p.id_modello = m.id_modello
      JOIN capacita c ON p.id_capacita = c.id_capacita
      JOIN colori co ON p.id_colore = co.id_colore
      JOIN gradi_estetici g ON p.id_grado_estetico = g.id_grado_estetico
      ORDER BY modello, capacita, colore, grado_estetico, p.id_prodotto

  prodotto.php
    SELECT p.id_prodotto, m.nome AS modello, c.valore AS capacita, co.nome AS colore,
           g.descrizione AS grado_estetico, p.path, p.prezzo
      FROM prodotti p
      JOIN modelli m ON p.id_modello = m.id_modello
      JOIN capacita c ON p.id_capacita = c.id_capacita
      JOIN colori co ON p.id_colore = co.id_colore
      JOIN gradi_estetici g ON p.id_grado_estetico = g.id_grado_estetico
      WHERE p.id_prodotto = ?
    SELECT p.id_prodotto, m.nome AS modello, c.valore AS capacita, co.nome AS colore,
           g.descrizione AS grado_estetico, p.path, p.prezzo
      FROM prodotti p
      JOIN modelli m ON p.id_modello = m.id_modello
      JOIN capacita c ON p.id_capacita = c.id_capacita
      JOIN colori co ON p.id_colore = co.id_colore
      JOIN gradi_estetici g ON p.id_grado_estetico = g.id_grado_estetico
      WHERE m.nome = ? ORDER BY c.valore, co.nome, g.descrizione

  carrello.php
    SELECT p.id_prodotto, m.nome AS modello, c.valore AS capacita, co.nome AS colore,
           g.descrizione AS grado_estetico, p.path, p.prezzo
      FROM prodotti p
      JOIN modelli m ON p.id_modello = m.id_modello
      JOIN capacita c ON p.id_capacita = c.id_capacita
      JOIN colori co ON p.id_colore = co.id_colore
      JOIN gradi_estetici g ON p.id_grado_estetico = g.id_grado_estetico
      WHERE p.id_prodotto = ?

  registrazione.php
    INSERT INTO utenti (username, email, password) VALUES (?, ?, ?)

  login.php
    SELECT id_utente, password FROM utenti WHERE username = ?

  profilo.php
    SELECT id_cliente, nome, cognome, indirizzo, citta, cap, telefono
      FROM clienti WHERE id_utente = ?
    UPDATE clienti SET nome=?, cognome=?, indirizzo=?, citta=?, cap=?, telefono=?
      WHERE id_cliente=?
    INSERT INTO clienti (id_utente, nome, cognome, indirizzo, citta, cap, telefono)
      VALUES (?, ?, ?, ?, ?, ?, ?)

  ordine.php
    SELECT id_cliente FROM clienti WHERE id_utente = ?
    SELECT m.nome AS modello, c.valore AS capacita, co.nome AS colore,
           g.descrizione AS grado_estetico, p.prezzo
      FROM prodotti p
      JOIN modelli m ON p.id_modello = m.id_modello
      JOIN capacita c ON p.id_capacita = c.id_capacita
      JOIN colori co ON p.id_colore = co.id_colore
      JOIN gradi_estetici g ON p.id_grado_estetico = g.id_grado_estetico
      WHERE p.id_prodotto = ?
    INSERT INTO ordini (id_cliente, data_ordine, stato, totale) VALUES (?, ?, ?, ?)
    INSERT INTO righe_ordine (id_ordine, id_prodotto, quantita, prezzo_unitario)
      VALUES (?, ?, ?, ?)

  checkout.php
    SELECT id_cliente FROM clienti WHERE id_utente = ?
    SELECT p.id_prodotto, m.nome AS modello, p.prezzo
      FROM prodotti p
      JOIN modelli m ON p.id_modello = m.id_modello
      WHERE p.id_prodotto = ?
    INSERT INTO ordini (id_cliente, data_ordine, stato, totale) VALUES (?, ?, ?, ?)
    INSERT INTO righe_ordine (id_ordine, id_prodotto, quantita, prezzo_unitario)
      VALUES (?, ?, ?, ?)
*/
