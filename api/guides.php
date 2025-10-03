<?php
require_once __DIR__ . '/../includes/db_connect.php';
header("Content-Type: application/json");
$guides = $pdo->query("SELECT * FROM Guides")->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($guides);
