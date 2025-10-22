<?php
session_start();
require_once 'headerFooter.php';
require_once __DIR__ . '/../includes/db_connect.php';
if (empty($_SESSION['admin_logged_in'])) { header("Location: login.php"); exit; }

$payments = $pdo->query("SELECT p.*,b.visitor_id,s.name AS site_name 
                         FROM Payments p
                         JOIN Bookings b ON p.booking_id=b.booking_id
                         JOIN HeritageSites s ON b.site_id=s.site_id
                         ORDER BY p.payment_date DESC")->fetchAll();
?>
<!doctype html>
<html>
<head><title>Manage Payments</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container py-4">
<h3>Payments</h3>
<table class="table table-bordered">
<tr><th>ID</th><th>Visitor</th><th>Site</th><th>Amount</th><th>Method</th><th>Date</th></tr>
<?php foreach ($payments as $p): ?>
<tr>
  <td><?= $p['payment_id'] ?></td>
  <td><?= $p['visitor_id'] ?></td>
  <td><?= htmlspecialchars($p['site_name']) ?></td>
  <td><?= number_format($p['amount'],2) ?></td>
  <td><?= htmlspecialchars($p['method']) ?></td>
  <td><?= htmlspecialchars($p['payment_date']) ?></td>
</tr>
<?php endforeach; ?>
</table>
</div>
</body>
</html>
