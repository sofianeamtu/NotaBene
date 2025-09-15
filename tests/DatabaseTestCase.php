<?php
// tests/DatabaseTestCase.php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/bootstrap.php';

abstract class DatabaseTestCase extends TestCase
{
    protected mysqli $conn;
    private string $dbName;

    protected function setUp(): void
    {
        $this->conn = db();
        // leggo il DB name che bootstrap ha selezionato
        $res = $this->conn->query("SELECT DATABASE() AS db");
        $this->dbName = $res->fetch_assoc()['db'] ?? 'notabene_test';

        $this->recreateDatabase();   // << chiave: drop+create DB
        $this->createSchema();       // poi ricreo le tabelle
    }

    protected function tearDown(): void
    {
        // opzionale: commenta se vuoi ispezionare lo stato post-test
        $this->recreateDatabase();
    }

    private function recreateDatabase(): void
    {
        // Riavvio completamente il DB di test per evitare residui/duplicati di key/constraint
        $this->conn->query("DROP DATABASE IF EXISTS `{$this->dbName}`");
        $this->conn->query("CREATE DATABASE `{$this->dbName}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $this->conn->select_db($this->dbName);
    }

    private function createSchema(): void
    {
        // USERS
        $this->conn->query("
            CREATE TABLE `users` (
              `username` VARCHAR(100) PRIMARY KEY,
              `password` VARCHAR(300) NULL,
              `password_hash` VARCHAR(300) NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // NOTE
        $this->conn->query("
            CREATE TABLE `note` (
              `id` INT AUTO_INCREMENT PRIMARY KEY,
              `autore` VARCHAR(100) NOT NULL,
              `titolo` VARCHAR(100) NOT NULL,
              `testo`  VARCHAR(280) NOT NULL,
              `tag` VARCHAR(200) NULL,
              `cartella` VARCHAR(200) NULL,
              `pubblica` TINYINT(1) NOT NULL DEFAULT 0,
              `allow_edit` TINYINT(1) NOT NULL DEFAULT 0,
              `data_creazione` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `data_ultima_modifica` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              CONSTRAINT `fk_note_autore_u` FOREIGN KEY (`autore`) REFERENCES `users`(`username`)
                ON UPDATE CASCADE ON DELETE RESTRICT,
              INDEX `idx_note_autore` (`autore`),
              INDEX `idx_note_updated` (`data_ultima_modifica`),
              INDEX `idx_note_pubblica` (`pubblica`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // NOTE_REVISION
        // NB: niente indice manuale su (note_id) — il FK si appoggia all’indice auto-creato,
        // evitiamo possibili collisioni su nomi/chiavi in alcuni setup.
        $this->conn->query("
            CREATE TABLE `note_revision` (
              `id` INT AUTO_INCREMENT PRIMARY KEY,
              `note_id` INT NOT NULL,
              `editor`  VARCHAR(100) NOT NULL,
              `titolo`  VARCHAR(100) NOT NULL,
              `testo`   VARCHAR(280) NOT NULL,
              `tag` VARCHAR(255) NULL,
              `cartella` VARCHAR(255) NULL,
              `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              CONSTRAINT `fk_rev_note_n` FOREIGN KEY (`note_id`) REFERENCES `note`(`id`) ON DELETE CASCADE,
              INDEX `idx_rev_created` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // NOTE_SHARE
        $this->conn->query("
            CREATE TABLE `note_share` (
              `note_id`  INT NOT NULL,
              `username` VARCHAR(100) NOT NULL,
              `permesso` ENUM('read','write') NOT NULL,
              PRIMARY KEY (`note_id`, `username`),
              CONSTRAINT `fk_share_note_n`  FOREIGN KEY (`note_id`)  REFERENCES `note`(`id`)        ON DELETE CASCADE,
              CONSTRAINT `fk_share_user_u`  FOREIGN KEY (`username`) REFERENCES `users`(`username`) ON DELETE CASCADE,
              INDEX `idx_share_user` (`username`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    // Helpers di seed (come prima)
    protected function seedUser(string $username, string $plainPassword): void
    {
        $hash = password_hash($plainPassword, PASSWORD_DEFAULT);
        $stmt = $this->conn->prepare("INSERT INTO `users`(`username`,`password_hash`) VALUES(?,?)");
        $stmt->bind_param('ss', $username, $hash);
        $stmt->execute();
        $stmt->close();
    }

    protected function seedNote(
        string $autore, string $titolo, string $testo,
        ?string $tag=null, ?string $cartella=null, bool $pubblica=false, bool $allowEdit=false
    ): int {
        $stmt = $this->conn->prepare("
            INSERT INTO `note`(`autore`,`titolo`,`testo`,`tag`,`cartella`,`pubblica`,`allow_edit`)
            VALUES (?,?,?,?,?,?,?)
        ");
        $pub = $pubblica ? 1 : 0;
        $aed = $allowEdit ? 1 : 0;
        $stmt->bind_param('sssssss', $autore, $titolo, $testo, $tag, $cartella, $pub, $aed);
        $stmt->execute();
        $id = $stmt->insert_id;
        $stmt->close();
        return $id;
    }

    protected function shareNote(int $noteId, string $username, string $permesso): void
    {
        $stmt = $this->conn->prepare("INSERT INTO `note_share`(`note_id`,`username`,`permesso`) VALUES (?,?,?)");
        $stmt->bind_param('iss', $noteId, $username, $permesso);
        $stmt->execute();
        $stmt->close();
    }

    protected function addRevision(int $noteId, string $editor, string $titolo, string $testo, ?string $tag=null, ?string $cartella=null): void
    {
        $stmt = $this->conn->prepare("
          INSERT INTO `note_revision`(`note_id`,`editor`,`titolo`,`testo`,`tag`,`cartella`)
          VALUES (?,?,?,?,?,?)
        ");
        $stmt->bind_param('isssss', $noteId, $editor, $titolo, $testo, $tag, $cartella);
        $stmt->execute();
        $stmt->close();
    }
}
