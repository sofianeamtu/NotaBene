<?php
session_start();
include 'db_connection.php';

if (!isset($_SESSION['utente'])) {
    header("Location: login.php");
    exit();
}

$utente = $_SESSION['utente'];

if (isset($_POST['note_id']) && isset($_POST['scrittura_permessi'])) {
    $noteId = $_POST['note_id'];
    $scritturaPermessi = $_POST['scrittura_permessi'];

    // Verifica che l'utente sia il proprietario della nota
    $stmt = $conn->prepare("SELECT autore FROM Note WHERE id = ?");
    $stmt->bind_param("i", $noteId);
    $stmt->execute();
    $result = $stmt->get_result();
    $nota = $result->fetch_assoc();
    $stmt->close();

    if ($nota['autore'] === $utente) {
        // Aggiorna il permesso di scrittura
        $stmt = $conn->prepare("UPDATE Note SET allow_edit = ? WHERE id = ?");
        $stmt->bind_param("ii", $scritturaPermessi, $noteId);
        $stmt->execute();
        $stmt->close();
        
        echo "Permessi aggiornati con successo!";
    } else {
        echo "Errore: Non sei il proprietario di questa nota.";
    }
}

$conn->close();
?>
