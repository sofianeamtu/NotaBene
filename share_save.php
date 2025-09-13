<?php
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');
session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax','secure'=>$secure]);
session_start();

require_once __DIR__.'/db_connection.php';
require_once __DIR__.'/perm_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Metodo non consentito'); }
$host = $_SERVER['HTTP_HOST'] ?? '';
$ok = false;
if (!empty($_SERVER['HTTP_ORIGIN']))   $ok = parse_url($_SERVER['HTTP_ORIGIN'], PHP_URL_HOST) === $host;
elseif (!empty($_SERVER['HTTP_REFERER'])) $ok = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST) === $host;
if (!$ok) { http_response_code(400); exit('Richiesta non valida'); }

if (empty($_SESSION['utente'])) { header('Location: login.php'); exit; }
$me       = $_SESSION['utente'];
$noteId   = (int)($_POST['note_id'] ?? 0);
$username = trim($_POST['username'] ?? '');
$permesso = !empty($_POST['write']) ? 'write' : 'read';

if ($noteId <= 0 || $username === '') { http_response_code(400); exit('Dati mancanti'); }
if (!user_is_owner($conn, $noteId, $me)) { http_response_code(403); exit('Solo il proprietario può condividere'); }
if ($username === $me) { header('Location: form_modifica.php?id='.$noteId); exit; }

$stmt = $conn->prepare("
  INSERT INTO Note_Share (note_id, username, permesso)
  VALUES (?, ?, ?)
  ON DUPLICATE KEY UPDATE permesso = VALUES(permesso)
");
$stmt->bind_param('iss', $noteId, $username, $permesso);
$stmt->execute();
$stmt->close();

header('Location: form_modifica.php?id='.$noteId);
