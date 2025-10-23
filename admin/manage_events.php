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

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
  $del = (int)$_POST['delete_id'];
  $csrf = $_POST['csrf_token'] ?? '';
  if (!hash_equals($_SESSION['csrf_token'], $csrf)) {
    http_response_code(400);
    echo "Invalid CSRF token.";
    exit;
  }
  $pdo->prepare('DELETE FROM Events WHERE event_id = :id')->execute(['id' => $del]);
  header('Location: manage_events.php');
  exit;
}

// === Dynamic thresholds ===
$priceRange = $pdo->query("SELECT MIN(ticket_price) AS min_price, MAX(ticket_price) AS max_price FROM Events")->fetch(PDO::FETCH_ASSOC);
$minTicket = $priceRange['min_price'] ?? 0;
$maxTicket = ($priceRange['max_price'] ?? 0) + 1; // +1 Taka increment

// === Filters ===
$where = [];
$params = [];

if (!empty($_GET['q'])) {
  $where[] = "(e.name LIKE :kw OR e.description LIKE :kw)";
  $params['kw'] = '%' . $_GET['q'] . '%';
}

if (!empty($_GET['site_id'])) {
  $where[] = "e.site_id = :sid";
  $params['sid'] = $_GET['site_id'];
}

if (!empty($_GET['unesco_status'])) {
  $where[] = "s.unesco_status = :unesco";
  $params['unesco'] = $_GET['unesco_status'];
}

if (!empty($_GET['from_date']) && !empty($_GET['to_date'])) {
  $where[] = "e.event_date BETWEEN :fromD AND :toD";
  $params['fromD'] = $_GET['from_date'];
  $params['toD'] = $_GET['to_date'];
}

if (!empty($_GET['min_price']) && !empty($_GET['max_price'])) {
  $where[] = "e.ticket_price BETWEEN :minP AND :maxP";
  $params['minP'] = $_GET['min_price'];
  $params['maxP'] = $_GET['max_price'];
}

$sql = "SELECT e.*, s.name AS site_name, s.unesco_status
        FROM Events e
        JOIN HeritageSites s ON e.site_id = s.site_id";
if ($where) {
  $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY e.event_date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

$sites = $pdo->query('SELECT site_id, name FROM HeritageSites ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
$unesco_statuses = ['None', 'Tentative', 'World Heritage'];
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Manage Events</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  .filter-bar { background: #f8f9fa; border-radius: 8px; padding: 10px; margin-bottom: 15px; }
  .filter-bar label { font-size: 0.85rem; font-weight: 600; margin-bottom: 3px; }
  .filter-bar input, .filter-bar select { font-size: 0.9rem; }
  .table th, .table td { vertical-align: middle; }
</style>
</head>
<body>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="m-0">📅 Manage Events</h2>
    <a href="event_edit.php" class="btn btn-success">➕ Add Event</a>
  </div>

  <!-- 🔍 Compact Filter Bar -->
  <form method="get" class="filter-bar row g-2 align-items-end">
    <div class="col-md-2">
      <label>Search</label>
      <input type="text" name="q" class="form-control" placeholder="Name or Description" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
    </div>
    <div class="col-md-2">
      <label>Site</label>
      <select name="site_id" class="form-select">
        <option value="">All</option>
        <?php foreach ($sites as $s): ?>
          <option value="<?= $s['site_id'] ?>" <?= ($s['site_id'] == ($_GET['site_id'] ?? '')) ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <label>UNESCO</label>
      <select name="unesco_status" class="form-select">
        <option value="">All</option>
        <?php foreach ($unesco_statuses as $status): ?>
          <option value="<?= $status ?>" <?= ($status == ($_GET['unesco_status'] ?? '')) ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <label>From Date</label>
      <input type="date" name="from_date" class="form-control" value="<?= htmlspecialchars($_GET['from_date'] ?? '') ?>">
    </div>
    <div class="col-md-2">
      <label>To Date</label>
      <input type="date" name="to_date" class="form-control" value="<?= htmlspecialchars($_GET['to_date'] ?? '') ?>">
    </div>
    <div class="col-md-2">
      <label>Ticket (Tk)</label>
      <div class="d-flex">
        <input type="number" name="min_price" class="form-control me-1" min="<?= $minTicket ?>" max="<?= $maxTicket ?>" step="1" value="<?= htmlspecialchars($_GET['min_price'] ?? $minTicket) ?>">
        <input type="number" name="max_price" class="form-control" min="<?= $minTicket ?>" max="<?= $maxTicket ?>" step="1" value="<?= htmlspecialchars($_GET['max_price'] ?? $maxTicket) ?>">
      </div>
    </div>
    <div class="col-md-1">
      <button class="btn btn-primary w-100">Filter</button>
    </div>
  </form>

  <!-- 🧾 SQL Preview -->
  <div class="alert alert-secondary small">
    <strong>Executed Query:</strong><br><code><?= htmlspecialchars($sql) ?></code>
  </div>

  <!-- 🧾 Data Table -->
  <table class="table table-bordered table-striped">
    <thead class="table-dark">
      <tr>
        <th>Name</th>
        <th>Site</th>
        <th>UNESCO</th>
        <th>Date</th>
        <th>Time</th>
        <th>Ticket</th>
        <th>Capacity</th>
        <th>Description</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($events): foreach ($events as $e): ?>
      <tr>
        <td><?= htmlspecialchars($e['name']) ?></td>
        <td><?= htmlspecialchars($e['site_name']) ?></td>
        <td><?= htmlspecialchars($e['unesco_status']) ?></td>
        <td><?= htmlspecialchars($e['event_date']) ?></td>
        <td><?= htmlspecialchars($e['event_time']) ?></td>
        <td><?= htmlspecialchars($e['ticket_price']) ?></td>
        <td><?= htmlspecialchars($e['capacity']) ?></td>
        <td><?= htmlspecialchars(substr($e['description'], 0, 40)) ?>...</td>
        <td>
          <a href="event_edit.php?id=<?= $e['event_id'] ?>" class="btn btn-sm btn-secondary">Edit</a>
          <form method="post" class="d-inline" onsubmit="return confirm('Delete this event?');">
            <input type="hidden" name="delete_id" value="<?= $e['event_id'] ?>">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
          </form>
        </td>
      </tr>
      <?php endforeach; else: ?>
      <tr><td colspan="9" class="text-center text-muted">No events found</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
</body>
</html>
