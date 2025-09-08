<?php
session_start();
include 'db_connection.php';

if (!isset($_SESSION['utente'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['note_id'])) {
    $noteId = intval($_POST['note_id']);
    $utente = $_SESSION['utente'];

    // Verifica che la nota appartenga all'utente
    $check = $conn->prepare("SELECT * FROM Note WHERE id = ? AND autore = ?");
    $check->bind_param("is", $noteId, $utente);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $delete = $conn->prepare("DELETE FROM Note WHERE id = ?");
        $delete->bind_param("i", $noteId);
        $delete->execute();
        $delete->close();
    }
    $check->close();
}

$conn->close();
header("Location: profile.php");
exit();
?>
