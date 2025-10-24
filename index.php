<?php
require_once __DIR__ . '/includes/db_connect.php';
session_start();

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
$validUnesco = ['None','Tentative','World Heritage','Yes','No'];
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
$total_pages = ceil($total_sites / $per_page);

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

/* ===========================================================
   🔹 LAB 4 – Aggregates, Grouping, HAVING
   =========================================================== */
   $method_filter = $_GET['method_filter'] ?? '';
   $methodWhere = $method_filter ? "WHERE method = :m" : "";
   $revenueStmt = $pdo->prepare("
       SELECT method, SUM(amount) AS total 
       FROM Payments 
       $methodWhere
       GROUP BY method 
       HAVING total > 0
       ORDER BY total DESC
   ");
   if ($method_filter) $revenueStmt->bindValue(':m', $method_filter);
   $revenueStmt->execute();
   $totalRevenue = $revenueStmt->fetchAll(PDO::FETCH_ASSOC);
//    $totalRevenue = $pdo->query("
//     SELECT method, SUM(amount) AS total 
//     FROM Payments 
//     GROUP BY method 
//     ORDER BY total DESC
// ")->fetchAll(PDO::FETCH_ASSOC);

$avgGuideSalary = $pdo->query("SELECT ROUND(AVG(salary),2) AS avg_salary FROM Guides")->fetchColumn();
$salary_min = $_GET['salary_min'] ?? '';
$salaryQuery = "SELECT full_name, salary FROM Guides";
if ($salary_min !== '') {
  $salaryQuery .= " WHERE salary >= :smin";
  $stmtSal = $pdo->prepare($salaryQuery);
  $stmtSal->bindValue(':smin',(float)$salary_min);
  $stmtSal->execute();
  $guideSalaryList = $stmtSal->fetchAll(PDO::FETCH_ASSOC);
} else {
  $guideSalaryList = $pdo->query($salaryQuery)->fetchAll(PDO::FETCH_ASSOC);
}
$unassignedGuides = $pdo->query("
    SELECT full_name FROM Guides 
    LEFT JOIN Assignments ON Guides.guide_id = Assignments.guide_id 
    WHERE Assignments.guide_id IS NULL
")->fetchAll(PDO::FETCH_COLUMN);

$topSites = $pdo->query("
    SELECT h.name, COUNT(b.booking_id) AS total_bookings 
    FROM HeritageSites h
    JOIN Bookings b ON h.site_id = b.site_id
    GROUP BY h.name
    ORDER BY total_bookings DESC
    LIMIT 3
")->fetchAll(PDO::FETCH_ASSOC);

$reviewsData = $pdo->query("
    SELECT v.full_name AS visitor, s.name AS site, r.rating, r.comment
    FROM Reviews r
    JOIN Visitors v ON r.visitor_id = v.visitor_id
    JOIN HeritageSites s ON r.site_id = s.site_id
    ORDER BY r.review_id DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

/* ===========================================================
   🔹 LAB 5 – Subqueries & Set Operations
   =========================================================== */
$sitesWithEvents = $pdo->query("
    SELECT name FROM HeritageSites
    WHERE site_id IN (SELECT site_id FROM Events)
")->fetchAll(PDO::FETCH_COLUMN);

$guidesWithoutSite = $pdo->query("
    SELECT full_name FROM Guides
    WHERE guide_id NOT IN (SELECT guide_id FROM Assignments)
")->fetchAll(PDO::FETCH_COLUMN);

$visitorsBookedNotReviewed = $pdo->query("
    SELECT full_name FROM Visitors
    WHERE visitor_id IN (SELECT visitor_id FROM Bookings)
    AND visitor_id NOT IN (SELECT visitor_id FROM Reviews)
")->fetchAll(PDO::FETCH_COLUMN);

$sitesWithBookingsOrReviews = $pdo->query("
    (SELECT site_id, name FROM HeritageSites WHERE site_id IN (SELECT site_id FROM Bookings))
    UNION
    (SELECT site_id, name FROM HeritageSites WHERE site_id IN (SELECT site_id FROM Reviews))
")->fetchAll(PDO::FETCH_ASSOC);

/* ===========================================================
   🔹 LAB 6 – Joins (INNER, LEFT, CROSS)
   =========================================================== */
$bookingsWithPayments = $pdo->query("
    SELECT b.booking_id, v.full_name, p.method, p.amount, p.status
    FROM Bookings b
    JOIN Visitors v ON b.visitor_id = v.visitor_id
    JOIN Payments p ON b.booking_id = p.booking_id
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

$guideAssignments = $pdo->query("
    SELECT g.full_name AS guide, s.name AS site, a.shift_time
    FROM Guides g
    JOIN Assignments a ON g.guide_id = a.guide_id
    JOIN HeritageSites s ON a.site_id = s.site_id
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Heritage Explorer</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background:#f4f6f9; }
    header, footer { background:#003366; color:white; padding:15px; text-align:center; }
    .site-card { background:white; padding:20px; border-radius:10px; box-shadow:0 2px 6px rgba(0,0,0,.1); margin-bottom:20px; }
    .filter-card { background:white; padding:15px; border-radius:10px; margin-bottom:20px; box-shadow:0 2px 6px rgba(0,0,0,.1); }
    h5 { color:#003366; }
  </style>
</head>
<body>
<header>
<div class="d-flex justify-content-between align-items-center">
  <h1>🏛 Heritage Explorer</h1>
  <nav>
      <a href="index.php" class="text-white me-3">Home</a>
      <a href="contact.php" class="text-white me-3">Contact</a>
      <?php if ($visitor_logged_in): ?>
        <a href="visitor/profile.php" class="text-white me-3">Profile</a>
        <a href="visitor/logout.php" class="btn btn-sm btn-light">Logout</a>
      <?php else: ?>
        <a href="visitor/login.php" class="btn btn-sm btn-light me-2">Login</a>
        <a href="visitor/signup.php" class="btn btn-sm btn-warning">Sign Up</a>
      <?php endif; ?>
    </nav>
</div>
</header>

<div class="container my-4">
  <h2>Explore Heritage Sites</h2>

  <!-- Filters -->
  <div class="filter-card">
    <form method="get" class="row g-3">
      <div class="col-md-3">
        <input type="text" name="filter_name" class="form-control" placeholder="Search by name" value="<?= htmlspecialchars($filter_name) ?>">
      </div>
      <div class="col-md-2">
        <select name="filter_location" class="form-select">
          <option value="">All Locations</option>
          <?php foreach ($locations as $loc): ?>
            <option value="<?= htmlspecialchars($loc) ?>" <?= $filter_location===$loc?'selected':'' ?>><?= htmlspecialchars($loc) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <select name="filter_type" class="form-select">
          <option value="">All Types</option>
          <?php foreach ($types as $t): ?>
            <option value="<?= htmlspecialchars($t) ?>" <?= $filter_type===$t?'selected':'' ?>><?= htmlspecialchars($t) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <select name="filter_unesco" class="form-select">
          <option value="">UNESCO Any</option>
          <option value="World Heritage" <?= $filter_unesco==='World Heritage'?'selected':'' ?>>World Heritage</option>
          <option value="Tentative" <?= $filter_unesco==='Tentative'?'selected':'' ?>>Tentative</option>
          <option value="None" <?= $filter_unesco==='None'?'selected':'' ?>>None</option>
          <option value="Yes" <?= $filter_unesco==='Yes'?'selected':'' ?>>Yes</option>
          <option value="No" <?= $filter_unesco==='No'?'selected':'' ?>>No</option>
        </select>
      </div>
      <div class="col-md-1">
        <input type="number" name="filter_price_min" class="form-control" placeholder="Min" value="<?= htmlspecialchars($filter_price_min ?? '') ?>">
      </div>
      <div class="col-md-1">
        <input type="number" name="filter_price_max" class="form-control" placeholder="Max" value="<?= htmlspecialchars($filter_price_max ?? '') ?>">
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

  <!-- Sites -->
  <?php if (empty($sites)): ?>
    <div class="alert alert-info">No sites match your filters.</div>
  <?php else: ?>
    <div class="row">
      <?php foreach ($sites as $s): ?>
        <div class="col-md-6">
          <div class="site-card">
            <h4><a href="site_view.php?id=<?= $s['site_id'] ?>"><?= htmlspecialchars($s['name']) ?></a></h4>
            <p><strong>Location:</strong> <?= htmlspecialchars($s['location']) ?></p>
            <p><strong>Type:</strong> <?= htmlspecialchars($s['type']) ?></p>
            <p><strong>Ticket:</strong> <?= number_format($s['ticket_price'],2) ?></p>
            <p><strong>UNESCO:</strong> <?= htmlspecialchars($s['unesco_status']) ?></p>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- Pagination -->
<nav>
  <ul class="pagination justify-content-center">
    <?php for ($i=1; $i <= $total_pages; $i++): ?>
      <li class="page-item <?= $i === $page ? 'active' : '' ?>">
        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page'=>$i])) ?>"><?= $i ?></a>
      </li>
    <?php endfor; ?>
  </ul>
</nav>

<!-- Analytics Section -->
<div class="container my-5">
  <h3>📊 Lab Query Showcases</h3>

  <div class="row">
    <div class="col-md-4">
    </div>
    <div class="col-md-4">
      <div class="site-card">
        <h5>🏆 Top 3 Booked Sites</h5>
        <ul><?php foreach ($topSites as $s): ?><li><?= htmlspecialchars($s['name']) ?> — <?= $s['total_bookings'] ?> bookings</li><?php endforeach; ?></ul>
      </div>
    </div>
  </div>

  <div class="site-card mt-4">
    <h5>⭐ Recent Reviews (JOIN Example)</h5>
    <?php foreach ($reviewsData as $r): ?>
      <p><strong><?= htmlspecialchars($r['visitor']) ?></strong> rated <em><?= htmlspecialchars($r['site']) ?></em> → <?= htmlspecialchars($r['rating']) ?>/5<br>
      "<?= htmlspecialchars($r['comment']) ?>"</p><hr>
    <?php endforeach; ?>
  </div>

  <div class="site-card mt-4">
    <h5>🧩 Subqueries & Joins</h5>
    <p><strong>Sites With Events:</strong> <?= implode(', ', $sitesWithEvents) ?: 'None' ?></p>
    <p><strong>Guides Without Site:</strong> <?= implode(', ', $guidesWithoutSite) ?: 'None' ?></p>
    <p><strong>Visitors Booked But Not Reviewed:</strong> <?= implode(', ', $visitorsBookedNotReviewed) ?: 'None' ?></p>
    <h6>Bookings + Payments (Join Example)</h6>
    <ul><?php foreach ($bookingsWithPayments as $b): ?><li>#<?= $b['booking_id'] ?> - <?= $b['full_name'] ?> paid <?= number_format($b['amount'],2) ?> via <?= $b['method'] ?> (<?= $b['status'] ?>)</li><?php endforeach; ?></ul>
  </div>
</div>

<footer class="bg-dark text-white text-center py-3 position-relative">
    <p class="mb-0">&copy; <?= date('Y') ?> Heritage Explorer</p>
    <a href="/Heritage-Database-Project/admin/login.php" 
       class="text-white-50 small position-absolute bottom-0 end-0 me-2 mb-1"
       style="font-size: 0.75rem; text-decoration: none;">
       Admin Login
    </a>
</footer>
</body>
</html>
