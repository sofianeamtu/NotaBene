<?php
require __DIR__.'/db_connection.php';
header('Content-Type: text/plain; charset=utf-8');

echo "DATABASE(): ".$conn->query("SELECT DATABASE() db")->fetch_assoc()['db']."\n\n";

echo "SHOW TABLES:\n";
$r = $conn->query("SHOW TABLES");
while ($row = $r->fetch_row()) echo " - {$row[0]}\n";

echo "\nCOUNT Utente:\n";
$r = $conn->query("SELECT COUNT(*) n FROM `Utente`");
echo "n=".$r->fetch_assoc()['n']."\n";
