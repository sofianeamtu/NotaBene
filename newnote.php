<?php
session_start();
include 'db_connection.php';

if (!isset($_SESSION['utente'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $autore = $_SESSION['utente'];
    $titolo = $_POST['titolo'] ?? '';
    $testo = $_POST['testo'] ?? '';
    $tag = $_POST['tag'] ?? '';
    $cartella = $_POST['cartella'] ?? '';
    $pubblica = isset($_POST['pubblica']) ? 1 : 0;
    $allow_edit = isset($_POST['allow_edit']) ? 1 : 0;

    $stmt = $conn->prepare("INSERT INTO Note (autore, titolo, testo, tag, cartella, pubblica, allow_edit) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssii", $autore, $titolo, $testo, $tag, $cartella, $pubblica, $allow_edit);
    
    if ($stmt->execute()) {
        if ($pubblica) {
            header("Location: home.php?msg=" . urlencode("Nota pubblica creata con successo!"));
        } else {
            header("Location: profile.php?msg=" . urlencode("Nota privata creata con successo!"));
        }
        exit();
    } else {
        $messaggio = "Errore durante il salvataggio.";
    }
    $stmt->close();
}

$messaggio = "";
?>

<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>Nuova Nota - Nota Bene</title>
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

    .form-modifica h1 {
        color: #2c3e50;
        margin-top: 0;
        font-weight: 700;
        border-bottom: 2px solid #e0e6eb;
        padding-bottom: 15px;
        margin-bottom: 30px;
        text-align: center;
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
      <p>Utente: <span><?= htmlspecialchars($_SESSION['utente']) ?></span></p>
      <a href="home.php">Home</a> |
      <a href="logout.php">Logout</a>
    </div>
  </div>
</header>

<main class="container">
  <div class="form-modifica">
    <h1>Crea una nuova nota</h1>

    <?php if ($messaggio): ?>
      <p id="noteMessage"><?= htmlspecialchars($messaggio) ?></p>
    <?php endif; ?>

    <form method="post">
      <label>Titolo della nota</label>
      <input type="text" name="titolo" placeholder="Titolo della nota" required>

      <label>Testo della nota</label>
      <textarea name="testo" rows="10" placeholder="Scrivi la tua nota qui..." required></textarea>

      <label>Tag</label>
      <input type="text" name="tag" placeholder="Tag (es. scuola, personale)">

      <label>Cartella</label>
      <input type="text" name="cartella" placeholder="Cartella (es. lavoro, università)">

      <div class="checkbox-group">
        <label class="checkbox-label">
          <input type="checkbox" name="pubblica"> Rendi pubblica
        </label>
        <label class="checkbox-label">
          <input type="checkbox" name="allow_edit"> Permetti modifica ad altri
        </label>
      </div>

      <button type="submit">Salva Nota</button>
    </form>
  </div>
</main>

<footer>
  <small>&copy; 2025 Nota Bene - Università di Bologna</small>
</footer>
</body>
</html>