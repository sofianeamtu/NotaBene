<?php
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');
session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax','secure'=>$secure]);
session_start();

require_once __DIR__.'/db_connection.php';
require_once __DIR__.'/perm_helper.php';

if (empty($_SESSION['utente'])) { header('Location: login.php'); exit; }
$me = $_SESSION['utente'];

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function fetch_all_assoc(mysqli_stmt $stmt): array {
  $res = $stmt->get_result();
  $out = [];
  while ($row = $res->fetch_assoc()) { $out[] = $row; }
  $stmt->close();
  return $out;
}

try {
  $filterFolder = isset($_GET['cartella']) ? trim((string)$_GET['cartella']) : '';

  if ($filterFolder !== '') {
    $sqlMine = "
      SELECT id, titolo, data_ultima_modifica, pubblica, allow_edit, cartella, tag
      FROM Note
      WHERE autore = ? AND cartella = ?
      ORDER BY data_ultima_modifica DESC, id DESC
    ";
    $stmt = $conn->prepare($sqlMine);
    $stmt->bind_param('ss', $me, $filterFolder);
  } else {
    $sqlMine = "
      SELECT id, titolo, data_ultima_modifica, pubblica, allow_edit, cartella, tag
      FROM Note
      WHERE autore = ?
      ORDER BY data_ultima_modifica DESC, id DESC
    ";
    $stmt = $conn->prepare($sqlMine);
    $stmt->bind_param('s', $me);
  }
  $stmt->execute();
  $mine = fetch_all_assoc($stmt);

  $sqlShared = "
    SELECT n.id, n.titolo, n.autore, n.data_ultima_modifica, n.pubblica, s.permesso
    FROM Note n
    JOIN Note_Share s ON s.note_id = n.id
    WHERE s.username = ?
    ORDER BY n.data_ultima_modifica DESC, n.id DESC
  ";
  $stmt = $conn->prepare($sqlShared);
  $stmt->bind_param('s', $me);
  $stmt->execute();
  $shared = fetch_all_assoc($stmt);

  $sqlCopied = "
    SELECT id, titolo, data_ultima_modifica, cartella
    FROM Note
    WHERE autore = ?
      AND (titolo LIKE 'Copia di %' OR (tag IS NOT NULL AND tag LIKE '%copiata%'))
    ORDER BY data_ultima_modifica DESC, id DESC
  ";
  $stmt = $conn->prepare($sqlCopied);
  $stmt->bind_param('s', $me);
  $stmt->execute();
  $copied = fetch_all_assoc($stmt);

  $sqlFolders = "
    SELECT t.cartella, t.cnt
    FROM (
      SELECT COALESCE(NULLIF(TRIM(cartella),''), '(Senza cartella)') AS cartella,
             COUNT(*) AS cnt
      FROM Note
      WHERE autore = ?
      GROUP BY COALESCE(NULLIF(TRIM(cartella),''), '(Senza cartella)')
    ) AS t
    ORDER BY (t.cartella = '(Senza cartella)') ASC, t.cartella ASC
  ";
  $stmt = $conn->prepare($sqlFolders);
  $stmt->bind_param('s', $me);
  $stmt->execute();
  $folders = fetch_all_assoc($stmt);

} catch (Throwable $e) {
  error_log('PROFILE ERROR: '.$e->getMessage().' @ '.$e->getFile().':'.$e->getLine());
  http_response_code(500);
  echo "<pre>Errore interno: ".$e->getMessage()."</pre>";
  exit;
}
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Profilo - Nota Bene</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .wrap{max-width:1100px;margin:24px auto;padding:0 12px}
    .head{display:flex;justify-content:space-between;align-items:center;gap:12px;margin:18px 0}
    .btn{display:inline-block;background:#2563eb;color:#fff;padding:10px 16px;border-radius:8px;text-decoration:none}
    .btn.secondary{background:#f3f4f6;color:#111827;border:1px solid #e5e7eb}
    .btn.danger{background:#ef4444}
    .section{background:#fff;padding:20px;border-radius:12px;box-shadow:0 8px 25px rgba(0,0,0,.08);margin-bottom:22px}
    .item{display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px solid #eee}
    .item:last-child{border-bottom:none}
    .title{font-weight:600}
    .meta{color:#6c757d;font-size:.9em}
    .badges{display:flex;gap:8px;align-items:center}
    .badge{font-size:.8em;padding:2px 8px;border-radius:999px;border:1px solid #ccd;background:#f6f7fb}
    .pill-green{background:#e9f8ee;border-color:#cfead8}
    .pill-yellow{background:#fff6e0;border-color:#ffe2a6}
    .actions a, .actions form{margin-left:8px; display:inline-block}
    .folders{display:flex;gap:10px;flex-wrap:wrap;margin-top:10px}
    .chip{background:#f3f4f6;border:1px solid #e5e7eb;border-radius:999px;padding:6px 10px;font-size:.9em}
    @media (max-width:700px){
      .hide-sm{display:none}
      .item{align-items:flex-start;flex-direction:column;gap:8px}
      .actions{margin-top:6px}
    }
  </style>
</head>
<body>
<header>
  <div class="navbar">
    <div class="site-title">Nota Bene</div>
    <div class="profile">
      <p>Utente: <span><?= h($me) ?></span></p>
      <a href="home.php">Home</a> |
      <a href="logout.php">Logout</a>
    </div>
  </div>
</header>

<main class="wrap">
  <div class="head">
    <h2 style="margin:0">Il tuo profilo</h2>
    <div>
      <a class="btn" href="newnote.php">+ Nuova nota</a>
    </div>
  </div>

  <section class="section">
    <h3 style="margin-top:0">Le mie cartelle</h3>
    <?php if (!$folders): ?>
      <p class="meta">Non hai ancora creato note.</p>
    <?php else: ?>
      <div class="folders">
        <?php $tot=0; foreach ($folders as $f){ $tot+=(int)$f['cnt']; } ?>
        <a class="chip" href="profile.php">Tutte (<?= $tot ?>)</a>
        <?php foreach ($folders as $f):
          $name = $f['cartella']; $cnt=(int)$f['cnt'];
          $param = ($name === '(Senza cartella)') ? '' : $name;
        ?>
          <a class="chip" href="profile.php?cartella=<?= urlencode($param) ?>"><?= h($name) ?> (<?= $cnt ?>)</a>
        <?php endforeach; ?>
      </div>
      <?php if (isset($_GET['cartella'])): ?>
        <p class="meta" style="margin-top:10px">Filtro attivo: <strong><?= h($_GET['cartella']===''?'(Senza cartella)':$_GET['cartella']) ?></strong> — <a href="profile.php">rimuovi filtro</a></p>
      <?php endif; ?>
    <?php endif; ?>
  </section>

  <section class="section">
    <h3 style="margin-top:0">Le mie note (<?= count($mine) ?>)</h3>
    <?php if (!$mine): ?>
      <p class="meta">Nessuna nota trovata<?= $filterFolder!=='' ? ' in questa cartella' : '' ?>.</p>
    <?php else: ?>
      <?php foreach ($mine as $n): ?>
        <div class="item">
          <div>
            <div class="title">
              <a href="form_modifica.php?id=<?= (int)$n['id'] ?>"><?= h($n['titolo']) ?></a>
            </div>
            <div class="meta hide-sm">
              Aggiornata il <?= date('d/m/Y H:i', strtotime($n['data_ultima_modifica'])) ?>
              <?php if (!empty($n['cartella'])): ?> · Cartella: <?= h($n['cartella']) ?><?php endif; ?>
              <?php if (!empty($n['tag'])): ?> · Tag: <?= h($n['tag']) ?><?php endif; ?>
            </div>
          </div>
          <div class="badges">
            <?php if (!empty($n['pubblica'])): ?><span class="badge pill-green">pubblica</span><?php endif; ?>
            <?php if (!empty($n['allow_edit'])): ?><span class="badge pill-yellow">modifica aperta</span><?php endif; ?>
            <span class="actions">
              <a class="btn secondary" href="form_modifica.php?id=<?= (int)$n['id'] ?>">Modifica</a>
              <form action="delete_note.php" method="POST" onsubmit="return confirm('Eliminare questa nota?');" style="margin:0;display:inline-block">
                <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
                <button type="submit" class="btn danger">Elimina</button>
              </form>
              <a class="btn secondary" href="history.php?id=<?= (int)$n['id'] ?>">Cronologia</a>
            </span>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>

  <section class="section">
    <h3 style="margin-top:0">Le mie note copiate (<?= count($copied) ?>)</h3>
    <?php if (!$copied): ?>
      <p class="meta">Non hai note copiate.</p>
    <?php else: ?>
      <?php foreach ($copied as $c): ?>
        <div class="item">
          <div>
            <div class="title">
              <a href="form_modifica.php?id=<?= (int)$c['id'] ?>"><?= h($c['titolo']) ?></a>
            </div>
            <div class="meta">
              Aggiornata il <?= date('d/m/Y H:i', strtotime($c['data_ultima_modifica'])) ?>
              <?php if (!empty($c['cartella'])): ?> · Cartella: <?= h($c['cartella']) ?><?php endif; ?>
            </div>
          </div>
          <div class="actions">
            <a class="btn secondary" href="form_modifica.php?id=<?= (int)$c['id'] ?>">Apri</a>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>

  <section class="section">
    <h3 style="margin-top:0">Condivise con me (<?= count($shared) ?>)</h3>
    <?php if (!$shared): ?>
      <p class="meta">Nessuna nota condivisa con te.</p>
    <?php else: ?>
      <?php foreach ($shared as $s): ?>
        <div class="item">
          <div>
            <div class="title">
              <a href="form_modifica.php?id=<?= (int)$s['id'] ?>"><?= h($s['titolo']) ?></a>
            </div>
            <div class="meta">
              di <?= h($s['autore']) ?> ·
              aggiornata il <?= date('d/m/Y H:i', strtotime($s['data_ultima_modifica'])) ?>
              <?php if (!empty($s['pubblica'])): ?> · pubblica<?php endif; ?>
            </div>
          </div>
          <div class="badges">
            <span class="badge"><?= h($s['permesso']) ?></span>
            <span class="actions">
              <a class="btn secondary" href="form_modifica.php?id=<?= (int)$s['id'] ?>">Apri</a>
              <a class="btn secondary" href="history.php?id=<?= (int)$s['id'] ?>">Cronologia</a>
            </span>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>
</main>

<footer>
  <small>&copy; <?= date('Y') ?> Nota Bene</small>
</footer>
</body>
</html>
