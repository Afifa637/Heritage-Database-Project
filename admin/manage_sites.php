<?php
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

//  . FILTERS & PAGINATION (validated)  .
$filter_name = trim($_GET['filter_name'] ?? '');
$filter_location = trim($_GET['filter_location'] ?? '');
$filter_type = trim($_GET['filter_type'] ?? '');
$filter_unesco = trim($_GET['filter_unesco'] ?? ''); // '', 'None', 'Tentative', 'World Heritage'
$filter_price_min = (isset($_GET['filter_price_min']) && $_GET['filter_price_min'] !== '') ? (float)$_GET['filter_price_min'] : null;
$filter_price_max = (isset($_GET['filter_price_max']) && $_GET['filter_price_max'] !== '') ? (float)$_GET['filter_price_max'] : null;
$sort_price = trim($_GET['sort_price'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 5; // rows per page (changeable)

//  . Dynamic dropdowns  .
$locations = $pdo->query("SELECT DISTINCT location FROM HeritageSites WHERE location IS NOT NULL AND location != '' ORDER BY location ASC")->fetchAll(PDO::FETCH_COLUMN);
$types = $pdo->query("SELECT DISTINCT type FROM HeritageSites WHERE type IS NOT NULL AND type != '' ORDER BY type ASC")->fetchAll(PDO::FETCH_COLUMN);

//  . Global stats (for min/max attributes)  .
$globalStats = $pdo->query("SELECT COUNT(*) AS total, MIN(ticket_price) AS min_price, MAX(ticket_price) AS max_price, AVG(ticket_price) AS avg_price FROM HeritageSites")->fetch(PDO::FETCH_ASSOC);
$globalMin = $globalStats['min_price'] !== null ? (float)$globalStats['min_price'] : 0.00;
$globalMax = $globalStats['max_price'] !== null ? (float)$globalStats['max_price'] : 0.00;
$globalAvg = $globalStats['avg_price'] !== null ? (float)$globalStats['avg_price'] : 0.00;

//  . Build WHERE & params (safe prepared statements)  .
$where = ' WHERE 1=1';
$params = [];

if ($filter_name !== '') {
    $where .= ' AND name LIKE :filter_name';
    $params['filter_name'] = "%$filter_name%";
}
if ($filter_location !== '') {
    $where .= ' AND location = :filter_location';
    $params['filter_location'] = $filter_location;
}
if ($filter_type !== '') {
    $where .= ' AND type = :filter_type';
    $params['filter_type'] = $filter_type;
}
$validUnesco = ['None','Tentative','World Heritage'];
if ($filter_unesco !== '' && in_array($filter_unesco, $validUnesco, true)) {
    $where .= ' AND unesco_status = :filter_unesco';
    $params['filter_unesco'] = $filter_unesco;
}
if ($filter_price_min !== null) {
    $where .= ' AND ticket_price >= :price_min';
    $params['price_min'] = $filter_price_min;
}
if ($filter_price_max !== null) {
    $where .= ' AND ticket_price <= :price_max';
    $params['price_max'] = $filter_price_max;
}

//  . ORDER BY (whitelisted)  .
$order_by = 'created_at DESC';
if ($sort_price === 'asc') $order_by = 'ticket_price ASC';
if ($sort_price === 'desc') $order_by = 'ticket_price DESC';

//  . CSV EXPORT  .
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $exportQuery = "SELECT site_id, name, location, type, opening_hours, ticket_price, unesco_status, description, created_at FROM HeritageSites $where ORDER BY $order_by";
    $exportStmt = $pdo->prepare($exportQuery);
    foreach ($params as $k => $v) $exportStmt->bindValue(':'.$k, $v);
    $exportStmt->execute();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="heritage_sites_export_' . date('Ymd_His') . '.csv"');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['site_id','name','location','type','opening_hours','ticket_price','unesco_status','description','created_at']);
    while ($row = $exportStmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($out, [
            $row['site_id'],
            $row['name'],
            $row['location'],
            $row['type'],
            $row['opening_hours'],
            $row['ticket_price'],
            $row['unesco_status'],
            $row['description'],
            $row['created_at'],
        ]);
    }
    fclose($out);
    exit;
}

//  . Deletion (POST + CSRF)  .
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $del = (int)$_POST['delete_id'];
    $csrf = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $csrf)) {
        http_response_code(400);
        $errors[] = "Invalid CSRF token.";
    } else {
        $delStmt = $pdo->prepare('DELETE FROM HeritageSites WHERE site_id = :id');
        $delStmt->execute(['id' => $del]);
        $messages[] = "Site #{$del} deleted.";
        header('Location: manage_sites.php');
        exit;
    }
}

//  . Pagination counts  .
$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM HeritageSites $where");
$totalStmt->execute($params);
$totalRows = (int)$totalStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$offset = ($page - 1) * $perPage;

//  . Fetch filtered/paginated rows  .
$query = "SELECT * FROM HeritageSites $where ORDER BY $order_by LIMIT :offset, :perPage";
$stmt = $pdo->prepare($query);
foreach ($params as $k => $v) $stmt->bindValue(':'.$k, $v);
$stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
$stmt->bindValue(':perPage', (int)$perPage, PDO::PARAM_INT);
$stmt->execute();
$sites = $stmt->fetchAll(PDO::FETCH_ASSOC);

//  . Filtered stats (for display)  .
$filteredStatsStmt = $pdo->prepare("SELECT COUNT(*) AS total, MIN(ticket_price) AS min_price, MAX(ticket_price) AS max_price, AVG(ticket_price) AS avg_price FROM HeritageSites $where");
$filteredStatsStmt->execute($params);
$filteredStats = $filteredStatsStmt->fetch(PDO::FETCH_ASSOC);

$safe_queries = [
    //  . LAB 2: DDL / DML inspection (read-only)  .
    'ddl_tables_columns' => [
        'title' => 'DDL: Tables & columns (information_schema)',
        'sql' => "SELECT TABLE_SCHEMA, TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
ORDER BY TABLE_NAME, ORDINAL_POSITION
LIMIT 500"
    ],
    'ddl_table_list' => [
        'title' => 'DDL: List of tables (information_schema)',
        'sql' => "SELECT TABLE_NAME, TABLE_ROWS, CREATE_TIME
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
ORDER BY TABLE_NAME
LIMIT 200"
    ],
    'view_definitions' => [
        'title' => 'DDL: View definitions (if any)',
        'sql' => "SELECT TABLE_NAME AS view_name, VIEW_DEFINITION
FROM information_schema.VIEWS
WHERE TABLE_SCHEMA = DATABASE()
LIMIT 200"
    ],

    //  . LAB 3: Filtering, Constraints, Range Search, Set Membership, Ordering  .
    'filter_by_type_and_location' => [
        'title' => 'Filter: Sites by type and location (example)',
        'sql' => "SELECT site_id, name, location, type, ticket_price
FROM HeritageSites
WHERE type = 'Mosque' AND location LIKE '%Bagerhat%'
ORDER BY ticket_price DESC
LIMIT 200"
    ],
    'range_search_price' => [
        'title' => 'Range search: Ticket price between min and max',
        'sql' => "SELECT site_id, name, ticket_price
FROM HeritageSites
WHERE ticket_price BETWEEN 30 AND 120
ORDER BY ticket_price ASC
LIMIT 200"
    ],
    'set_membership_in' => [
        'title' => 'Set membership: Sites of selected types (IN)',
        'sql' => "SELECT site_id, name, type
FROM HeritageSites
WHERE type IN ('Museum','Monastery','Ancient City')
ORDER BY type, name
LIMIT 200"
    ],
    'ordering_by_multiple_columns' => [
        'title' => 'Ordering: by UNESCO status then ticket price',
        'sql' => "SELECT site_id, name, unesco_status, ticket_price
FROM HeritageSites
ORDER BY FIELD(unesco_status,'World Heritage','Tentative','None'), ticket_price DESC
LIMIT 200"
    ],
    'constraint_checks_bookings' => [
        'title' => 'Constraint check: Bookings with both site_id and event_id (should be prevented)',
        'sql' => "SELECT booking_id, visitor_id, site_id, event_id
FROM Bookings
WHERE site_id IS NOT NULL AND event_id IS NOT NULL
LIMIT 100"
    ],

    //  . LAB 4: Aggregates, GROUP BY, HAVING  .
    'sites_by_unesco' => [
        'title' => 'Sites grouped by UNESCO status (COUNT/ORDER)',
        'sql' => "SELECT unesco_status, COUNT(*) AS sites_count
FROM HeritageSites
GROUP BY unesco_status
HAVING sites_count > 0
ORDER BY sites_count DESC"
    ],
    'avg_price_by_location' => [
        'title' => 'Aggregates: Avg ticket price by location',
        'sql' => "SELECT location, ROUND(AVG(ticket_price),2) AS avg_price, COUNT(*) AS sites_count
FROM HeritageSites
GROUP BY location
HAVING sites_count >= 1
ORDER BY avg_price DESC
LIMIT 200"
    ],
    'revenue_by_method_having' => [
        'title' => 'Aggregates+HAVING: Revenue by method (show only >0)',
        'sql' => "SELECT method, ROUND(SUM(amount),2) AS total_revenue, COUNT(*) AS payments_count
FROM Payments
WHERE status IN ('successful','success')
GROUP BY method
HAVING total_revenue > 0
ORDER BY total_revenue DESC"
    ],

    //  . LAB 5: Subqueries & Set Operations (UNION, INTERSECT emulation, EXCEPT emulation), Views  .
    'sites_with_events_subquery' => [
        'title' => 'Subquery: Sites that have at least one future event',
        'sql' => "SELECT name, site_id
FROM HeritageSites
WHERE site_id IN (SELECT DISTINCT site_id FROM Events WHERE event_date >= CURDATE())
ORDER BY name
LIMIT 300"
    ],
    'sites_with_bookings_but_no_reviews' => [
        'title' => 'Subquery: Sites booked but not reviewed (EXISTS / NOT EXISTS)',
        'sql' => "SELECT DISTINCT hs.site_id, hs.name
FROM HeritageSites hs
WHERE EXISTS (SELECT 1 FROM Bookings b WHERE b.site_id = hs.site_id)
  AND NOT EXISTS (SELECT 1 FROM Reviews r WHERE r.site_id = hs.site_id)
ORDER BY hs.name
LIMIT 300"
    ],
    'union_example_sites_and_museums' => [
        'title' => 'Set op (UNION): Site names (Museums) + Event names (as items) — union example',
        'sql' => "SELECT name AS title, 'site' AS type FROM HeritageSites WHERE type = 'Museum'
UNION
SELECT name AS title, 'event' AS type FROM Events
ORDER BY title
LIMIT 500"
    ],
    'intersect_emulation' => [
        'title' => 'Intersect emulation: Sites that appear in both Bookings and Reviews',
        'sql' => "SELECT DISTINCT s.site_id, s.name
FROM HeritageSites s
JOIN Bookings b ON s.site_id = b.site_id
JOIN Reviews r ON s.site_id = r.site_id
ORDER BY s.name
LIMIT 500"
    ],
    'except_emulation' => [
        'title' => 'Except emulation: Sites with bookings but not with successful payments (LEFT JOIN/NULL)',
        'sql' => "SELECT DISTINCT s.site_id, s.name
FROM HeritageSites s
JOIN Bookings b ON s.site_id = b.site_id
LEFT JOIN Payments p ON b.booking_id = p.booking_id AND p.status IN ('successful','success')
WHERE p.payment_id IS NULL
ORDER BY s.name
LIMIT 500"
    ],
    'views_example' => [
        'title' => 'View-like: top revenue sites (derived view SELECT)',
        'sql' => "SELECT s.site_id, s.name, ROUND(IFNULL(SUM(p.amount),0),2) AS revenue
FROM HeritageSites s
LEFT JOIN Bookings b ON b.site_id = s.site_id
LEFT JOIN Payments p ON p.booking_id = b.booking_id AND p.status IN ('successful','success')
GROUP BY s.site_id
ORDER BY revenue DESC
LIMIT 200"
    ],

    //  . LAB 6: JOINS — inner, left, right (emulated), cross, natural, self-join, non-equi, equi join  .
    'inner_join_bookings_payments' => [
        'title' => 'Inner JOIN: Bookings with successful payments (joined details)',
        'sql' => "SELECT b.booking_id, v.full_name AS visitor, hs.name AS site, p.amount, p.method, p.status
FROM Bookings b
INNER JOIN Visitors v ON b.visitor_id = v.visitor_id
INNER JOIN Payments p ON b.booking_id = p.booking_id AND p.status IN ('successful','success')
LEFT JOIN HeritageSites hs ON b.site_id = hs.site_id
ORDER BY b.booking_date DESC
LIMIT 200"
    ],
    'left_join_sites_events' => [
        'title' => 'LEFT JOIN: sites and (optional) upcoming events count',
        'sql' => "SELECT s.site_id, s.name, COUNT(e.event_id) AS upcoming_events
FROM HeritageSites s
LEFT JOIN Events e ON e.site_id = s.site_id AND e.event_date >= CURDATE()
GROUP BY s.site_id
ORDER BY upcoming_events DESC
LIMIT 200"
    ],
    'right_join_emulation' => [
        'title' => 'Right join emulation: events with site info (emulate RIGHT JOIN via LEFT JOIN swap)',
        'sql' => "SELECT e.event_id, e.name AS event_name, COALESCE(s.name,'(site removed)') AS site_name, e.event_date
FROM Events e
LEFT JOIN HeritageSites s ON e.site_id = s.site_id
ORDER BY e.event_date DESC
LIMIT 200"
    ],
    'cross_join_example' => [
        'title' => 'CROSS JOIN example: pair top-5 guides x top-5 sites (cartesian small set)',
        'sql' => "SELECT g.full_name AS guide, s.name AS site
FROM (SELECT guide_id, full_name FROM Guides ORDER BY guide_id LIMIT 5) g
CROSS JOIN (SELECT site_id, name FROM HeritageSites ORDER BY site_id LIMIT 5) s
LIMIT 200"
    ],
    'natural_join_example' => [
        'title' => 'NATURAL JOIN example (if column names match) — safe demo with alias',
        'sql' => "SELECT a.assign_id, g.full_name AS guide_name, COALESCE(h.name,'(site)') AS site_name, a.shift_time
FROM Assignments a
JOIN Guides g ON a.guide_id = g.guide_id
LEFT JOIN HeritageSites h ON a.site_id = h.site_id
ORDER BY a.assign_id
LIMIT 200"
    ],
    'self_join_guides' => [
        'title' => 'SELF JOIN: guides paired by same specialization (mentorship pairs)',
        'sql' => "SELECT g1.guide_id AS mentor_id, g1.full_name AS mentor, g2.guide_id AS mentee_id, g2.full_name AS mentee, g1.specialization
FROM Guides g1
JOIN Guides g2 ON g1.specialization = g2.specialization AND g1.guide_id <> g2.guide_id
ORDER BY g1.specialization, g1.full_name
LIMIT 200"
    ],
    'non_equi_join_example' => [
        'title' => 'Non-equi join: guides whose salary is > average salary (join with aggregate subquery)',
        'sql' => "SELECT g.guide_id, g.full_name, g.salary
FROM Guides g
JOIN (SELECT ROUND(AVG(salary),2) AS avg_salary FROM Guides) a
  ON g.salary > a.avg_salary
ORDER BY g.salary DESC
LIMIT 200"
    ],
    'equi_join_example_assignments' => [
        'title' => 'Equi-join: assignments with guide & site names (typical JOIN on equality)',
        'sql' => "SELECT a.assign_id, g.full_name AS guide, COALESCE(s.name,'(site removed)') AS site, a.shift_time
FROM Assignments a
JOIN Guides g ON a.guide_id = g.guide_id
LEFT JOIN HeritageSites s ON a.site_id = s.site_id
ORDER BY a.assign_id
LIMIT 40"
    ],

    //  . Practical / mixed queries useful for labs  .
    'top_sites_by_bookings' => [
        'title' => 'Top sites by successful payments (revenue)',
        'sql' => "SELECT s.site_id, s.name, COALESCE(SUM(p.amount),0) AS revenue, COUNT(DISTINCT b.booking_id) AS bookings
FROM HeritageSites s
LEFT JOIN Bookings b ON b.site_id = s.site_id
LEFT JOIN Payments p ON p.booking_id = b.booking_id AND p.status IN ('successful','success')
GROUP BY s.site_id
ORDER BY revenue DESC
LIMIT 200"
    ],
    'recent_sites' => [
        'title' => 'Recently added sites (last 200)',
        'sql' => "SELECT site_id, name, location, created_at
FROM HeritageSites
ORDER BY created_at DESC
LIMIT 200"
    ],
    'fulltext_sample' => [
        'title' => 'Full-text search sample (description) — boolean match for \"museum\"',
        'sql' => "SELECT site_id, name, LEFT(description,200) as excerpt
FROM HeritageSites
WHERE MATCH(description) AGAINST ('+museum' IN BOOLEAN MODE)
LIMIT 200"
    ],
    'bookings_by_nationality' => [
        'title' => 'Bookings by visitor nationality (grouping example)',
        'sql' => "SELECT v.nationality, COUNT(b.booking_id) AS bookings_count
FROM Bookings b
JOIN Visitors v ON b.visitor_id = v.visitor_id
GROUP BY v.nationality
ORDER BY bookings_count DESC
LIMIT 50"
    ],
    'recent_reviews_join' => [
        'title' => 'Recent reviews with visitor and site names (JOIN example)',
        'sql' => "SELECT r.review_id, v.full_name AS visitor, COALESCE(hs.name,'(site)') AS site, r.rating, LEFT(r.comment,200) AS comment, r.review_date
FROM Reviews r
JOIN Visitors v ON r.visitor_id = v.visitor_id
LEFT JOIN HeritageSites hs ON r.site_id = hs.site_id
ORDER BY r.review_date DESC
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
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Manage Heritage Sites</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { background-color: #f8f9fa; }
.filter-card { background: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 2px 6px rgba(0,0,0,0.06); margin-bottom: 20px; }
.truncate { max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sql-card { background:#fff7eb; padding:0.8rem; border-radius:8px; margin-bottom:12px; }
</style>
</head>
<body>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="m-0">🏛️ Heritage Sites</h4>
        <div>
            <a class="btn btn-primary" href="site_edit.php">Add New Site</a>
        </div>
    </div>

    <?php foreach ($messages as $m): ?><div class="alert alert-success"><?= h($m) ?></div><?php endforeach; ?>
    <?php foreach ($errors as $e): ?><div class="alert alert-danger"><?= h($e) ?></div><?php endforeach; ?>

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

    <!-- Filters -->
    <div class="filter-card">
        <form class="row g-3" method="get" novalidate>
            <div class="col-md-2"><input type="text" name="filter_name" class="form-control" placeholder="Name" value="<?= h($filter_name) ?>"></div>

            <div class="col-md-2">
                <select name="filter_location" class="form-select">
                    <option value="">All Locations</option>
                    <?php foreach ($locations as $loc): ?>
                        <option value="<?= h($loc) ?>" <?= $filter_location === $loc ? 'selected' : '' ?>><?= h($loc) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <select name="filter_type" class="form-select">
                    <option value="">All Types</option>
                    <?php foreach ($types as $type): ?>
                        <option value="<?= h($type) ?>" <?= $filter_type === $type ? 'selected' : '' ?>><?= h($type) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <select name="filter_unesco" class="form-select">
                    <option value="">All UNESCO</option>
                    <option value="None" <?= $filter_unesco === 'None' ? 'selected' : '' ?>>None</option>
                    <option value="Tentative" <?= $filter_unesco === 'Tentative' ? 'selected' : '' ?>>Tentative</option>
                    <option value="World Heritage" <?= $filter_unesco === 'World Heritage' ? 'selected' : '' ?>>World Heritage</option>
                </select>
            </div>

            <div class="col-md-1"><input type="number" step="1" min="<?= h((string)$globalMin) ?>" max="<?= h((string)$globalMax) ?>" name="filter_price_min" class="form-control" placeholder="Min" value="<?= h($filter_price_min ?? '') ?>"></div>
            <div class="col-md-1"><input type="number" step="1" min="<?= h((string)$globalMin) ?>" max="<?= h((string)$globalMax) ?>" name="filter_price_max" class="form-control" placeholder="Max" value="<?= h($filter_price_max ?? '') ?>"></div>

            <div class="col-md-1">
                <select name="sort_price" class="form-select">
                    <option value="">Sort Price</option>
                    <option value="asc" <?= $sort_price === 'asc' ? 'selected' : '' ?>>Low to High</option>
                    <option value="desc" <?= $sort_price === 'desc' ? 'selected' : '' ?>>High to Low</option>
                </select>
            </div>

            <div class="col-md-1 d-grid"><button class="btn btn-success">Apply</button></div>
            <div class="col-md-1 d-grid"><a class="btn btn-secondary" href="manage_sites.php">Clear</a></div>
        </form>
    </div>

    <div class="mb-3">
        <div class="card p-3">
            <div class="row">
                <div class="col-md-6"><strong>Filtered results:</strong> <?= (int)($filteredStats['total'] ?? 0) ?> (page <?= $page ?> / <?= $totalPages ?>)</div>
                <div class="col-md-6 text-md-end">
                    <small>Global price range: <?= number_format((float)$globalMin, 2) ?> — <?= number_format((float)$globalMax, 2) ?> (avg <?= number_format((float)$globalAvg, 2) ?>)</small><br>
                    <small>Filtered price range: <?= $filteredStats['min_price'] !== null ? number_format((float)$filteredStats['min_price'], 2) : '-' ?> — <?= $filteredStats['max_price'] !== null ? number_format((float)$filteredStats['max_price'], 2) : '-' ?> (avg <?= $filteredStats['avg_price'] !== null ? number_format((float)$filteredStats['avg_price'], 2) : '-' ?>)</small>
                </div>
            </div>
        </div>
    </div>

    <?php if (empty($sites)): ?>
        <div class="alert alert-info">No heritage sites found with these filters.</div>
    <?php else: ?>
        <table class="table table-hover table-bordered bg-white">
            <thead class="table-dark">
                <tr>
                    <th>Name</th>
                    <th>Location</th>
                    <th>Type</th>
                    <th>Opening Hours</th>
                    <th>Ticket Price</th>
                    <th>UNESCO Status</th>
                    <th>Description</th>
                    <th>Created At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($sites as $s): ?>
                <tr>
                    <td><?= h($s['name']) ?></td>
                    <td><?= h($s['location']) ?></td>
                    <td><?= h($s['type']) ?></td>
                    <td><?= h($s['opening_hours']) ?></td>
                    <td><?= number_format((float)($s['ticket_price'] ?? 0), 2) ?></td>
                    <td><?= h($s['unesco_status']) ?></td>
                    <td class="truncate" title="<?= h($s['description']) ?>"><?= h($s['description']) ?></td>
                    <td><?= h($s['created_at']) ?></td>
                    <td>
                        <a href="../site_view.php?id=<?= (int)$s['site_id'] ?>" class="btn btn-sm btn-outline-primary mb-1">View</a>
                        <a class="btn btn-sm btn-outline-secondary mb-1" href="site_edit.php?id=<?= (int)$s['site_id'] ?>">Edit</a>

                        <form method="post" class="d-inline" onsubmit="return confirm('Delete site?');">
                            <input type="hidden" name="delete_id" value="<?= (int)$s['site_id'] ?>">
                            <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <nav aria-label="pagination">
                <ul class="pagination">
                    <?php
                    // build base URL with existing GET params except page
                    $baseParams = $_GET;
                    unset($baseParams['page']);
                    $baseUrl = '?' . http_build_query($baseParams);
                    $start = max(1, $page - 5);
                    $end = min($totalPages, $page + 5);
                    if ($page > 1) {
                        echo '<li class="page-item"><a class="page-link" href="'.$baseUrl.'&page=1">« First</a></li>';
                        echo '<li class="page-item"><a class="page-link" href="'.$baseUrl.'&page='.($page-1).'">‹ Prev</a></li>';
                    }
                    for ($p = $start; $p <= $end; $p++) {
                        $active = $p === $page ? ' active' : '';
                        echo '<li class="page-item'.$active.'"><a class="page-link" href="'.$baseUrl.'&page='.$p.'">'.$p.'</a></li>';
                    }
                    if ($page < $totalPages) {
                        echo '<li class="page-item"><a class="page-link" href="'.$baseUrl.'&page='.($page+1).'">Next ›</a></li>';
                        echo '<li class="page-item"><a class="page-link" href="'.$baseUrl.'&page='.$totalPages.'">Last »</a></li>';
                    }
                    ?>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>

</div>
</body>
</html>
