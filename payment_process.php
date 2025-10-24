<?php
session_start();
require_once __DIR__ . '/includes/db_connect.php';

$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;
if (!$booking_id) die("Invalid booking ID");

// Fetch booking
$stmt = $pdo->prepare("
    SELECT b.*, s.name AS site_name, e.name AS event_name
    FROM Bookings b
    LEFT JOIN HeritageSites s ON b.site_id = s.site_id
    LEFT JOIN Events e ON b.event_id = e.event_id
    WHERE b.booking_id = ?
");
$stmt->execute([$booking_id]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$booking) die("Booking not found");

// Fetch latest payment
$q = $pdo->prepare("SELECT * FROM Payments WHERE booking_id=? ORDER BY paid_at DESC LIMIT 1");
$q->execute([$booking_id]);
$payment = $q->fetch(PDO::FETCH_ASSOC);

// --- Handle payment submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $method = strtolower(trim($_POST['method'] ?? ''));
    $allowed = ['bkash', 'nagad', 'rocket', 'card', 'bank_transfer'];
    if (!in_array($method, $allowed, true)) die("Invalid payment method");

    $pdo->beginTransaction();
    try {
        // Update Payment
        $amount = (float)$booking['booked_ticket_price'];

        $ins = $pdo->prepare("
            INSERT INTO Payments (booking_id, amount, method, status, paid_at)
            VALUES (?, ?, ?, 'successful', NOW())
        ");
        $ins->execute([$booking_id, $amount, $method]);

        // Update booking status
        $upd = $pdo->prepare("UPDATE Bookings SET payment_status='paid' WHERE booking_id=?");
        $upd->execute([$booking_id]);

        $pdo->commit();
        header("Location: payment_process.php?booking_id={$booking_id}");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Payment failed: " . htmlspecialchars($e->getMessage()));
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Payment - Heritage Explorer</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container my-5">
  <div class="card shadow p-4 mx-auto" style="max-width:700px;">
    <h2 class="text-primary mb-3">Booking Payment</h2>
    <p><strong>Booking ID:</strong> #<?= (int)$booking['booking_id'] ?></p>
    <p><strong>Site/Event:</strong> <?= htmlspecialchars($booking['site_name'] ?: $booking['event_name'], ENT_QUOTES) ?></p>
    <p><strong>Tickets:</strong> <?= (int)$booking['no_of_tickets'] ?></p>
    <p><strong>Total Price:</strong> ৳<?= number_format($booking['booked_ticket_price'], 2) ?></p>
    <p><strong>Status:</strong> <?= htmlspecialchars($booking['payment_status'], ENT_QUOTES) ?></p>

    <?php if ($payment): ?>
      <hr>
      <p><strong>Last Payment:</strong> <?= htmlspecialchars($payment['method']) ?> (<?= htmlspecialchars($payment['status']) ?>)</p>
      <p class="small text-muted">At <?= htmlspecialchars($payment['paid_at']) ?></p>
    <?php endif; ?>

    <?php if ($booking['payment_status'] !== 'paid'): ?>
      <form method="post" class="mt-3">
        <label for="method" class="form-label">Choose Payment Method</label>
        <select name="method" id="method" class="form-select" required>
          <option value="">--Select--</option>
          <option value="bkash">Bkash</option>
          <option value="nagad">Nagad</option>
          <option value="rocket">Rocket</option>
          <option value="card">Card</option>
          <option value="bank_transfer">Bank Transfer</option>
        </select>
        <button type="submit" class="btn btn-primary mt-3">Pay Now</button>
      </form>
    <?php else: ?>
      <div class="alert alert-success mt-3">✅ Payment completed successfully.</div>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
