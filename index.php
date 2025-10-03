<?php
require_once __DIR__ . '/includes/db_connect.php';

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

// ---------- Fetch ----------
$stmt = $pdo->prepare("SELECT site_id, name, location, type, ticket_price, unesco_status FROM HeritageSites $where ORDER BY $order_by");
foreach ($params as $k => $v) {
    $stmt->bindValue(':'.$k, $v);
}
$stmt->execute();
$sites = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ---------- Pagination ----------
$per_page = 6; // sites per page
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;

// ---------- Count total ----------
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM HeritageSites $where");
foreach ($params as $k => $v) {
    $countStmt->bindValue(':'.$k, $v);
}
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
foreach ($params as $k => $v) {
    $stmt->bindValue(':'.$k, $v);
}
$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$sites = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    .site-card h3 { color:#003366; }
    a { color:#003366; text-decoration:none; }
    .filter-card { background:white; padding:15px; border-radius:10px; margin-bottom:20px; box-shadow:0 2px 6px rgba(0,0,0,.1); }
  </style>
</head>
<body>
<header>
  <h1>🏛 Heritage Explorer</h1>
  <nav>
    <a href="index.php" class="text-white">Home</a> | 
    <a href="contact.php" class="text-white">Contact</a>
  </nav>
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
        <input type="number" 
               name="filter_price_min" 
               class="form-control" 
               placeholder="Min" 
               min="<?= $db_min_price ?>" 
               max="<?= $db_max_price ?>" 
               value="<?= htmlspecialchars($filter_price_min ?? '') ?>">
      </div>
      <div class="col-md-1">
        <input type="number" 
               name="filter_price_max" 
               class="form-control" 
               placeholder="Max" 
               min="<?= $db_min_price ?>" 
               max="<?= $db_max_price ?>" 
               value="<?= htmlspecialchars($filter_price_max ?? '') ?>">
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
            <h3><a href="site_view.php?id=<?= $s['site_id'] ?>"><?= htmlspecialchars($s['name']) ?></a></h3>
            <p><strong>Location:</strong> <?= htmlspecialchars($s['location']) ?></p>
            <p><strong>Type:</strong> <?= htmlspecialchars($s['type']) ?></p>
            <p><strong>Ticket Price:</strong> <?= number_format($s['ticket_price'],2) ?></p>
            <p><strong>UNESCO:</strong> <?= htmlspecialchars($s['unesco_status']) ?></p>
            <a href="site_view.php?id=<?= $s['site_id'] ?>" class="btn btn-outline-primary btn-sm">View Details →</a>
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
        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page'=>$i])) ?>">
          <?= $i ?>
        </a>
      </li>
    <?php endfor; ?>
  </ul>
</nav>

<footer>
  <p>&copy; <?= date('Y') ?> Heritage Explorer</p>
</footer>
</body>
</html>
