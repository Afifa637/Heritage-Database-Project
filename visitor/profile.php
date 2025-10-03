<?php
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';
requireVisitorLogin();

$visitor_id = $_SESSION['visitor_id'];

# Visitor info
$stmt = $pdo->prepare("SELECT * FROM Visitors WHERE visitor_id=?");
$stmt->execute([$visitor_id]);
$visitor = $stmt->fetch();

# Bookings
$stmt = $pdo->prepare("SELECT b.*, s.name AS site_name, e.name AS event_name
                       FROM Bookings b
                       LEFT JOIN HeritageSites s ON b.site_id=s.site_id
                       LEFT JOIN Events e ON b.event_id=e.event_id
                       WHERE b.visitor_id=?");
$stmt->execute([$visitor_id]);
$bookings = $stmt->fetchAll();

# Payments
$stmt = $pdo->prepare("SELECT p.*, b.booking_id 
                       FROM Payments p 
                       JOIN Bookings b ON p.booking_id=b.booking_id
                       WHERE b.visitor_id=?");
$stmt->execute([$visitor_id]);
$payments = $stmt->fetchAll();

# Reviews
$stmt = $pdo->prepare("SELECT r.*, s.name AS site_name, e.name AS event_name 
                       FROM Reviews r
                       LEFT JOIN HeritageSites s ON r.site_id=s.site_id
                       LEFT JOIN Events e ON r.event_id=e.event_id
                       WHERE r.visitor_id=?");
$stmt->execute([$visitor_id]);
$reviews = $stmt->fetchAll();
?>
<h2>Welcome, <?= htmlspecialchars($visitor['name']) ?></h2>

<h3>Your Bookings</h3>
<ul>
<?php foreach ($bookings as $b): ?>
  <li><?= $b['site_name'] ?? $b['event_name'] ?> - <?= $b['no_of_tickets'] ?> tickets (<?= $b['payment_status'] ?>)</li>
<?php endforeach; ?>
</ul>

<h3>Your Payments</h3>
<ul>
<?php foreach ($payments as $p): ?>
  <li>Booking #<?= $p['booking_id'] ?> - <?= $p['amount'] ?> (<?= $p['status'] ?>)</li>
<?php endforeach; ?>
</ul>

<h3>Your Reviews</h3>
<ul>
<?php foreach ($reviews as $r): ?>
  <li><?= $r['site_name'] ?? $r['event_name'] ?> - ⭐<?= $r['rating'] ?> - <?= htmlspecialchars($r['comment']) ?></li>
<?php endforeach; ?>
</ul>
