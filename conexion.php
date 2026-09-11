<?php
$host = getenv('DB_HOST') ?: 'mariadb';
$db = getenv('DB_NAME') ?: 'corpo_agregados';
$user = getenv('DB_USER') ?: 'corpo';
$pass = getenv('DB_PASSWORD') ?: 'CorpoDB2026!';
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) { die('Error de conexión a la base de datos: ' . $conn->connect_error); }
$conn->set_charset('utf8mb4');
