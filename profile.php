<?php
session_start();
include 'db_connection.php';

if (!isset($_SESSION['utente'])) {
    header("Location: login.php");
    exit();
}

$utente = $_SESSION['utente'];

// Recupero cartelle uniche
$stmt = $conn->prepare("SELECT DISTINCT cartella FROM Note WHERE autore = ?");
$stmt->bind_param("s", $utente);
$stmt->execute();
$result = $stmt->get_result();
$cartelle = [];
while ($row = $result->fetch_assoc()) {
    $c = $row['cartella'] ?: 'Senza Cartella';
    if (!in_array($c, $cartelle)) {
        $cartelle[] = $c;
    }
}
$stmt->close();

// Controlla se ci sono note copiate
$stmt_copiate = $conn->prepare("SELECT COUNT(*) as count FROM Note WHERE cartella = 'Note Copiate'");
$stmt_copiate->execute();
$result_copiate = $stmt_copiate->get_result();
$count_copiate = $result_copiate->fetch_assoc()['count'];
$stmt_copiate->close();

// Aggiungi "Note Copiate" se ci sono note copiate
if ($count_copiate > 0 && !in_array('Note Copiate', $cartelle)) {
    $cartelle[] = 'Note Copiate';
}

// Cartella selezionata
$cartellaSelezionata = $_GET['cartella'] ?? 'Tutte';

// Recupero note filtrate
if ($cartellaSelezionata === 'Tutte') {
    $stmt = $conn->prepare("SELECT * FROM Note WHERE autore = ? OR cartella = 'Note Copiate'");
    $stmt->bind_param("s", $utente);
    $stmt->execute();
} elseif ($cartellaSelezionata === 'Senza Cartella') {
    $stmt = $conn->prepare("SELECT * FROM Note WHERE autore = ? AND (cartella IS NULL OR cartella = '')");
    $stmt->bind_param("s", $utente);
    $stmt->execute();
} elseif ($cartellaSelezionata === 'Note Copiate') {
    $stmt = $conn->prepare("SELECT * FROM Note WHERE cartella = 'Note Copiate'");
    $stmt->execute();
} else {
    $stmt = $conn->prepare("SELECT * FROM Note WHERE autore = ? AND cartella = ?");
    $stmt->bind_param("ss", $utente, $cartellaSelezionata);
    $stmt->execute();
}
$noteUtente = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>Profilo Utente - Nota Bene</title>
  <link rel="stylesheet" href="style.css">
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
</head>
<body>
<header>
  <div class="navbar">
    <div class="site-title">Nota Bene</div>
    <div class="profile">
      <p>Utente: <span><?= htmlspecialchars($utente) ?></span></p>
      <a href="home.php">Home</a> |
      <a href="logout.php">Logout</a>
    </div>
  </div>
</header>

<main class="container profile-container">
  <aside class="tags-sidebar">
    <h3>Cartelle</h3>
    <ul>
      <li><a href="profile.php?cartella=Tutte">Tutte</a></li>
      <?php foreach ($cartelle as $c): ?>
        <li><a href="profile.php?cartella=<?= urlencode($c) ?>"><?= htmlspecialchars($c) ?></a></li>
      <?php endforeach; ?>
    </ul>
  </aside>

  <section class="notes-area">
    <?php if (isset($_GET['msg'])): ?>
      <div id="noteMessage" style="margin-bottom: 20px;">
        <?= htmlspecialchars($_GET['msg']) ?>
      </div>
    <?php endif; ?>
    
    <div style="display:flex; justify-content: space-between; align-items: center;">
      <h1>Note: <?= htmlspecialchars($cartellaSelezionata) ?></h1>
      <a href="newnote.php" class="btn-new-note">+ Nuova Nota</a>
    </div>
    <?php if (count($noteUtente) === 0): ?>
      <p>Nessuna nota presente in questa cartella.</p>
    <?php else: ?>
      <?php foreach ($noteUtente as $nota): ?>
        <div class="note-card">
          <p><strong>Titolo:</strong> <?= htmlspecialchars($nota['titolo']) ?></p>
          <p><?= nl2br(htmlspecialchars($nota['testo'])) ?></p>
          <p><strong>Autore:</strong> <?= htmlspecialchars($nota['autore']) ?></p>
          <p><strong>Tag:</strong> <?= htmlspecialchars($nota['tag']) ?></p>
          <p><strong>Cartella:</strong> <?= $nota['cartella'] ?: 'Senza Cartella' ?></p>
          <p><strong>Pubblica:</strong> <?= $nota['pubblica'] ? 'Sì' : 'No' ?></p>
          <p><strong>Modificabile da altri:</strong> <?= $nota['allow_edit'] ? 'Sì' : 'No' ?></p>

          <?php if ($nota['autore'] === $utente && $nota['cartella'] !== 'Note Copiate'): ?>
            <!-- Sezione permessi - solo per l'autore delle note originali -->
            <div class="permission-section">
              <h3>Gestione Permessi</h3>
              <form method="post" class="update-permissions" data-id="<?= $nota['id'] ?>">
                <label for="scrittura_permessi_<?= $nota['id'] ?>">Permessi di scrittura:</label>
                <input type="checkbox" name="scrittura_permessi" id="scrittura_permessi_<?= $nota['id'] ?>" <?= $nota['allow_edit'] ? 'checked' : '' ?>> Consentire scrittura<br><br>

                <button type="submit" class="permission-btn">Aggiorna Permessi</button>
              </form>
            </div>

            <!-- Pulsante Modifica - solo per l'autore delle note originali -->
            <a href="form_modifica.php?id=<?= $nota['id'] ?>" class="btn-edit">Modifica</a>

            <!-- Pulsante Elimina - solo per l'autore delle note originali -->
            <form method="post" action="delete_note.php" style="display:inline;">
              <input type="hidden" name="note_id" value="<?= $nota['id'] ?>">
              <button type="submit" class="permission-cancel">Elimina</button>
            </form>

            <!-- Pulsante Rendi Pubblica/Privata - solo per l'autore delle note originali -->
            <form method="post" action="toggle_privacy.php" style="display:inline;">
              <input type="hidden" name="note_id" value="<?= $nota['id'] ?>">
              <input type="hidden" name="new_status" value="<?= $nota['pubblica'] ? 0 : 1 ?>">
              <button type="submit" class="permission-btn"><?= $nota['pubblica'] ? 'Rendi Privata' : 'Rendi Pubblica' ?></button>
            </form>
          <?php elseif ($nota['cartella'] === 'Note Copiate'): ?>
            <!-- Per le note copiate, mostra solo il pulsante modifica se consentito dalla nota originale -->
            <?php if ($nota['pubblica'] == 1 && $nota['allow_edit'] == 1): ?>
              <a href="form_modifica.php?id=<?= $nota['id'] ?>" class="btn-edit">Modifica</a>
            <?php endif; ?>
            <p style="color: #6c757d; font-style: italic; margin-top: 10px;">
              Nota copiata - puoi solo visualizzarla
            </p>
          <?php else: ?>
            <!-- Per le altre note (se l'utente è autore ma non sono note copiate) -->
            <a href="form_modifica.php?id=<?= $nota['id'] ?>" class="btn-edit">Modifica</a>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>
</main>

<footer>
  <small>&copy; 2025 Nota Bene - Università di Bologna</small>
</footer>

<script>
  $(document).ready(function() {
    // Gestione aggiornamento dei permessi via AJAX
    $('.update-permissions').on('submit', function(event) {
      event.preventDefault();

      var noteId = $(this).data('id');
      var scritturaPermessi = $(this).find('input[name="scrittura_permessi"]').is(':checked') ? 1 : 0;

      $.ajax({
        url: 'update_permission.php',
        method: 'POST',
        data: {
          note_id: noteId,
          scrittura_permessi: scritturaPermessi
        },
        success: function(response) {
          alert('Permessi aggiornati con successo!');
        },
        error: function() {
          alert('Errore nell\'aggiornamento dei permessi.');
        }
      });
    });
  });
</script>

</body>
</html>
