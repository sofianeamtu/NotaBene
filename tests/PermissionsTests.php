<?php
// tests/PermissionsTests.php

declare(strict_types=1);

require_once __DIR__ . '/DatabaseTestCase.php';

final class PermissionsTests extends DatabaseTestCase
{
    public function testOwnerCanShareReadWrite(): void
    {
        $this->seedUser('alice', 'a');
        $this->seedUser('bob',   'b');

        $noteId = $this->seedNote('alice', 'Spesa', 'Latte, uova', 'spesa', 'casa', false, false);

        // Condivido con bob in read
        $this->shareNote($noteId, 'bob', 'read');

        // Verifico che bob abbia read e non write
        $stmt = $this->conn->prepare("SELECT `permesso` FROM `note_share` WHERE `note_id`=? AND `username`=?");
        $stmt->bind_param('is', $noteId, $bob = 'bob');
        $stmt->execute();
        $perm = $stmt->get_result()->fetch_assoc()['permesso'] ?? null;
        $stmt->close();

        $this->assertSame('read', $perm);

        // Aggiorno a write
        $stmt = $this->conn->prepare("UPDATE `note_share` SET `permesso`='write' WHERE `note_id`=? AND `username`=?");
        $stmt->bind_param('is', $noteId, $bob);
        $stmt->execute();
        $stmt->close();

        // Ricontrollo
        $stmt = $this->conn->prepare("SELECT `permesso` FROM `note_share` WHERE `note_id`=? AND `username`=?");
        $stmt->bind_param('is', $noteId, $bob);
        $stmt->execute();
        $perm2 = $stmt->get_result()->fetch_assoc()['permesso'] ?? null;
        $stmt->close();

        $this->assertSame('write', $perm2);
    }

    public function testPublicNotesAreVisibleWithoutShare(): void
    {
        $this->seedUser('alice', 'a');
        $this->seedUser('bob', 'b');

        $noteId = $this->seedNote('alice', 'Pubblica', 'Ciao mondo', null, null, true, false);

        // bob vede la nota pubblica
        $stmt = $this->conn->prepare("SELECT `id` FROM `note` WHERE `id`=? AND `pubblica`=1");
        $stmt->bind_param('i', $noteId);
        $stmt->execute();
        $stmt->store_result();

        $this->assertSame(1, $stmt->num_rows);
        $stmt->close();
    }
}
