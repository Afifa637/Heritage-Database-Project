<?php
// api/bookings.php
require_once __DIR__ . '/../includes/db_connect.php';

header('Content-Type: application/json');

$stmt = $pdo->query("SELECT b.booking_id, v.name AS visitor_name, s.name AS site_name, 
                            b.no_of_tickets, b.total_price, b.payment_status, b.booking_date
                     FROM Bookings b
                     LEFT JOIN Visitors v ON b.visitor_id=v.visitor_id
                     LEFT JOIN HeritageSites s ON b.site_id=s.site_id
                     ORDER BY b.booking_date DESC");

echo json_encode($stmt->fetchAll());
