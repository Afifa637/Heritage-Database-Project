<?php
// admin/export_csv.php
session_start();
require_once __DIR__ . '/../includes/db_connect.php';
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}
$type = $_GET['type'] ?? 'sites';
if ($type === 'sites') {
    $stmt = $pdo->query('SELECT site_id, name, location, type, ticket_price, unesco_status, created_at FROM HeritageSites');
    $rows = $stmt->fetchAll();
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="sites.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['site_id', 'name', 'location', 'type', 'ticket_price', 'unesco_status', 'created_at']);
    foreach ($rows as $r) fputcsv($out, $r);
    fclose($out);
    exit;
}
if ($type === 'bookings') {
    $stmt = $pdo->query('SELECT b.booking_id, v.name AS visitor_name, s.name AS site_name, b.no_of_tickets, b.total_price, b.payment_status, b.booking_date FROM bookings b LEFT JOIN visitors v ON b.visitor_id=v.visitor_id LEFT JOIN heritage_sites s ON b.site_id=s.site_id');
    $rows = $stmt->fetchAll();
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="bookings.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['booking_id', 'visitor_name', 'site_name', 'no_of_tickets', 'total_price', 'payment_status', 'booking_date']);
    foreach ($rows as $r) fputcsv($out, $r);
    fclose($out);
    exit;
}
header('Location: dashboard.php');
exit;
