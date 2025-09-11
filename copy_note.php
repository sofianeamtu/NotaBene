<?php
session_start();
require_once __DIR__.'/db_connection.php';
require_once __DIR__.'/perm_helper.php';

if (!isset($_SESSION['utente'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST'
    || !isset($_POST['csrf_token'], $_SESSION['csrf_token'])
    || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(400);
    $_SESSION['flash_error'] = 'Richiesta non valida.';
    header("Location: home.php");
    exit();
}

$note_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($note_id <= 0) {
    http_response_code(400);
    $_SESSION['flash_error'] = 'Nota non valida.';
    header("Location: home.php");
    exit();
}

$stmt = $conn->prepare("SELECT id, titolo, testo, autore, tag, cartella FROM Note WHERE id = ?");
$stmt->bind_param("i", $note_id);
$stmt->execute();
$orig = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$orig) {
    http_response_code(404);
    $_SESSION['flash_error'] = 'Nota non trovata.';
    header("Location: home.php");
    exit();
}

$me = $_SESSION['utente'];
if (!user_can_read($conn, $note_id, $me)) {
    http_response_code(403);
    $_SESSION['flash_error'] = 'Non hai i permessi per copiare questa nota.';
    header("Location: home.php");
    exit();
}

$newTitolo   = 'Copia di: ' . ($orig['titolo'] ?? '');
$newTesto    = (string)$orig['testo'];
if (mb_strlen($newTesto, 'UTF-8') > 280) {
    $newTesto = mb_substr($newTesto, 0, 280, 'UTF-8');
}
$newTag      = $orig['tag'] ?? null;
$newCartella = 'Note Copiate'; 

$conn->begin_transaction();
try {
    $stmt = $conn->prepare("
        INSERT INTO Note (titolo, testo, autore, tag, cartella, pubblica, allow_edit, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, 0, 0, NOW(), NOW())
    ");
    $stmt->bind_param("sssss", $newTitolo, $newTesto, $me, $newTag, $newCartella);
    $stmt->execute();
    $newNoteId = $stmt->insert_id;
    $stmt->close();

    if ($stmt = $conn->prepare("INSERT INTO NoteRevision (note_id, contenuto, editor, created_at) VALUES (?, ?, ?, NOW())")) {
        $stmt->bind_param("iss", $newNoteId, $newTesto, $me);
        $stmt->execute();
        $stmt->close();
    }

    $conn->commit();
    $_SESSION['flash_success'] = "Copia creata correttamente. La trovi in '$newCartella'.";
    header("Location: profile.php");
    exit();

} catch (Throwable $e) {
    $conn->rollback();
    error_log('Copy error: '.$e->getMessage());
    http_response_code(500);
    $_SESSION['flash_error'] = 'Errore durante la copia della nota.';
    header("Location: home.php");
    exit();
}
