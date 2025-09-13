-- Tabella Utente
CREATE TABLE Utente (
    username VARCHAR(100) PRIMARY KEY,
    password VARCHAR(300)
);

-- 1. Registrazione e Login
DELIMITER //
CREATE PROCEDURE RegistraUtente(
    IN p_username VARCHAR(100),
    IN p_password VARCHAR(300),
    OUT p_result BOOLEAN
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SET p_result = FALSE;
        ROLLBACK;
    END;
    
    START TRANSACTION;
    
    -- Inserisci utente base
    INSERT INTO Utente (username, password)
    VALUES (p_username, p_password);

    COMMIT;
    SET p_result = TRUE;
END //
DELIMITER ;

CREATE TABLE Note (
    id INT AUTO_INCREMENT PRIMARY KEY,
    autore VARCHAR(100),
    titolo VARCHAR(100),
    testo TEXT,
    tag VARCHAR(200),
    cartella VARCHAR(200),
    pubblica BOOLEAN DEFAULT FALSE,
    allow_edit BOOLEAN DEFAULT FALSE,
    data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_ultima_modifica TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (autore) REFERENCES Utente(username)
);

CREATE TABLE IF NOT EXISTS NoteRevision (
  id INT AUTO_INCREMENT PRIMARY KEY,
  note_id INT NOT NULL,
  editor VARCHAR(255) NOT NULL,
  titolo TEXT NOT NULL,
  testo  LONGTEXT NOT NULL,
  tag VARCHAR(255),
  cartella VARCHAR(255),
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (note_id),
  CONSTRAINT fk_rev_note FOREIGN KEY (note_id) REFERENCES Note(id) ON DELETE CASCADE
);


