<?php
session_start();
require_once 'headerFooter.php';
require_once __DIR__ . '/../includes/db_connect.php';

if (empty($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Default empty event
$event = [
    'event_id' => '',
    'name' => '',
    'site_id' => '',
    'event_date' => '',
    'event_time' => '',
    'description' => '',
    'ticket_price' => '',
    'capacity' => ''
];
$message = ''; $queryLog = '';

// Load event for editing
if (!empty($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT * FROM Events WHERE event_id = :id');
    $stmt->execute(['id' => $_GET['id']]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$event) die('Event not found');
}

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $csrf)) {
        http_response_code(400);
        exit('Invalid CSRF token');
    }

    $data = [
        'name' => $_POST['name'],
        'site_id' => (int)$_POST['site_id'],
        'event_date' => $_POST['event_date'],
        'event_time' => $_POST['event_time'],
        'description' => $_POST['description'],
        'ticket_price' => (float)$_POST['ticket_price'],
        'capacity' => (int)$_POST['capacity']
    ];

    if (!empty($_POST['event_id'])) {
        $queryLog = 'UPDATE Events 
                     SET name=:name, site_id=:site_id, event_date=:event_date, event_time=:event_time, 
                         description=:description, ticket_price=:ticket_price, capacity=:capacity 
                     WHERE event_id=:id';
        $data['id'] = (int)$_POST['event_id'];
        $stmt = $pdo->prepare($queryLog);
        $stmt->execute($data);
        $message = "✅ Event updated successfully!";
    } else {
        $queryLog = 'INSERT INTO Events (name, site_id, event_date, event_time, description, ticket_price, capacity)
                     VALUES (:name,:site_id,:event_date,:event_time,:description,:ticket_price,:capacity)';
        $stmt = $pdo->prepare($queryLog);
        $stmt->execute($data);
        $message = "✅ New event added successfully!";
    }
}

$sites = $pdo->query('SELECT site_id, name FROM HeritageSites ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title><?= $event['event_id'] ? 'Edit Event' : 'Add Event' ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container py-4">
  <h2><?= $event['event_id'] ? 'Edit Event' : 'Add Event' ?></h2>

  <?php if ($message): ?>
  <div class="alert alert-success"><?= $message ?></div>
  <?php endif; ?>

  <form method="post" class="row g-3">
    <div class="col-md-6">
      <label class="form-label">Event Name</label>
      <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($event['name']) ?>" required>
    </div>
    <div class="col-md-6">
      <label class="form-label">Site</label>
      <select name="site_id" class="form-select" required>
        <option value="">Select Site</option>
        <?php foreach ($sites as $s): ?>
          <option value="<?= $s['site_id'] ?>" <?= $s['site_id']==$event['site_id']?'selected':'' ?>><?= htmlspecialchars($s['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-md-6">
      <label class="form-label">Event Date</label>
      <input type="date" name="event_date" class="form-control" value="<?= htmlspecialchars($event['event_date']) ?>" required>
    </div>
    <div class="col-md-6">
      <label class="form-label">Event Time</label>
      <input type="time" name="event_time" class="form-control" value="<?= htmlspecialchars($event['event_time']) ?>" required>
    </div>

    <div class="col-md-6">
      <label class="form-label">Ticket Price (৳)</label>
      <input type="number" step="0.01" name="ticket_price" class="form-control" value="<?= htmlspecialchars($event['ticket_price']) ?>">
    </div>
    <div class="col-md-6">
      <label class="form-label">Capacity</label>
      <input type="number" name="capacity" class="form-control" value="<?= htmlspecialchars($event['capacity']) ?>">
    </div>

    <div class="col-12">
      <label class="form-label">Description</label>
      <textarea name="description" class="form-control"><?= htmlspecialchars($event['description']) ?></textarea>
    </div>

    <input type="hidden" name="event_id" value="<?= $event['event_id'] ?>">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="col-12">
      <button type="submit" class="btn btn-success"><?= $event['event_id'] ? 'Update' : 'Add' ?></button>
      <a href="manage_events.php" class="btn btn-secondary">Cancel</a>
    </div>
  </form>

  <?php if ($queryLog): ?>
  <div class="alert alert-info mt-3 small">
    <strong>Executed Query:</strong><br>
    <code><?= htmlspecialchars($queryLog) ?></code>
  </div>
  <?php endif; ?>
</div>
</body>
</html>
