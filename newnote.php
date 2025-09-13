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
      /* tieni il tuo stile minimale; il layout è ora quello del form modifica */
      .card{background:#fff;padding:24px;border-radius:12px;box-shadow:0 8px 25px rgba(0,0,0,.08);max-width:800px;margin:24px auto}
      label{display:block;margin-top:14px;color:#4a627a}
      input,textarea{width:100%;padding:12px;border:1px solid #ced4da;border-radius:8px}
      textarea{min-height:180px}
      .row{display:flex;gap:12px;align-items:center;flex-wrap:wrap}
      .btn{margin-top:18px;padding:12px 22px;border:none;border-radius:8px;background:#007bff;color:#fff;cursor:pointer}
    </style>
  </head>
  <body>

  <!-- NAVBAR identica a form_modifica.php -->
  <header>
    <div class="navbar">
      <div class="site-title">Nota Bene</div>
      <div class="profile">
        <p>Utente: <span><?= htmlspecialchars($_SESSION['utente'], ENT_QUOTES, 'UTF-8') ?></span></p>
        <a href="home.php">Home</a> |
        <a href="profile.php">Profilo</a> |
        <a href="logout.php">Logout</a>
      </div>
    </div>
  </header>

  <!-- Struttura contenuti identica: container + form-modifica -->
  <main class="container">
    <div class="form-modifica">
      <!-- barra superiore come nel form di modifica: titolo + indietro -->
      <div class="row" style="justify-content:space-between; align-items:center;">
        <h2 style="margin:0">Crea nuova nota</h2>
        <a href="home.php">↩︎ Indietro</a>
      </div>

      <!-- messaggio meta (come stile nel form modifica) -->
      <div class="meta" style="margin-top:8px;">
        Massimo <strong>280</strong> caratteri per il testo. Puoi rendere la nota pubblica,
        consentire modifiche globali o condividere la scrittura con utenti specifici.
      </div>

      <form method="post" action="newnote.php" style="margin-top:14px;">
        <label for="titolo">Titolo</label>
        <input id="titolo" name="titolo" required maxlength="100" placeholder="Titolo (max 100)">

        <label for="testo">Testo</label>
        <textarea id="testo" name="testo" required maxlength="280" placeholder="Testo (max 280)"></textarea>
        <div style="font-size:.9em;color:#6c757d"><span id="counter">0/280</span></div>

        <div class="row">
          <div style="flex:1 1 320px; min-width:240px">
            <label for="tag">Tag</label>
            <input id="tag" name="tag" maxlength="255" placeholder="es. lavoro, idee">
          </div>
          <div style="flex:1 1 320px; min-width:240px">
            <label for="cartella">Cartella</label>
            <input id="cartella" name="cartella" maxlength="255" placeholder="es. Personali">
          </div>
        </div>

        <!-- gruppo opzioni come nel form modifica -->
        <div class="row" style="margin-top:8px">
          <label style="display:flex;align-items:center;gap:8px;">
            <input type="checkbox" name="pubblica" value="1"> Pubblica (visibile a tutti)
          </label>
          <label style="display:flex;align-items:center;gap:8px;">
            <input type="checkbox" name="allow_edit" value="1"> Consenti modifica a chiunque
          </label>
        </div>

        <label for="share_users" style="margin-top:18px;">Utenti con permesso di scrittura (separati da virgola)</label>
        <input id="share_users" name="share_users" placeholder="mario, lucia, anna">
        <small class="muted">Se spunti “Consenti modifica a chiunque”, questa lista è opzionale.</small>

        <div class="row" style="justify-content:flex-end; margin-top:12px;">
          <button type="submit" class="btn">Crea nota</button>
        </div>
      </form>
    </div>
  </main>

  <footer>
    <small class="muted">&copy; 2025 Nota Bene</small>
  </footer>

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

/* ✅ ora allow_edit è esplicito dalla checkbox (come nel form modifica) */
$allow_edit = !empty($_POST['allow_edit']) ? 1 : 0;

if ($titolo === '' || mb_strlen($titolo,'UTF-8') > 100) { http_response_code(422); exit('Titolo mancante o troppo lungo'); }
if ($testo  === '' || mb_strlen($testo,'UTF-8') > 280) { http_response_code(422); exit('Testo mancante o troppo lungo'); }
if (mb_strlen($tag,'UTF-8') > 255)      $tag = mb_substr($tag,0,255,'UTF-8');
if (mb_strlen($cartella,'UTF-8') > 255) $cartella = mb_substr($cartella,0,255,'UTF-8');

$shareUsers = array_values(array_filter(array_map('trim', $rawList !== '' ? explode(',', $rawList) : [])));

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

  if ($pubblica) {
    // se la nota è pubblica → vai alla home
    header('Location: home.php');
  } else {
    // se è privata o di prova → vai al profilo
    header('Location: profile.php');
  }
  exit;


} catch (Throwable $e) {
  $conn->rollback();
  error_log('newnote error: '.$e->getMessage());
  http_response_code(500);
  exit('Errore durante la creazione della nota');
}
