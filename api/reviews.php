<?php
// api/reviews.php
require_once __DIR__ . '/../includes/db_connect.php';

header('Content-Type: application/json');

$site_id = $_GET['site_id'] ?? null;

if ($site_id) {
    $stmt = $pdo->prepare("SELECT r.review_id, v.name AS visitor_name, r.rating, r.comment, r.created_at
                           FROM Reviews r
                           LEFT JOIN Visitors v ON r.visitor_id=v.visitor_id
                           WHERE r.site_id=? ORDER BY r.created_at DESC");
    $stmt->execute([$site_id]);
    echo json_encode($stmt->fetchAll());
} else {
    echo json_encode(['error' => 'Missing site_id']);
}
