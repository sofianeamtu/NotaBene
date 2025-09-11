<?php
require 'db_connection.php';
session_start();

if (!isset($_SESSION['utente'])) { header('Location: login.php'); exit; }
$utente = $_SESSION['utente'];

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) { http_response_code(400); echo "ID non valido"; exit; }

$stmt = $conn->prepare("SELECT id, autore, titolo, testo, tag, cartella, pubblica, allow_edit FROM Note WHERE id=?");
$stmt->bind_param('i', $id);
$stmt->execute();
$nota = $stmt->get_result()->fetch_assoc();

if (!$nota) { http_response_code(404); echo "Nota non trovata"; exit; }

$sonoAutore = ($nota['autore'] === $utente);
$puoModificare = $sonoAutore || ($nota['pubblica'] == 1 && $nota['allow_edit'] == 1);
if (!$puoModificare) { http_response_code(403); echo "Non hai i permessi per modificare questa nota."; exit; }

// Ultima revisione (chi e quando)
$revStmt = $conn->prepare("
  SELECT editor, created_at
  FROM NoteRevision
  WHERE note_id=?
  ORDER BY created_at DESC
  LIMIT 1
");
$revStmt->bind_param('i', $nota['id']);
$revStmt->execute();
$lastRev = $revStmt->get_result()->fetch_assoc();

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Modifica nota - Nota Bene</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .form-modifica {
        background-color: #ffffff;
        padding: 35px;
        margin-bottom: 40px;
        border-radius: 12px;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
        transition: box-shadow 0.3s ease;
    }

    .form-modifica:hover {
        box-shadow: 0 12px 30px rgba(0, 0, 0, 0.12);
    }

    .form-modifica h3 {
        color: #2c3e50;
        margin-top: 0;
        font-weight: 700;
        border-bottom: 2px solid #e0e6eb;
        padding-bottom: 15px;
        margin-bottom: 30px;
    }

    .form-modifica form {
        display: flex;
        flex-direction: column;
    }

    .form-modifica label {
        display: block;
        margin-bottom: 10px;
        font-weight: 500;
        color: #4a627a;
        margin-top: 20px;
    }

    .form-modifica input[type="text"],
    .form-modifica textarea {
        width: 100%;
        padding: 14px 18px;
        margin-bottom: 20px;
        border: 1px solid #ced4da;
        border-radius: 8px;
        font-family: 'Roboto', sans-serif;
        font-size: 1.05em;
        box-sizing: border-box;
        resize: vertical;
        min-height: 45px;
        transition: border-color 0.3s ease, box-shadow 0.3s ease;
    }

    .form-modifica textarea {
        min-height: 200px;
        max-height: 400px;
    }

    .form-modifica input:focus,
    .form-modifica textarea:focus {
        border-color: #80bdff;
        outline: none;
        box-shadow: 0 0 0 0.3rem rgba(0, 123, 255, 0.2);
    }

    .form-modifica .checkbox-group {
        display: flex;
        flex-wrap: wrap;
        gap: 25px;
        margin-bottom: 25px;
    }

    .form-modifica .checkbox-label {
        display: flex;
        align-items: center;
        font-weight: normal;
        color: #333;
        font-size: 1em;
        margin-top: 10px;
    }

    .form-modifica .checkbox-label input[type="checkbox"] {
        margin-right: 10px;
        width: 18px;
        height: 18px;
        cursor: pointer;
    }

    .form-modifica button[type="submit"] {
        background-color: #28a745;
        color: white;
        padding: 14px 30px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-size: 1.1em;
        font-weight: 500;
        transition: background-color 0.3s ease, box-shadow 0.3s ease;
        margin-top: 25px;
        box-shadow: 0 3px 6px rgba(40, 167, 69, 0.2);
        align-self: flex-start;
    }

    .form-modifica button[type="submit"]:hover {
        background-color: #218838;
        box-shadow: 0 5px 10px rgba(40, 167, 69, 0.3);
    }

    .form-modifica p {
        color: #6c757d;
        font-style: italic;
        margin-bottom: 20px;
        padding: 10px;
        background-color: #f8f9fa;
        border-radius: 6px;
        border-left: 4px solid #007bff;
    }

    .form-modifica p strong {
        color: #495057;
    }

    @media (max-width: 768px) {
        .form-modifica {
            padding: 25px;
        }
        
        .form-modifica .checkbox-group {
            flex-direction: column;
            gap: 15px;
        }
        
        .form-modifica button[type="submit"] {
            width: 100%;
            text-align: center;
        }
    }
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
    <h3><?= h($nota['titolo']) ?></h3>
    <?php if ($lastRev): ?>
      <p>
        Ultima modifica di <strong><?= h($lastRev['editor']) ?></strong>
        il <?= date('d/m/Y H:i', strtotime($lastRev['created_at'])) ?>
        — <a href="history.php?id=<?= (int)$nota['id'] ?>">cronologia</a>
      </p>
    <?php else: ?>
      <p>Nessuna modifica registrata.</p>
    <?php endif; ?>

    <form method="post" action="update_note.php">
      <input type="hidden" name="id" value="<?= (int)$nota['id'] ?>">

      <label>Titolo</label>
      <input name="titolo" required value="<?= h($nota['titolo']) ?>">

      <label>Testo</label>
      <textarea name="testo" required rows="10"><?= h($nota['testo']) ?></textarea>

      <label>Tag</label>
      <input name="tag" value="<?= h($nota['tag']) ?>">

      <label>Cartella</label>
      <input name="cartella" value="<?= h($nota['cartella']) ?>">

      <?php if ($sonoAutore): ?>
        <div class="checkbox-group">
          <label class="checkbox-label">
            <input type="checkbox" name="pubblica" <?= $nota['pubblica'] ? 'checked' : '' ?>> Pubblica
          </label>
          <label class="checkbox-label">
            <input type="checkbox" name="allow_edit" <?= $nota['allow_edit'] ? 'checked' : '' ?>> Consenti modifica ad altri
          </label>
        </div>
      <?php else: ?>
        <p>Privacy e permessi sono modificabili solo dall'autore.</p>
      <?php endif; ?>

      <button type="submit">Salva Modifiche</button>
    </form>
  </div>
</main>

<footer>
  <small>&copy; 2025 Nota Bene - Università di Bologna</small>
</footer>
</body>
</html>