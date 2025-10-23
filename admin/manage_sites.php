<?php
session_start();
require_once 'headerFooter.php';
require_once __DIR__ . '/../includes/db_connect.php'; // ensure this returns a $pdo PDO instance

// ---------- AUTH ----------
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

// ensure a CSRF token for safe POST actions (delete)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ---------- FILTERS & PAGINATION ----------
$filter_name = trim($_GET['filter_name'] ?? '');
$filter_location = trim($_GET['filter_location'] ?? '');
$filter_type = trim($_GET['filter_type'] ?? '');
$filter_unesco = trim($_GET['filter_unesco'] ?? ''); // expected: '', 'None', 'Tentative', 'World Heritage'
$filter_price_min = (isset($_GET['filter_price_min']) && $_GET['filter_price_min'] !== '') ? (float)$_GET['filter_price_min'] : null;
$filter_price_max = (isset($_GET['filter_price_max']) && $_GET['filter_price_max'] !== '') ? (float)$_GET['filter_price_max'] : null;
$sort_price = trim($_GET['sort_price'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 5; // rows per page

// ---------- Dynamic dropdowns ----------
$locations = $pdo->query("SELECT DISTINCT location FROM HeritageSites WHERE location IS NOT NULL AND location != '' ORDER BY location ASC")->fetchAll(PDO::FETCH_COLUMN);
$types = $pdo->query("SELECT DISTINCT type FROM HeritageSites WHERE type IS NOT NULL AND type != '' ORDER BY type ASC")->fetchAll(PDO::FETCH_COLUMN);

// ---------- Global stats (for min/max attributes) ----------
$globalStats = $pdo->query("SELECT COUNT(*) AS total, MIN(ticket_price) AS min_price, MAX(ticket_price) AS max_price, AVG(ticket_price) AS avg_price FROM HeritageSites")->fetch(PDO::FETCH_ASSOC);
$globalMin = $globalStats['min_price'] !== null ? (float)$globalStats['min_price'] : 0.00;
$globalMax = $globalStats['max_price'] !== null ? (float)$globalStats['max_price'] : 0.00;
$globalAvg = $globalStats['avg_price'] !== null ? (float)$globalStats['avg_price'] : 0.00;

// ---------- Build WHERE & params (safe prepared statements) ----------
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

// ---------- ORDER BY (whitelisted) ----------
$order_by = 'created_at DESC';
if ($sort_price === 'asc') $order_by = 'ticket_price ASC';
if ($sort_price === 'desc') $order_by = 'ticket_price DESC';

// ---------- CSV EXPORT (before LIMIT and before sending HTML) ----------
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $exportQuery = "SELECT site_id, name, location, type, opening_hours, ticket_price, unesco_status, description, created_at FROM HeritageSites $where ORDER BY $order_by";
    $exportStmt = $pdo->prepare($exportQuery);
    foreach ($params as $k => $v) {
        $exportStmt->bindValue(':'.$k, $v);
    }
    $exportStmt->execute();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="heritage_sites_export_' . date('Ymd_His') . '.csv"');

    $out = fopen('php://output', 'w');
    // header row
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

// ---------- Deletion (POST + CSRF) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $del = (int)$_POST['delete_id'];
    $csrf = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $csrf)) {
        http_response_code(400);
        echo "Invalid CSRF token.";
        exit;
    }
    $delStmt = $pdo->prepare('DELETE FROM HeritageSites WHERE site_id = :id');
    $delStmt->execute(['id' => $del]);
    header('Location: manage_sites.php');
    exit;
}

// ---------- Pagination counts ----------
$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM HeritageSites $where");
$totalStmt->execute($params);
$totalRows = (int)$totalStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$offset = ($page - 1) * $perPage;

// ---------- Fetch filtered/paginated rows ----------
$query = "SELECT * FROM HeritageSites $where ORDER BY $order_by LIMIT :offset, :perPage";
$stmt = $pdo->prepare($query);
foreach ($params as $k => $v) {
    $stmt->bindValue(':'.$k, $v);
}
$stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
$stmt->bindValue(':perPage', (int)$perPage, PDO::PARAM_INT);
$stmt->execute();
$sites = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ---------- Filtered stats (for display) ----------
$filteredStatsStmt = $pdo->prepare("SELECT COUNT(*) AS total, MIN(ticket_price) AS min_price, MAX(ticket_price) AS max_price, AVG(ticket_price) AS avg_price FROM HeritageSites $where");
$filteredStatsStmt->execute($params);
$filteredStats = $filteredStatsStmt->fetch(PDO::FETCH_ASSOC);

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
.filter-card { background: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); margin-bottom: 20px; }
.truncate { max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
</style>
</head>
<body>
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4>Heritage Sites</h4>
        <div>
            <a class="btn btn-primary" href="site_edit.php">Add New Site</a>
            <?php
                // export link preserves current GET filters and sets export=csv
                $exportParams = $_GET;
                $exportParams['export'] = 'csv';
                echo '<a class="btn btn-outline-primary ms-2" href="?'.htmlspecialchars(http_build_query($exportParams)).'">Export CSV</a>';
            ?>
        </div>
    </div>

    <!-- Filters -->
    <div class="filter-card">
        <form class="row g-3" method="get" novalidate>
            <div class="col-md-2">
                <input type="text" name="filter_name" class="form-control" placeholder="Name" value="<?php echo htmlspecialchars($filter_name); ?>">
            </div>

            <div class="col-md-2">
                <select name="filter_location" class="form-select" aria-label="Location">
                    <option value="">All Locations</option>
                    <?php foreach ($locations as $loc): ?>
                        <option value="<?php echo htmlspecialchars($loc); ?>" <?php if ($filter_location === $loc) echo 'selected'; ?>><?php echo htmlspecialchars($loc); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <select name="filter_type" class="form-select" aria-label="Type">
                    <option value="">All Types</option>
                    <?php foreach ($types as $type): ?>
                        <option value="<?php echo htmlspecialchars($type); ?>" <?php if ($filter_type === $type) echo 'selected'; ?>><?php echo htmlspecialchars($type); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <select name="filter_unesco" class="form-select" aria-label="UNESCO status">
                    <option value="">All UNESCO</option>
                    <option value="None" <?php if ($filter_unesco === 'None') echo 'selected'; ?>>None</option>
                    <option value="Tentative" <?php if ($filter_unesco === 'Tentative') echo 'selected'; ?>>Tentative</option>
                    <option value="World Heritage" <?php if ($filter_unesco === 'World Heritage') echo 'selected'; ?>>World Heritage</option>
                </select>
            </div>

            <!-- Price range: min/max attributes come from DB global min/max; step set to 1 -->
            <div class="col-md-1">
                <input type="number" step="1" min="<?php echo htmlspecialchars((string)$globalMin); ?>" max="<?php echo htmlspecialchars((string)$globalMax); ?>"
                       name="filter_price_min" class="form-control" placeholder="Min" value="<?php echo htmlspecialchars($filter_price_min ?? ''); ?>">
            </div>
            <div class="col-md-1">
                <input type="number" step="1" min="<?php echo htmlspecialchars((string)$globalMin); ?>" max="<?php echo htmlspecialchars((string)$globalMax); ?>"
                       name="filter_price_max" class="form-control" placeholder="Max" value="<?php echo htmlspecialchars($filter_price_max ?? ''); ?>">
            </div>

            <div class="col-md-1">
                <select name="sort_price" class="form-select" aria-label="Sort price">
                    <option value="">Sort Price</option>
                    <option value="asc" <?php if ($sort_price === 'asc') echo 'selected'; ?>>Low to High</option>
                    <option value="desc" <?php if ($sort_price === 'desc') echo 'selected'; ?>>High to Low</option>
                </select>
            </div>

            <div class="col-md-1 d-grid">
                <button class="btn btn-success">Apply</button>
            </div>

            <div class="col-md-1 d-grid">
                <a class="btn btn-secondary" href="manage_sites.php">Clear</a>
            </div>
        </form>
    </div>

    <!-- Stats -->
    <div class="mb-3">
        <div class="card p-3">
            <div class="row">
                <div class="col-md-6">
                    <strong>Filtered results:</strong> <?php echo (int)($filteredStats['total'] ?? 0); ?> shown (page <?php echo $page; ?> / <?php echo $totalPages; ?>)
                </div>
                <div class="col-md-6 text-md-end">
                    <small>Global price range: <?php echo number_format($globalMin, 2); ?> — <?php echo number_format($globalMax, 2); ?> (avg <?php echo number_format($globalAvg, 2); ?>)</small>
                    <br>
                    <small>Filtered price range: <?php echo $filteredStats['min_price'] !== null ? number_format($filteredStats['min_price'], 2) : '-'; ?> — <?php echo $filteredStats['max_price'] !== null ? number_format($filteredStats['max_price'], 2) : '-'; ?> (avg <?php echo $filteredStats['avg_price'] !== null ? number_format($filteredStats['avg_price'], 2) : '-'; ?>)</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Sites Table -->
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
                    <td><?php echo htmlspecialchars($s['name']); ?></td>
                    <td><?php echo htmlspecialchars($s['location']); ?></td>
                    <td><?php echo htmlspecialchars($s['type']); ?></td>
                    <td><?php echo htmlspecialchars($s['opening_hours']); ?></td>
                    <td><?php echo number_format($s['ticket_price'], 2); ?></td>
                    <td><?php echo htmlspecialchars($s['unesco_status']); ?></td>
                    <td class="truncate" title="<?php echo htmlspecialchars($s['description']); ?>"><?php echo htmlspecialchars($s['description']); ?></td>
                    <td><?php echo htmlspecialchars($s['created_at']); ?></td>
                    <td>
                        <a href="../site_view.php?id=<?php echo (int)$s['site_id']; ?>">View</a>
                        <a class="btn btn-sm btn-outline-secondary" href="site_edit.php?id=<?php echo (int)$s['site_id']; ?>">Edit</a>

                        <!-- Delete: POST form with CSRF token -->
                        <form method="post" class="d-inline" onsubmit="return confirm('Delete site?');">
                            <input type="hidden" name="delete_id" value="<?php echo (int)$s['site_id']; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <nav>
                <ul class="pagination">
                    <?php
                        for ($p = 1; $p <= $totalPages; $p++):
                            $queryParams = $_GET;
                            $queryParams['page'] = $p;
                    ?>
                        <li class="page-item <?php if ($p === $page) echo 'active'; ?>">
                            <a class="page-link" href="?<?php echo htmlspecialchars(http_build_query($queryParams)); ?>"><?php echo $p; ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>
</div>
</body>
</html>
