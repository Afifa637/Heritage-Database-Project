<?php
// api/sites.php
require_once __DIR__ . '/../includes/db_connect.php';

header('Content-Type: application/json');

$filters = [];
$params = [];

if (!empty($_GET['location'])) {
    $filters[] = "location = ?";
    $params[] = $_GET['location'];
}
if (!empty($_GET['unesco'])) {
    $filters[] = "unesco_status = ?";
    $params[] = $_GET['unesco'];
}
if (!empty($_GET['min_price']) && !empty($_GET['max_price'])) {
    $filters[] = "ticket_price BETWEEN ? AND ?";
    $params[] = $_GET['min_price'];
    $params[] = $_GET['max_price'];
}

$where = $filters ? "WHERE " . implode(" AND ", $filters) : "";

$stmt = $pdo->prepare("SELECT site_id, name, location, type, ticket_price FROM HeritageSites $where ORDER BY name");
$stmt->execute($params);

echo json_encode($stmt->fetchAll());
