<?php
require_once __DIR__.'/db_connection.php';
require_once __DIR__.'/perm_helper.php';

$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');
session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax','secure'=>$secure]);
session_start();

if (empty($_SESSION['utente'])) { header('Location: login.php'); exit; }
$utente = $_SESSION['utente'];

$noteId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($noteId <= 0) { http_response_code(400); exit('ID non valido'); }

$stmt = $conn->prepare("
  SELECT id, autore, titolo, testo, tag, cartella, pubblica, allow_edit, data_ultima_modifica
  FROM Note
  WHERE id = ?
");
$stmt->bind_param('i', $noteId);
$stmt->execute();
$nota = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$nota) { http_response_code(404); exit('Nota non trovata'); }

$puoLeggere  = user_can_read($conn, $noteId, $utente);
$puoScrivere = user_can_write($conn, $noteId, $utente);
if (!$puoLeggere) { http_response_code(403); exit('Non puoi vedere questa nota'); }

$readOnly   = !$puoScrivere;
$sonoAutore = ($nota['autore'] === $utente);

$revStmt = $conn->prepare("
  SELECT editor, created_at
  FROM NoteRevision
  WHERE note_id=?
  ORDER BY created_at DESC, id DESC
  LIMIT 1
");
$revStmt->bind_param('i', $nota['id']);
$revStmt->execute();
$lastRev = $revStmt->get_result()->fetch_assoc();
$revStmt->close();

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Modifica nota - Nota Bene</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .form-modifica{background:#fff;padding:35px;margin:24px auto;border-radius:12px;box-shadow:0 8px 25px rgba(0,0,0,.08);max-width:900px}
    .form-modifica h3{color:#2c3e50;margin-top:0;font-weight:700;border-bottom:2px solid #e0e6eb;padding-bottom:15px;margin-bottom:24px}
    label{display:block;margin-bottom:10px;color:#4a627a;margin-top:16px}
    input,textarea{width:100%;padding:12px 16px;border:1px solid #ced4da;border-radius:8px;font-size:1.05em}
    textarea{min-height:200px;max-height:400px}
    .meta{color:#6c757d;font-style:italic;margin-bottom:14px;padding:10px;background:#f8f9fa;border-radius:6px;border-left:4px solid #007bff}
    .warn{border-left-color:#dc3545}
    .row{display:flex;gap:20px;flex-wrap:wrap}
    .btn-primary{background:#28a745;color:#fff;padding:12px 24px;border:none;border-radius:8px;cursor:pointer;margin-top:18px}
    .actions a{margin-right:12px}
    ul{padding-left:18px}
  </style>
</head>
<body>
<header>
  <div class="navbar">
    <div class="site-title">Nota Bene</div>
    <div class="profile">
      <p>Utente: <span><?= h($utente) ?></span></p>
      <a href="home.php">Home</a> |
      <a href="profile.php">Profilo</a> |
      <a href="logout.php">Logout</a>
    </div>
  </div>
</header>

<main class="container">
  <div class="form-modifica">
    <div class="actions" style="margin-bottom:10px;">
      <a href="history.php?id=<?= (int)$nota['id'] ?>">↩︎ Cronologia</a>
      <a href="<?= $sonoAutore ? 'profile.php' : 'home.php' ?>">↩︎ Indietro</a>
    </div>

    <h3><?= h($nota['titolo']) ?></h3>

    <?php if ($lastRev): ?>
      <p class="meta">Ultima modifica di <strong><?= h($lastRev['editor']) ?></strong> il <?= date('d/m/Y H:i', strtotime($lastRev['created_at'])) ?></p>
    <?php else: ?>
      <p class="meta">Nessuna modifica registrata.</p>
    <?php endif; ?>

    <?php if ($readOnly): ?>
      <div class="meta warn">
        Non hai i permessi per modificare questa nota. Stai visualizzando in sola lettura.
      </div>
    <?php endif; ?>

    <form method="post" action="update_note.php">
      <input type="hidden" name="id" value="<?= (int)$nota['id'] ?>">

      <label for="titolo">Titolo</label>
      <input id="titolo" name="titolo" required maxlength="100" value="<?= h($nota['titolo']) ?>" <?= $readOnly?'disabled':'' ?>>

      <label for="testo">Testo</label>
      <textarea id="testo" name="testo" required maxlength="280" rows="10" <?= $readOnly?'disabled':'' ?>><?= h($nota['testo']) ?></textarea>
      <div style="font-size:.9em;color:#6c757d"><span id="counter">0/280</span></div>

      <label for="tag">Tag</label>
      <input id="tag" name="tag" maxlength="255" value="<?= h($nota['tag']) ?>" <?= $readOnly?'disabled':'' ?>>

      <label for="cartella">Cartella</label>
      <input id="cartella" name="cartella" maxlength="255" value="<?= h($nota['cartella']) ?>" <?= $readOnly?'disabled':'' ?>>

      <?php if ($sonoAutore): ?>
        <div class="row">
          <label style="display:flex;align-items:center;gap:8px;">
            <input type="checkbox" name="pubblica" <?= $nota['pubblica'] ? 'checked' : '' ?> <?= $readOnly?'disabled':'' ?>> Pubblica
          </label>
          <label style="display:flex;align-items:center;gap:8px;">
            <input type="checkbox" name="allow_edit" <?= $nota['allow_edit'] ? 'checked' : '' ?> <?= $readOnly?'disabled':'' ?>> Consenti modifica ad altri (globale)
          </label>
        </div>
      <?php endif; ?>

      <?php if (!$readOnly): ?>
        <button type="submit" class="btn-primary">Salva modifiche</button>
      <?php endif; ?>
    </form>

    <?php if ($sonoAutore): ?>
      <hr style="margin:26px 0;">
      <h3>Condividi con un utente</h3>
      <form action="share_save.php" method="POST" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
        <input type="hidden" name="note_id" value="<?= (int)$nota['id'] ?>">
        <label>Username:
          <input name="username" required placeholder="es. mario.rossi">
        </label>
        <label style="display:flex;align-items:center;gap:8px;">
          <input type="checkbox" name="write" value="1"> Permesso di scrittura
        </label>
        <button type="submit" class="btn-primary">Condividi</button>
      </form>

      <?php
      $s = $conn->prepare("SELECT username, permesso FROM Note_Share WHERE note_id=? ORDER BY username");
      $s->bind_param('i', $nota['id']);
      $s->execute();
      $shares = $s->get_result()->fetch_all(MYSQLI_ASSOC);
      $s->close();
      ?>
      <div style="margin-top:12px">
        <h4>Condivisa con:</h4>
        <?php if (!$shares): ?>
          <p>Nessuno</p>
        <?php else: ?>
          <ul>
            <?php foreach ($shares as $row): ?>
              <li>
                <?= h($row['username']) ?> — <?= h($row['permesso']) ?>
                <form action="share_remove.php" method="POST" style="display:inline" onsubmit="return confirm('Rimuovere accesso a <?= h($row['username']) ?>?');">
                  <input type="hidden" name="note_id" value="<?= (int)$nota['id'] ?>">
                  <input type="hidden" name="username" value="<?= h($row['username']) ?>">
                  <button type="submit" style="margin-left:8px">Rimuovi</button>
                </form>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    <?php endif; ?>

  </div>
</main>

<footer><small>&copy; <?= date('Y') ?> Nota Bene</small></footer>
<script>
  (function(){var ta=document.getElementById('testo');var c=document.getElementById('counter');function u(){c.textContent=(ta.value.length||0)+'/280';}ta.addEventListener('input',u);u();})();
</script>
</body>
</html>
