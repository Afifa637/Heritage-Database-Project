<?php
// admin/export_csv.php
session_start();
require_once __DIR__ . '/../includes/db_connect.php';
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$type = $_GET['type'] ?? 'sites';
$type = strtolower($type);

$queries = [
    'sites' => [
        'sql' => 'SELECT site_id, name, location, type, ticket_price, unesco_status, created_at 
                  FROM HeritageSites',
        'headers' => ['site_id', 'name', 'location', 'type', 'ticket_price', 'unesco_status', 'created_at'],
        'filename' => 'sites.csv'
    ],
    'bookings' => [
        'sql' => 'SELECT b.booking_id, v.name AS visitor_name, s.name AS site_name, 
                         b.no_of_tickets, b.total_price, b.payment_status, b.booking_date 
                  FROM Bookings b 
                  LEFT JOIN Visitors v ON b.visitor_id = v.visitor_id 
                  LEFT JOIN HeritageSites s ON b.site_id = s.site_id',
        'headers' => ['booking_id', 'visitor_name', 'site_name', 'no_of_tickets', 'total_price', 'payment_status', 'booking_date'],
        'filename' => 'bookings.csv'
    ],
    'payments' => [
        'sql' => 'SELECT p.payment_id, v.name AS visitor_name, s.name AS site_name, 
                         p.amount, p.method, p.status, p.payment_date 
                  FROM Payments p
                  LEFT JOIN Bookings b ON p.booking_id = b.booking_id
                  LEFT JOIN Visitors v ON b.visitor_id = v.visitor_id
                  LEFT JOIN HeritageSites s ON b.site_id = s.site_id',
        'headers' => ['payment_id', 'visitor_name', 'site_name', 'amount', 'method', 'status', 'payment_date'],
        'filename' => 'payments.csv'
    ],
    'guides' => [
        'sql' => 'SELECT g.guide_id, g.full_name, g.phone, g.language, g.experience_years, 
                         COUNT(a.assignment_id) AS total_assignments 
                  FROM Guides g 
                  LEFT JOIN Assignments a ON g.guide_id = a.guide_id 
                  GROUP BY g.guide_id, g.full_name, g.phone, g.language, g.experience_years',
        'headers' => ['guide_id', 'name', 'phone', 'language', 'experience_years', 'total_assignments'],
        'filename' => 'guides.csv'
    ]
];

if (!array_key_exists($type, $queries)) {
    header('Location: dashboard.php');
    exit;
}

$q = $queries[$type];
$stmt = $pdo->query($q['sql']);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="'.$q['filename'].'"');

$out = fopen('php://output', 'w');
fputcsv($out, $q['headers']);
foreach ($rows as $r) {
    fputcsv($out, $r);
}
fclose($out);
exit;
