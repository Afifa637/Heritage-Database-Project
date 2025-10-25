<?php
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/headerFooter.php';

if (!isset($_SESSION['visitor_id'])) {
  header("Location: /Heritage-Database-Project/visitor/login.php");
  exit;
}

$visitor_id = $_SESSION['visitor_id'];

$stmt = $pdo->prepare("
  SELECT p.payment_id, p.method, p.status, p.amount, p.paid_at,
         s.name AS site_name, b.booking_id
  FROM Payments p
  JOIN Bookings b ON p.booking_id = b.booking_id
  JOIN HeritageSites s ON b.site_id = s.site_id
  WHERE b.visitor_id = ?
  ORDER BY p.paid_at DESC
");
$stmt->execute([$visitor_id]);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="my-5">
  <h3 class="mb-4 text-primary">💳 My Payments</h3>

  <?php if (!$payments): ?>
    <div class="alert alert-info">No payments found.</div>
  <?php else: ?>
    <table class="table table-bordered bg-white shadow-sm">
      <thead class="table-light">
        <tr>
          <th>ID</th>
          <th>Site</th>
          <th>Method</th>
          <th>Amount (৳)</th>
          <th>Status</th>
          <th>Date</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($payments as $p): ?>
          <tr>
            <td>#<?= htmlspecialchars($p['payment_id']) ?></td>
            <td><?= htmlspecialchars($p['site_name']) ?></td>
            <td><?= htmlspecialchars(ucfirst($p['method'])) ?></td>
            <td><?= number_format($p['amount'],2) ?></td>
            <td>
              <?php if ($p['status'] === 'successful'): ?>
                <span class="badge bg-success">Paid</span>
              <?php elseif ($p['status'] === 'failed'): ?>
                <span class="badge bg-danger">Failed</span>
              <?php elseif ($p['status'] === 'refunded'): ?>
                <span class="badge bg-warning text-dark">Refunded</span>
              <?php else: ?>
                <span class="badge bg-secondary">Pending</span>
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($p['paid_at']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/includes/headerFooter.php'; ?>
