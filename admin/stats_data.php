<?php
// admin/stats_data.php
session_start();
require_once __DIR__ . '/../includes/db_connect.php';

if (empty($_SESSION['admin_logged_in'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$type = $_GET['type'] ?? '';

header('Content-Type: application/json');

switch ($type) {
    case 'bookings_per_month':
        $stmt = $pdo->query("SELECT DATE_FORMAT(booking_date, '%Y-%m') AS ym, COUNT(*) AS cnt 
                             FROM Bookings 
                             GROUP BY ym ORDER BY ym");
        echo json_encode($stmt->fetchAll());
        break;

    case 'revenue_by_method':
        $stmt = $pdo->query("SELECT method, SUM(amount) AS total 
                             FROM Payments 
                             WHERE status='success'
                             GROUP BY method");
        echo json_encode($stmt->fetchAll());
        break;

    case 'guide_workload':
        $stmt = $pdo->query("SELECT g.full_name, COUNT(a.assignment_id) AS assignments 
                             FROM Guides g 
                             LEFT JOIN Assignments a ON g.guide_id=a.guide_id 
                             GROUP BY g.guide_id, g.full_name");
        echo json_encode($stmt->fetchAll());
        break;

    case 'review_distribution':
        $stmt = $pdo->query("SELECT rating, COUNT(*) AS cnt 
                             FROM Reviews 
                             GROUP BY rating ORDER BY rating");
        echo json_encode($stmt->fetchAll());
        break;

    case 'payment_status':
        $stmt = $pdo->query("SELECT status, COUNT(*) AS cnt 
                             FROM Payments 
                             GROUP BY status");
        echo json_encode($stmt->fetchAll());
        break;

    default:
        echo json_encode(['error' => 'Invalid type']);
}
