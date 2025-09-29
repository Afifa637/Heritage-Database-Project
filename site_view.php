<?php
require_once __DIR__ . '/includes/db_connect.php';

// ----------------- Validate Input -----------------
$site_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$site_id) {
    http_response_code(400);
    echo "Invalid site id.";
    exit;
}

/* ----------------- SQL QUERIES USED -----------------
1. Fetch site details:
   SELECT * FROM HeritageSites WHERE site_id = ?

2. Fetch events for site:
   SELECT * FROM Events WHERE site_id = ? ORDER BY event_date

3. Fetch reviews with visitor info:
   SELECT r.*, v.name AS visitor_name 
   FROM Reviews r 
   JOIN Visitors v USING (visitor_id) 
   WHERE r.site_id = ? 
   ORDER BY review_date DESC 
   LIMIT 20
-------------------------------------------------------*/

// ----------------- Fetch Site Details -----------------
$stmt = $pdo->prepare("SELECT * FROM HeritageSites WHERE site_id = ?");
$stmt->execute([$site_id]);
$site = $stmt->fetch();
if (!$site) {
    http_response_code(404);
    echo "Site not found.";
    exit;
}

// ----------------- Fetch Events -----------------
$evStmt = $pdo->prepare("SELECT * FROM Events WHERE site_id = ? ORDER BY event_date");
$evStmt->execute([$site_id]);
$events = $evStmt->fetchAll();

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
$reviews = $revStmt->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($site['name']) ?> - Heritage Site</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    body { font-family: Arial, sans-serif; margin: 0; padding: 0; background: #f7f9fb; }
    header, footer { background: #003366; color: #fff; padding: 15px; text-align: center; }
    main { max-width: 900px; margin: 20px auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 3px 10px rgba(0,0,0,.1); }
    h1, h2, h3 { color: #003366; }
    a { color: #003366; text-decoration: none; }
    a:hover { text-decoration: underline; }
    form { margin-top: 15px; padding: 10px; border: 1px solid #ddd; background: #f9f9f9; border-radius: 6px; }
    label { display: block; margin: 8px 0; }
    input, select, textarea { width: 100%; padding: 8px; margin-top: 5px; border: 1px solid #ccc; border-radius: 4px; }
    button { background: #003366; color: #fff; padding: 10px 15px; border: none; border-radius: 5px; cursor: pointer; }
    button:hover { background: #0055aa; }
    .review { border-bottom: 1px solid #eee; padding: 10px 0; }
    .review strong { color: #222; }
    .review small { color: #666; }
    ul { padding-left: 20px; }
    .event-card { background: #eef4fa; padding: 10px; margin-bottom: 10px; border-radius: 6px; }
  </style>
</head>
<body>
<header>
  <h1><?= htmlspecialchars($site['name']) ?></h1>
</header>

<main>
  <a href="index.php">&larr; Back to all sites</a>

  <!-- Site details -->
  <section>
    <h2>About the Site</h2>
    <p><strong>Location:</strong> <?= htmlspecialchars($site['location']) ?></p>
    <p><strong>Type:</strong> <?= htmlspecialchars($site['type']) ?></p>
    <p><strong>Opening Hours:</strong> <?= htmlspecialchars($site['opening_hours']) ?></p>
    <p><?= nl2br(htmlspecialchars($site['description'])) ?></p>
  </section>

  <!-- Book visit -->
  <section>
    <h2>Book a Visit</h2>
    <form action="booking_process.php" method="post">
      <input type="hidden" name="site_id" value="<?= htmlspecialchars($site_id) ?>">
      <label>Your Name: <input name="name" required></label>
      <label>Email: <input name="email" type="email"></label>
      <label>Phone: <input name="phone"></label>
      <label>No. of Tickets: <input name="no_of_tickets" type="number" value="1" min="1" required></label>
      <label>Payment Method:
        <select name="method">
          <option value="online">Online</option>
          <option value="card">Card</option>
          <option value="cash">Cash</option>
        </select>
      </label>
      <button type="submit">Book Visit</button>
    </form>
  </section>

  <!-- Events -->
  <?php if ($events): ?>
  <section>
    <h2>Upcoming Events</h2>
    <?php foreach ($events as $e): ?>
      <div class="event-card">
        <h3><?= htmlspecialchars($e['name']) ?></h3>
        <p><strong>Date:</strong> <?= htmlspecialchars($e['event_date']) ?> <?= htmlspecialchars($e['event_time']) ?></p>
        <p><strong>Ticket Price:</strong> <?= number_format($e['ticket_price'], 2) ?></p>
        <form action="booking_process.php" method="post">
          <input type="hidden" name="event_id" value="<?= htmlspecialchars($e['event_id']) ?>">
          <label>No. of Tickets <input name="no_of_tickets" type="number" value="1" min="1"></label>
          <label>Your Name: <input name="name" required></label>
          <label>Email: <input name="email" type="email"></label>
          <button type="submit">Book Event</button>
        </form>
      </div>
    <?php endforeach; ?>
  </section>
  <?php endif; ?>

  <!-- Reviews -->
  <section>
    <h2>Visitor Reviews</h2>
    <?php if (!$reviews): ?>
      <p>No reviews yet. Be the first!</p>
    <?php else: ?>
      <?php foreach ($reviews as $r): ?>
        <div class="review">
          <strong><?= htmlspecialchars($r['visitor_name']) ?></strong> — ⭐ <?= htmlspecialchars($r['rating']) ?>/5
          <div><?= nl2br(htmlspecialchars($r['comment'])) ?></div>
          <small>Posted on <?= htmlspecialchars($r['review_date']) ?></small>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

    <h3>Leave a Review</h3>
    <form action="review_process.php" method="post">
      <input type="hidden" name="site_id" value="<?= htmlspecialchars($site_id) ?>">
      <label>Your Name: <input name="name" required></label>
      <label>Email: <input name="email" type="email"></label>
      <label>Rating:
        <select name="rating">
          <option>5</option><option>4</option><option>3</option><option>2</option><option>1</option>
        </select>
      </label>
      <label>Comment:<textarea name="comment" rows="4"></textarea></label>
      <button type="submit">Submit Review</button>
    </form>
  </section>
</main>

<footer>
  <p>&copy; <?= date("Y") ?> Heritage Explorer | All rights reserved.</p>
</footer>

<script src="assets/js/main.js"></script>
</body>
</html>
