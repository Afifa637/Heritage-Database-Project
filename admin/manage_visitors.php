<?php
// manage_visitors.php (updated with SQL Runner)
declare(strict_types=1);
session_start();
require_once 'headerFooter.php';
require_once __DIR__ . '/../includes/db_connect.php';

//  . AUTH  .
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

// Ensure CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// small helper
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_HTML5); }

// Messages container
$messages = [];
$errors = [];

// Minimal sanitizer helper
function post_str($k, $max = 255) {
    $v = trim($_POST[$k] ?? '');
    if ($v === '') return null;
    return mb_substr($v, 0, $max);
}

// === Handle CRUD ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $csrf)) {
        http_response_code(400);
        exit('Invalid CSRF');
    }

    // Add
    if (isset($_POST['add'])) {
        $name = post_str('name', 120);
        $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL) ? $_POST['email'] : null;
        $nationality = post_str('nationality', 80);
        $phone = post_str('phone', 30);
        $password = $_POST['password'] ?? '';

        if (!$name || !$email || !$password) {
            $errors[] = 'Name, email and password are required.';
        } else {
            $stmt = $pdo->prepare("INSERT INTO Visitors (full_name, email, nationality, phone, password_hash) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $name, $email, $nationality, $phone,
                password_hash($password, PASSWORD_DEFAULT)
            ]);
            $messages[] = 'Visitor added.';
            header("Location: manage_visitors.php");
            exit;
        }
    }

    // Update
    if (isset($_POST['update_id'])) {
        $update_id = (int)($_POST['update_id'] ?? 0);
        $name = post_str('name', 120);
        $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL) ? $_POST['email'] : null;
        $nationality = post_str('nationality', 80);
        $phone = post_str('phone', 30);
        $password = $_POST['password'] ?? '';

        if (!$update_id || !$name || !$email) {
            $errors[] = 'Invalid update parameters.';
        } else {
            if (!empty($password)) {
                $stmt = $pdo->prepare("UPDATE Visitors SET full_name=?, email=?, nationality=?, phone=?, password_hash=? WHERE visitor_id=?");
                $stmt->execute([
                    $name, $email, $nationality, $phone,
                    password_hash($password, PASSWORD_DEFAULT), $update_id
                ]);
            } else {
                $stmt = $pdo->prepare("UPDATE Visitors SET full_name=?, email=?, nationality=?, phone=? WHERE visitor_id=?");
                $stmt->execute([
                    $name, $email, $nationality, $phone, $update_id
                ]);
            }
            $messages[] = 'Visitor updated.';
            header("Location: manage_visitors.php");
            exit;
        }
    }

    // Delete
    if (isset($_POST['delete_id'])) {
        $delete_id = (int)($_POST['delete_id'] ?? 0);
        if ($delete_id > 0) {
            $stmt = $pdo->prepare("DELETE FROM Visitors WHERE visitor_id=?");
            $stmt->execute([$delete_id]);
            $messages[] = 'Visitor deleted.';
            header("Location: manage_visitors.php");
            exit;
        } else {
            $errors[] = 'Invalid delete id.';
        }
    }

    // Run safe read-only query (SQL Runner)
    if (isset($_POST['action']) && $_POST['action'] === 'run_query') {
        // handled later below with whitelist
    }
}

//  . .
// SAFE SQL RUNNER: whitelist of read-only SELECTs
//  . .
$safe_queries = [
    'recent_visitors' => [
        'title' => 'Recent visitors (last 100)',
        'sql' => "SELECT visitor_id, full_name, nationality, email, phone, created_at FROM Visitors ORDER BY created_at DESC LIMIT 100"
    ],
    'visitors_no_bookings' => [
        'title' => 'Visitors with NO bookings',
        'sql' => "SELECT v.visitor_id, v.full_name, v.email, v.nationality, v.created_at
FROM Visitors v
LEFT JOIN Bookings b ON v.visitor_id = b.visitor_id
WHERE b.booking_id IS NULL
ORDER BY v.created_at DESC
LIMIT 200"
    ],
    'top_visitors_by_bookings' => [
        'title' => 'Top visitors by number of bookings (last 1 year)',
        'sql' => "SELECT v.visitor_id, v.full_name, v.email, COUNT(b.booking_id) AS bookings_count
FROM Visitors v
LEFT JOIN Bookings b ON v.visitor_id = b.visitor_id AND b.booking_date >= DATE_SUB(NOW(), INTERVAL 1 YEAR)
GROUP BY v.visitor_id
ORDER BY bookings_count DESC
LIMIT 100"
    ],
    'visitors_high_avg_rating' => [
        'title' => 'Visitors with high average rating (avg >= 4.5)',
        'sql' => "SELECT v.visitor_id, v.full_name, ROUND(AVG(r.rating),2) AS avg_rating, COUNT(r.review_id) AS reviews_count
FROM Visitors v
JOIN Reviews r ON r.visitor_id = v.visitor_id
GROUP BY v.visitor_id
HAVING avg_rating >= 4.5
ORDER BY avg_rating DESC, reviews_count DESC
LIMIT 100"
    ],
    'bookings_per_visitor' => [
        'title' => 'Bookings per visitor (sample)',
        'sql' => "SELECT v.visitor_id, v.full_name, COUNT(b.booking_id) AS total_bookings, SUM(b.booked_ticket_price) AS total_spent
FROM Visitors v
LEFT JOIN Bookings b ON v.visitor_id = b.visitor_id
GROUP BY v.visitor_id
ORDER BY total_bookings DESC
LIMIT 200"
    ],
    'visitors_by_nationality' => [
        'title' => 'Visitors count by nationality',
        'sql' => "SELECT nationality, COUNT(*) AS cnt FROM Visitors GROUP BY nationality ORDER BY cnt DESC"
    ],
    'recent_reviews' => [
        'title' => 'Recent reviews with visitor & target (site or event)',
        'sql' => "SELECT r.review_id, r.rating, r.comment, r.review_date, v.full_name AS visitor, COALESCE(s.name, e.name) AS target_name
FROM Reviews r
JOIN Visitors v ON r.visitor_id = v.visitor_id
LEFT JOIN HeritageSites s ON r.site_id = s.site_id
LEFT JOIN Events e ON r.event_id = e.event_id
ORDER BY r.review_date DESC
LIMIT 200"
    ],
    'bookings_without_payments' => [
        'title' => 'Bookings without payments (bookings where no payment row exists)',
        'sql' => "SELECT b.booking_id, b.visitor_id, b.site_id, b.event_id, b.booking_date, b.booked_ticket_price
FROM Bookings b
LEFT JOIN Payments p ON p.booking_id = b.booking_id
WHERE p.payment_id IS NULL
ORDER BY b.booking_date DESC
LIMIT 200"
    ],
    'visitors_with_large_single_booking' => [
        'title' => 'Visitors with any single booking cost >= 1000',
        'sql' => "SELECT DISTINCT v.visitor_id, v.full_name, b.booking_id, b.booked_ticket_price
FROM Visitors v
JOIN Bookings b ON v.visitor_id = b.visitor_id
WHERE b.booked_ticket_price >= 1000
ORDER BY b.booked_ticket_price DESC
LIMIT 200"
    ],
];

// Handle the SQL Runner POST (safe only)
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
                // run query (read-only). Limit results handled in query definitions.
                $stmtQ = $pdo->query($safe_queries[$qkey]['sql']);
                $query_result = $stmtQ->fetchAll(PDO::FETCH_ASSOC);
                $messages[] = 'Query executed.';
            } catch (Exception $e) {
                $errors[] = 'Query failed: ' . $e->getMessage();
            }
        }
    }
}

// --- Filters from GET (validated / normalized) ---
// NOTE: the main name filter uses "name" param per your request
$name          = trim($_GET['name'] ?? '');
$nationality   = trim($_GET['nationality'] ?? '');
$from          = trim($_GET['from'] ?? '');
$min_bookings  = trim($_GET['min_bookings'] ?? '');
$max_bookings  = trim($_GET['max_bookings'] ?? '');
$min_rating    = trim($_GET['min_rating'] ?? '');
$max_rating    = trim($_GET['max_rating'] ?? '');
$has_booking   = ($_GET['has_booking'] ?? '') === 'yes' ? 'yes' : (($_GET['has_booking'] ?? '') === 'no' ? 'no' : '');
$has_review    = ($_GET['has_review'] ?? '') === 'yes' ? 'yes' : (($_GET['has_review'] ?? '') === 'no' ? 'no' : '');
$sort          = $_GET['sort'] ?? 'created_at';
$order         = (($_GET['order'] ?? 'DESC') === 'ASC') ? 'ASC' : 'DESC';

$where = [];
$having = [];
$params = [];

// --- Basic filters ---
if ($name !== '') {
    $where[] = "v.full_name LIKE :name_kw";
    $params['name_kw'] = "%$name%";
}

if (!empty($_GET['q'])) {
    $q = trim($_GET['q']);
    if ($q !== '') {
        $where[] = "(v.full_name LIKE :kw1 OR v.email LIKE :kw2 OR v.phone LIKE :kw3)";
        $params['kw1'] = "%$q%";
        $params['kw2'] = "%$q%";
        $params['kw3'] = "%$q%";
    }
}

if ($nationality !== '') {
    $where[] = "v.nationality = :nation";
    $params['nation'] = $nationality;
}

if ($from !== '') {
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
        $where[] = "DATE(v.created_at) >= :from";
        $params['from'] = $from;
    }
}

// bookings thresholds normalization
$minB = ($min_bookings !== '' && is_numeric($min_bookings)) ? (int) floor((float)$min_bookings) : null;
$maxB = ($max_bookings !== '' && is_numeric($max_bookings)) ? (int) ceil((float)$max_bookings) : null;
if ($minB !== null && $minB < 0) $minB = 0;
if ($maxB !== null && $maxB < 0) $maxB = 0;
if ($minB !== null && $maxB !== null && $minB > $maxB) {
    $tmp = $minB; $minB = $maxB; $maxB = $tmp;
}

// rating thresholds normalization (one decimal)
$minR = ($min_rating !== '' && is_numeric($min_rating)) ? round((float)$min_rating, 1) : null;
$maxR = ($max_rating !== '' && is_numeric($max_rating)) ? round((float)$max_rating, 1) : null;
if ($minR !== null && $minR < 0.0) $minR = 0.0;
if ($maxR !== null && $maxR < 0.0) $maxR = 0.0;
if ($minR !== null && $maxR !== null && $minR > $maxR) {
    $tmp = $minR; $minR = $maxR; $maxR = $tmp;
}
if ($minR !== null) $minR = min(max($minR, 0.0), 5.0);
if ($maxR !== null) $maxR = min(max($maxR, 0.0), 5.0);

// --- Build SQL with LEFT JOIN for bookings & reviews ---
$sql = "SELECT v.visitor_id, v.full_name, v.email, v.nationality, v.phone, v.created_at,
       COUNT(DISTINCT b.booking_id) AS total_bookings,
       ROUND(AVG(r.rating),2) AS avg_rating,
       COUNT(DISTINCT r.review_id) AS total_reviews
FROM Visitors v
LEFT JOIN Bookings b ON b.visitor_id = v.visitor_id
LEFT JOIN Reviews r ON r.visitor_id = v.visitor_id";

if ($where) $sql .= " WHERE " . implode(" AND ", $where);

$sql .= " GROUP BY v.visitor_id";

// --- Advanced filters (HAVING) ---
if ($has_booking === 'yes') $having[] = "total_bookings > 0";
if ($has_booking === 'no')  $having[] = "total_bookings = 0";
if ($has_review === 'yes')  $having[] = "total_reviews > 0";
if ($has_review === 'no')   $having[] = "total_reviews = 0";

if ($minB !== null) {
    $having[] = "total_bookings >= :min_bookings";
    $params['min_bookings'] = $minB;
}
if ($maxB !== null) {
    $having[] = "total_bookings <= :max_bookings";
    $params['max_bookings'] = $maxB;
}

// Treat NULL avg_rating as 0 when comparing (use IFNULL)
if ($minR !== null) {
    $having[] = "IFNULL(avg_rating, 0) >= :min_rating";
    $params['min_rating'] = $minR;
}
if ($maxR !== null) {
    $having[] = "IFNULL(avg_rating, 0) <= :max_rating";
    $params['max_rating'] = $maxR;
}

if ($having) $sql .= " HAVING " . implode(" AND ", $having);

// --- Sorting protection ---
$allowedSorts = ['created_at','full_name','email','total_bookings','avg_rating','total_reviews'];
if (!in_array($sort, $allowedSorts, true)) $sort = 'created_at';
$sql .= " ORDER BY $sort $order";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$visitors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// === Distinct Nationalities for filter ===
$nations = $pdo->query("SELECT DISTINCT nationality FROM Visitors WHERE nationality IS NOT NULL AND nationality != '' ORDER BY nationality")->fetchAll(PDO::FETCH_COLUMN);

?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Manage Visitors</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  .sql-card { background:#f8f9fa; padding:1rem; border-radius:10px; }
  .stat-box { background:#fff; padding:10px; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,0.06); text-align:center; }
  .small-muted { font-size:0.85rem; color:#6c757d; }
</style>
</head>
<body>
  <div class="container py-4">
<h2 class="mb-4">👥 Manage Visitors</h2>

<?php foreach ($messages as $m): ?><div class="alert alert-success"><?= h($m) ?></div><?php endforeach; ?>
<?php foreach ($errors as $e): ?><div class="alert alert-danger"><?= h($e) ?></div><?php endforeach; ?>

<div class="row mb-4">
  <div class="col-md-5">
    <!-- Add Visitor Card -->
    <div class="card p-3">
      <h5 class="mb-2">➕ Add Visitor</h5>
      <form method="post" class="row g-2">
        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
        <div class="col-12"><label class="form-label">Name</label><input name="name" class="form-control" required></div>
        <div class="col-12"><label class="form-label">Email</label><input name="email" type="email" class="form-control" required></div>
        <div class="col-12"><label class="form-label">Nationality</label><input name="nationality" class="form-control"></div>
        <div class="col-12"><label class="form-label">Phone</label><input name="phone" class="form-control"></div>
        <div class="col-12"><label class="form-label">Password</label><input name="password" type="password" class="form-control" required></div>
        <div class="col-12 text-end"><button name="add" class="btn btn-success">Add</button></div>
      </form>
      <div class="small-muted mt-2">Passwords are stored hashed. Required: name, email, password.</div>
    </div>
  </div>

  <div class="col-md-7">
    <div class="sql-card">
      <h5 class="mb-2">🧾 SQL Runner — Safe Read-Only Queries</h5>
      <form method="post" class="d-flex gap-2">
        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="action" value="run_query">
        <select name="query_key" class="form-select" required>
          <option value="">-- Select a pre-defined query --</option>
          <?php foreach ($safe_queries as $k => $qinfo): ?>
            <option value="<?= h($k) ?>" <?= $selected_query_key === $k ? 'selected' : '' ?>><?= h($qinfo['title']) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-primary">Run</button>
      </form>
      <div class="small-muted mt-2">These queries are read-only and safe. Results are shown below.</div>
    </div>

    <?php if ($query_result !== null): ?>
      <div class="card mt-3 p-3">
        <h6>Query Result (<?= count($query_result) ?> rows)</h6>
        <?php if (count($query_result) === 0): ?>
          <div class="text-muted">No rows returned.</div>
        <?php else: ?>
          <div style="overflow:auto; max-height:420px;">
            <table class="table table-sm table-bordered">
              <thead class="table-dark">
                <tr><?php foreach (array_keys($query_result[0]) as $col): ?><th><?= h($col) ?></th><?php endforeach; ?></tr>
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

  </div>
</div>

<!-- Filter Form -->
<form method="get" class="row g-3 mb-3">
  <div class="col-md-2"><input type="text" name="name" class="form-control" placeholder="Name" value="<?= h($name) ?>"></div>
  <div class="col-md-2"><input type="text" name="q" class="form-control" placeholder="Search name/email/phone (q)" value="<?= h($_GET['q'] ?? '') ?>"></div>
  <div class="col-md-2">
    <select name="nationality" class="form-select">
      <option value="">All Nationalities</option>
      <?php foreach ($nations as $n): ?>
        <option value="<?= h($n) ?>" <?= $n === $nationality ? 'selected' : '' ?>><?= h($n) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-2"><input type="date" name="from" class="form-control" value="<?= h($from) ?>"></div>
  <div class="col-md-1"><input type="number" name="min_bookings" class="form-control" placeholder="Min bookings" value="<?= h($min_bookings) ?>"></div>
  <div class="col-md-1"><input type="number" name="max_bookings" class="form-control" placeholder="Max bookings" value="<?= h($max_bookings) ?>"></div>
  <div class="col-md-1"><input type="number" step="0.1" name="min_rating" class="form-control" placeholder="Min rating" value="<?= h($min_rating) ?>"></div>
  <div class="col-md-1"><input type="number" step="0.1" name="max_rating" class="form-control" placeholder="Max rating" value="<?= h($max_rating) ?>"></div>

  <div class="col-md-2">
    <select name="has_booking" class="form-select">
      <option value="">Any Booking</option>
      <option value="yes" <?= $has_booking==='yes'?'selected':'' ?>>Has Booking</option>
      <option value="no" <?= $has_booking==='no'?'selected':'' ?>>Never Booked</option>
    </select>
  </div>
  <div class="col-md-2">
    <select name="has_review" class="form-select">
      <option value="">Any Review</option>
      <option value="yes" <?= $has_review==='yes'?'selected':'' ?>>Has Review</option>
      <option value="no" <?= $has_review==='no'?'selected':'' ?>>Never Reviewed</option>
    </select>
  </div>
  <div class="col-md-2">
    <select name="sort" class="form-select">
      <option value="created_at" <?= $sort==='created_at'?'selected':'' ?>>Join Date</option>
      <option value="full_name" <?= $sort==='full_name'?'selected':'' ?>>Name</option>
      <option value="email" <?= $sort==='email'?'selected':'' ?>>Email</option>
      <option value="total_bookings" <?= $sort==='total_bookings'?'selected':'' ?>>Total Bookings</option>
      <option value="avg_rating" <?= $sort==='avg_rating'?'selected':'' ?>>Avg Rating</option>
      <option value="total_reviews" <?= $sort==='total_reviews'?'selected':'' ?>>Total Reviews</option>
    </select>
  </div>
  <div class="col-md-1">
    <select name="order" class="form-select">
      <option value="ASC" <?= $order==='ASC'?'selected':'' ?>>⬆️</option>
      <option value="DESC" <?= $order==='DESC'?'selected':'' ?>>⬇️</option>
    </select>
  </div>
  <div class="col-md-1"><button class="btn btn-primary w-100">Filter</button></div>
</form>

<button class="btn btn-success mb-3" data-bs-toggle="modal" data-bs-target="#addModal">➕ Add Visitor</button>

<!-- Visitors Table -->
<table class="table table-striped table-bordered">
<thead class="table-dark">
<tr>
  <th>ID</th>
  <th>Name</th>
  <th>Email</th>
  <th>Nationality</th>
  <th>Phone</th>
  <th>Joined</th>
  <th>Total Bookings</th>
  <th>Avg Rating</th>
  <th>Total Reviews</th>
  <th>Actions</th>
</tr>
</thead>
<tbody>
<?php if ($visitors): foreach ($visitors as $v): ?>
<tr>
  <td><?= (int)$v['visitor_id'] ?></td>
  <td><?= h($v['full_name']) ?></td>
  <td><?= h($v['email']) ?></td>
  <td><?= h($v['nationality']) ?></td>
  <td><?= h($v['phone']) ?></td>
  <td><?= h($v['created_at']) ?></td>
  <td><?= (int)$v['total_bookings'] ?></td>
  <td><?= $v['avg_rating'] === null ? '—' : h($v['avg_rating']) ?></td>
  <td><?= (int)$v['total_reviews'] ?></td>
  <td>
    <a href="visitor_details.php?id=<?= (int)$v['visitor_id'] ?>" class="btn btn-sm btn-info">Details</a>
    <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editModal"
      data-id="<?= (int)$v['visitor_id'] ?>" data-name="<?= h($v['full_name']) ?>"
      data-email="<?= h($v['email']) ?>" data-nationality="<?= h($v['nationality']) ?>"
      data-phone="<?= h($v['phone']) ?>">Edit</button>
    <form method="post" class="d-inline" onsubmit="return confirm('Delete this visitor?')">
      <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
      <input type="hidden" name="delete_id" value="<?= (int)$v['visitor_id'] ?>">
      <button class="btn btn-sm btn-danger">Delete</button>
    </form>
  </td>
</tr>
<?php endforeach; else: ?>
<tr><td colspan="10" class="text-center text-muted">No visitors found</td></tr>
<?php endif; ?>
</tbody>
</table>

<!-- Add Modal (duplicate of add card for convenience) -->
<div class="modal fade" id="addModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post" class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Add Visitor</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
        <div class="mb-2"><label>Name</label><input name="name" class="form-control" required></div>
        <div class="mb-2"><label>Email</label><input name="email" type="email" class="form-control" required></div>
        <div class="mb-2"><label>Nationality</label><input name="nationality" class="form-control"></div>
        <div class="mb-2"><label>Phone</label><input name="phone" class="form-control"></div>
        <div class="mb-2"><label>Password</label><input name="password" type="password" class="form-control" required></div>
      </div>
      <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal" type="button">Cancel</button><button name="add" class="btn btn-primary">Add</button></div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post" class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Edit Visitor</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
        <input type="hidden" id="edit_id" name="update_id" value="">
        <div class="mb-2"><label>Name</label><input id="edit_name" name="name" class="form-control" required></div>
        <div class="mb-2"><label>Email</label><input id="edit_email" name="email" type="email" class="form-control" required></div>
        <div class="mb-2"><label>Nationality</label><input id="edit_nationality" name="nationality" class="form-control"></div>
        <div class="mb-2"><label>Phone</label><input id="edit_phone" name="phone" class="form-control"></div>
        <div class="mb-2"><label>New password (leave blank to keep)</label><input id="edit_password" name="password" type="password" class="form-control"></div>
      </div>
      <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal" type="button">Cancel</button><button class="btn btn-primary">Save</button></div>
    </form>
  </div>
</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const editModal = document.getElementById('editModal');
editModal.addEventListener('show.bs.modal', function (event) {
  const btn = event.relatedTarget;
  document.getElementById('edit_id').value = btn.getAttribute('data-id');
  document.getElementById('edit_name').value = btn.getAttribute('data-name');
  document.getElementById('edit_email').value = btn.getAttribute('data-email');
  document.getElementById('edit_nationality').value = btn.getAttribute('data-nationality');
  document.getElementById('edit_phone').value = btn.getAttribute('data-phone');
});
</script>
</body>
</html>
