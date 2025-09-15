<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$host = '127.0.0.1';   // evita il socket
$port = 3306;          // verifica che sia quello indicato da MAMP
$user = 'root';
$pass = 'root';
$db   = 'notabene';

$conn = new mysqli($host, $user, $pass, $db, $port);
$conn->set_charset('utf8mb4');

// ridondante ma chiarissimo
$conn->select_db($db);
error_log("HOST_INFO = ".$conn->host_info);
error_log("DB = ".$conn->query("SELECT DATABASE() AS db")->fetch_assoc()['db']);
error_log("PORT = ".$conn->query("SELECT @@port p")->fetch_assoc()['p']);
error_log("lower_case_table_names = ".$conn->query("SHOW VARIABLES LIKE 'lower_case_table_names'")->fetch_assoc()['Value']);
