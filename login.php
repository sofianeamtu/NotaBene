<?php
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on');
session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax','secure'=>$secure]);
session_start();

require_once __DIR__.'/db_connection.php';

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

// se già loggato → profilo
if (!empty($_SESSION['utente'])) {
  header('Location: profile.php'); exit;
}

// flash (da register.php)
$flash_success = $_SESSION['flash_success'] ?? '';
$flash_error   = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$username = '';
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $username = trim($_POST['username'] ?? '');
  $password = $_POST['password'] ?? '';
  if ($username === '' || $password === '') {
    $flash_error = 'Inserisci username e password';
  } else {
    $stmt = $conn->prepare('SELECT password FROM Utente WHERE username = ?');
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();

    if ($row) {
      $hashed = $row['password'];
      $ok = password_verify($password, $hashed);
      // fallback se avete password in chiaro:
      // $ok = $ok || ($password === $hashed);

      if ($ok) {
        $_SESSION['utente'] = $username;
        header('Location: profile.php'); exit;
      }
    }
    $flash_error = 'Credenziali non valide';
  }
}
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Accedi – Nota Bene</title>
  <link rel="stylesheet" href="style.css">
  <style>
    body{background:#f8fafc;margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,'Helvetica Neue',Arial}
    .wrap{max-width:460px;margin:60px auto;padding:0 16px}
    .card{background:#fff;padding:24px 22px;border-radius:14px;box-shadow:0 10px 30px rgba(0,0,0,.08)}
    h1{margin:0 0 16px 0;font-size:1.5rem}
    .meta{color:#6b7280;font-size:.95em;margin-bottom:18px}
    .field{margin-bottom:14px}
    label{display:block;font-weight:600;margin-bottom:6px}
    input[type="text"],input[type="password"]{width:100%;padding:10px 12px;border:1px solid #e5e7eb;border-radius:10px;font-size:1rem}
    .btn{display:inline-block;background:#2563eb;color:#fff;padding:10px 16px;border-radius:10px;text-decoration:none;border:none;cursor:pointer}
    .btn.secondary{background:#f3f4f6;color:#111827;border:1px solid #e5e7eb}
    .row{display:flex;justify-content:space-between;align-items:center;margin-top:12px}
    .alerts{margin-bottom:12px}
    .alert{padding:10px 12px;border-radius:10px;margin-bottom:8px;font-size:.95em}
    .alert.success{background:#e9f8ee;border:1px solid #cfead8;color:#14532d}
    .alert.error{background:#fde8e8;border:1px solid #f8b4b4;color:#7f1d1d}
  </style>
</head>
<body>
<header>
  <div class="navbar">
    <div class="site-title">Nota Bene</div>
  </div>
</header>

<main class="wrap">
  <div class="card">
    <h1>Accedi</h1>
    <p class="meta">Inserisci le tue credenziali per entrare.</p>

    <div class="alerts">
      <?php if ($flash_success): ?><div class="alert success"><?= h($flash_success) ?></div><?php endif; ?>
      <?php if ($flash_error):   ?><div class="alert error"><?= h($flash_error) ?></div><?php endif; ?>
    </div>

    <form method="post" action="login.php" autocomplete="on">
      <div class="field">
        <label for="username">Username</label>
        <input id="username" name="username" type="text" required maxlength="100" value="<?= h($username) ?>">
      </div>
      <div class="field">
        <label for="password">Password</label>
        <input id="password" name="password" type="password" required>
      </div>
      <div class="row">
        <button class="btn" type="submit">Entra</button>
        <a class="btn secondary" href="register.php">Crea un account</a>
      </div>
    </form>
  </div>
</main>
</body>
</html>
