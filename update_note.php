<?php
require 'db_connection.php';
session_start();

if (!isset($_SESSION['utente'])) { header('Location: login.php'); exit; }
$utente = $_SESSION['utente'];

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$id       = isset($_POST['id']) ? intval($_POST['id']) : 0;
$titolo   = trim($_POST['titolo']   ?? '');
$testo    = trim($_POST['testo'] ?? '');
      if (mb_strlen($testo, 'UTF-8') > 280) {
          http_response_code(422);
          $_SESSION['flash_error'] = 'La nota non può superare 280 caratteri.';
          header('Location: form_modifica.php?id='.(int)$_POST['id']); 
          exit;
      }
$tag      = trim($_POST['tag']      ?? '');
$cartella = trim($_POST['cartella'] ?? '');

if ($id <= 0 || $titolo === '' || $testo === '') {
  http_response_code(400);
  echo "Parametri non validi";
  exit;
}

// Carica stato attuale della nota
$stmt = $conn->prepare("SELECT id, autore, pubblica, allow_edit FROM Note WHERE id=?");
$stmt->bind_param('i', $id);
$stmt->execute();
$nota = $stmt->get_result()->fetch_assoc();

if (!$nota) {
  http_response_code(404);
  echo "Nota non trovata";
  exit;
}

$sonoAutore       = ($nota['autore'] === $utente);
$collabConsentita = ($nota['pubblica'] == 1 && $nota['allow_edit'] == 1);

if (!$sonoAutore && !$collabConsentita) {
  http_response_code(403);
  echo "Non hai i permessi per modificare questa nota.";
  exit;
}

if ($sonoAutore) {
  $pubblica   = isset($_POST['pubblica']) ? 1 : 0;
  $allow_edit = isset($_POST['allow_edit']) ? 1 : 0;

  $sql = "UPDATE Note 
          SET titolo=?, testo=?, tag=?, cartella=?, pubblica=?, allow_edit=?
          WHERE id=? AND autore=?";
  $upd = $conn->prepare($sql);
  // tipi: s s s s i i i s  -> 'ssssiiis'
  $upd->bind_param('ssssiiis', $titolo, $testo, $tag, $cartella, $pubblica, $allow_edit, $id, $utente);
  $upd->execute();

} else {
  // Collaboratore: può cambiare solo contenuti, e solo se la nota resta pubblica e editabile
  $sql = "UPDATE Note 
          SET titolo=?, testo=?, tag=?, cartella=?
          WHERE id=? AND pubblica=1 AND allow_edit=1";
  $upd = $conn->prepare($sql);
  $upd->bind_param('ssssi', $titolo, $testo, $tag, $cartella, $id);
  $upd->execute();
}

// Registra la revisione
$rev = $conn->prepare("
  INSERT INTO NoteRevision (note_id, editor, titolo, testo, tag, cartella)
  VALUES (?,?,?,?,?,?)
");
$rev->bind_param('isssss', $id, $utente, $titolo, $testo, $tag, $cartella);
$rev->execute();

// Reindirizza in base al tipo di nota
if ($sonoAutore) {
    if ($pubblica) {
        header("Location: home.php?msg=" . urlencode("Nota pubblica aggiornata da $utente"));
    } else {
        header("Location: profile.php?msg=" . urlencode("Nota privata aggiornata da $utente"));
    }
} else {
    header("Location: home.php?msg=" . urlencode("Nota aggiornata da $utente"));
}
exit;
