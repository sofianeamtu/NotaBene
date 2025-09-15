<?php
// tests/NoteRevisionsTest.php

declare(strict_types=1);

require_once __DIR__ . '/DatabaseTestCase.php';

final class NoteRevisionsTest extends DatabaseTestCase
{
    public function testAddingRevisionsCreatesHistory(): void
    {
        $this->seedUser('alice', 'a');
        $this->seedUser('bob',   'b');

        $noteId = $this->seedNote('alice', 'Titolo', 'Testo v1', 'tag1', 'work', false, true);

        // prima revisione di alice
        $this->addRevision($noteId, 'alice', 'Titolo', 'Testo v1', 'tag1', 'work');
        // seconda revisione di bob (collaboratore)
        $this->shareNote($noteId, 'bob', 'write');
        $this->addRevision($noteId, 'bob', 'Titolo 2', 'Testo v2', 'tag2', 'work');

        // Conta revisioni
        $stmt = $this->conn->prepare("SELECT COUNT(*) AS n FROM `note_revision` WHERE `note_id`=?");
        $stmt->bind_param('i', $noteId);
        $stmt->execute();
        $n = (int)$stmt->get_result()->fetch_assoc()['n'];
        $stmt->close();

        $this->assertSame(2, $n);

        // Ordine cronologico
        $q = $this->conn->query("SELECT `editor`,`titolo`,`testo` FROM `note_revision` WHERE `note_id`={$noteId} ORDER BY `created_at` ASC, `id` ASC");
        $rows = $q->fetch_all(MYSQLI_ASSOC);

        $this->assertSame('alice', $rows[0]['editor']);
        $this->assertSame('bob',   $rows[1]['editor']);
        $this->assertSame('Titolo',  $rows[0]['titolo']);
        $this->assertSame('Titolo 2', $rows[1]['titolo']);
    }
}
