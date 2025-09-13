<?php

$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');
session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax','secure'=>$secure]);
session_start();

require_once __DIR__.'/db_connection.php';

if (empty($_SESSION['utente'])) { header('Location: login.php'); exit; }
$autore = $_SESSION['utente'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  ?>
  <!doctype html>
  <html lang="it">
  <head>
    <meta charset="utf-8">
    <title>Nuova nota - Nota Bene</title>
    <link rel="stylesheet" href="style.css">
    <style>
      .card{background:#fff;padding:24px;border-radius:12px;box-shadow:0 8px 25px rgba(0,0,0,.08);max-width:800px;margin:24px auto}
      label{display:block;margin-top:14px;color:#4a627a}
      input,textarea{width:100%;padding:12px;border:1px solid #ced4da;border-radius:8px}
      textarea{min-height:180px}
      .row{display:flex;gap:12px;align-items:center;flex-wrap:wrap}
      .btn{margin-top:18px;padding:12px 22px;border:none;border-radius:8px;background:#007bff;color:#fff;cursor:pointer}
    </style>
  </head>
  <body>
  <main class="card">
    <h2>Crea nuova nota</h2>
    <form method="post" action="newnote.php">
      <label for="titolo">Titolo</label>
      <input id="titolo" name="titolo" required maxlength="100" placeholder="Titolo (max 100)">

      <label for="testo">Testo</label>
      <textarea id="testo" name="testo" required maxlength="280" placeholder="Testo (max 280)"></textarea>
      <div style="font-size:.9em;color:#6c757d"><span id="counter">0/280</span></div>

      <label for="tag">Tag</label>
      <input id="tag" name="tag" maxlength="255" placeholder="es. lavoro, idee">

      <label for="cartella">Cartella</label>
      <input id="cartella" name="cartella" maxlength="255" placeholder="es. Personali">

      <div class="row" style="margin-top:8px">
        <label style="display:flex;align-items:center;gap:8px;">
          <input type="checkbox" name="pubblica" value="1"> Pubblica (visibile a tutti)
        </label>
      </div>

      <label for="share_users" style="margin-top:18px;">Utenti che possono modificare(lasciare vuoto se chiunque può modificare)</label>
      <input id="share_users" name="share_users" placeholder="mario, lucia, anna">

      <button type="submit" class="btn">Crea nota</button>
    </form>
  </main>
  <script>
    const ta=document.getElementById('testo');const c=document.getElementById('counter');
    function u(){c.textContent=(ta.value.length||0)+'/280';} ta.addEventListener('input',u);u();
  </script>
  </body>
  </html>
  <?php
  exit;
}

$titolo   = trim((string)($_POST['titolo'] ?? ''));
$testo    = trim((string)($_POST['testo'] ?? ''));
$tag      = trim((string)($_POST['tag'] ?? ''));
$cartella = trim((string)($_POST['cartella'] ?? ''));
$pubblica = !empty($_POST['pubblica']) ? 1 : 0;
$rawList  = trim((string)($_POST['share_users'] ?? ''));

if ($titolo === '' || mb_strlen($titolo,'UTF-8') > 100) { http_response_code(422); exit('Titolo mancante o troppo lungo'); }
if ($testo  === '' || mb_strlen($testo,'UTF-8') > 280) { http_response_code(422); exit('Testo mancante o troppo lungo'); }
if (mb_strlen($tag,'UTF-8') > 255)      $tag = mb_substr($tag,0,255,'UTF-8');
if (mb_strlen($cartella,'UTF-8') > 255) $cartella = mb_substr($cartella,0,255,'UTF-8');

$shareUsers = array_values(array_filter(array_map('trim', $rawList !== '' ? explode(',', $rawList) : [])));
$allow_edit = empty($shareUsers) ? 1 : 0; 

$conn->begin_transaction();
try {
  $stmt = $conn->prepare("
    INSERT INTO Note (autore, titolo, testo, tag, cartella, pubblica, allow_edit, data_creazione, data_ultima_modifica)
    VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
  ");
  $stmt->bind_param('sssssii', $autore, $titolo, $testo, $tag, $cartella, $pubblica, $allow_edit);
  $stmt->execute();
  $noteId = $stmt->insert_id;
  $stmt->close();

  $stmt = $conn->prepare("
    INSERT INTO NoteRevision (note_id, editor, titolo, testo, tag, cartella, created_at)
    VALUES (?, ?, ?, ?, ?, ?, NOW())
  ");
  $stmt->bind_param('isssss', $noteId, $autore, $titolo, $testo, $tag, $cartella);
  $stmt->execute();
  $stmt->close();

  if (!empty($shareUsers)) {
    $stmt = $conn->prepare("
      INSERT INTO Note_Share (note_id, username, permesso)
      VALUES (?, ?, 'write')
      ON DUPLICATE KEY UPDATE permesso='write'
    ");
    foreach ($shareUsers as $u) {
      if ($u === '' || $u === $autore) continue;
      $stmt->bind_param('is', $noteId, $u);
      $stmt->execute();
    }
    $stmt->close();
  }

  $conn->commit();
  header('Location: form_modifica.php?id='.$noteId);
  exit;

} catch (Throwable $e) {
  $conn->rollback();
  error_log('newnote error: '.$e->getMessage());
  http_response_code(500);
  exit('Errore durante la creazione della nota');
}
