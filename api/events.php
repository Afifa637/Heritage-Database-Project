<?php
require_once __DIR__ . '/../includes/db_connect.php';
header("Content-Type: application/json");
$site = $_GET['site'] ?? null;
if ($site) {
    $stmt = $pdo->prepare("SELECT * FROM Events WHERE site_id=? ORDER BY event_date DESC");
    $stmt->execute([$site]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} else {
    $events = $pdo->query("SELECT * FROM Events ORDER BY event_date DESC")->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($events);
}
