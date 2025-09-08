<?php
$host = 'localhost';
$dbname = 'notabene';
$username = 'root';    // corretto nome variabile
$password = 'root';    // corretto nome variabile
$port = 3306;          // porta specifica per MySQL

$conn = new mysqli($host, $username, $password, $dbname, $port);


// Controllo connessione
if ($conn->connect_error) {
    die("Connessione fallita: " . $conn->connect_error);
}
?>