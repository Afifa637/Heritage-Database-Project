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
// SAFE SQL RUNNER (read-only queries) - covers Lab 3–6
// ----------------------
$safe_queries = [
  // ---------------- LAB 3: Filtering / Constraints / Range / Set Membership / Ordering ----------------
  'filter_by_keyword' => [
    'title' => 'Filter: Events containing keyword "Festival" (LIKE)',
    'sql' => "SELECT e.event_id, e.name, e.event_date, s.name AS site_name
FROM Events e
JOIN HeritageSites s ON e.site_id = s.site_id
WHERE e.name LIKE '%Festival%'
ORDER BY e.event_date ASC
LIMIT 200"
  ],
  'range_search_ticket_price' => [
    'title' => 'Range search: Events priced between 50 and 200',
    'sql' => "SELECT e.event_id, e.name, e.ticket_price, e.event_date, s.name AS site_name
FROM Events e
JOIN HeritageSites s ON e.site_id = s.site_id
WHERE e.ticket_price BETWEEN 50 AND 200
ORDER BY e.ticket_price ASC
LIMIT 200"
  ],
  'set_membership_in' => [
    'title' => 'Set membership: Events at selected UNESCO statuses',
    'sql' => "SELECT e.event_id, e.name, s.unesco_status, e.event_date
FROM Events e
JOIN HeritageSites s ON e.site_id = s.site_id
WHERE s.unesco_status IN ('World Heritage', 'Tentative')
ORDER BY s.unesco_status, e.event_date
LIMIT 200"
  ],
  'constraint_check_capacity' => [
    'title' => 'Constraint check: Events with zero or negative capacity',
    'sql' => "SELECT event_id, name, capacity, event_date
FROM Events
WHERE capacity <= 0
ORDER BY event_date DESC
LIMIT 100"
  ],
  'order_by_multiple_columns' => [
    'title' => 'Ordering: by ticket_price DESC then event_date ASC',
    'sql' => "SELECT e.event_id, e.name, e.ticket_price, e.event_date, s.name AS site_name
FROM Events e
JOIN HeritageSites s ON e.site_id = s.site_id
ORDER BY e.ticket_price DESC, e.event_date ASC
LIMIT 200"
  ],

  // ---------------- LAB 4: Aggregates / GROUP BY / HAVING ----------------
  'count_events_per_site' => [
    'title' => 'Aggregates: Count events per site',
    'sql' => "SELECT s.site_id, s.name AS site_name, COUNT(e.event_id) AS events_count
FROM HeritageSites s
LEFT JOIN Events e ON e.site_id = s.site_id
GROUP BY s.site_id
HAVING events_count >= 0
ORDER BY events_count DESC
LIMIT 200"
  ],
  'avg_price_by_month' => [
    'title' => 'Aggregates: Avg ticket price per month',
    'sql' => "SELECT DATE_FORMAT(event_date, '%Y-%m') AS month, ROUND(AVG(ticket_price),2) AS avg_price, COUNT(*) AS total_events
FROM Events
GROUP BY month
ORDER BY month DESC
LIMIT 200"
  ],
  'capacity_stats_unesco' => [
    'title' => 'Aggregates: Total capacity by UNESCO status (HAVING)',
    'sql' => "SELECT s.unesco_status, SUM(e.capacity) AS total_capacity, AVG(e.ticket_price) AS avg_price
FROM Events e
JOIN HeritageSites s ON e.site_id = s.site_id
GROUP BY s.unesco_status
HAVING total_capacity > 0
ORDER BY total_capacity DESC"
  ],

  // ---------------- LAB 5: Subqueries / Set Operations / Views ----------------
  'subquery_future_events' => [
    'title' => 'Subquery: Events at sites with >3 upcoming events',
    'sql' => "SELECT e.event_id, e.name, e.event_date, s.name AS site_name
FROM Events e
JOIN HeritageSites s ON e.site_id = s.site_id
WHERE e.site_id IN (
  SELECT site_id
  FROM Events
  WHERE event_date >= CURDATE()
  GROUP BY site_id
  HAVING COUNT(*) > 3
)
ORDER BY e.event_date ASC
LIMIT 300"
  ],
  'exists_notexists' => [
    'title' => 'Subquery: Events with bookings but no payments',
    'sql' => "SELECT DISTINCT e.event_id, e.name, s.name AS site_name
FROM Events e
JOIN HeritageSites s ON e.site_id = s.site_id
WHERE EXISTS (SELECT 1 FROM Bookings b WHERE b.event_id = e.event_id)
  AND NOT EXISTS (
    SELECT 1 FROM Payments p
    JOIN Bookings b2 ON p.booking_id = b2.booking_id
    WHERE b2.event_id = e.event_id AND p.status = 'successful'
  )
ORDER BY e.event_date DESC
LIMIT 300"
  ],
  'union_example' => [
    'title' => 'Set Operation: UNION — Events and Sites list',
    'sql' => "SELECT name AS item_name, 'Event' AS type FROM Events
UNION
SELECT name AS item_name, 'Site' AS type FROM HeritageSites
ORDER BY item_name
LIMIT 300"
  ],
  'intersect_emulation' => [
    'title' => 'INTERSECT emulation: Events that have both bookings and payments',
    'sql' => "SELECT DISTINCT e.event_id, e.name, s.name AS site_name
FROM Events e
JOIN HeritageSites s ON e.site_id = s.site_id
WHERE e.event_id IN (SELECT event_id FROM Bookings)
  AND e.event_id IN (
    SELECT b.event_id
    FROM Bookings b
    JOIN Payments p ON p.booking_id = b.booking_id AND p.status='successful'
  )
ORDER BY e.name
LIMIT 300"
  ],
  'minus_emulation' => [
    'title' => 'MINUS emulation: Events with bookings but no payments',
    'sql' => "SELECT DISTINCT e.event_id, e.name, s.name AS site_name
FROM Events e
JOIN HeritageSites s ON e.site_id = s.site_id
WHERE e.event_id IN (SELECT event_id FROM Bookings)
  AND e.event_id NOT IN (
    SELECT b.event_id
    FROM Bookings b
    JOIN Payments p ON p.booking_id = b.booking_id AND p.status='successful'
  )
ORDER BY e.event_date DESC
LIMIT 300"
  ],
  'view_like_revenue_summary' => [
    'title' => 'View-like derived table: Event revenue summary',
    'sql' => "SELECT e.event_id, e.name, IFNULL(SUM(p.amount),0) AS total_revenue, COUNT(DISTINCT b.booking_id) AS total_bookings
FROM Events e
LEFT JOIN Bookings b ON e.event_id = b.event_id
LEFT JOIN Payments p ON p.booking_id = b.booking_id AND p.status='successful'
GROUP BY e.event_id
ORDER BY total_revenue DESC
LIMIT 200"
  ],

  // ---------------- LAB 6: Joins (inner, outer, cross, natural, equi, non-equi, self) ----------------
  'inner_join_example' => [
    'title' => 'INNER JOIN: Events with corresponding sites',
    'sql' => "SELECT e.event_id, e.name AS event_name, s.name AS site_name, e.event_date, e.ticket_price
FROM Events e
INNER JOIN HeritageSites s ON e.site_id = s.site_id
ORDER BY e.event_date DESC
LIMIT 200"
  ],
  'left_join_example' => [
    'title' => 'LEFT JOIN: All events with optional bookings',
    'sql' => "SELECT e.event_id, e.name, COUNT(b.booking_id) AS total_bookings
FROM Events e
LEFT JOIN Bookings b ON e.event_id = b.event_id
GROUP BY e.event_id
ORDER BY total_bookings DESC
LIMIT 200"
  ],
  'right_join_emulation' => [
    'title' => 'RIGHT JOIN (emulated): Sites with or without events',
    'sql' => "SELECT s.site_id, s.name AS site_name, e.event_id, e.name AS event_name
FROM HeritageSites s
LEFT JOIN Events e ON s.site_id = e.site_id
ORDER BY s.name
LIMIT 200"
  ],
  'cross_join_example' => [
    'title' => 'CROSS JOIN: Cartesian small set (top 3 sites × top 3 events)',
    'sql' => "SELECT s.name AS site_name, e.name AS event_name
FROM (SELECT site_id, name FROM HeritageSites LIMIT 3) s
CROSS JOIN (SELECT event_id, name FROM Events LIMIT 3) e"
  ],
  'natural_join_emulation' => [
    'title' => 'NATURAL JOIN style: Explicit same-column join (site_id)',
    'sql' => "SELECT e.event_id, e.name AS event_name, s.name AS site_name, s.location
FROM Events e
JOIN HeritageSites s USING(site_id)
ORDER BY e.event_id
LIMIT 200"
  ],
  'equi_join_example' => [
    'title' => 'Equi JOIN: Events + Payments (matching booking.event_id)',
    'sql' => "SELECT e.event_id, e.name, SUM(p.amount) AS total_amount
FROM Events e
JOIN Bookings b ON e.event_id = b.event_id
JOIN Payments p ON p.booking_id = b.booking_id AND p.status='successful'
GROUP BY e.event_id
ORDER BY total_amount DESC
LIMIT 200"
  ],
  'non_equi_join_example' => [
    'title' => 'Non-equi JOIN: Events priced above average ticket',
    'sql' => "SELECT e.event_id, e.name, e.ticket_price
FROM Events e
JOIN (SELECT AVG(ticket_price) AS avg_price FROM Events) a
  ON e.ticket_price > a.avg_price
ORDER BY e.ticket_price DESC
LIMIT 200"
  ],
  'self_join_example' => [
    'title' => 'SELF JOIN: Events on same date (different sites)',
    'sql' => "SELECT e1.event_id AS event1, e1.name AS name1, e2.event_id AS event2, e2.name AS name2, e1.event_date
FROM Events e1
JOIN Events e2 ON e1.event_date = e2.event_date AND e1.event_id <> e2.event_id
ORDER BY e1.event_date DESC
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
      <label>Date</label>
      <input type="date" name="from_date" class="form-control" value="<?= h($from_date) ?>">
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
  <div class="card mb-3 p-3 shadow-sm">
      <form method="post" class="row g-2 align-items-center">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="action" value="run_query">
        <div class="col-md-8">
          <select name="query_key" class="form-select" required>
            <option value="">-- Select Demo Query --</option>
            <?php foreach ($safe_queries as $k => $q): ?>
              <option value="<?= htmlspecialchars($k) ?>" <?= ($selected_query_key === $k) ? 'selected' : '' ?>><?= htmlspecialchars($q['title']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <button class="btn btn-primary w-100">Run Selected Query</button>
        </div>
      </form>
    </div>

    <?php if ($query_result !== null): ?>
      <div class="card p-3 mb-4">
        <h6>Query Result: <?= htmlspecialchars($safe_queries[$selected_query_key]['title'] ?? '') ?> (<?= count($query_result) ?> rows)</h6>
        <?php if (count($query_result) === 0): ?>
          <div class="text-muted">No results found.</div>
        <?php else: ?>
          <div style="overflow:auto; max-height:480px;">
            <table class="table table-bordered table-sm">
              <thead class="table-dark">
                <tr>
                  <?php foreach (array_keys($query_result[0]) as $col): ?>
                    <th><?= htmlspecialchars($col) ?></th>
                  <?php endforeach; ?>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($query_result as $row): ?>
                  <tr>
                    <?php foreach ($row as $val): ?>
                      <td><?= htmlspecialchars((string)$val) ?></td>
                    <?php endforeach; ?>
                  </tr>
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
