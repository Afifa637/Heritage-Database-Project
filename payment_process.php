<?php
require_once __DIR__ . '/includes/db_connect.php';

$booking_id = (int)($_GET['booking_id'] ?? 0);
$method     = $_GET['method'] ?? 'cash';

if (!$booking_id) die("Invalid booking ID");

// Fetch booking + site/event for summary
$stmt = $pdo->prepare("
    SELECT b.*, s.name AS site_name, e.name AS event_name
    FROM Bookings b
    LEFT JOIN HeritageSites s ON b.site_id=s.site_id
    LEFT JOIN Events e ON b.event_id=e.event_id
    WHERE booking_id=?");
$stmt->execute([$booking_id]);
$booking = $stmt->fetch();
if (!$booking) die("Booking not found");

// Insert into Payments
$status = ($method === 'cash') ? 'pending' : 'success'; // simulate success for online/card
$amount = 100; // default
if ($booking['site_id']) {
    $amtQ = $pdo->prepare("SELECT ticket_price FROM HeritageSites WHERE site_id=?");
    $amtQ->execute([$booking['site_id']]);
    $amount = $amtQ->fetchColumn() * $booking['no_of_tickets'];
} elseif ($booking['event_id']) {
    $amtQ = $pdo->prepare("SELECT ticket_price FROM Events WHERE event_id=?");
    $amtQ->execute([$booking['event_id']]);
    $amount = $amtQ->fetchColumn() * $booking['no_of_tickets'];
}

$ins = $pdo->prepare("INSERT INTO Payments (booking_id, method, amount, status, payment_date) VALUES (?,?,?,?,NOW())");
$ins->execute([$booking_id, $method, $amount, $status]);

?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Payment - Heritage Explorer</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    body { font-family: Arial; background:#f9fafc; padding:40px; }
    .card { max-width:500px; margin:auto; background:#fff; padding:20px; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,.1); }
    h1 { color:#003366; }
  </style>
</head>
<body>
  <div class="card">
    <h1>Payment Confirmation</h1>
    <p><strong>Booking:</strong> #<?= $booking['booking_id'] ?></p>
    <p><strong>Site/Event:</strong> <?= $booking['site_name'] ?: $booking['event_name'] ?></p>
    <p><strong>Tickets:</strong> <?= $booking['no_of_tickets'] ?></p>
    <p><strong>Amount:</strong> <?= number_format($amount,2) ?></p>
    <p><strong>Method:</strong> <?= htmlspecialchars($method) ?></p>
    <p><strong>Status:</strong> <?= htmlspecialchars($status) ?></p>
    <a href="index.php">← Back to Home</a>
  </div>
</body>
</html>
