<?php
require_once __DIR__ . '/../includes/db_connect.php';
header("Content-Type: application/json");
$sql = "SELECT p.payment_id,p.amount,p.method,p.payment_date,
               b.visitor_id,s.name AS site
        FROM Payments p
        JOIN Bookings b ON p.booking_id=b.booking_id
        JOIN HeritageSites s ON b.site_id=s.site_id";
echo json_encode($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC));
