<?php
session_start();
include 'db_connection.php';

if (!isset($_SESSION['utente'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_POST['note_id']) || !isset($_POST['new_status'])) {
    die("Dati mancanti.");
}

$note_id = intval($_POST['note_id']);
$new_status = intval($_POST['new_status']); // 1 = pubblica, 0 = privata
$utente = $_SESSION['utente'];

$stmt = $conn->prepare("UPDATE Note SET pubblica = ? WHERE id = ? AND autore = ?");
$stmt->bind_param("iis", $new_status, $note_id, $utente);

if ($stmt->execute()) {
    header("Location: profile.php?msg=Stato della nota aggiornato");
} else {
    echo "Errore nell'aggiornamento della privacy.";
}
$stmt->close();
$conn->close();
?>