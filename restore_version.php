<?php
session_start();
require_once __DIR__.'/db_connection.php';
require_once __DIR__.'/perm_helper.php';
require_once __DIR__.'/revision_helper.php';

if (!isset($_SESSION['utente'])) { header('Location: login.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST'
 || !isset($_POST['csrf_token'], $_SESSION['csrf_token'])
 || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(400);
    $_SESSION['flash_error'] = 'Richiesta non valida.';
    header('Location: home.php'); exit;
}

$noteId     = (int)($_POST['note_id'] ?? 0);
$revisionId = (int)($_POST['revision_id'] ?? 0);
$me         = $_SESSION['utente'] ?? '';

if ($noteId <= 0 || $revisionId <= 0) {
    http_response_code(400);
    $_SESSION['flash_error'] = 'Parametri non validi.';
    header('Location: home.php'); exit;
}

// Permessi: serve write
if (!user_can_write($conn, $noteId, $me)) {
    http_response_code(403);
    $_SESSION['flash_error'] = 'Non hai i permessi per ripristinare questa nota.';
    header('Location: home.php'); exit;
}

// Carica la revisione (coerente con la nota)
$stmt = $conn->prepare('
    SELECT titolo, testo, tag, cartella
    FROM NoteRevision
    WHERE id = ? AND note_id = ?
');
$stmt->bind_param('ii', $revisionId, $noteId);
$stmt->execute();
$res = $stmt->get_result();
$rev = $res->fetch_assoc();
$stmt->close();

if (!$rev) {
    http_response_code(404);
    $_SESSION['flash_error'] = 'Revisione non trovata.';
    header('Location: home.php'); exit;
}

$newTitolo   = (string)$rev['titolo'];
$newTesto    = (string)$rev['testo'];
$newTag      = $rev['tag'] ?? null;
$newCartella = $rev['cartella'] ?? null;

if (mb_strlen($newTitolo,'UTF-8') > 100) $newTitolo = mb_substr($newTitolo,0,100,'UTF-8');
if (mb_strlen($newTesto, 'UTF-8') > 280) $newTesto  = mb_substr($newTesto, 0, 280, 'UTF-8');

$conn->begin_transaction();
try {
    $stmt = $conn->prepare('
        UPDATE Note
        SET titolo = ?, testo = ?, tag = ?, cartella = ?, data_ultima_modifica = NOW()
        WHERE id = ?
    ');
    $stmt->bind_param('ssssi', $newTitolo, $newTesto, $newTag, $newCartella, $noteId);
    $stmt->execute();
    $stmt->close();

    add_revision($conn, $noteId, $newTitolo, $newTesto, $newTag, $newCartella, $me);

    $conn->commit();
    $_SESSION['flash_success'] = 'Nota ripristinata alla versione selezionata.';
    header('Location: form_modifica.php?id='.$noteId);
    exit;

} catch (Throwable $e) {
    $conn->rollback();
    error_log('restore error: '.$e->getMessage());
    http_response_code(500);
    $_SESSION['flash_error'] = 'Errore durante il ripristino.';
    header('Location: form_modifica.php?id='.$noteId);
    exit;
}
