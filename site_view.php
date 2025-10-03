<?php
session_start();
require_once __DIR__ . '/includes/db_connect.php';

// ----------------- Validate Input -----------------
$site_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$site_id) {
    http_response_code(400);
    echo "Invalid site id.";
    exit;
}

// ----------------- Fetch Site Details -----------------
$stmt = $pdo->prepare("SELECT * FROM HeritageSites WHERE site_id = ?");
$stmt->execute([$site_id]);
$site = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$site) {
    http_response_code(404);
    echo "Site not found.";
    exit;
}

// ----------------- Fetch Events (all for this site) -----------------
$evStmt = $pdo->prepare("SELECT * FROM Events WHERE site_id = ? ORDER BY event_date ASC");
$evStmt->execute([$site_id]);
$events = $evStmt->fetchAll(PDO::FETCH_ASSOC);

// ----------------- Fetch Reviews -----------------
$revStmt = $pdo->prepare("
    SELECT r.*, v.name as visitor_name 
    FROM Reviews r 
    JOIN Visitors v USING (visitor_id) 
    WHERE r.site_id = ? 
    ORDER BY review_date DESC 
    LIMIT 20
");
$revStmt->execute([$site_id]);
$reviews = $revStmt->fetchAll(PDO::FETCH_ASSOC);

$loggedIn = isset($_SESSION['visitor_id']);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($site['name'], ENT_QUOTES | ENT_SUBSTITUTE) ?> - Heritage Site</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
</head>
<body class="bg-light">

<header class="bg-primary text-white text-center py-5">
  <h1><?= htmlspecialchars($site['name'], ENT_QUOTES | ENT_SUBSTITUTE) ?></h1>
  <p class="lead">Discover the history, culture, and beauty of this heritage treasure</p>
</header>

<main class="container my-4">
  <a href="index.php" class="btn btn-outline-secondary mb-3">&larr; Back to all sites</a>

  <!-- Site Details -->
  <section class="mb-5">
    <h2 class="text-primary">About the Site</h2>
    <div class="card shadow-sm p-4">
      <div class="row">
        <div class="col-md-6">
          <p><strong>📍 Location:</strong> <?= htmlspecialchars($site['location'] ?? '—', ENT_QUOTES | ENT_SUBSTITUTE) ?></p>
          <p><strong>🏛 Type:</strong> <?= htmlspecialchars($site['type'] ?? '—', ENT_QUOTES | ENT_SUBSTITUTE) ?></p>
          <p><strong>🕒 Opening Hours:</strong> <?= htmlspecialchars($site['opening_hours'] ?? '—', ENT_QUOTES | ENT_SUBSTITUTE) ?></p>
        </div>
        <div class="col-md-6">
          <p><strong>✨ Highlights:</strong></p>
          <ul>
            <li>Rich historical background</li>
            <li>Guided tours available</li>
            <li>Family-friendly facilities</li>
          </ul>
        </div>
      </div>
      <p><?= nl2br(htmlspecialchars($site['description'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE)) ?></p>
      <div id="map" style="height:300px;" class="mt-3 rounded"></div>
    </div>
  </section>

  <!-- Booking -->
  <section class="mb-5">
    <h2 class="text-primary">Book a Visit</h2>
    <div class="card shadow-sm p-4">
      <form action="booking_process.php" method="post" class="row g-3">
        <input type="hidden" name="site_id" value="<?= (int)$site_id ?>">
        <?php if (!$loggedIn): ?>
          <div class="col-md-6">
            <label class="form-label">Full Name</label>
            <input type="text" name="name" class="form-control" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Phone</label>
            <input type="text" name="phone" class="form-control">
          </div>
        <?php endif; ?>
        <div class="col-md-3">
          <label class="form-label">Tickets</label>
          <input name="no_of_tickets" type="number" value="1" min="1" class="form-control" required>
        </div>
        <div class="col-12">
          <button type="submit" class="btn btn-primary">Proceed to Payment</button>
        </div>
      </form>
    </div>
  </section>

  <!-- Events -->
  <?php if ($events): ?>
  <section class="mb-5">
    <h2 class="text-primary">Upcoming Events at <?= htmlspecialchars($site['name'], ENT_QUOTES | ENT_SUBSTITUTE) ?></h2>
    <?php foreach ($events as $e): ?>
      <div class="card shadow-sm p-3 mb-3">
        <h4><?= htmlspecialchars($e['name'], ENT_QUOTES | ENT_SUBSTITUTE) ?></h4>
        <p><strong>Date:</strong> <?= htmlspecialchars($e['event_date'], ENT_QUOTES | ENT_SUBSTITUTE) ?> <?= htmlspecialchars($e['event_time'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE) ?></p>
        <p><strong>Ticket Price:</strong> <?= number_format((float)$e['ticket_price'], 2) ?></p>

        <form action="booking_process.php" method="post" class="row g-3">
          <input type="hidden" name="event_id" value="<?= (int)$e['event_id'] ?>">
          <?php if (!$loggedIn): ?>
            <div class="col-md-3">
              <input type="text" name="name" placeholder="Your Name" class="form-control" required>
            </div>
            <div class="col-md-3">
              <input type="email" name="email" placeholder="Email" class="form-control" required>
            </div>
            <div class="col-md-3">
              <input type="text" name="phone" placeholder="Phone" class="form-control">
            </div>
          <?php endif; ?>
          <div class="col-md-2">
            <input name="no_of_tickets" type="number" value="1" min="1" class="form-control" required>
          </div>
          <div class="col-md-2">
            <select name="method" class="form-select" required>
              <option value="online">Online</option>
              <option value="card">Card</option>
              <option value="cash">Cash</option>
            </select>
          </div>
          <div class="col-12">
            <button type="submit" class="btn btn-success">Book Event</button>
          </div>
        </form>
      </div>
    <?php endforeach; ?>
  </section>
  <?php endif; ?>

  <!-- Reviews -->
  <section>
    <h2 class="text-primary">Visitor Reviews</h2>
    <div class="card shadow-sm p-4 mb-3">
      <?php if (!$reviews): ?>
        <p>No reviews yet. Be the first!</p>
      <?php else: ?>
        <?php foreach ($reviews as $r): ?>
          <div class="mb-3 border-bottom pb-2">
            <strong><?= htmlspecialchars($r['visitor_name'], ENT_QUOTES | ENT_SUBSTITUTE) ?></strong> : ⭐ <?= htmlspecialchars($r['rating'], ENT_QUOTES | ENT_SUBSTITUTE) ?>/5
            <p><?= nl2br(htmlspecialchars($r['comment'], ENT_QUOTES | ENT_SUBSTITUTE)) ?></p>
            <small class="text-muted">Posted on <?= htmlspecialchars($r['review_date'], ENT_QUOTES | ENT_SUBSTITUTE) ?></small>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </section>
</main>

<footer class="bg-dark text-white text-center py-3">
  <p>&copy; <?= date("Y") ?> Heritage Explorer | All rights reserved.</p>
</footer>

<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script>
  var lat = <?= isset($site['latitude']) && is_numeric($site['latitude']) ? json_encode((float)$site['latitude']) : '23.8103' ?>;
  var lng = <?= isset($site['longitude']) && is_numeric($site['longitude']) ? json_encode((float)$site['longitude']) : '90.4125' ?>;
  var map = L.map('map').setView([lat, lng], 13);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);
  L.marker([lat, lng]).addTo(map).bindPopup(<?= json_encode($site['name']) ?>).openPopup();
</script>
</body>
</html>
