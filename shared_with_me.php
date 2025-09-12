<?php
require_once __DIR__.'/db_connection.php';
require_once __DIR__.'/perm_helper.php';

if (empty($_SESSION['utente'])) { header('Location: login.php'); exit; }
$me = $_SESSION['utente'];

$sql = "
SELECT n.id, n.titolo, n.autore, n.data_ultima_modifica, s.permesso
FROM Note n
JOIN Note_Share s ON s.note_id = n.id
WHERE s.username = ?
ORDER BY n.data_ultima_modifica DESC, n.id DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $me);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Condivise con me - Nota Bene</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .card{background:#fff;padding:20px;border-radius:12px;box-shadow:0 8px 25px rgba(0,0,0,.08);max-width:900px;margin:24px auto}
    .item{display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px solid #eee}
    .item:last-child{border-bottom:none}
    .meta{color:#6c757d;font-size:.9em}
    .badge{padding:2px 8px;border-radius:999px;background:#eef;border:1px solid #ccd}
  </style>
</head>
<body>
<header>
  <div class="navbar">
    <div class="site-title">Nota Bene</div>
    <div class="profile">
      <p>Utente: <span><?= h($me) ?></span></p>
      <a href="home.php">Home</a> |
      <a href="profile.php">Profilo</a> |
      <a href="shared_with_me.php"><strong>Condivise con me</strong></a> |
      <a href="logout.php">Logout</a>
    </div>
  </div>
</header>

<main class="card">
  <h2>Note condivise con te</h2>
  <?php if (!$rows): ?>
    <p class="meta">Nessuna nota condivisa.</p>
  <?php else: ?>
    <?php foreach ($rows as $r): ?>
      <div class="item">
        <div>
          <a href="form_modifica.php?id=<?= (int)$r['id'] ?>"><?= h($r['titolo']) ?></a>
          <div class="meta">di <?= h($r['autore']) ?> — aggiornata il <?= date('d/m/Y H:i', strtotime($r['data_ultima_modifica'])) ?></div>
        </div>
        <div class="meta"><span class="badge"><?= h($r['permesso']) ?></span></div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</main>
</body>
</html>
