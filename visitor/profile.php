<?php
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';
requireVisitorLogin();

$visitor_id = $_SESSION['visitor_id'];

# --- Fetch Visitor Info ---
$stmt = $pdo->prepare("SELECT * FROM Visitors WHERE visitor_id=?");
$stmt->execute([$visitor_id]);
$visitor = $stmt->fetch();

# --- Update Profile ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone']);
    $nationality = trim($_POST['nationality']);

    if (!empty($_POST['password'])) {
        $password_hash = password_hash($_POST['password'], PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE Visitors SET full_name=?, phone=?, nationality=?, password_hash=? WHERE visitor_id=?");
        $stmt->execute([$full_name, $phone, $nationality, $password_hash, $visitor_id]);
    } else {
        $stmt = $pdo->prepare("UPDATE Visitors SET full_name=?, phone=?, nationality=? WHERE visitor_id=?");
        $stmt->execute([$full_name, $phone, $nationality, $visitor_id]);
    }

    header("Location: profile.php?updated=1");
    exit;
}

# --- Bookings ---
$stmt = $pdo->prepare("SELECT b.*, s.name AS site_name, e.name AS event_name
                       FROM Bookings b
                       LEFT JOIN HeritageSites s ON b.site_id=s.site_id
                       LEFT JOIN Events e ON b.event_id=e.event_id
                       WHERE b.visitor_id=?");
$stmt->execute([$visitor_id]);
$bookings = $stmt->fetchAll();

# --- Payments ---
$stmt = $pdo->prepare("SELECT p.*, b.booking_id 
                       FROM Payments p 
                       JOIN Bookings b ON p.booking_id=b.booking_id
                       WHERE b.visitor_id=?");
$stmt->execute([$visitor_id]);
$payments = $stmt->fetchAll();

# --- Reviews ---
$stmt = $pdo->prepare("SELECT r.*, s.name AS site_name, e.name AS event_name 
                       FROM Reviews r
                       LEFT JOIN HeritageSites s ON r.site_id=s.site_id
                       LEFT JOIN Events e ON r.event_id=e.event_id
                       WHERE r.visitor_id=?");
$stmt->execute([$visitor_id]);
$reviews = $stmt->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>My Profile - Heritage Explorer</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background: #f4f7fa; }
    .card { border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.08); }
    .nav-pills .nav-link.active { background-color: #003366; }
    .btn-custom { border-radius: 8px; }
  </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
  <div class="container">
    <a class="navbar-brand fw-bold" href="../index.php">🌍 Heritage Explorer</a>
    <div>
      <a href="logout.php" class="btn btn-outline-light btn-sm">Logout</a>
    </div>
  </div>
</nav>

<div class="container py-5">
  <div class="row">
    <div class="col-md-3 mb-4">
      <div class="list-group">
        <a href="#profile" class="list-group-item list-group-item-action active" data-bs-toggle="tab">👤 Profile</a>
        <a href="#bookings" class="list-group-item list-group-item-action" data-bs-toggle="tab">🎟 Bookings</a>
        <a href="#payments" class="list-group-item list-group-item-action" data-bs-toggle="tab">💳 Payments</a>
        <a href="#reviews" class="list-group-item list-group-item-action" data-bs-toggle="tab">⭐ Reviews</a>
      </div>
    </div>

    <div class="col-md-9">
      <div class="tab-content">

        <!-- Profile -->
        <div class="tab-pane fade show active" id="profile">
          <div class="card p-4">
            <h3 class="mb-3">Welcome, <?= htmlspecialchars($visitor['full_name']) ?></h3>
            <?php if (isset($_GET['updated'])): ?>
              <div class="alert alert-success">✅ Profile updated successfully!</div>
            <?php endif; ?>

            <form method="POST">
              <input type="hidden" name="update_profile" value="1">
              <div class="mb-3">
                <label class="form-label">Full Name</label>
                <input type="text" name="full_name" value="<?= htmlspecialchars($visitor['full_name']) ?>" class="form-control" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Phone</label>
                <input type="text" name="phone" value="<?= htmlspecialchars($visitor['phone']) ?>" class="form-control">
              </div>
              <div class="mb-3">
                <label class="form-label">Nationality</label>
                <input type="text" name="nationality" value="<?= htmlspecialchars($visitor['nationality']) ?>" class="form-control">
              </div>
              <div class="mb-3">
                <label class="form-label">New Password</label>
                <input type="password" name="password" class="form-control" placeholder="Leave blank to keep current">
              </div>
              <button type="submit" class="btn btn-primary btn-custom">Update Profile</button>
            </form>
          </div>
        </div>

        <!-- Bookings -->
        <div class="tab-pane fade" id="bookings">
          <div class="card p-4">
            <h3>Your Bookings</h3>
            <?php if (!$bookings): ?>
              <p class="text-muted">No bookings found.</p>
            <?php else: ?>
              <ul class="list-group">
                <?php foreach ($bookings as $b): ?>
                  <li class="list-group-item">
                    <strong><?= $b['site_name'] ?? $b['event_name'] ?></strong> - 
                    <?= $b['no_of_tickets'] ?> tickets 
                    <span class="badge bg-<?= $b['payment_status'] === 'Paid' ? 'success' : 'warning' ?>">
                      <?= $b['payment_status'] ?>
                    </span>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>
        </div>

        <!-- Payments -->
        <div class="tab-pane fade" id="payments">
          <div class="card p-4">
            <h3>Your Payments</h3>
            <?php if (!$payments): ?>
              <p class="text-muted">No payments found.</p>
            <?php else: ?>
              <table class="table table-bordered">
                <thead>
                  <tr><th>Booking ID</th><th>Amount</th><th>Status</th><th>Date</th></tr>
                </thead>
                <tbody>
                  <?php foreach ($payments as $p): ?>
                    <tr>
                      <td>#<?= $p['booking_id'] ?></td>
                      <td><?= number_format($p['amount'], 2) ?></td>
                      <td><span class="badge bg-<?= $p['status'] === 'Completed' ? 'success' : 'secondary' ?>"><?= $p['status'] ?></span></td>
                      <td><?= htmlspecialchars($p['payment_date']) ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php endif; ?>
          </div>
        </div>

        <!-- Reviews -->
        <div class="tab-pane fade" id="reviews">
          <div class="card p-4">
            <h3>Your Reviews</h3>
            <?php if (!$reviews): ?>
              <p class="text-muted">You haven't written any reviews yet.</p>
            <?php else: ?>
              <?php foreach ($reviews as $r): ?>
                <div class="border-bottom pb-2 mb-2">
                  <strong><?= $r['site_name'] ?? $r['event_name'] ?></strong>  
                  <span class="text-warning">⭐<?= $r['rating'] ?>/5</span>
                  <p><?= htmlspecialchars($r['comment']) ?></p>
                  <small class="text-muted">Posted on <?= $r['review_date'] ?></small>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>

<footer class="text-center py-3 bg-dark text-light">
  <small>&copy; <?= date("Y") ?> Heritage Explorer | All Rights Reserved</small>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
