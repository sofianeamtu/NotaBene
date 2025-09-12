<?php
function user_is_owner(mysqli $conn, int $noteId, string $user): bool {
  $q = $conn->prepare("SELECT 1 FROM Note WHERE id=? AND autore=?");
  $q->bind_param('is', $noteId, $user);
  $q->execute();
  $ok = (bool)$q->get_result()->fetch_row();
  $q->close();
  return $ok;
}

function user_can_read(mysqli $conn, int $noteId, string $user): bool {
  $sql = "
    SELECT 1
    FROM Note n
    LEFT JOIN Note_Share s ON s.note_id = n.id AND s.username = ?
    WHERE n.id = ?
      AND ( n.autore = ?
         OR n.pubblica = 1
         OR s.permesso IN ('read','write') )
    LIMIT 1
  ";
  $q = $conn->prepare($sql);
  $q->bind_param('sis', $user, $noteId, $user);
  $q->execute();
  $ok = (bool)$q->get_result()->fetch_row();
  $q->close();
  return $ok;
}

function user_can_write(mysqli $conn, int $noteId, string $user): bool {
  $sql = "
    SELECT 1
    FROM Note n
    LEFT JOIN Note_Share s ON s.note_id = n.id AND s.username = ?
    WHERE n.id = ?
      AND ( n.autore = ?
         OR n.allow_edit = 1
         OR s.permesso = 'write' )
    LIMIT 1
  ";
  $q = $conn->prepare($sql);
  $q->bind_param('sis', $user, $noteId, $user);
  $q->execute();
  $ok = (bool)$q->get_result()->fetch_row();
  $q->close();
  return $ok;
}
