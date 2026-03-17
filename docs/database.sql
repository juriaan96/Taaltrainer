-- =============================================
-- Taaltrainer Database
-- =============================================

CREATE DATABASE IF NOT EXISTS taaltrainer CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE taaltrainer;

-- =============================================
-- Tabel: users
-- =============================================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('student', 'docent') NOT NULL DEFAULT 'student',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- =============================================
-- Tabel: woordenlijsten
-- =============================================
CREATE TABLE woordenlijsten (
    id INT AUTO_INCREMENT PRIMARY KEY,
    naam VARCHAR(100) NOT NULL,
    taal_van VARCHAR(50) NOT NULL,
    taal_naar VARCHAR(50) NOT NULL,
    created_by INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

-- =============================================
-- Tabel: woorden
-- =============================================
CREATE TABLE woorden (
    id INT AUTO_INCREMENT PRIMARY KEY,
    woordenlijst_id INT NOT NULL,
    woord VARCHAR(100) NOT NULL,
    vertaling VARCHAR(100) NOT NULL,
    FOREIGN KEY (woordenlijst_id) REFERENCES woordenlijsten(id) ON DELETE CASCADE
);

-- =============================================
-- Tabel: resultaten
-- =============================================
CREATE TABLE resultaten (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    woordenlijst_id INT NOT NULL,
    score DECIMAL(4,1) NOT NULL,
    totaal INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (woordenlijst_id) REFERENCES woordenlijsten(id) ON DELETE CASCADE
);

-- =============================================
-- Tabel: woord_statistieken
-- =============================================
CREATE TABLE woord_statistieken (
    user_id  INT NOT NULL,
    woord_id INT NOT NULL,
    correct  INT NOT NULL DEFAULT 0,
    fout     INT NOT NULL DEFAULT 0,
    PRIMARY KEY (user_id, woord_id),
    FOREIGN KEY (user_id)  REFERENCES users(id)   ON DELETE CASCADE,
    FOREIGN KEY (woord_id) REFERENCES woorden(id) ON DELETE CASCADE
);

-- =============================================
-- Testdata
-- =============================================

-- Gebruikers (wachtwoord voor beiden: 'welkom123')
INSERT INTO users (username, password_hash, role) VALUES
('docent', '$2y$10$pZ30K373HXfvAcF3opHYHuWRdEclVgKwBR6iaklXNs8CZ2Ez26iUO', 'docent'),
('leerling', '$2y$10$pZ30K373HXfvAcF3opHYHuWRdEclVgKwBR6iaklXNs8CZ2Ez26iUO', 'student');

-- Woordenlijst
INSERT INTO woordenlijsten (naam, taal_van, taal_naar, created_by) VALUES
('Dieren (NL → EN)', 'Nederlands', 'Engels', 1),
('Kleuren (NL → EN)', 'Nederlands', 'Engels', 1);

-- Woorden lijst 1: Dieren
INSERT INTO woorden (woordenlijst_id, woord, vertaling) VALUES
(1, 'hond', 'dog'),
(1, 'kat', 'cat'),
(1, 'paard', 'horse'),
(1, 'vis', 'fish'),
(1, 'vogel', 'bird'),
(1, 'konijn', 'rabbit'),
(1, 'koe', 'cow'),
(1, 'schaap', 'sheep'),
(1, 'varken', 'pig'),
(1, 'olifant', 'elephant');

-- =============================================
-- Multiplayer tabellen
-- =============================================

CREATE TABLE multiplayer_games (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    code            VARCHAR(6) NOT NULL UNIQUE,
    speler1_id      INT NOT NULL,
    speler2_id      INT DEFAULT NULL,
    lijst_id        INT NOT NULL,
    max_rondes      INT NOT NULL DEFAULT 5,
    status          ENUM('wachten','lobby','bezig','klaar') NOT NULL DEFAULT 'wachten',
    ronde           INT NOT NULL DEFAULT 1,
    score_speler1   INT NOT NULL DEFAULT 0,
    score_speler2   INT NOT NULL DEFAULT 0,
    speler1_klaar   TINYINT NOT NULL DEFAULT 0,
    speler2_klaar   TINYINT NOT NULL DEFAULT 0,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (speler1_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (lijst_id)   REFERENCES woordenlijsten(id) ON DELETE CASCADE
);

CREATE TABLE multiplayer_woorden (
    id       INT AUTO_INCREMENT PRIMARY KEY,
    game_id  INT NOT NULL,
    volgorde INT NOT NULL,
    woord_id INT NOT NULL,
    FOREIGN KEY (game_id)  REFERENCES multiplayer_games(id) ON DELETE CASCADE,
    FOREIGN KEY (woord_id) REFERENCES woorden(id) ON DELETE CASCADE
);

CREATE TABLE multiplayer_antwoorden (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    game_id      INT NOT NULL,
    ronde        INT NOT NULL,
    user_id      INT NOT NULL,
    antwoord     VARCHAR(200) NOT NULL DEFAULT '',
    correct      TINYINT NOT NULL DEFAULT 0,
    ingediend_op DATETIME DEFAULT NOW(),
    UNIQUE KEY uniq_antwoord (game_id, ronde, user_id),
    FOREIGN KEY (game_id) REFERENCES multiplayer_games(id) ON DELETE CASCADE
);

CREATE TABLE multiplayer_chat (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    game_id      INT NOT NULL,
    user_id      INT NOT NULL,
    bericht      VARCHAR(300) NOT NULL,
    verzonden_op DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (game_id) REFERENCES multiplayer_games(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Als je een bestaande database bijwerkt (in plaats van opnieuw aanmaken):
-- ALTER TABLE multiplayer_games ADD COLUMN speler1_klaar TINYINT NOT NULL DEFAULT 0;
-- ALTER TABLE multiplayer_games ADD COLUMN speler2_klaar TINYINT NOT NULL DEFAULT 0;
-- ALTER TABLE multiplayer_games MODIFY COLUMN status ENUM('wachten','lobby','bezig','klaar') NOT NULL DEFAULT 'wachten';
-- (Bovenstaande tabel multiplayer_chat aanmaken met CREATE TABLE hierboven)

-- Woorden lijst 2: Kleuren
INSERT INTO woorden (woordenlijst_id, woord, vertaling) VALUES
(2, 'rood', 'red'),
(2, 'blauw', 'blue'),
(2, 'groen', 'green'),
(2, 'geel', 'yellow'),
(2, 'zwart', 'black'),
(2, 'wit', 'white'),
(2, 'oranje', 'orange'),
(2, 'paars', 'purple'),
(2, 'roze', 'pink'),
(2, 'bruin', 'brown');
