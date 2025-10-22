<?php
require_once 'headerFooter.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdminLogin();

$visitor_id = $_GET['id'] ?? null;
if (!$visitor_id) {
    header("Location: manage_visitors.php");
    exit;
}

# === Visitor Info ===
$stmt = $pdo->prepare("SELECT * FROM Visitors WHERE visitor_id=?");
$stmt->execute([$visitor_id]);
$visitor = $stmt->fetch();
if (!$visitor) {
    echo "Visitor not found.";
    exit;
}

# === Filters ===
$booking_status = $_GET['booking_status'] ?? '';
$payment_status = $_GET['payment_status'] ?? '';
$review_rating  = $_GET['review_rating'] ?? '';
$date_from      = $_GET['date_from'] ?? '';
$date_to        = $_GET['date_to'] ?? '';

# === Bookings ===
$query = "SELECT b.*, s.name AS site_name, e.name AS event_name
          FROM Bookings b
          LEFT JOIN HeritageSites s ON b.site_id=s.site_id
          LEFT JOIN Events e ON b.event_id=e.event_id
          WHERE b.visitor_id=?";
$params = [$visitor_id];

if ($booking_status) {
    $query .= " AND b.payment_status=?";
    $params[] = $booking_status;
}
if ($date_from) {
    $query .= " AND b.booking_date >= ?";
    $params[] = $date_from;
}
if ($date_to) {
    $query .= " AND b.booking_date <= ?";
    $params[] = $date_to;
}

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

# === Payments ===
$query = "SELECT p.*, b.booking_id 
          FROM Payments p 
          JOIN Bookings b ON p.booking_id=b.booking_id
          WHERE b.visitor_id=?";
$params = [$visitor_id];
if ($payment_status) {
    $query .= " AND p.status=?";
    $params[] = $payment_status;
}
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$payments = $stmt->fetchAll();

# === Reviews ===
$query = "SELECT r.*, s.name AS site_name, e.name AS event_name 
          FROM Reviews r
          LEFT JOIN HeritageSites s ON r.site_id=s.site_id
          LEFT JOIN Events e ON r.event_id=e.event_id
          WHERE r.visitor_id=?";
$params = [$visitor_id];
if ($review_rating) {
    $query .= " AND r.rating=?";
    $params[] = $review_rating;
}
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$reviews = $stmt->fetchAll();

# Average rating
$stmt = $pdo->prepare("SELECT ROUND(AVG(rating),2) as avg_rating 
                       FROM Reviews WHERE visitor_id=?");
$stmt->execute([$visitor_id]);
$avg_rating = $stmt->fetchColumn();
?>
<h2>Visitor Details</h2>
<p><b>Name:</b> <?= htmlspecialchars($visitor['name']) ?></p>
<p><b>Email:</b> <?= htmlspecialchars($visitor['email']) ?></p>
<p><b>Phone:</b> <?= htmlspecialchars($visitor['phone']) ?></p>
<p><b>Nationality:</b> <?= htmlspecialchars($visitor['nationality']) ?></p>
<p><b>Joined:</b> <?= $visitor['created_at'] ?></p>

<hr>
<h3>Bookings</h3>
<form method="get">
  <input type="hidden" name="id" value="<?= $visitor_id ?>">
  Status:
  <select name="booking_status">
    <option value="">All</option>
    <option value="pending"   <?= $booking_status==='pending'?'selected':'' ?>>Pending</option>
    <option value="paid"      <?= $booking_status==='paid'?'selected':'' ?>>Paid</option>
    <option value="failed"    <?= $booking_status==='failed'?'selected':'' ?>>Failed</option>
    <option value="refunded"  <?= $booking_status==='refunded'?'selected':'' ?>>Refunded</option>
  </select>
  From: <input type="date" name="date_from" value="<?= $date_from ?>">
  To: <input type="date" name="date_to" value="<?= $date_to ?>">
  <button type="submit">Filter</button>
</form>
<table border="1">
<tr><th>ID</th><th>Site/Event</th><th>Tickets</th><th>Status</th><th>Date</th></tr>
<?php foreach ($bookings as $b): ?>
<tr>
  <td><?= $b['booking_id'] ?></td>
  <td><?= $b['site_name'] ?? $b['event_name'] ?></td>
  <td><?= $b['no_of_tickets'] ?></td>
  <td><?= $b['payment_status'] ?></td>
  <td><?= $b['booking_date'] ?></td>
</tr>
<?php endforeach; ?>
</table>

<hr>
<h3>Payments</h3>
<form method="get">
  <input type="hidden" name="id" value="<?= $visitor_id ?>">
  Status:
  <select name="payment_status">
    <option value="">All</option>
    <option value="initiated" <?= $payment_status==='initiated'?'selected':'' ?>>Initiated</option>
    <option value="successful"<?= $payment_status==='successful'?'selected':'' ?>>Successful</option>
    <option value="failed"    <?= $payment_status==='failed'?'selected':'' ?>>Failed</option>
    <option value="refunded"  <?= $payment_status==='refunded'?'selected':'' ?>>Refunded</option>
  </select>
  <button type="submit">Filter</button>
</form>
<table border="1">
<tr><th>ID</th><th>Booking</th><th>Amount</th><th>Method</th><th>Status</th><th>Date</th></tr>
<?php foreach ($payments as $p): ?>
<tr>
  <td><?= $p['payment_id'] ?></td>
  <td><?= $p['booking_id'] ?></td>
  <td><?= $p['amount'] ?></td>
  <td><?= $p['method'] ?></td>
  <td><?= $p['status'] ?></td>
  <td><?= $p['paid_at'] ?></td>
</tr>
<?php endforeach; ?>
</table>

<hr>
<h3>Reviews (Avg: <?= $avg_rating ?: '0' ?>)</h3>
<form method="get">
  <input type="hidden" name="id" value="<?= $visitor_id ?>">
  Rating:
  <select name="review_rating">
    <option value="">All</option>
    <?php for ($i=1;$i<=5;$i++): ?>
      <option value="<?= $i ?>" <?= $review_rating==$i?'selected':'' ?>><?= $i ?></option>
    <?php endfor; ?>
  </select>
  <button type="submit">Filter</button>
</form>
<table border="1">
<tr><th>ID</th><th>Site/Event</th><th>Rating</th><th>Comment</th><th>Date</th></tr>
<?php foreach ($reviews as $r): ?>
<tr>
  <td><?= $r['review_id'] ?></td>
  <td><?= $r['site_name'] ?? $r['event_name'] ?></td>
  <td><?= $r['rating'] ?></td>
  <td><?= htmlspecialchars($r['comment']) ?></td>
  <td><?= $r['review_date'] ?></td>
</tr>
<?php endforeach; ?>
</table>
</div>
