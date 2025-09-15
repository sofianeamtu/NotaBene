<?php
session_start();
require_once __DIR__.'/db_connection.php';

if (!isset($_SESSION['utente'])) { header("Location: login.php"); exit(); }

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['note_id'])) {
    // CSRF consigliato
    if (!isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403); exit('Token CSRF non valido');
    }

    $noteId = (int)$_POST['note_id'];
    $utente = $_SESSION['utente'];

    // Verifica autore
    $check = $conn->prepare("SELECT autore FROM Note WHERE id = ?");
    $check->bind_param("i", $noteId);
    $check->execute();
    $res = $check->get_result();
    $nota = $res->fetch_assoc();
    $check->close();

    if ($nota) {
        if ($nota['autore'] === $utente) {
            // Proprietario → elimina la nota, CASCADE si occupa di revisioni/condivisioni
            $delete = $conn->prepare("DELETE FROM Note WHERE id = ?");
            $delete->bind_param("i", $noteId);
            $delete->execute();
            $delete->close();
        } else {
            // Non proprietario → rimuovi me dalla condivisione
            $delShare = $conn->prepare("DELETE FROM Note_Share WHERE note_id = ? AND username = ?");
            $delShare->bind_param("is", $noteId, $utente);
            $delShare->execute();
            $delShare->close();
        }
    }
}

$conn->close();
$redirect = $_POST['redirect'] ?? 'profile.php';
header("Location: $redirect");
exit();
