<?php

$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');
session_set_cookie_params([
  'httponly' => true,
  'samesite' => 'Lax',
  'secure'   => $secure,
]);
session_start();

require_once __DIR__.'/db_connection.php';
require_once __DIR__.'/perm_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405); exit('Metodo non consentito');
}
$host = $_SERVER['HTTP_HOST'] ?? '';
$ok   = false;
if (!empty($_SERVER['HTTP_ORIGIN'])) {
  $ok = (parse_url($_SERVER['HTTP_ORIGIN'], PHP_URL_HOST) === $host);
} elseif (!empty($_SERVER['HTTP_REFERER'])) {
  $ok = (parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST) === $host);
}
if (!$ok) { http_response_code(400); exit('Richiesta non valida'); }

if (empty($_SESSION['utente'])) { header('Location: login.php'); exit; }
$me = $_SESSION['utente'];

$noteId   = (int)($_POST['id'] ?? 0);
$titolo   = trim((string)($_POST['titolo'] ?? ''));
$testo    = trim((string)($_POST['testo'] ?? ''));
$tag      = trim((string)($_POST['tag'] ?? ''));
$cartella = trim((string)($_POST['cartella'] ?? ''));

if ($noteId <= 0) { http_response_code(400); exit('ID non valido'); }

if (!user_can_write($conn, $noteId, $me)) {
  http_response_code(403); exit('Permesso negato (write)');
}

$stmt = $conn->prepare('SELECT autore, pubblica, allow_edit FROM Note WHERE id=?');
$stmt->bind_param('i', $noteId);
$stmt->execute();
$noteRow = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$noteRow) { http_response_code(404); exit('Nota non trovata'); }

$sonoAutore = ($noteRow['autore'] === $me);

if ($titolo === '' || mb_strlen($titolo,'UTF-8') > 100) {
  http_response_code(422); exit('Titolo mancante o troppo lungo');
}
if ($testo === '' || mb_strlen($testo,'UTF-8') > 280) {
  http_response_code(422); exit('Testo mancante o troppo lungo');
}
if (mb_strlen($tag,'UTF-8') > 255)      $tag = mb_substr($tag,0,255,'UTF-8');
if (mb_strlen($cartella,'UTF-8') > 255) $cartella = mb_substr($cartella,0,255,'UTF-8');

if ($sonoAutore) {
  $pubblica   = !empty($_POST['pubblica']) ? 1 : 0;
  $allow_edit = !empty($_POST['allow_edit']) ? 1 : 0;
} else {
  $pubblica   = (int)$noteRow['pubblica'];
  $allow_edit = (int)$noteRow['allow_edit'];
}

$redirectAfter = $pubblica ? 'home.php' : 'profile.php';

$conn->begin_transaction();
try {
  $stmt = $conn->prepare('
    UPDATE Note
    SET titolo = ?, testo = ?, tag = ?, cartella = ?, pubblica = ?, allow_edit = ?, data_ultima_modifica = NOW()
    WHERE id = ?
  ');
  $stmt->bind_param('ssssiii', $titolo, $testo, $tag, $cartella, $pubblica, $allow_edit, $noteId);
  $stmt->execute();
  $stmt->close();

  $stmt = $conn->prepare('
    INSERT INTO NoteRevision (note_id, editor, titolo, testo, tag, cartella, created_at)
    VALUES (?, ?, ?, ?, ?, ?, NOW())
  ');
  $stmt->bind_param('isssss', $noteId, $me, $titolo, $testo, $tag, $cartella);
  $stmt->execute();
  $stmt->close();

  $conn->commit();

  $_SESSION['flash_success'] = 'Nota aggiornata.';
  header('Location: '.$redirectAfter);
  exit;

} catch (Throwable $e) {
  $conn->rollback();
  error_log('update_note error: '.$e->getMessage());
  http_response_code(500);
  $_SESSION['flash_error'] = 'Errore durante l\'aggiornamento.';
  header('Location: form_modifica.php?id='.$noteId);
  exit;
}
