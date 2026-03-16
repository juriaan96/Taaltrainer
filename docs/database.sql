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
