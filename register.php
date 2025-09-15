<?php
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on');
session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax','secure'=>$secure]);
session_start();

require_once __DIR__.'/db_connection.php';

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

$username = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD']==='POST') {
  $username = trim($_POST['username'] ?? '');
  $password = $_POST['password'] ?? '';
  $confirm  = $_POST['confirm']  ?? '';

  if ($username === '' || mb_strlen($username)>100) {
    $errors[] = 'Username obbligatorio (max 100 caratteri).';
  }
  if ($password === '' || mb_strlen($password)<6) {
    $errors[] = 'Password obbligatoria (minimo 6 caratteri).';
  }
  if ($password !== $confirm) {
    $errors[] = 'Le password non coincidono.';
  }

  if (!$errors) {
    $stmt = $conn->prepare('SELECT 1 FROM Utente WHERE username = ?');
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $exists = $stmt->get_result()->fetch_row();
    $stmt->close();

    if ($exists) {
      $errors[] = 'Username già in uso.';
    } else {
      $hash = password_hash($password, PASSWORD_BCRYPT);
      $ins = $conn->prepare('INSERT INTO Utente(username,password) VALUES (?,?)');
      $ins->bind_param('ss', $username, $hash);
      $ok = $ins->execute();
      $ins->close();

      if ($ok) {
        $_SESSION['flash_success'] = 'Registrazione completata! Ora effettua il login.';
        header('Location: login.php'); exit;
      } else {
        $errors[] = 'Errore durante la registrazione. Riprova.';
      }
    }
  }
}
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Registrati – Nota Bene</title>
  <link rel="stylesheet" href="style.css">
  <style>
    body{background:#f8fafc;margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,'Helvetica Neue',Arial}
    .wrap{max-width:560px;margin:60px auto;padding:0 16px}
    .card{background:#fff;padding:24px 22px;border-radius:14px;box-shadow:0 10px 30px rgba(0,0,0,.08)}
    h1{margin:0 0 16px 0;font-size:1.5rem}
    .meta{color:#6b7280;font-size:.95em;margin-bottom:18px}
    .grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
    .field{margin-bottom:14px}
    label{display:block;font-weight:600;margin-bottom:6px}
    input[type="text"],input[type="password"]{width:100%;padding:10px 12px;border:1px solid #e5e7eb;border-radius:10px;font-size:1rem}
    .btn{display:inline-block;background:#2563eb;color:#fff;padding:10px 16px;border-radius:10px;text-decoration:none;border:none;cursor:pointer}
    .btn.secondary{background:#f3f4f6;color:#111827;border:1px solid #e5e7eb}
    .row{display:flex;justify-content:space-between;align-items:center;margin-top:12px}
    .alerts{margin-bottom:12px}
    .alert{padding:10px 12px;border-radius:10px;margin-bottom:8px;font-size:.95em}
    .alert.error{background:#fde8e8;border:1px solid #f8b4b4;color:#7f1d1d}
    @media (max-width:640px){ .grid{grid-template-columns:1fr} }
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
    <h1>Crea un account</h1>
    <p class="meta">Compila i campi qui sotto. Dopo la registrazione verrai reindirizzato al login.</p>

    <?php if ($errors): ?>
      <div class="alerts">
        <?php foreach ($errors as $e): ?>
          <div class="alert error"><?= h($e) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="post" action="register.php" autocomplete="off">
      <div class="field">
        <label for="username">Username</label>
        <input id="username" name="username" type="text" required maxlength="100" value="<?= h($username) ?>">
      </div>

      <div class="grid">
        <div class="field">
          <label for="password">Password</label>
          <input id="password" name="password" type="password" required minlength="6">
        </div>
        <div class="field">
          <label for="confirm">Conferma password</label>
          <input id="confirm" name="confirm" type="password" required minlength="6">
        </div>
      </div>

      <div class="row">
        <a class="btn secondary" href="login.php">Torna al login</a>
        <button class="btn" type="submit">Registrati</button>
      </div>
    </form>
  </div>
</main>
</body>
</html>
