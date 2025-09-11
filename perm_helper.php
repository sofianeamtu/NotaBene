<?php
function user_can_read($conn, $note_id, $me) {
    $stmt = $conn->prepare("
        SELECT 1
        FROM Note n
        LEFT JOIN Note_Share s ON s.note_id = n.id AND s.username = ?
        WHERE n.id = ?
          AND (n.autore = ? OR n.pubblica = 1 OR s.permesso IN ('read','write'))
        LIMIT 1
    ");
    $stmt->bind_param("sis", $me, $note_id, $me);
    $stmt->execute();
    $res = $stmt->get_result();
    return $res->num_rows > 0;
}

function user_can_write($conn, $note_id, $me) {
    $stmt = $conn->prepare("
        SELECT 1
        FROM Note n
        LEFT JOIN Note_Share s ON s.note_id = n.id AND s.username = ?
        WHERE n.id = ?
          AND (n.autore = ? OR s.permesso = 'write')
        LIMIT 1
    ");
    $stmt->bind_param("sis", $me, $note_id, $me);
    $stmt->execute();
    $res = $stmt->get_result();
    return $res->num_rows > 0;
}

function user_is_owner($conn, $note_id, $me) {
    $stmt = $conn->prepare("SELECT 1 FROM Note WHERE id = ? AND autore = ? LIMIT 1");
    $stmt->bind_param("is", $note_id, $me);
    $stmt->execute();
    $res = $stmt->get_result();
    return $res->num_rows > 0;
}
?>