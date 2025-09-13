<?php
require __DIR__ . '/api/db.php';

$site_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$site_id) {
    http_response_code(400);
    echo "Invalid site id.";
    exit;
}

// site details
$stmt = $pdo->prepare("SELECT * FROM HeritageSites WHERE site_id = ?");
$stmt->execute([$site_id]);
$site = $stmt->fetch();
if (!$site) {
    http_response_code(404);
    echo "Site not found.";
    exit;
}

// events for site
$evStmt = $pdo->prepare("SELECT * FROM Events WHERE site_id = ? ORDER BY event_date");
$evStmt->execute([$site_id]);
$events = $evStmt->fetchAll();

// reviews
$revStmt = $pdo->prepare("SELECT r.*, v.name as visitor_name FROM Reviews r JOIN Visitors v USING (visitor_id) WHERE r.site_id = ? ORDER BY review_date DESC LIMIT 20");
$revStmt->execute([$site_id]);
$reviews = $revStmt->fetchAll();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title><?=htmlspecialchars($site['name'])?></title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
  <a href="index.php">&larr; Back</a>
  <h1><?=htmlspecialchars($site['name'])?></h1>
  <p><strong>Location:</strong> <?=htmlspecialchars($site['location'])?></p>
  <p><strong>Type:</strong> <?=htmlspecialchars($site['type'])?></p>
  <p><strong>Opening:</strong> <?=htmlspecialchars($site['opening_hours'])?></p>
  <p><?=nl2br(htmlspecialchars($site['description']))?></p>

  <h2>Book a visit</h2>
  <form action="booking_process.php" method="post">
    <input type="hidden" name="site_id" value="<?=htmlspecialchars($site_id)?>">
    <label>Your name: <input name="name" required></label><br>
    <label>Email: <input name="email" type="email"></label><br>
    <label>Phone: <input name="phone"></label><br>
    <label>No. of tickets: <input name="no_of_tickets" type="number" value="1" min="1" required></label><br>
    <label>Payment method:
      <select name="method">
        <option value="online">Online</option>
        <option value="card">Card</option>
        <option value="cash">Cash</option>
      </select>
    </label><br>
    <button type="submit">Book</button>
  </form>

  <?php if ($events): ?>
  <h3>Events at this site</h3>
  <ul>
    <?php foreach($events as $e): ?>
      <li>
        <?=htmlspecialchars($e['name'])?> - <?=htmlspecialchars($e['event_date'])?> <?=htmlspecialchars($e['event_time'])?>
        - <em>Ticket <?=number_format($e['ticket_price'],2)?></em>
        <form action="booking_process.php" method="post" style="display:inline;">
          <input type="hidden" name="event_id" value="<?=htmlspecialchars($e['event_id'])?>">
          <label>No. tickets <input name="no_of_tickets" type="number" value="1" min="1"></label>
          <input name="name" placeholder="Your name" required>
          <input name="email" placeholder="Email">
          <button type="submit">Book Event</button>
        </form>
      </li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>

  <h2>Reviews</h2>
  <?php if (!$reviews): ?>
    <p>No reviews yet. Be the first!</p>
  <?php else: ?>
    <?php foreach($reviews as $r): ?>
      <div class="review">
        <strong><?=htmlspecialchars($r['visitor_name'])?></strong> — <?=htmlspecialchars($r['rating'])?>/5
        <div><?=nl2br(htmlspecialchars($r['comment']))?></div>
        <small><?=htmlspecialchars($r['review_date'])?></small>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <h3>Leave a review</h3>
  <form action="review_process.php" method="post">
    <input type="hidden" name="site_id" value="<?=htmlspecialchars($site_id)?>">
    <label>Your name: <input name="name" required></label><br>
    <label>Email: <input name="email" type="email"></label><br>
    <label>Rating:
      <select name="rating">
        <option>5</option><option>4</option><option>3</option><option>2</option><option>1</option>
      </select>
    </label><br>
    <label>Comment:<br><textarea name="comment" rows="4"></textarea></label><br>
    <button type="submit">Submit review</button>
  </form>

  <script src="assets/js/main.js"></script>
</body>
</html>
