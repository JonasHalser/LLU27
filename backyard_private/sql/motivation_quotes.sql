CREATE TABLE IF NOT EXISTS motivation_quotes (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 quote VARCHAR(120) NOT NULL,
 created_by INT UNSIGNED NULL,
 updated_by INT UNSIGNED NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_motivation_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_motivation_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Startsprüche (nur einmal ausführen; danach im Reiter „Motivation“ pflegen).
INSERT INTO motivation_quotes (quote) VALUES
 ('Eine Runde nach der anderen.'),
 ('Jede Stunde zählt. Jeder Schritt auch.'),
 ('Der Kopf läuft die letzte Runde.'),
 ('Heute trainieren, am 30. April lächeln.'),
 ('Langsam ist schnell genug, solange du weiterläufst.'),
 ('Müde Beine, starker Wille.'),
 ('Wer am Start steht, hat schon gewonnen.');
