<?php
session_start();
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdminLogin();
require_once 'headerFooter.php';

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
    echo "<div class='alert alert-danger m-4'>Visitor not found.</div>";
    exit;
}

# === Filters ===
$booking_status = $_GET['booking_status'] ?? '';
$payment_status = $_GET['payment_status'] ?? '';
$review_rating  = $_GET['review_rating'] ?? '';
$date_from      = $_GET['date_from'] ?? '';
$date_to        = $_GET['date_to'] ?? '';

# === Bookings ===
$search_booking = $_GET['search_booking'] ?? '';
$sort_booking = $_GET['sort_booking'] ?? 'b.booking_date DESC';

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
if ($search_booking) {
    $query .= " AND (s.name LIKE ? OR e.name LIKE ?)";
    $params[] = "%$search_booking%";
    $params[] = "%$search_booking%";
}
$query .= " ORDER BY $sort_booking";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

# === Payments ===
$payment_method = $_GET['payment_method'] ?? '';
$amount_min = $_GET['amount_min'] ?? '';
$amount_max = $_GET['amount_max'] ?? '';
$sort_payment = $_GET['sort_payment'] ?? 'p.paid_at DESC';

$query = "SELECT p.*, b.booking_id 
          FROM Payments p 
          JOIN Bookings b ON p.booking_id=b.booking_id
          WHERE b.visitor_id=?";
$params = [$visitor_id];

if ($payment_status) {
    $query .= " AND p.status=?";
    $params[] = $payment_status;
}
if ($payment_method) {
    $query .= " AND p.method=?";
    $params[] = $payment_method;
}
if ($amount_min) {
    $query .= " AND p.amount >= ?";
    $params[] = $amount_min;
}
if ($amount_max) {
    $query .= " AND p.amount <= ?";
    $params[] = $amount_max;
}

$query .= " ORDER BY $sort_payment";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$payments = $stmt->fetchAll();

# === Reviews ===
$keyword = $_GET['keyword'] ?? '';
$sort_review = $_GET['sort_review'] ?? 'r.review_date DESC';

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
if ($keyword) {
    $query .= " AND (r.comment LIKE ? OR s.name LIKE ? OR e.name LIKE ?)";
    $params[] = "%$keyword%";
    $params[] = "%$keyword%";
    $params[] = "%$keyword%";
}

$query .= " ORDER BY $sort_review";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$reviews = $stmt->fetchAll();

# === Average Rating ===
$stmt = $pdo->prepare("SELECT ROUND(AVG(rating),2) as avg_rating 
                       FROM Reviews WHERE visitor_id=?");
$stmt->execute([$visitor_id]);
$avg_rating = $stmt->fetchColumn();
?>

<div class="container my-5">
  <h2 class="text-center text-success mb-4"><i class="fas fa-user"></i> Visitor Details</h2>
  <div class="card shadow p-4 mb-4">
    <h4><?= htmlspecialchars($visitor['full_name'] ?? $visitor['name']) ?></h4>
    <p><b>Email:</b> <?= htmlspecialchars($visitor['email']) ?></p>
    <p><b>Phone:</b> <?= htmlspecialchars($visitor['phone']) ?></p>
    <p><b>Nationality:</b> <?= htmlspecialchars($visitor['nationality']) ?></p>
    <p><b>Joined:</b> <?= $visitor['created_at'] ?></p>
  </div>

  <hr>
  <h3 class="text-primary mb-3">Bookings</h3>
  <form class="row g-2 mb-3" method="get">
  <input type="hidden" name="id" value="<?= $visitor_id ?>">
  <div class="col-md-3">
    <input type="text" name="search_booking" class="form-control" placeholder="Search site/event" value="<?= htmlspecialchars($search_booking) ?>">
  </div>
  <div class="col-md-2">
    <select name="booking_status" class="form-select">
      <option value="">All Status</option>
      <option value="pending" <?= $booking_status==='pending'?'selected':'' ?>>Pending</option>
      <option value="paid" <?= $booking_status==='paid'?'selected':'' ?>>Paid</option>
      <option value="failed" <?= $booking_status==='failed'?'selected':'' ?>>Failed</option>
      <option value="refunded" <?= $booking_status==='refunded'?'selected':'' ?>>Refunded</option>
    </select>
  </div>
  <div class="col-md-2"><input type="date" name="date_from" class="form-control" value="<?= $date_from ?>"></div>
  <div class="col-md-2"><input type="date" name="date_to" class="form-control" value="<?= $date_to ?>"></div>
  <div class="col-md-2">
    <select name="sort_booking" class="form-select">
      <option value="b.booking_date DESC" <?= $sort_booking=='b.booking_date DESC'?'selected':'' ?>>Newest</option>
      <option value="b.booking_date ASC" <?= $sort_booking=='b.booking_date ASC'?'selected':'' ?>>Oldest</option>
      <option value="b.no_of_tickets DESC" <?= $sort_booking=='b.no_of_tickets DESC'?'selected':'' ?>>Most Tickets</option>
    </select>
  </div>
  <div class="col-md-1"><button class="btn btn-success w-100">Go</button></div>
</form>

  <table class="table table-bordered table-striped">
    <thead class="table-light">
      <tr><th>ID</th><th>Site/Event</th><th>Tickets</th><th>Status</th><th>Date</th></tr>
    </thead>
    <tbody>
      <?php if ($bookings): foreach ($bookings as $b): ?>
      <tr>
        <td><?= $b['booking_id'] ?></td>
        <td><?= htmlspecialchars($b['site_name'] ?? $b['event_name']) ?></td>
        <td><?= $b['no_of_tickets'] ?></td>
        <td><span class="badge bg-info"><?= $b['payment_status'] ?></span></td>
        <td><?= $b['booking_date'] ?></td>
      </tr>
      <?php endforeach; else: ?>
        <tr><td colspan="5" class="text-center text-muted">No bookings found</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <hr>
  <h3 class="text-primary mb-3">Payments</h3>
  <form class="row g-2 mb-3" method="get">
  <input type="hidden" name="id" value="<?= $visitor_id ?>">
  <div class="col-md-2">
    <select name="payment_status" class="form-select">
      <option value="">All Status</option>
      <option value="successful" <?= $payment_status==='successful'?'selected':'' ?>>Successful</option>
      <option value="failed" <?= $payment_status==='failed'?'selected':'' ?>>Failed</option>
      <option value="refunded" <?= $payment_status==='refunded'?'selected':'' ?>>Refunded</option>
    </select>
  </div>
  <div class="col-md-2">
    <select name="payment_method" class="form-select">
      <option value="">All Methods</option>
      <option value="Credit Card" <?= $payment_method==='Credit Card'?'selected':'' ?>>Credit Card</option>
      <option value="bKash" <?= $payment_method==='bKash'?'selected':'' ?>>bKash</option>
      <option value="Nagad" <?= $payment_method==='Nagad'?'selected':'' ?>>Nagad</option>
      <option value="Cash" <?= $payment_method==='Cash'?'selected':'' ?>>Cash</option>
    </select>
  </div>
  <div class="col-md-2"><input type="number" step="0.01" name="amount_min" class="form-control" placeholder="Min amount" value="<?= $amount_min ?>"></div>
  <div class="col-md-2"><input type="number" step="0.01" name="amount_max" class="form-control" placeholder="Max amount" value="<?= $amount_max ?>"></div>
  <div class="col-md-2">
    <select name="sort_payment" class="form-select">
      <option value="p.paid_at DESC" <?= $sort_payment=='p.paid_at DESC'?'selected':'' ?>>Newest</option>
      <option value="p.amount DESC" <?= $sort_payment=='p.amount DESC'?'selected':'' ?>>Highest Amount</option>
      <option value="p.amount ASC" <?= $sort_payment=='p.amount ASC'?'selected':'' ?>>Lowest Amount</option>
    </select>
  </div>
  <div class="col-md-2"><button class="btn btn-success w-100">Filter</button></div>
</form>

  <table class="table table-bordered table-striped">
    <thead class="table-light">
      <tr><th>ID</th><th>Booking</th><th>Amount</th><th>Method</th><th>Status</th><th>Date</th></tr>
    </thead>
    <tbody>
      <?php if ($payments): foreach ($payments as $p): ?>
      <tr>
        <td><?= $p['payment_id'] ?></td>
        <td><?= $p['booking_id'] ?></td>
        <td><?= $p['amount'] ?></td>
        <td><?= htmlspecialchars($p['method']) ?></td>
        <td><span class="badge bg-secondary"><?= $p['status'] ?></span></td>
        <td><?= $p['paid_at'] ?></td>
      </tr>
      <?php endforeach; else: ?>
        <tr><td colspan="6" class="text-center text-muted">No payments found</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <hr>
  <h3 class="text-primary mb-3">Reviews (Avg: <?= $avg_rating ?: '0' ?>)</h3>
  <form class="row g-2 mb-3" method="get">
  <input type="hidden" name="id" value="<?= $visitor_id ?>">
  <div class="col-md-3">
    <input type="text" name="keyword" class="form-control" placeholder="Search comments or site/event" value="<?= htmlspecialchars($keyword) ?>">
  </div>
  <div class="col-md-2">
    <select name="review_rating" class="form-select">
      <option value="">All Ratings</option>
      <?php for ($i=1;$i<=5;$i++): ?>
        <option value="<?= $i ?>" <?= $review_rating==$i?'selected':'' ?>><?= $i ?> ⭐</option>
      <?php endfor; ?>
    </select>
  </div>
  <div class="col-md-2">
    <select name="sort_review" class="form-select">
      <option value="r.review_date DESC" <?= $sort_review=='r.review_date DESC'?'selected':'' ?>>Newest</option>
      <option value="r.review_date ASC" <?= $sort_review=='r.review_date ASC'?'selected':'' ?>>Oldest</option>
      <option value="r.rating DESC" <?= $sort_review=='r.rating DESC'?'selected':'' ?>>Highest Rating</option>
      <option value="r.rating ASC" <?= $sort_review=='r.rating ASC'?'selected':'' ?>>Lowest Rating</option>
    </select>
  </div>
  <div class="col-md-2"><button class="btn btn-success w-100">Apply</button></div>
</form>

  <table class="table table-bordered table-striped">
    <thead class="table-light">
      <tr><th>ID</th><th>Site/Event</th><th>Rating</th><th>Comment</th><th>Date</th></tr>
    </thead>
    <tbody>
      <?php if ($reviews): foreach ($reviews as $r): ?>
      <tr>
        <td><?= $r['review_id'] ?></td>
        <td><?= htmlspecialchars($r['site_name'] ?? $r['event_name']) ?></td>
        <td><?= $r['rating'] ?></td>
        <td><?= htmlspecialchars($r['comment']) ?></td>
        <td><?= $r['review_date'] ?></td>
      </tr>
      <?php endforeach; else: ?>
        <tr><td colspan="5" class="text-center text-muted">No reviews found</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <hr>
  <h3 class="text-success mb-3">📘 SQL Practice Examples</h3>
  <details class="border rounded p-3 bg-light">
    <summary class="fw-bold text-primary mb-2">Click to expand all SQL labs (DDL, DML, FILTERS, AGGREGATES, SUBQUERIES)</summary>
    <pre class="bg-white p-3 rounded mt-3"><code>
<!-- -- LAB 2 – DDL + Basic DML (ALTER, UPDATE, DELETE)
ALTER TABLE Guides ADD experience_years INT DEFAULT 0;
ALTER TABLE Guides RENAME COLUMN experience_years TO years_of_experience;
ALTER TABLE HeritageSites MODIFY ticket_price DECIMAL(10,2);
ALTER TABLE Guides DROP COLUMN years_of_experience;
UPDATE Guides SET salary = salary + 5000 WHERE guide_id = 1;
DELETE FROM Events WHERE event_date < CURDATE();

-- LAB 3 – Filtering & Sorting
SELECT name, ticket_price FROM HeritageSites WHERE ticket_price BETWEEN 100 AND 500;
SELECT name, language FROM Guides WHERE language LIKE '%English%' OR language LIKE '%Bangla%';
SELECT name, event_date FROM Events WHERE event_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY);
SELECT name, location, ticket_price FROM HeritageSites WHERE location='Dhaka' ORDER BY ticket_price DESC;
SELECT full_name, email FROM Visitors WHERE email LIKE '%@gmail.com%';

-- LAB 4 – Aggregates, Grouping, HAVING
SELECT method, SUM(amount) AS total_revenue FROM Payments GROUP BY method ORDER BY total_revenue DESC;
SELECT ROUND(AVG(salary), 2) AS avg_salary FROM Guides;
SELECT site_id, COUNT(*) AS total_reviews, ROUND(AVG(rating),1) AS avg_rating FROM Reviews GROUP BY site_id HAVING COUNT(*) >= 2;
SELECT visitor_id, COUNT(*) AS bookings FROM Bookings GROUP BY visitor_id;
SELECT type, MAX(ticket_price) AS max_price, MIN(ticket_price) AS min_price FROM HeritageSites GROUP BY type;

-- LAB 5 – Subqueries & Set Operations
SELECT name FROM HeritageSites WHERE site_id IN (SELECT site_id FROM Events);
SELECT name FROM Guides WHERE guide_id NOT IN (SELECT guide_id FROM Assignments);
SELECT full_name FROM Visitors WHERE visitor_id IN (SELECT visitor_id FROM Bookings)
AND visitor_id NOT IN (SELECT visitor_id FROM Reviews);
(SELECT site_id, name FROM HeritageSites WHERE site_id IN (SELECT site_id FROM Bookings))
UNION
(SELECT site_id, name FROM HeritageSites WHERE site_id IN (SELECT site_id FROM Reviews));
(SELECT event_id, name FROM Events WHERE event_id IN (SELECT event_id FROM Bookings))
INTERSECT
(SELECT event_id, name FROM Events WHERE event_id IN (SELECT event_id FROM Reviews));
(SELECT site_id, name FROM HeritageSites WHERE site_id IN (SELECT site_id FROM Reviews))
EXCEPT
(SELECT site_id, name FROM HeritageSites WHERE site_id IN (SELECT site_id FROM Bookings)); -->
    </code></pre>
  </details>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
