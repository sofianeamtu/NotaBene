CREATE DATABASE IF NOT EXISTS notabene
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;
USE notabene;

CREATE TABLE IF NOT EXISTS Utente (
  username VARCHAR(100) PRIMARY KEY,
  password VARCHAR(300) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS Note (
  id INT AUTO_INCREMENT PRIMARY KEY,
  autore VARCHAR(100) NOT NULL,
  titolo VARCHAR(100) NOT NULL,
  testo  VARCHAR(280) NOT NULL,
  tag VARCHAR(200),
  cartella VARCHAR(200),
  pubblica BOOLEAN NOT NULL DEFAULT FALSE,
  allow_edit BOOLEAN NOT NULL DEFAULT FALSE,
  data_creazione TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  data_ultima_modifica TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_note_autore FOREIGN KEY (autore) REFERENCES Utente(username)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  INDEX idx_note_autore (autore),
  INDEX idx_note_updated (data_ultima_modifica),
  INDEX idx_note_pubblica (pubblica)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS NoteRevision (
  id INT AUTO_INCREMENT PRIMARY KEY,
  note_id INT NOT NULL,
  editor  VARCHAR(100) NOT NULL,
  titolo  VARCHAR(100) NOT NULL,
  testo   VARCHAR(280) NOT NULL,
  tag VARCHAR(255),
  cartella VARCHAR(255),
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_rev_note FOREIGN KEY (note_id) REFERENCES Note(id) ON DELETE CASCADE,
  INDEX idx_rev_note (note_id),
  INDEX idx_rev_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS Note_Share (
  note_id  INT NOT NULL,
  username VARCHAR(100) NOT NULL,
  permesso ENUM('read','write') NOT NULL,
  PRIMARY KEY (note_id, username),
  CONSTRAINT fk_share_note  FOREIGN KEY (note_id)  REFERENCES Note(id)       ON DELETE CASCADE,
  CONSTRAINT fk_share_user  FOREIGN KEY (username) REFERENCES Utente(username) ON DELETE CASCADE,
  INDEX idx_share_user (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DELIMITER //
CREATE PROCEDURE RegistraUtente(
  IN  p_username VARCHAR(100),
  IN  p_password VARCHAR(300),
  OUT p_result   BOOLEAN
)
BEGIN
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    SET p_result = FALSE;
    ROLLBACK;
  END;

  START TRANSACTION;

  INSERT INTO Utente (username, password)
  VALUES (p_username, p_password);

  COMMIT;
  SET p_result = TRUE;
END//
DELIMITER ;