<?php
// C:\xampp\htdocs\Heritage-Database-Project\site_view.php
session_start();
require_once __DIR__ . '/includes/headerFooter.php'; // optional UI header/footer; keeps session handling consistent
require_once __DIR__ . '/includes/db_connect.php';   // must provide $pdo (PDO)

//  . Validate Input  .
$site_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$site_id) {
    http_response_code(400);
    exit('Invalid site ID.');
}

//  . Helper  .
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES|ENT_HTML5); }
function to_float($v){ return (float)($v ?? 0.0); }

//  . Fetch Site Details  .
$stmt = $pdo->prepare("SELECT * FROM HeritageSites WHERE site_id = :id");
$stmt->execute(['id' => $site_id]);
$site = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$site) {
    http_response_code(404);
    exit("Site not found.");
}

// Try to read latitude/longitude if columns exist (not required by original schema)
$hasLatLng = false;
$latitude = null;
$longitude = null;
if (array_key_exists('latitude', $site) && array_key_exists('longitude', $site)) {
    $latitude = is_numeric($site['latitude']) ? (float)$site['latitude'] : null;
    $longitude = is_numeric($site['longitude']) ? (float)$site['longitude'] : null;
    $hasLatLng = ($latitude !== null && $longitude !== null);
}

//  . Fetch Events  .
$evStmt = $pdo->prepare("SELECT e.*, 
    COALESCE((SELECT COUNT(*) FROM Bookings b WHERE b.event_id = e.event_id),0) AS booked_tickets
    FROM Events e
    WHERE e.site_id = :sid
    ORDER BY e.event_date ASC, e.event_time ASC
");
$evStmt->execute(['sid' => $site_id]);
$events = $evStmt->fetchAll(PDO::FETCH_ASSOC);

//  . Fetch Reviews  .
$revStmt = $pdo->prepare("
    SELECT r.review_id, r.rating, r.comment, r.review_date, v.full_name AS visitor_name
    FROM Reviews r
    JOIN Visitors v ON r.visitor_id = v.visitor_id
    WHERE r.site_id = :sid
    ORDER BY r.review_date DESC
    LIMIT 50
");
$revStmt->execute(['sid' => $site_id]);
$reviews = $revStmt->fetchAll(PDO::FETCH_ASSOC);

//  . Site-level aggregates  .
$aggStmt = $pdo->prepare("
    SELECT 
      COALESCE((SELECT COUNT(*) FROM Bookings b WHERE b.site_id = :sid),0) AS total_bookings,
      COALESCE((SELECT ROUND(AVG(r.rating),2) FROM Reviews r WHERE r.site_id = :sid), NULL) AS avg_rating,
      COALESCE((SELECT COUNT(*) FROM Reviews r WHERE r.site_id = :sid),0) AS total_reviews,
      COALESCE((SELECT ROUND(SUM(p.amount),2) FROM Payments p JOIN Bookings b2 ON b2.booking_id = p.booking_id WHERE b2.site_id = :sid AND p.status = 'successful'),0) AS revenue
");
$aggStmt->execute(['sid' => $site_id]);
$agg = $aggStmt->fetch(PDO::FETCH_ASSOC);

//  . Guides assigned to site  .
$guidesStmt = $pdo->prepare("
    SELECT g.guide_id, g.full_name, g.language, g.specialization
    FROM Assignments a
    JOIN Guides g ON g.guide_id = a.guide_id
    WHERE a.site_id = :sid
    ORDER BY g.full_name
");
$guidesStmt->execute(['sid' => $site_id]);
$assignedGuides = $guidesStmt->fetchAll(PDO::FETCH_ASSOC);

//  . Related / Nearby / Suggested sites (same type)  .
$relatedStmt = $pdo->prepare("
    SELECT site_id, name, location, ticket_price 
    FROM HeritageSites 
    WHERE type = :type AND site_id != :sid
    ORDER BY name
    LIMIT 6
");
$relatedStmt->execute(['type' => $site['type'] ?? '', 'sid' => $site_id]);
$relatedSites = $relatedStmt->fetchAll(PDO::FETCH_ASSOC);

//  . Payment methods dynamically loaded from Payments.method enum  .
$paymentMethods = ['bkash','nagad','rocket','card','bank_transfer']; // fallback
try {
    $col = $pdo->query("SHOW COLUMNS FROM Payments LIKE 'method'")->fetch(PDO::FETCH_ASSOC);
    if (!empty($col['Type']) && preg_match("/^enum\\((.*)\\)$/i", $col['Type'], $m)) {
        $raw = $m[1];
        // split by comma while respecting single quotes
        $vals = [];
        $parts = str_getcsv($raw, ',', "'");
        foreach ($parts as $p) {
            $p = trim($p, " \t\n\r\0\x0B'\"");
            if ($p !== '') $vals[] = $p;
        }
        if (!empty($vals)) $paymentMethods = $vals;
    }
} catch (Exception $e) {
    // ignore, keep fallback
}

//  . Login Status + CSRF (for booking + review forms)  .
$loggedIn = isset($_SESSION['visitor_id']);
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf_token'];

//  . Safety: ensure numeric formatting uses floats  .
$site_ticket_price = to_float($site['ticket_price'] ?? 0.0);
$agg['revenue'] = to_float($agg['revenue'] ?? 0.0);
$agg['avg_rating'] = $agg['avg_rating'] !== null ? to_float($agg['avg_rating']) : null;
$agg['total_bookings'] = (int)($agg['total_bookings'] ?? 0);
$agg['total_reviews'] = (int)($agg['total_reviews'] ?? 0);

//  . Page rendering  .
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?= h($site['name']) ?> — Heritage Explorer</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
<style>
  body {
    font-family: 'Mulish', sans-serif;
    background: #faf7f0 url('assets/images/paper-texture.jpg');
    color: #3a2f20;
  }
  header.site-hero {
    background: linear-gradient(135deg,#b08968,#7f5539);
    color: #fff;
    padding: 60px 0;
    margin-bottom: 30px;
    text-shadow: 1px 1px 2px rgba(0,0,0,0.2);
  }
  header.site-hero h1 {
    font-family: 'Libre Baskerville', serif;
    letter-spacing: 1px;
  }
  .site-card, .meta-box {
    background: #fffdf7;
    border: 1px solid #e2d2b8;
    border-radius: 10px;
    padding: 18px;
    box-shadow: 0 3px 8px rgba(0,0,0,0.05);
    transition: transform 0.2s;
  }
  .site-card:hover, .meta-box:hover {
    transform: scale(1.01);
  }
  .meta-box h5, .site-card h4 {
    font-family: 'Libre Baskerville', serif;
    color: #6b4f3b;
  }
  .muted { color:#7d6b5a; }
  a, a:hover {
    color: #8c6239;
    text-decoration: none;
  }
  .btn-primary {
    background-color: #8c6239;
    border-color: #8c6239;
  }
  .btn-primary:hover {
    background-color: #704a27;
    border-color: #704a27;
  }
  .btn-outline-primary {
    color: #8c6239;
    border-color: #8c6239;
  }
  .btn-outline-primary:hover {
    background-color: #8c6239;
    color: #fff;
  }
  footer {
    background: #3e2c1c;
    color: #d9cfc2;
    font-family: 'Libre Baskerville', serif;
  }
  footer a { color:#d4b483; }
</style>
</head>
<body>

<header class="site-hero text-center">
  <div class="container">
    <h1 class="display-6 mb-1"><?= h($site['name']) ?></h1>
    <p class="lead mb-0"><?= h($site['location'] ?? 'Location not specified') ?></p>
  </div>
</header>

<main class="container mb-5">

  <div class="row g-4">
    <!-- Left column: details, events, reviews -->
    <div class="col-lg-8">
      <div class="site-card">
        <h4>About this site</h4>
        <p class="muted mb-1"><strong>Type:</strong> <?= h($site['type'] ?? '—') ?> &nbsp; | &nbsp; <strong>Opening hours:</strong> <?= h($site['opening_hours'] ?? '—') ?></p>
        <p class="mb-2"><strong>Ticket Price:</strong> <?= number_format($site_ticket_price, 2) ?> ৳</p>
        <p><?= nl2br(h($site['description'] ?? 'No description provided.')) ?></p>
      </div>

      <!-- Upcoming Events -->
      <div class="site-card">
        <h4>Upcoming Events</h4>
        <?php if ($events): ?>
          <?php foreach ($events as $e): 
             $booked = (int)($e['booked_tickets'] ?? 0);
             $capacity = (int)($e['capacity'] ?? 0);
             $available = $capacity > 0 ? max(0, $capacity - $booked) : null;
             $event_price = to_float($e['ticket_price'] ?? 0.0);
          ?>
            <div class="mb-3 border-bottom pb-2">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  <h5 class="h6 mb-1"><?= h($e['name']) ?></h5>
                  <div class="muted small"><?= h($e['event_date']) ?> <?= h($e['event_time']) ?> &middot; <?= h($e['event_time'] ? 'Time shown' : '') ?></div>
                </div>
                <div class="text-end">
                  <div class="fw-bold"><?= number_format($event_price,2) ?> ৳</div>
                  <div class="muted small"><?= $capacity ? ("Capacity: $capacity") : 'Open capacity' ?></div>
                </div>
              </div>

              <p class="mt-2 mb-1"><?= nl2br(h(substr($e['description'] ?? '', 0, 400))) ?><?= strlen($e['description'] ?? '') > 400 ? '...' : '' ?></p>

              <?php if ($loggedIn): ?>
                <form method="post" action="booking_process.php" class="row g-2 align-items-center">
                  <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                  <input type="hidden" name="event_id" value="<?= (int)$e['event_id'] ?>">
                  <div class="col-auto" style="width:110px">
                    <input name="no_of_tickets" type="number" value="1" min="1" class="form-control form-control-sm" required>
                  </div>
                  <div class="col-auto">
                    <select name="method" class="form-select form-select-sm" required>
                      <?php foreach($paymentMethods as $m): ?>
                        <option value="<?= h($m) ?>"><?= h(ucfirst($m)) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-auto">
                    <button class="btn btn-sm btn-success">Buy</button>
                  </div>
                  <div class="col-12 mt-1 small muted">
                    <?= $available === null ? '' : ("Available: $available") ?>
                  </div>
                </form>
              <?php else: ?>
                <div class="mt-2">
                  <a class="btn btn-outline-primary btn-sm" href="visitor/login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>">Login to purchase</a>
                </div>
              <?php endif; ?>

            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="alert alert-light">No upcoming events.</div>
        <?php endif; ?>
      </div>

      <!-- Reviews -->
      <div class="site-card">
        <h4>Visitor Reviews</h4>
        <?php if ($reviews): ?>
          <?php foreach ($reviews as $r): ?>
            <div class="mb-3">
              <div class="d-flex justify-content-between">
                <div><strong><?= h($r['visitor_name']) ?></strong></div>
                <div class="muted small"><?= h($r['review_date']) ?></div>
              </div>
              <div class="mb-1">Rating: <strong><?= (int)$r['rating'] ?>/5</strong></div>
              <div><?= nl2br(h($r['comment'])) ?></div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="alert alert-light">No reviews yet — be the first!</div>
        <?php endif; ?>

        <?php if ($loggedIn): ?>
          <form method="post" action="review_process.php" class="mt-3">
            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
            <input type="hidden" name="site_id" value="<?= (int)$site_id ?>">
            <div class="row g-2">
              <div class="col-md-3">
                <label class="form-label small">Rating</label>
                <select name="rating" class="form-select form-select-sm" required>
                  <?php for ($i=5;$i>=1;$i--): ?>
                    <option value="<?= $i ?>"><?= $i ?> ⭐</option>
                  <?php endfor; ?>
                </select>
              </div>
              <div class="col-md-9">
                <label class="form-label small">Comment</label>
                <textarea name="comment" class="form-control form-control-sm" rows="2" required></textarea>
              </div>
              <div class="col-12 text-end">
                <button class="btn btn-primary btn-sm mt-2">Submit Review</button>
              </div>
            </div>
          </form>
        <?php else: ?>
          <p class="mt-3 small muted">You must <a href="visitor/login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>">log in</a> to leave a review.</p>
        <?php endif; ?>
      </div>
    </div>

    <!-- Right column: quick stats, booking, guides, related -->
    <div class="col-lg-4">
      <div class="meta-box mb-3">
        <h5 class="mb-2">Quick Stats</h5>
        <p class="mb-1"><strong>Total Bookings:</strong> <?= (int)$agg['total_bookings'] ?></p>
        <p class="mb-1"><strong>Total Revenue:</strong> <?= number_format(to_float($agg['revenue']), 2) ?> ৳</p>
        <p class="mb-1"><strong>Average Rating:</strong> <?= $agg['avg_rating'] !== null ? number_format(to_float($agg['avg_rating']), 2) . '/5' : '—' ?></p>
        <p class="mb-0"><strong>Total Reviews:</strong> <?= (int)$agg['total_reviews'] ?></p>
      </div>

      <div class="meta-box mb-3">
        <h5 class="mb-2">Book a Visit</h5>
        <?php if ($loggedIn): ?>
          <form method="post" action="booking_process.php">
            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
            <input type="hidden" name="site_id" value="<?= (int)$site_id ?>">
            <div class="mb-2">
              <label class="form-label small">Tickets</label>
              <input name="no_of_tickets" type="number" min="1" value="1" class="form-control form-control-sm" required>
            </div>
            <div class="mb-2">
              <label class="form-label small">Payment method</label>
              <select name="method" class="form-select form-select-sm" required>
                <?php foreach($paymentMethods as $m): ?>
                  <option value="<?= h($m) ?>"><?= h(ucfirst($m)) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="d-grid">
              <button class="btn btn-primary btn-sm">Proceed to Payment</button>
            </div>
          </form>
        <?php else: ?>
          <a href="visitor/login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="btn btn-outline-primary btn-sm w-100">Login to Book</a>
        <?php endif; ?>
      </div>

      <div class="meta-box mb-3">
        <h5 class="mb-2">Guides assigned here</h5>
        <?php if ($assignedGuides): ?>
          <ul class="list-unstyled mb-0">
            <?php foreach ($assignedGuides as $g): ?>
              <li><strong><?= h($g['full_name']) ?></strong><br><small class="muted"><?= h($g['language']) ?> · <?= h($g['specialization']) ?></small></li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <div class="muted small">No guides assigned to this site currently.</div>
        <?php endif; ?>
      </div>

      <div class="meta-box">
        <h5 class="mb-2">Related sites (same type)</h5>
        <?php if ($relatedSites): ?>
          <ul class="list-unstyled mb-0">
            <?php foreach ($relatedSites as $rs): ?>
              <li>
                <a href="site_view.php?id=<?= (int)$rs['site_id'] ?>"><?= h($rs['name']) ?></a>
                <div class="small muted">Ticket: <?= number_format(to_float($rs['ticket_price']),2) ?> ৳</div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <div class="muted small">No related sites.</div>
        <?php endif; ?>
      </div>

    </div>
  </div>

</main>

<footer class="bg-dark text-white text-center py-3">
  <div class="container">
    <small>&copy; <?= date('Y') ?> Heritage Explorer</small>
    <div><a href="/Heritage-Database-Project/admin/login.php" class="text-white-50 small">Admin</a></div>
  </div>
</footer>

<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<?php if ($hasLatLng): ?>
<?php endif; ?>
</body>
</html>
