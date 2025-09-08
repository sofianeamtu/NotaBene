<?php
session_start();
include 'db_connection.php';

if (!isset($_SESSION['utente'])) {
    header("Location: login.php");
    exit();
}

// Controllo della ricerca
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$searchDate = isset($_GET['date']) ? $_GET['date'] : '';
$filterTag = isset($_GET['tag']) ? trim($_GET['tag']) : '';
$filterCartella = isset($_GET['cartella']) ? trim($_GET['cartella']) : '';

// Preparazione della query di ricerca
$query = "SELECT * FROM Note WHERE pubblica = 1";
$conditions = [];
$params = [];
$types = "";

// Ricerca testuale
if ($searchTerm) {
    $searchWildcard = "%" . $searchTerm . "%";
    $conditions[] = "(titolo LIKE ? OR testo LIKE ? OR tag LIKE ? OR cartella LIKE ?)";
    $params = array_merge($params, [$searchWildcard, $searchWildcard, $searchWildcard, $searchWildcard]);
    $types .= "ssss";
}

// Filtro per tag
if ($filterTag) {
    $conditions[] = "tag = ?";
    $params[] = $filterTag;
    $types .= "s";
}

// Filtro per cartella
if ($filterCartella) {
    $conditions[] = "cartella = ?";
    $params[] = $filterCartella;
    $types .= "s";
}

// Filtro per data
if ($searchDate) {
    $conditions[] = "(data_creazione BETWEEN ? AND ? OR data_ultima_modifica BETWEEN ? AND ?)";
    $startDate = $searchDate . " 00:00:00";
    $endDate = $searchDate . " 23:59:59";
    $params = array_merge($params, [$startDate, $endDate, $startDate, $endDate]);
    $types .= "ssss";
}

// Aggiungi condizioni alla query
if (count($conditions) > 0) {
    $query .= " AND " . implode(" AND ", $conditions);
}

// Prepara ed esegui
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$notePubbliche = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Sidebar: tag e cartelle
$queryTags = "SELECT DISTINCT tag FROM Note WHERE pubblica = 1 AND tag IS NOT NULL AND tag != ''";
$resultTags = $conn->query($queryTags);
$tags = $resultTags->fetch_all(MYSQLI_ASSOC);

$queryCartelle = "SELECT DISTINCT cartella FROM Note WHERE pubblica = 1 AND cartella IS NOT NULL AND cartella != ''";
$resultCartelle = $conn->query($queryCartelle);
$cartelle = $resultCartelle->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>Home - Nota Bene</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<header>
  <div class="navbar">
    <div class="site-title">Nota Bene</div>
    <div class="profile">
      <p>Utente: <span><?= htmlspecialchars($_SESSION['utente']) ?></span></p>
      <a href="profile.php">Profilo</a> |
      <a href="logout.php">Logout</a>
    </div>
  </div>
</header>

<div class="container">
  <section class="welcome-section">
    <h1>Benvenuto su Nota Bene</h1>
  </section>

  <section class="notes-section">
    <?php if (isset($_GET['msg'])): ?>
      <div id="noteMessage" style="margin-bottom: 20px;">
        <?= htmlspecialchars($_GET['msg']) ?>
      </div>
    <?php endif; ?>
    
    <div style="display:flex; justify-content: space-between; align-items: center;">
      <h2>Note pubbliche</h2>
      <a href="newnote.php" class="btn-new-note">+ Nuova Nota</a>
    </div>

    <!-- Modulo di ricerca -->
    <form method="GET" class="search-form">
      <input type="text" name="search" placeholder="Cerca per titolo, testo, tag o cartella..." value="<?= htmlspecialchars($searchTerm) ?>" />
      <input type="date" name="date" value="<?= htmlspecialchars($searchDate) ?>" />
      <button type="submit">Cerca</button>
    </form>

    <div class="notes-content">
      <aside class="tags-sidebar">
        <h3><a href="home.php">Tutte le note</a></h3>

        <h3>Tag</h3>
        <ul class="tag-list">
          <?php if (count($tags) > 0): ?>
            <?php foreach ($tags as $t): ?>
              <li><a href="home.php?tag=<?= urlencode($t['tag']) ?>"><?= htmlspecialchars($t['tag']) ?></a></li>
            <?php endforeach; ?>
          <?php else: ?>
            <li>Nessun tag disponibile</li>
          <?php endif; ?>
        </ul>

        <h3>Cartelle</h3>
        <ul class="tag-list">
          <?php if (count($cartelle) > 0): ?>
            <?php foreach ($cartelle as $c): ?>
              <li><a href="home.php?cartella=<?= urlencode($c['cartella']) ?>"><?= htmlspecialchars($c['cartella']) ?></a></li>
            <?php endforeach; ?>
          <?php else: ?>
            <li>Nessuna cartella disponibile</li>
          <?php endif; ?>
        </ul>
      </aside>

      <div class="notes-list">
        <?php if (count($notePubbliche) > 0): ?>
          <?php foreach ($notePubbliche as $nota): ?>
            <div class="note-card">
              <h3><?= htmlspecialchars($nota['titolo']) ?></h3>
              <p><?= nl2br(htmlspecialchars($nota['testo'])) ?></p>
              <p><strong>Autore:</strong> <?= htmlspecialchars($nota['autore']) ?></p>
              <p><strong>Tag:</strong> <?= htmlspecialchars($nota['tag']) ?></p>
              <p><strong>Cartella:</strong> <?= htmlspecialchars($nota['cartella']) ?></p>
              <p><strong>Data di creazione:</strong> <?= htmlspecialchars($nota['data_creazione']) ?></p>
              <p><strong>Ultima modifica:</strong> <?= htmlspecialchars($nota['data_ultima_modifica']) ?></p>
              <a href="copy_note.php?id=<?= $nota['id'] ?>" class="btn-copy">Copia</a>
              <?php if ($nota['allow_edit']): ?>
                <a href="form_modifica.php?id=<?= $nota['id'] ?>" class="btn-edit">Modifica</a>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <p>Nessun risultato trovato.</p>
        <?php endif; ?>
      </div>
    </div>
  </section>
</div>

<footer>
  <small>&copy; 2025 Nota Bene - Università di Bologna</small>
</footer>
</body>
</html>
