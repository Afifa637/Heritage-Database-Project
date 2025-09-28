<?php
// includes/db_connect.php
// Simple PDO connection used by the frontend & admin pages (no JSON headers)
$host = '127.0.0.1';
$db = 'heritage_db';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];
try {
$pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
// On production, hide error details
die('DB connection failed: ' . $e->getMessage());
}
