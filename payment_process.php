<?php
declare(strict_types=1);
include __DIR__ . '/includes/headerFooter.php';
require_once __DIR__ . '/includes/db_connect.php';

// Minimal helper
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_HTML5); }

// CSRF helper
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// booking_id from GET
$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;
if ($booking_id <= 0) {
    http_response_code(400);
    exit("Invalid booking ID");
}

// fetch booking (join site/event for display)
$stmt = $pdo->prepare("
    SELECT b.*, s.name AS site_name, e.name AS event_name
    FROM Bookings b
    LEFT JOIN HeritageSites s ON b.site_id = s.site_id
    LEFT JOIN Events e ON b.event_id = e.event_id
    WHERE b.booking_id = ?
    LIMIT 1
");
$stmt->execute([$booking_id]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$booking) {
    http_response_code(404);
    exit("Booking not found");
}

// Fetch latest payment for this booking
$q = $pdo->prepare("SELECT * FROM Payments WHERE booking_id = ? ORDER BY paid_at DESC LIMIT 1");
$q->execute([$booking_id]);
$payment = $q->fetch(PDO::FETCH_ASSOC);

// ===== Get allowed payment methods from DB schema (enum) =====
// Try information_schema to pull enum options for Payments.method
$allowed_methods = [];

try {
    $sql = "
      SELECT COLUMN_TYPE
      FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'Payments'
        AND COLUMN_NAME = 'method'
      LIMIT 1
    ";
    $row = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);

    if (!empty($row['COLUMN_TYPE'])) {
        // COLUMN_TYPE looks like: enum('bkash','nagad','rocket','card','bank_transfer')
        $col = $row['COLUMN_TYPE'];
        if (preg_match("/^enum\((.*)\)$/i", $col, $m)) {
            // split quoted, comma-separated values safely
            $vals = str_getcsv($m[1], ',', "'");
            foreach ($vals as $v) {
                $v = trim($v, " \t\n\r\0\x0B'");
                if ($v !== '') $allowed_methods[] = $v;
            }
        }
    }
} catch (Exception $e) {
    // ignore and fallback below
}

// Fallback: if enum parsing failed, use distinct methods present in table
if (empty($allowed_methods)) {
    $rows = $pdo->query("SELECT DISTINCT method FROM Payments WHERE method IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
    if ($rows) $allowed_methods = array_values($rows);
}

// If still empty, provide a safe default (won't be hard-coded in your final DB)
if (empty($allowed_methods)) {
    $allowed_methods = ['card', 'bank_transfer']; // emergency fallback
}

// Normalize: lowercase for comparison, but preserve display-case from DB
$allowed_lc_map = [];
foreach ($allowed_methods as $m) {
    $allowed_lc_map[strtolower($m)] = $m;
}

// --- Handle payment submission ---
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // CSRF
  $csrf = $_POST['csrf_token'] ?? '';
  if (!hash_equals($_SESSION['csrf_token'], (string)$csrf)) {
      http_response_code(400);
      exit("Invalid CSRF token");
  }

  $method_in = strtolower(trim((string)($_POST['method'] ?? '')));
  if ($method_in === '' || !array_key_exists($method_in, $allowed_lc_map)) {
      $errors[] = "Invalid payment method selected.";
  }

  if ($booking['payment_status'] === 'paid') {
      $errors[] = "This booking is already paid.";
  }

  $amount = (float)($booking['booked_ticket_price'] ?? 0.00);
  if ($amount <= 0) {
      $errors[] = "Invalid amount to charge.";
  }

  if (empty($errors)) {
      $method_db = $allowed_lc_map[$method_in]; // canonical case from DB

      try {
          $pdo->beginTransaction();

          // --- Check if a payment already exists for this booking ---
          $check = $pdo->prepare("SELECT payment_id, status FROM Payments WHERE booking_id = ? LIMIT 1");
          $check->execute([$booking_id]);
          $existing = $check->fetch(PDO::FETCH_ASSOC);

          if ($existing) {
              // ✅ Update existing payment
              $updPay = $pdo->prepare("
                  UPDATE Payments
                  SET amount = :amt, method = :method, status = :status, paid_at = NOW()
                  WHERE payment_id = :pid
              ");
              $updPay->execute([
                  ':amt'    => number_format($amount, 2, '.', ''),
                  ':method' => $method_db,
                  ':status' => 'successful',
                  ':pid'    => $existing['payment_id']
              ]);
          } else {
              // 🆕 Create new payment record only if none exists
              $ins = $pdo->prepare("
                  INSERT INTO Payments (booking_id, amount, method, status, paid_at)
                  VALUES (:bid, :amt, :method, :status, NOW())
              ");
              $ins->execute([
                  ':bid' => $booking_id,
                  ':amt' => number_format($amount, 2, '.', ''),
                  ':method' => $method_db,
                  ':status' => 'initiated'
              ]);
          }

          // ✅ Update booking payment_status if payment is successful
          $upd = $pdo->prepare("UPDATE Bookings SET payment_status = 'paid' WHERE booking_id = :bid");
          $upd->execute([':bid' => $booking_id]);

          $pdo->commit();
          $success = true;

          // reload latest payment for display
          $q->execute([$booking_id]);
          $payment = $q->fetch(PDO::FETCH_ASSOC);

          // Refresh booking status for display
          $stmt->execute([$booking_id]);
          $booking = $stmt->fetch(PDO::FETCH_ASSOC);

      } catch (Exception $e) {
          $pdo->rollBack();
          $errors[] = "Payment processing failed: " . h($e->getMessage());
      }
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

    <?php if ($errors): ?>
      <div class="alert alert-danger">
        <ul class="mb-0">
          <?php foreach ($errors as $err): ?>
            <li><?= h($err) ?></li>
          <?php endforeach;?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="alert alert-success">✅ Payment completed successfully.</div>
    <?php endif; ?>

    <p><strong>Booking ID:</strong> #<?= (int)$booking['booking_id'] ?></p>
    <p><strong>Site/Event:</strong> <?= h($booking['site_name'] ?: $booking['event_name'] ?? '—') ?></p>
    <p><strong>Tickets:</strong> <?= (int)$booking['no_of_tickets'] ?></p>
    <p><strong>Total Price:</strong> ৳<?= number_format((float)$booking['booked_ticket_price'], 2) ?></p>
    <p><strong>Status:</strong> <?= h($booking['payment_status']) ?></p>

    <?php if ($payment): ?>
      <hr>
      <p><strong>Last Payment:</strong> <?= h($payment['method']) ?> (<?= h($payment['status']) ?>)</p>
      <p class="small text-muted">At <?= h($payment['paid_at']) ?></p>
    <?php endif; ?>

    <?php if ($booking['payment_status'] !== 'paid'): ?>
      <form method="post" class="mt-3" novalidate>
        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
        <div class="mb-3">
          <label for="method" class="form-label">Choose Payment Method</label>
          <select name="method" id="method" class="form-select" required>
            <option value="">-- Select payment method --</option>
            <?php foreach ($allowed_methods as $m): ?>
              <option value="<?= h($m) ?>"><?= h(ucfirst(str_replace('_', ' ', $m))) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="btn btn-primary mt-2">Pay Now</button>
      </form>
    <?php else: ?>
      <div class="alert alert-success mt-3">✅ Payment completed successfully.</div>
    <?php endif; ?>

    <div class="mt-4 text-muted small">
      <strong>Allowed methods from DB:</strong> <?= h(implode(', ', $allowed_methods)) ?>
    </div>
  </div>
</div>
<footer class="bg-dark text-white text-center py-3 w-100" style="margin-top: 40px;">
  <div class="container position-relative">
    <p class="mb-0">&copy; <?= date('Y') ?> Heritage Explorer</p>
    <a href="/Heritage-Database-Project/admin/login.php" 
       class="text-white-50 small position-absolute bottom-0 end-0 me-2 mb-1"
       style="font-size: 0.75rem; text-decoration: none;">
       Admin Login
    </a>
  </div>
</footer>
</body>
</html>
