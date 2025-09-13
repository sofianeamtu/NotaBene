<?php
function add_revision(mysqli $conn, int $noteId, string $titolo, string $testo, ?string $tag, ?string $cartella, string $editor): void {
    if (mb_strlen($testo, 'UTF-8') > 280) $testo = mb_substr($testo, 0, 280, 'UTF-8');
    if (mb_strlen($titolo,'UTF-8') > 100) $titolo = mb_substr($titolo,0,100,'UTF-8');

    $stmt = $conn->prepare(
        "INSERT INTO NoteRevision (note_id, editor, titolo, testo, tag, cartella, created_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW())"
    );
    $stmt->bind_param("isssss", $noteId, $editor, $titolo, $testo, $tag, $cartella);
    $stmt->execute();
    $stmt->close();
}
