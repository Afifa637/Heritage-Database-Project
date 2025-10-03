<?php
// api/stats.php
require_once __DIR__ . '/../includes/db_connect.php';
header('Content-Type: application/json');

$total_sites = $pdo->query("SELECT COUNT(*) FROM HeritageSites")->fetchColumn();
$total_events = $pdo->query("SELECT COUNT(*) FROM Events")->fetchColumn();
$total_bookings = $pdo->query("SELECT COUNT(*) FROM Bookings")->fetchColumn();
$total_payments = $pdo->query("SELECT COUNT(*) FROM Payments")->fetchColumn();

echo json_encode([
    'sites' => (int)$total_sites,
    'events' => (int)$total_events,
    'bookings' => (int)$total_bookings,
    'payments' => (int)$total_payments
]);
