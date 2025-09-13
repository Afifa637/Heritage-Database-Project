<?php
// api/db.php - Database connection (edit credentials)
header('Content-Type: application/json; charset=utf-8');
$DB_HOST = '127.0.0.1';
$DB_NAME = 'heritage_db';
$DB_USER = 'root';
$DB_PASS = '';
// edit above variables for your environment

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_errno) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to connect to DB: '.$mysqli->connect_error]);
    exit;
}
$mysqli->set_charset('utf8mb4');
