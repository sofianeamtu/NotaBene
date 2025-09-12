<?php

session_start();
require_once __DIR__.'/db_connection.php';
require_once __DIR__.'/perm_helper.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['POST','GET'], true)) {
  http_response_code(405); exit('Metodo non consentito');
}

if (empty($_SESSION['utente'])) { header('Location: login.php'); exit; }
$me = $_SESSION['utente'];

$note_id = (int)($method === 'POST' ? ($_POST['id'] ?? 0) : ($_GET['id'] ?? 0));
if ($note_id <= 0) { http_response_code(400); exit('ID della nota non specificato.'); }

if (!user_can_read($conn, $note_id, $me)) {
  http_response_code(403); exit('Non hai i permessi per copiare questa nota.');
}

$stmt = $conn->prepare("SELECT titolo, testo, autore, tag, cartella FROM Note WHERE id = ?");
$stmt->bind_param("i", $note_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) { $stmt->close(); exit('Nota non trovata.'); }
$nota = $res->fetch_assoc();
$stmt->close();

$srcTitolo   = (string)$nota['titolo'];
$srcTesto    = (string)$nota['testo'];
$srcTag      = (string)($nota['tag'] ?? '');
$srcCartella = (string)($nota['cartella'] ?? '');

$nuovoTitolo = 'Copia di ' . $srcTitolo;
if (mb_strlen($nuovoTitolo, 'UTF-8') > 100) {
  $maxRest = 100 - mb_strlen('Copia di ', 'UTF-8');
  $nuovoTitolo = 'Copia di ' . mb_substr($srcTitolo, 0, max(0,$maxRest), 'UTF-8');
}

$nuovoTesto = (mb_strlen($srcTesto,'UTF-8') > 280) ? mb_substr($srcTesto,0,280,'UTF-8') : $srcTesto;

$tags = array_filter(array_map('trim', $srcTag === '' ? [] : explode(',', $srcTag)));
$hasCopiata = false;
foreach ($tags as $t) { if (mb_strtolower($t,'UTF-8') === 'copiata') { $hasCopiata = true; break; } }
if (!$hasCopiata) { $tags[] = 'copiata'; }
$nuoviTag = implode(', ', $tags);
if (mb_strlen($nuoviTag,'UTF-8') > 255) $nuoviTag = mb_substr($nuoviTag,0,255,'UTF-8');

$nuovaCartella = 'Note Copiate';

$pubblica   = 0;
$allow_edit = 0;

$conn->begin_transaction();
try {
  $stmt = $conn->prepare("
    INSERT INTO Note (autore, titolo, testo, tag, cartella, pubblica, allow_edit, data_creazione, data_ultima_modifica)
    VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
  ");
  $stmt->bind_param("sssssii", $me, $nuovoTitolo, $nuovoTesto, $nuoviTag, $nuovaCartella, $pubblica, $allow_edit);
  $stmt->execute();
  $newId = $stmt->insert_id;
  $stmt->close();

  $stmt = $conn->prepare("
    INSERT INTO NoteRevision (note_id, editor, titolo, testo, tag, cartella, created_at)
    VALUES (?, ?, ?, ?, ?, ?, NOW())
  ");
  $stmt->bind_param("isssss", $newId, $me, $nuovoTitolo, $nuovoTesto, $nuoviTag, $nuovaCartella);
  $stmt->execute();
  $stmt->close();

  $conn->commit();

  $_SESSION['flash_success'] = "Nota copiata con successo nella cartella 'Note Copiate'.";
    header('Location: profile.php');
  exit;

} catch (Throwable $e) {
  $conn->rollback();
  error_log('copy_note error: '.$e->getMessage());
  http_response_code(500);
  $_SESSION['flash_error'] = 'Errore durante la copia.';
  header("Location: ".(!empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'home.php'));
  exit;
}
