<?php
// api/site.php
require_once __DIR__ . '/../includes/db_connect.php';
header('Content-Type: application/json');

$id = $_GET['id'] ?? null;
if (!$id) {
    echo json_encode(['error' => 'Missing site id']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM HeritageSites WHERE site_id=?");
$stmt->execute([$id]);
$site = $stmt->fetch();

if (!$site) {
    echo json_encode(['error' => 'Site not found']);
    exit;
}

echo json_encode($site);
