<?php
// manage_events.php (updated with SQL Runner)
declare(strict_types=1);
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

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_HTML5); }

$messages = [];
$errors = [];

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
  $del = (int)$_POST['delete_id'];
  $csrf = $_POST['csrf_token'] ?? '';
  if (!hash_equals($_SESSION['csrf_token'], $csrf)) {
    http_response_code(400);
    $errors[] = "Invalid CSRF token.";
  } else {
    // safe deletion
    $pdo->prepare('DELETE FROM Events WHERE event_id = :id')->execute(['id' => $del]);
    $messages[] = "Event #{$del} deleted.";
    header('Location: manage_events.php');
    exit;
  }
}

// === Dynamic thresholds ===
$priceRange = $pdo->query("SELECT MIN(ticket_price) AS min_price, MAX(ticket_price) AS max_price FROM Events")->fetch(PDO::FETCH_ASSOC);
$minTicket = $priceRange['min_price'] ?? 0;
$maxTicket = ($priceRange['max_price'] ?? 0) + 1; // +1 Taka increment

// === Filters (validated / normalized) ===
$where = [];
$params = [];

$q = trim($_GET['q'] ?? '');
$site_id = isset($_GET['site_id']) && $_GET['site_id'] !== '' ? (int)$_GET['site_id'] : null;
$unesco_status = trim($_GET['unesco_status'] ?? '');
$from_date = trim($_GET['from_date'] ?? '');
$to_date = trim($_GET['to_date'] ?? '');
$min_price = isset($_GET['min_price']) && $_GET['min_price'] !== '' ? floatval($_GET['min_price']) : null;
$max_price = isset($_GET['max_price']) && $_GET['max_price'] !== '' ? floatval($_GET['max_price']) : null;
$sort = $_GET['sort'] ?? 'event_date';
$order = (($_GET['order'] ?? 'DESC') === 'ASC') ? 'ASC' : 'DESC';

// basic q
if ($q !== '') {
  $where[] = "(e.name LIKE :kw OR e.description LIKE :kw)";
  $params['kw'] = "%$q%";
}

if ($site_id !== null) {
  $where[] = "e.site_id = :sid";
  $params['sid'] = $site_id;
}

if ($unesco_status !== '') {
  $where[] = "s.unesco_status = :unesco";
  $params['unesco'] = $unesco_status;
}

if ($from_date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from_date)) {
  $where[] = "e.event_date >= :fromD";
  $params['fromD'] = $from_date;
}
if ($to_date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to_date)) {
  $where[] = "e.event_date <= :toD";
  $params['toD'] = $to_date;
}

if ($min_price !== null && $max_price !== null && $min_price > $max_price) {
  // swap if user supplied backwards thresholds
  $tmp = $min_price; $min_price = $max_price; $max_price = $tmp;
}
if ($min_price !== null) { $where[] = "e.ticket_price >= :minP"; $params['minP'] = $min_price; }
if ($max_price !== null) { $where[] = "e.ticket_price <= :maxP"; $params['maxP'] = $max_price; }

// protect sorting column
$allowedSorts = ['event_date','name','ticket_price','capacity','site_name'];
if (!in_array($sort, $allowedSorts, true)) $sort = 'event_date';

// Build events listing SQL
$sql = "SELECT e.*, s.name AS site_name, s.unesco_status
        FROM Events e
        JOIN HeritageSites s ON e.site_id = s.site_id";

if ($where) {
  $sql .= " WHERE " . implode(" AND ", $where);
}

// map site_name sort to joined column
$orderBy = ($sort === 'site_name') ? 'site_name' : "e.$sort";
$sql .= " ORDER BY {$orderBy} {$order}";

// prepare and execute
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// sites for filter
$sites = $pdo->query('SELECT site_id, name FROM HeritageSites ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
$unesco_statuses = ['None', 'Tentative', 'World Heritage'];

// ----------------------
// SAFE SQL RUNNER (read-only queries) - whitelist
// ----------------------
$safe_queries = [
  'events_next_30' => [
    'title' => 'Events in next 30 days',
    'sql' => "SELECT e.event_id, e.name, e.event_date, e.event_time, s.name AS site_name, e.ticket_price
FROM Events e
JOIN HeritageSites s ON e.site_id = s.site_id
WHERE e.event_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
ORDER BY e.event_date ASC
LIMIT 500"
  ],
  'most_expensive_events' => [
    'title' => 'Top 100 most expensive events by ticket_price',
    'sql' => "SELECT e.event_id, e.name, s.name AS site_name, e.ticket_price, e.event_date
FROM Events e
JOIN HeritageSites s ON e.site_id = s.site_id
ORDER BY e.ticket_price DESC
LIMIT 100"
  ],
  'events_per_site' => [
    'title' => 'Number of events per site',
    'sql' => "SELECT s.site_id, s.name AS site_name, COUNT(e.event_id) AS events_count
FROM HeritageSites s
LEFT JOIN Events e ON e.site_id = s.site_id
GROUP BY s.site_id
ORDER BY events_count DESC
LIMIT 200"
  ],
  'upcoming_capacity_summary' => [
    'title' => 'Upcoming events capacity summary (next 90 days)',
    'sql' => "SELECT COUNT(*) AS events_count, SUM(capacity) AS total_capacity, AVG(ticket_price) AS avg_ticket
FROM Events
WHERE event_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)"
  ],
  'events_with_no_bookings' => [
    'title' => 'Events with no bookings',
    'sql' => "SELECT e.event_id, e.name, s.name AS site_name, e.event_date
FROM Events e
LEFT JOIN Bookings b ON b.event_id = e.event_id
JOIN HeritageSites s ON e.site_id = s.site_id
WHERE b.booking_id IS NULL
ORDER BY e.event_date DESC
LIMIT 500"
  ],
  'events_by_unesco_status' => [
    'title' => 'Events grouped by site UNESCO status',
    'sql' => "SELECT s.unesco_status, COUNT(e.event_id) AS events_count
FROM Events e
JOIN HeritageSites s ON e.site_id = s.site_id
GROUP BY s.unesco_status"
  ],
  'events_search_sample' => [
    'title' => 'Sample text search in event descriptions (first 200 matches)',
    'sql' => "SELECT e.event_id, e.name, LEFT(e.description,200) AS excerpt, s.name AS site_name
FROM Events e
JOIN HeritageSites s ON e.site_id = s.site_id
WHERE MATCH(e.description) AGAINST ('+festival +culture' IN BOOLEAN MODE)
LIMIT 200"
  ],
  'revenue_by_event' => [
    'title' => 'Revenue by event (payments sum) — read-only',
    'sql' => "SELECT e.event_id, e.name, COALESCE(SUM(p.amount),0) AS total_collected
FROM Events e
LEFT JOIN Bookings b ON b.event_id = e.event_id
LEFT JOIN Payments p ON p.booking_id = b.booking_id AND p.status = 'successful'
GROUP BY e.event_id
ORDER BY total_collected DESC
LIMIT 200"
  ],
];

// run SQL Runner (if requested)
$query_result = null;
$selected_query_key = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['action']) && $_POST['action'] === 'run_query')) {
  $csrf = $_POST['csrf_token'] ?? '';
  if (!hash_equals($_SESSION['csrf_token'], $csrf)) {
    $errors[] = 'Invalid CSRF for SQL Runner';
  } else {
    $qkey = $_POST['query_key'] ?? '';
    if (!isset($safe_queries[$qkey])) {
      $errors[] = 'Invalid query selected.';
    } else {
      $selected_query_key = $qkey;
      try {
        // read-only run
        $stmtQ = $pdo->query($safe_queries[$qkey]['sql']);
        $query_result = $stmtQ->fetchAll(PDO::FETCH_ASSOC);
        $messages[] = 'Query executed.';
      } catch (Exception $e) {
        $errors[] = 'Query failed: ' . $e->getMessage();
      }
    }
  }
}
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
  .sql-card { background:#fff7eb; padding:0.8rem; border-radius:8px; margin-bottom:12px; }
</style>
</head>
<body>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="m-0">📅 Manage Events</h2>
    <a href="event_edit.php" class="btn btn-success">➕ Add Event</a>
  </div>

  <?php foreach ($messages as $m): ?><div class="alert alert-success"><?= h($m) ?></div><?php endforeach; ?>
  <?php foreach ($errors as $e): ?><div class="alert alert-danger"><?= h($e) ?></div><?php endforeach; ?>

  <!-- 🔍 Compact Filter Bar -->
  <form method="get" class="filter-bar row g-2 align-items-end">
    <div class="col-md-2">
      <label>Search</label>
      <input type="text" name="q" class="form-control" placeholder="Name or Description" value="<?= h($q) ?>">
    </div>
    <div class="col-md-2">
      <label>Site</label>
      <select name="site_id" class="form-select">
        <option value="">All</option>
        <?php foreach ($sites as $s): ?>
          <option value="<?= (int)$s['site_id'] ?>" <?= ($site_id === (int)$s['site_id']) ? 'selected' : '' ?>><?= h($s['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <label>UNESCO</label>
      <select name="unesco_status" class="form-select">
        <option value="">All</option>
        <?php foreach ($unesco_statuses as $status): ?>
          <option value="<?= h($status) ?>" <?= ($unesco_status === $status) ? 'selected' : '' ?>><?= h($status) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <label>From Date</label>
      <input type="date" name="from_date" class="form-control" value="<?= h($from_date) ?>">
    </div>
    <div class="col-md-2">
      <label>To Date</label>
      <input type="date" name="to_date" class="form-control" value="<?= h($to_date) ?>">
    </div>
    <div class="col-md-2">
      <label>Ticket (Tk)</label>
      <div class="d-flex">
        <input type="number" name="min_price" class="form-control me-1" min="<?= h($minTicket) ?>" max="<?= h($maxTicket) ?>" step="1" value="<?= h($min_price ?? $minTicket) ?>">
        <input type="number" name="max_price" class="form-control" min="<?= h($minTicket) ?>" max="<?= h($maxTicket) ?>" step="1" value="<?= h($max_price ?? $maxTicket) ?>">
      </div>
    </div>
    <div class="col-md-2 mt-2">
      <label>Sort</label>
      <div class="d-flex">
        <select name="sort" class="form-select me-1">
          <option value="event_date" <?= $sort==='event_date'?'selected':'' ?>>Date</option>
          <option value="name" <?= $sort==='name'?'selected':'' ?>>Name</option>
          <option value="ticket_price" <?= $sort==='ticket_price'?'selected':'' ?>>Ticket</option>
          <option value="capacity" <?= $sort==='capacity'?'selected':'' ?>>Capacity</option>
          <option value="site_name" <?= $sort==='site_name'?'selected':'' ?>>Site</option>
        </select>
        <select name="order" class="form-select">
          <option value="DESC" <?= $order==='DESC'?'selected':'' ?>>⬇️</option>
          <option value="ASC" <?= $order==='ASC'?'selected':'' ?>>⬆️</option>
        </select>
      </div>
    </div>
    <div class="col-md-1 mt-3">
      <button class="btn btn-primary w-100">Filter</button>
    </div>
  </form>

  <!-- SQL Runner -->
  <div class="sql-card row g-2 align-items-center">
    <div class="col-md-6">
      <strong>🧾 Safe SQL Runner</strong>
      <div class="small text-muted">Choose a predefined read-only query to demonstrate joins/aggregates/filters. Results displayed below.</div>
    </div>
    <div class="col-md-4">
      <form method="post" class="d-flex gap-2">
        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="action" value="run_query">
        <select name="query_key" class="form-select" required>
          <option value="">-- Select query --</option>
          <?php foreach ($safe_queries as $k => $qi): ?>
            <option value="<?= h($k) ?>" <?= ($selected_query_key === $k) ? 'selected' : '' ?>><?= h($qi['title']) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-primary">Run</button>
      </form>
    </div>
    <div class="col-md-2 text-end">
      <a href="event_edit.php" class="btn btn-outline-secondary">Add Event</a>
    </div>
  </div>

  <?php if ($query_result !== null): ?>
    <div class="card mb-3 p-3">
      <h6>Query Result: <?= h($safe_queries[$selected_query_key]['title'] ?? '') ?> (<?= count($query_result) ?> rows)</h6>
      <?php if (count($query_result) === 0): ?>
        <div class="text-muted">No rows returned.</div>
      <?php else: ?>
        <div style="overflow:auto; max-height:420px;">
          <table class="table table-sm table-bordered">
            <thead class="table-dark">
              <tr>
                <?php foreach (array_keys($query_result[0]) as $col): ?><th><?= h($col) ?></th><?php endforeach; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($query_result as $row): ?>
                <tr><?php foreach ($row as $cell): ?><td><?= h($cell) ?></td><?php endforeach; ?></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <!-- 🧾 SQL Preview (Events listing) -->
  <div class="alert alert-secondary small">
    <strong>Executed Query (events list):</strong><br><code><?= h($sql) ?></code>
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
        <td><?= h($e['name']) ?></td>
        <td><?= h($e['site_name']) ?></td>
        <td><?= h($e['unesco_status']) ?></td>
        <td><?= h($e['event_date']) ?></td>
        <td><?= h($e['event_time']) ?></td>
        <td><?= number_format((float)$e['ticket_price'], 2) ?></td>
        <td><?= (int)$e['capacity'] ?></td>
        <td><?= h(substr($e['description'] ?? '', 0, 40)) ?>...</td>
        <td>
          <a href="event_edit.php?id=<?= (int)$e['event_id'] ?>" class="btn btn-sm btn-secondary">Edit</a>
          <form method="post" class="d-inline" onsubmit="return confirm('Delete this event?');">
            <input type="hidden" name="delete_id" value="<?= (int)$e['event_id'] ?>">
            <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
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
