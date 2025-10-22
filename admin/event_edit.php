<?php
session_start();
require_once 'headerFooter.php';
require_once __DIR__ . '/../includes/db_connect.php';

if (empty($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$event = [
    'event_id' => '',
    'title' => '',
    'site_id' => '',
    'start_date' => '',
    'end_date' => '',
    'description' => ''
];

// Check if editing
if (!empty($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT * FROM Events WHERE event_id = :id');
    $stmt->execute(['id' => $_GET['id']]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$event) {
        die('Event not found');
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $csrf)) { http_response_code(400); exit; }

    $data = [
        'title' => $_POST['title'],
        'site_id' => (int)$_POST['site_id'],
        'start_date' => $_POST['start_date'],
        'end_date' => $_POST['end_date'],
        'description' => $_POST['description']
    ];

    if (!empty($_POST['event_id'])) {
        $data['id'] = (int)$_POST['event_id'];
        $stmt = $pdo->prepare('UPDATE Events SET title=:title, site_id=:site_id, start_date=:start_date, end_date=:end_date, description=:description WHERE event_id=:id');
        $stmt->execute($data);
    } else {
        $stmt = $pdo->prepare('INSERT INTO Events (title, site_id, start_date, end_date, description) VALUES (:title,:site_id,:start_date,:end_date,:description)');
        $stmt->execute($data);
    }
    header('Location: manage_events.php');
    exit;
}

// Fetch sites for dropdown
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

<form method="post" class="row g-3">
<div class="col-md-6">
<label class="form-label">Title</label>
<input type="text" name="title" class="form-control" value="<?= htmlspecialchars($event['title']) ?>" required>
</div>

<div class="col-md-6">
<label class="form-label">Site</label>
<select name="site_id" class="form-select" required>
<option value="">Select Site</option>
<?php foreach ($sites as $s): ?>
<option value="<?= $s['site_id'] ?>" <?= $s['site_id']==$event['site_id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option>
<?php endforeach; ?>
</select>
</div>

<div class="col-md-6">
<label class="form-label">Start Date</label>
<input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($event['start_date']) ?>" required>
</div>

<div class="col-md-6">
<label class="form-label">End Date</label>
<input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($event['end_date']) ?>" required>
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
</div>
</div>
</body>
</html>
