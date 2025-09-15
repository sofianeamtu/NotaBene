<?php
// tests/bootstrap.php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// Config DB (puoi leggere da .env se vuoi)
$DB_HOST = getenv('DB_HOST') ?: '127.0.0.1';
$DB_PORT = (int)(getenv('DB_PORT') ?: 3306);
$DB_USER = getenv('DB_USER') ?: 'root';
$DB_PASS = getenv('DB_PASS') ?: 'root';
$DB_NAME = getenv('DB_NAME') ?: 'notabene_test';

// Connessione condivisa per i test
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function db(): mysqli {
    static $conn = null;
    global $DB_HOST, $DB_PORT, $DB_USER, $DB_PASS, $DB_NAME;
    if ($conn === null) {
        $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, '', $DB_PORT);
        $conn->set_charset('utf8mb4');
        // crea DB test se manca e seleziona
        $conn->query("CREATE DATABASE IF NOT EXISTS `{$DB_NAME}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $conn->select_db($DB_NAME);
    }
    return $conn;
}
