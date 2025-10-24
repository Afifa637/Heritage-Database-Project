<?php
session_start();
require_once __DIR__ . '/includes/db_connect.php';

// ----------------- Validate Input -----------------
$site_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$site_id) {
    http_response_code(400);
    exit("Invalid site ID.");
}

// ----------------- Fetch Site Details -----------------
$stmt = $pdo->prepare("SELECT * FROM HeritageSites WHERE site_id = ?");
$stmt->execute([$site_id]);
$site = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$site) {
    http_response_code(404);
    exit("Site not found.");
}

// ----------------- Fetch Events -----------------
$evStmt = $pdo->prepare("SELECT * FROM Events WHERE site_id = ? ORDER BY event_date ASC");
$evStmt->execute([$site_id]);
$events = $evStmt->fetchAll(PDO::FETCH_ASSOC);

// ----------------- Fetch Reviews -----------------
$revStmt = $pdo->prepare("
    SELECT r.review_id, r.rating, r.comment, r.review_date, v.full_name AS visitor_name
    FROM Reviews r
    INNER JOIN Visitors v ON r.visitor_id = v.visitor_id
    WHERE r.site_id = ?
    ORDER BY r.review_date DESC
    LIMIT 20
");
$revStmt->execute([$site_id]);
$reviews = $revStmt->fetchAll(PDO::FETCH_ASSOC);

// ----------------- Login Status -----------------
$loggedIn = isset($_SESSION['visitor_id']);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?= htmlspecialchars($site['name']) ?> - Heritage Site</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
</head>
<body class="bg-light">

<header class="bg-primary text-white text-center py-5">
    <h1><?= htmlspecialchars($site['name']) ?></h1>
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
                    <p><strong>📍 Location:</strong> <?= htmlspecialchars($site['location'] ?? '—') ?></p>
                    <p><strong>🏛 Type:</strong> <?= htmlspecialchars($site['type'] ?? '—') ?></p>
                    <p><strong>🕒 Opening Hours:</strong> <?= htmlspecialchars($site['opening_hours'] ?? '—') ?></p>
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
            <p><?= nl2br(htmlspecialchars($site['description'] ?? '')) ?></p>
            <div id="map" style="height:300px;" class="mt-3 rounded"></div>
        </div>
    </section>

    <!-- Booking Section -->
    <section class="mb-5">
        <h2 class="text-primary">Book a Visit</h2>
        <div class="card shadow-sm p-4">
            <?php if ($loggedIn): ?>
                <form action="booking_process.php" method="post" class="row g-3">
                    <input type="hidden" name="site_id" value="<?= (int)$site_id ?>">
                    <div class="col-md-3">
                        <label class="form-label">Tickets</label>
                        <input name="no_of_tickets" type="number" value="1" min="1" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Payment Method</label>
                        <select name="method" class="form-select" required>
                            <option value="bkash">Bkash</option>
                            <option value="nagad">Nagad</option>
                            <option value="rocket">Rocket</option>
                            <option value="card">Card</option>
                            <option value="bank_transfer">Bank Transfer</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Proceed to Payment</button>
                    </div>
                </form>
            <?php else: ?>
                <div class="text-center">
                    <p class="mb-3">You must be logged in to book a visit.</p>
                    <a href="login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="btn btn-outline-primary">Login to Book</a>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Events Section -->
    <section class="mb-5">
        <h2 class="text-primary">Upcoming Events</h2>
        <?php if ($events && count($events) > 0): ?>
            <?php foreach ($events as $e): ?>
                <div class="card shadow-sm p-3 mb-3">
                    <h4><?= htmlspecialchars($e['name']) ?></h4>
                    <p><strong>Date:</strong> <?= htmlspecialchars($e['event_date']) ?> <?= htmlspecialchars($e['event_time']) ?></p>
                    <p><strong>Ticket Price:</strong> ৳<?= number_format((float)$e['ticket_price'], 2) ?></p>
                    <?php if ($loggedIn): ?>
                        <form action="booking_process.php" method="post" class="row g-3">
                            <input type="hidden" name="event_id" value="<?= (int)$e['event_id'] ?>">
                            <div class="col-md-2">
                                <label class="form-label">Tickets</label>
                                <input name="no_of_tickets" type="number" value="1" min="1" class="form-control" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Payment Method</label>
                                <select name="method" class="form-select" required>
                                    <option value="bkash">Bkash</option>
                                    <option value="nagad">Nagad</option>
                                    <option value="rocket">Rocket</option>
                                    <option value="card">Card</option>
                                    <option value="bank_transfer">Bank Transfer</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-success">Purchase Ticket</button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="text-center">
                            <p class="mb-2">Login to purchase tickets for this event.</p>
                            <a href="login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="btn btn-outline-success">Login to Purchase</a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="alert alert-info">No upcoming events available.</div>
        <?php endif; ?>
    </section>

    <!-- Reviews Section -->
    <section>
        <h2 class="text-primary">Visitor Reviews</h2>
        <div class="card shadow-sm p-4 mb-3">
            <?php if ($reviews): ?>
                <?php foreach ($reviews as $r): ?>
                    <div class="mb-3 border-bottom pb-2">
                        <strong><?= htmlspecialchars($r['visitor_name']) ?></strong> ⭐ <?= htmlspecialchars($r['rating']) ?>/5
                        <p><?= nl2br(htmlspecialchars($r['comment'])) ?></p>
                        <small class="text-muted">Posted on <?= htmlspecialchars($r['review_date']) ?></small>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No reviews yet. Be the first to review!</p>
            <?php endif; ?>

            <?php if ($loggedIn): ?>
                <form action="review_process.php" method="post" class="mt-3">
                    <input type="hidden" name="site_id" value="<?= (int)$site_id ?>">
                    <div class="mb-2">
                        <label class="form-label">Rating</label>
                        <select name="rating" class="form-select" required>
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                <option value="<?= $i ?>"><?= $i ?> ⭐</option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Comment</label>
                        <textarea name="comment" class="form-control" rows="3" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Submit Review</button>
                </form>
            <?php else: ?>
                <p class="mt-3"><a href="login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>">Login</a> to submit a review.</p>
            <?php endif; ?>
        </div>
    </section>
</main>

<footer class="bg-dark text-white text-center py-3">
    <p>&copy; <?= date("Y") ?> Heritage Explorer | All rights reserved.</p>
</footer>

<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script>
var lat = <?= json_encode((float)($site['latitude'] ?? 23.8103)) ?>;
var lng = <?= json_encode((float)($site['longitude'] ?? 90.4125)) ?>;
var map = L.map('map').setView([lat, lng], 13);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);
L.marker([lat, lng]).addTo(map).bindPopup(<?= json_encode($site['name']) ?>).openPopup();
</script>
</body>
</html>
