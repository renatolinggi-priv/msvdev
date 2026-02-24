-- Migration 009: Umfragen-System für Mitgliederportal
-- Führe dieses Script direkt auf der Datenbank aus.

CREATE TABLE umfragen (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titel VARCHAR(255) NOT NULL,
    beschreibung TEXT,
    erstellt_von INT NOT NULL,
    erstellt_am DATETIME DEFAULT NOW(),
    gueltig_bis DATE DEFAULT NULL,
    status ENUM('entwurf','aktiv','geschlossen') DEFAULT 'entwurf',
    zielgruppe ENUM('alle','vorstand') DEFAULT 'alle'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE umfragen_fragen (
    id INT AUTO_INCREMENT PRIMARY KEY,
    umfrage_id INT NOT NULL,
    frage_text VARCHAR(500) NOT NULL,
    frage_typ ENUM('radio','checkbox','dropdown','text') NOT NULL,
    pflichtfeld TINYINT(1) DEFAULT 1,
    reihenfolge INT DEFAULT 0,
    optionen JSON DEFAULT NULL,
    FOREIGN KEY (umfrage_id) REFERENCES umfragen(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE umfragen_antworten (
    id INT AUTO_INCREMENT PRIMARY KEY,
    umfrage_id INT NOT NULL,
    frage_id INT NOT NULL,
    mitglied_id INT NOT NULL,
    antwort TEXT,
    beantwortet_am DATETIME DEFAULT NOW(),
    UNIQUE KEY uq_frage_mitglied (frage_id, mitglied_id),
    FOREIGN KEY (umfrage_id) REFERENCES umfragen(id) ON DELETE CASCADE,
    FOREIGN KEY (frage_id) REFERENCES umfragen_fragen(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
