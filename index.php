<?php
// C:\xampp\htdocs\Heritage-Database-Project\index.php
session_start();
include __DIR__ . '/includes/headerFooter.php';
require_once __DIR__ . '/includes/db_connect.php';

// Check if visitor is logged in
$visitor_logged_in = isset($_SESSION['visitor_id']);

// ---------- Filters ----------
$filter_name = trim($_GET['filter_name'] ?? '');
$filter_location = trim($_GET['filter_location'] ?? '');
$filter_type = trim($_GET['filter_type'] ?? '');
$filter_unesco = trim($_GET['filter_unesco'] ?? '');
$filter_price_min = (isset($_GET['filter_price_min']) && $_GET['filter_price_min'] !== '') ? (float)$_GET['filter_price_min'] : null;
$filter_price_max = (isset($_GET['filter_price_max']) && $_GET['filter_price_max'] !== '') ? (float)$_GET['filter_price_max'] : null;
$sort_price = trim($_GET['sort_price'] ?? '');

// ---------- Dropdown Data ----------
$locations = $pdo->query("SELECT DISTINCT location FROM HeritageSites WHERE location IS NOT NULL AND location!='' ORDER BY location")->fetchAll(PDO::FETCH_COLUMN);
$types = $pdo->query("SELECT DISTINCT type FROM HeritageSites WHERE type IS NOT NULL AND type!='' ORDER BY type")->fetchAll(PDO::FETCH_COLUMN);

// ---------- Get price boundaries ----------
$price_range = $pdo->query("SELECT MIN(ticket_price) AS min_price, MAX(ticket_price) AS max_price FROM HeritageSites")->fetch(PDO::FETCH_ASSOC);
$db_min_price = (float)($price_range['min_price'] ?? 0);
$db_max_price = (float)($price_range['max_price'] ?? 0);

// ---------- Build WHERE ----------
$where = ' WHERE 1=1';
$params = [];
if ($filter_name !== '') {
    $where .= ' AND name LIKE :name';
    $params['name'] = "%$filter_name%";
}
if ($filter_location !== '') {
    $where .= ' AND location = :location';
    $params['location'] = $filter_location;
}
if ($filter_type !== '') {
    $where .= ' AND type = :type';
    $params['type'] = $filter_type;
}
$validUnesco = ['None','Tentative','World Heritage'];
if ($filter_unesco !== '' && in_array($filter_unesco, $validUnesco, true)) {
    $where .= ' AND unesco_status = :unesco';
    $params['unesco'] = $filter_unesco;
}
if ($filter_price_min !== null) {
    $where .= ' AND ticket_price >= :min_price';
    $params['min_price'] = $filter_price_min;
}
if ($filter_price_max !== null) {
    $where .= ' AND ticket_price <= :max_price';
    $params['max_price'] = $filter_price_max;
}

// ---------- Order By ----------
$order_by = 'name ASC';
if ($sort_price === 'asc') $order_by = 'ticket_price ASC';
if ($sort_price === 'desc') $order_by = 'ticket_price DESC';

// ---------- Pagination ----------
$per_page = 6;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;

// ---------- Count total ----------
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM HeritageSites $where");
foreach ($params as $k => $v) $countStmt->bindValue(':'.$k, $v);
$countStmt->execute();
$total_sites = (int)$countStmt->fetchColumn();
$total_pages = max(1, (int)ceil($total_sites / $per_page));

// ---------- Fetch with LIMIT ----------
$stmt = $pdo->prepare("
    SELECT site_id, name, location, type, ticket_price, unesco_status 
    FROM HeritageSites 
    $where 
    ORDER BY $order_by 
    LIMIT :limit OFFSET :offset
");
foreach ($params as $k => $v) $stmt->bindValue(':'.$k, $v);
$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$sites = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ---------- Safe SQL Runner (predefined read-only queries) ----------
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$safe_queries = [
    // ORDER BY / SORT
    'orderby_ticket_desc' => [
        'title' => 'Order sites by ticket_price DESC (ORDER BY)',
        'sql' => "SELECT site_id, name, ticket_price FROM HeritageSites ORDER BY ticket_price DESC LIMIT 100"
    ],
    // GROUP BY + COUNT
    'count_sites_by_location' => [
        'title' => 'Count of sites by location (GROUP BY, COUNT)',
        'sql' => "SELECT location, COUNT(*) AS cnt FROM HeritageSites GROUP BY location ORDER BY cnt DESC"
    ],
    // AGGREGATES
    'price_aggregates' => [
        'title' => 'Aggregates: MIN, MAX, AVG, SUM on ticket_price',
        'sql' => "SELECT MIN(ticket_price) AS min_price, MAX(ticket_price) AS max_price, ROUND(AVG(ticket_price),2) AS avg_price, ROUND(SUM(ticket_price),2) AS sum_price FROM HeritageSites"
    ],
    // HAVING
    'locations_with_many_sites' => [
        'title' => 'Locations having > 2 sites (HAVING)',
        'sql' => "SELECT location, COUNT(*) AS cnt FROM HeritageSites GROUP BY location HAVING cnt > 2 ORDER BY cnt DESC"
    ],
    // LIKE
    'like_museum' => [
        'title' => "Sites where name or description LIKE '%museum%' (LIKE)",
        'sql' => "SELECT site_id, name, LEFT(description,120) AS excerpt FROM HeritageSites WHERE name LIKE '%museum%' OR description LIKE '%museum%' LIMIT 200"
    ],
    // Subquery (IN)
    'sites_with_bookings_in' => [
        'title' => 'Sites that have bookings (IN + subquery)',
        'sql' => "SELECT DISTINCT s.site_id, s.name FROM HeritageSites s WHERE s.site_id IN (SELECT site_id FROM Bookings WHERE site_id IS NOT NULL) ORDER BY s.name"
    ],
    // EXISTS example
    'sites_with_events_exists' => [
        'title' => 'Sites that have events (EXISTS)',
        'sql' => "SELECT s.site_id, s.name FROM HeritageSites s WHERE EXISTS (SELECT 1 FROM Events e WHERE e.site_id = s.site_id) ORDER BY s.name"
    ],
    // NOT IN / NOT EXISTS (MINUS equivalent)
    'sites_without_bookings' => [
        'title' => 'Sites without bookings (NOT IN / NOT EXISTS)',
        'sql' => "SELECT s.site_id, s.name FROM HeritageSites s WHERE NOT EXISTS (SELECT 1 FROM Bookings b WHERE b.site_id = s.site_id) ORDER BY s.name"
    ],
    // UNION
    'union_sites_and_events_names' => [
        'title' => 'Union of site names and event names (UNION)',
        'sql' => "SELECT name, 'site' AS type FROM HeritageSites UNION SELECT name, 'event' AS type FROM Events LIMIT 200"
    ],
    // Emulate INTERSECT (common to both) via JOIN
    'intersect_emulated_sites_with_both_booking_and_event' => [
        'title' => 'Sites that appear both in Bookings and Events (emulated INTERSECT via JOIN)',
        'sql' => "SELECT DISTINCT s.site_id, s.name FROM HeritageSites s JOIN Bookings b ON b.site_id = s.site_id JOIN Events e ON e.site_id = s.site_id ORDER BY s.name LIMIT 200"
    ],
    // Aggregates with join (SUM revenue)
    'site_revenue' => [
        'title' => 'Site revenue (SUM payments JOIN bookings) grouped by site',
        'sql' => "SELECT s.site_id, s.name, COALESCE(SUM(p.amount),0) AS revenue, COUNT(DISTINCT b.booking_id) AS bookings
FROM HeritageSites s
LEFT JOIN Bookings b ON b.site_id = s.site_id
LEFT JOIN Payments p ON p.booking_id = b.booking_id AND p.status = 'successful'
GROUP BY s.site_id
ORDER BY revenue DESC
LIMIT 200"
    ],
    // HAVING on aggregate (revenue threshold)
    'sites_revenue_gt_500' => [
        'title' => 'Sites with revenue > 500 (HAVING on SUM)',
        'sql' => "SELECT s.site_id, s.name, COALESCE(SUM(p.amount),0) AS revenue
FROM HeritageSites s
LEFT JOIN Bookings b ON b.site_id = s.site_id
LEFT JOIN Payments p ON p.booking_id = b.booking_id AND p.status = 'successful'
GROUP BY s.site_id
HAVING revenue > 500
ORDER BY revenue DESC
LIMIT 200"
    ],
    // COUNT + JOIN
    'top_sites_by_bookings' => [
        'title' => 'Top sites by bookings (COUNT + JOIN)',
        'sql' => "SELECT s.site_id, s.name, COUNT(b.booking_id) AS total_bookings
FROM HeritageSites s
LEFT JOIN Bookings b ON b.site_id = s.site_id
GROUP BY s.site_id
ORDER BY total_bookings DESC
LIMIT 10"
    ],
    // MIN / MAX
    'min_max_ticket_per_type' => [
        'title' => 'MIN and MAX ticket_price per type',
        'sql' => "SELECT type, MIN(ticket_price) AS min_price, MAX(ticket_price) AS max_price FROM HeritageSites GROUP BY type"
    ],
    // subquery in SELECT
    'site_with_latest_event_date' => [
        'title' => 'Site with its latest event date (subquery in SELECT)',
        'sql' => "SELECT s.site_id, s.name, (SELECT MAX(event_date) FROM Events e WHERE e.site_id = s.site_id) AS latest_event FROM HeritageSites s ORDER BY latest_event DESC LIMIT 100"
    ],
    // window function example (MySQL 8+)
    'rank_sites_by_price' => [
        'title' => 'Rank sites by ticket_price (window function, MySQL 8+)',
        'sql' => "SELECT site_id, name, ticket_price, RANK() OVER (ORDER BY ticket_price DESC) AS price_rank FROM HeritageSites"
    ],
    // Views demonstration (read-only: shows what a view would return)
    'view_sites_summary' => [
        'title' => 'Sites summary (like a VIEW) - count events & bookings per site',
        'sql' => "SELECT s.site_id, s.name, COUNT(DISTINCT e.event_id) AS event_count, COUNT(DISTINCT b.booking_id) AS booking_count
FROM HeritageSites s
LEFT JOIN Events e ON e.site_id = s.site_id
LEFT JOIN Bookings b ON b.site_id = s.site_id
GROUP BY s.site_id
ORDER BY booking_count DESC
LIMIT 200"
    ],
    // EXAMPLE: set operations emulation - MINUS using NOT EXISTS (since EXCEPT may not be available)
    'sites_in_bookings_not_in_reviews' => [
        'title' => 'Sites that have bookings but no reviews (MINUS-like via NOT EXISTS)',
        'sql' => "SELECT DISTINCT s.site_id, s.name FROM HeritageSites s
WHERE EXISTS (SELECT 1 FROM Bookings b WHERE b.site_id = s.site_id)
AND NOT EXISTS (SELECT 1 FROM Reviews r WHERE r.site_id = s.site_id)
ORDER BY s.name"
    ],
];

// execute runner if requested
$runner_result = null;
$runner_sql = '';
$runner_title = '';
$runner_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'run_safe_query') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $csrf)) {
        $runner_error = 'Invalid CSRF token for SQL Runner.';
    } else {
        $key = $_POST['query_key'] ?? '';
        if (!isset($safe_queries[$key])) {
            $runner_error = 'Invalid query selection.';
        } else {
            $runner_title = $safe_queries[$key]['title'];
            $runner_sql = $safe_queries[$key]['sql'];
            try {
                // All our queries are read-only SELECTs; use query()
                $stmtQ = $pdo->query($runner_sql);
                $runner_result = $stmtQ->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                $runner_error = 'Query error: ' . $e->getMessage();
            }
        }
    }
}

// helper
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_HTML5); }

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Heritage Explorer</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
  body {
    background-color: #f8f3e9;
  }
  .filter-card {
    background-color: #fffaf2;
    border: 1px solid #e0c79b;
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 20px;
    box-shadow: 0 2px 6px rgba(90, 56, 37, 0.1);
  }
  .site-card {
    background: #fffdf9;
    border: 1px solid #d8b98b;
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 25px;
    box-shadow: 0 3px 8px rgba(90, 56, 37, 0.15);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
  }
  .site-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 12px rgba(90, 56, 37, 0.25);
  }
  .site-card h4 a {
    color: #5a3825;
    text-decoration: none;
  }
  .site-card h4 a:hover {
    color: #b5853d;
  }
  h2 {
    font-family: 'Merriweather', serif;
    color: #4b2e17;
    margin-bottom: 20px;
  }
  .btn-primary {
    background-color: #b5853d;
    border: none;
  }
  .btn-primary:hover {
    background-color: #8f642a;
  }
  .btn-secondary {
    background-color: #d8b98b;
    border: none;
  }
  .sql-runner{
    margin-bottom: 10px;
  }
  footer {
    background-color: #3e2718 !important;
  }
</style>
</head>
<body>

<div class="container my-4">
  <h2>Explore Heritage Sites</h2>

  <!-- Filters -->
  <div class="filter-card">
    <form method="get" class="row g-3">
      <div class="col-md-3">
        <input type="text" name="filter_name" class="form-control" placeholder="Search by name" value="<?= h($filter_name) ?>">
      </div>
      <div class="col-md-2">
        <select name="filter_location" class="form-select">
          <option value="">All Locations</option>
          <?php foreach ($locations as $loc): ?>
            <option value="<?= h($loc) ?>" <?= $filter_location===$loc?'selected':'' ?>><?= h($loc) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <select name="filter_type" class="form-select">
          <option value="">All Types</option>
          <?php foreach ($types as $t): ?>
            <option value="<?= h($t) ?>" <?= $filter_type===$t?'selected':'' ?>><?= h($t) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <select name="filter_unesco" class="form-select">
          <option value="">UNESCO Any</option>
          <option value="World Heritage" <?= $filter_unesco==='World Heritage'?'selected':'' ?>>World Heritage</option>
          <option value="Tentative" <?= $filter_unesco==='Tentative'?'selected':'' ?>>Tentative</option>
          <option value="None" <?= $filter_unesco==='None'?'selected':'' ?>>None</option>
        </select>
      </div>
      <div class="col-md-1">
        <input type="number" name="filter_price_min" class="form-control" placeholder="Min" value="<?= h($filter_price_min ?? '') ?>">
      </div>
      <div class="col-md-1">
        <input type="number" name="filter_price_max" class="form-control" placeholder="Max" value="<?= h($filter_price_max ?? '') ?>">
      </div>
      <div class="col-md-1">
        <select name="sort_price" class="form-select">
          <option value="">Sort Price</option>
          <option value="asc" <?= $sort_price==='asc'?'selected':'' ?>>Low→High</option>
          <option value="desc" <?= $sort_price==='desc'?'selected':'' ?>>High→Low</option>
        </select>
      </div>
      <div class="col-md-2 d-grid">
        <button type="submit" class="btn btn-primary">Apply Filters</button>
      </div>
      <div class="col-md-1 d-grid">
        <a href="index.php" class="btn btn-secondary">Clear</a>
      </div>
    </form>
  </div>

  <!-- Safe SQL Runner -->
  <div class="sql-runner">
    <form method="post" class="row g-2 align-items-center">
      <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
      <input type="hidden" name="action" value="run_safe_query">
      <div class="col-md-6">
        <label class="form-label fw-bold">🧾 Safe SQL Runner — choose a predefined read-only query</label>
        <select name="query_key" class="form-select">
          <option value="">-- Select query --</option>
          <?php foreach ($safe_queries as $k => $q): ?>
            <option value="<?= h($k) ?>" <?= (isset($key) && $key === $k) ? 'selected' : '' ?>><?= h($q['title']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2 d-grid">
        <button class="btn btn-primary">Run Query</button>
      </div>
    </form>
  </div>

  <?php if ($runner_error): ?>
    <div class="alert alert-danger"><?= h($runner_error) ?></div>
  <?php endif; ?>

  <?php if ($runner_result !== null): ?>
    <div class="card mb-3 p-3">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <h5 class="mb-1"><?= h($runner_title) ?></h5>
          <small class="text-muted">Executed SQL:</small><pre style="font-size:0.85rem;"><?= h($runner_sql) ?></pre>
        </div>
        <div class="text-end">
          <small class="text-muted"><?= count($runner_result) ?> rows</small>
        </div>
      </div>

      <?php if (count($runner_result) === 0): ?>
        <div class="text-muted">No rows returned.</div>
      <?php else: ?>
        <div style="overflow:auto; max-height:420px;">
          <table class="table table-sm table-bordered">
            <thead class="table-dark">
              <tr>
                <?php foreach (array_keys($runner_result[0]) as $col): ?><th><?= h($col) ?></th><?php endforeach; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($runner_result as $row): ?>
                <tr>
                  <?php foreach ($row as $val): ?>
                    <td><?= h($val) ?></td>
                  <?php endforeach; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <!-- Sites -->
  <?php if (empty($sites)): ?>
    <div class="alert alert-info">No sites match your filters.</div>
  <?php else: ?>
    <div class="row">
      <?php foreach ($sites as $s): ?>
        <div class="col-md-6">
          <div class="site-card">
            <h4><a href="site_view.php?id=<?= (int)$s['site_id'] ?>"><?= h($s['name']) ?></a></h4>
            <p><strong>Location:</strong> <?= h($s['location']) ?></p>
            <p><strong>Type:</strong> <?= h($s['type']) ?></p>
            <p><strong>Ticket:</strong> <?= number_format((float)($s['ticket_price'] ?? 0), 2) ?></p>
            <p><strong>UNESCO:</strong> <?= h($s['unesco_status']) ?></p>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- Pagination -->
  <nav>
    <ul class="pagination justify-content-center">
      <?php
      // preserve GET params
      $baseParams = $_GET;
      for ($i=1; $i <= $total_pages; $i++): 
        $baseParams['page'] = $i;
      ?>
        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
          <a class="page-link" href="?<?= h(http_build_query($baseParams)) ?>"><?= $i ?></a>
        </li>
      <?php endfor; ?>
    </ul>
  </nav>

</div>

<footer class="bg-dark text-white text-center py-3 w-100" style="margin-top: 40px;">
  <div class="container position-relative">
    <p class="mb-0">&copy; <?= date('Y') ?> Heritage Explorer</p>
    <a href="/Heritage-Database-Project/admin/login.php" 
       class="text-white-50 small position-absolute bottom-0 end-0 me-2 mb-1"
       style="font-size: 0.75rem; text-decoration: none;">
       Admin Login
    </a>
  </div>
</footer>

</body>
</html>
