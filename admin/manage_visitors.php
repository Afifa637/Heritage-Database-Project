<?php
session_start();
require_once 'headerFooter.php';
require_once __DIR__ . '/../includes/db_connect.php';

// ---------- AUTH ----------
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

// Ensure CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// === Handle CRUD ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $csrf)) { http_response_code(400); exit('Invalid CSRF'); }

    // Basic sanitizer for inputs
    function post_str($k, $max = 255) {
        $v = trim($_POST[$k] ?? '');
        if ($v === '') return null;
        return mb_substr($v, 0, $max);
    }

    // Add
    if (isset($_POST['add'])) {
        $name = post_str('name', 120);
        $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL) ? $_POST['email'] : null;
        $nationality = post_str('nationality', 80);
        $phone = post_str('phone', 30);
        $password = $_POST['password'] ?? '';

        if (!$name || !$email || !$password) {
            // Minimal validation failure
            $_SESSION['flash_error'] = 'Name, email and password are required.';
        } else {
            $stmt = $pdo->prepare("INSERT INTO Visitors (full_name, email, nationality, phone, password_hash) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $name, $email, $nationality, $phone,
                password_hash($password, PASSWORD_DEFAULT)
            ]);
            header("Location: manage_visitors.php"); exit;
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
            $_SESSION['flash_error'] = 'Invalid update parameters.';
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
            header("Location: manage_visitors.php"); exit;
        }
    }

    // Delete
    if (isset($_POST['delete_id'])) {
        $delete_id = (int)($_POST['delete_id'] ?? 0);
        if ($delete_id > 0) {
            $stmt = $pdo->prepare("DELETE FROM Visitors WHERE visitor_id=?");
            $stmt->execute([$delete_id]);
            header("Location: manage_visitors.php"); exit;
        } else {
            $_SESSION['flash_error'] = 'Invalid delete id.';
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
    // keep a general q param if present (search across multiple fields)
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
    // validate date format (YYYY-MM-DD)
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
        $where[] = "DATE(v.created_at) >= :from";
        $params['from'] = $from;
    }
}

// bookings thresholds normalization
$minB = ($min_bookings !== '' && is_numeric($min_bookings)) ? (int) floor($min_bookings) : null;
$maxB = ($max_bookings !== '' && is_numeric($max_bookings)) ? (int) ceil($max_bookings) : null;
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

// Treat NULL avg_rating as 0 when comparing (change to raw avg_rating if you want to exclude NULLs)
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
</head>
<body>
  <div class="container py-4">
<h2 class="mb-4">👥 Manage Visitors</h2>

<?php if (!empty($_SESSION['flash_error'])): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['flash_error']) ?></div>
  <?php unset($_SESSION['flash_error']); endif; ?>

<!-- Filter Form -->
<form method="get" class="row g-3 mb-3">
  <div class="col-md-2"><input type="text" name="name" class="form-control" placeholder="Name" value="<?= htmlspecialchars($name) ?>"></div>
  <div class="col-md-2"><input type="text" name="q" class="form-control" placeholder="Search name/email/phone (q)" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>"></div>
  <div class="col-md-2">
    <select name="nationality" class="form-select">
      <option value="">All Nationalities</option>
      <?php foreach ($nations as $n): ?>
        <option value="<?= htmlspecialchars($n) ?>" <?= $n === $nationality ? 'selected' : '' ?>><?= htmlspecialchars($n) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-2"><input type="date" name="from" class="form-control" value="<?= htmlspecialchars($from) ?>"></div>
  <div class="col-md-1"><input type="number" name="min_bookings" class="form-control" placeholder="Min bookings" value="<?= htmlspecialchars($min_bookings) ?>"></div>
  <div class="col-md-1"><input type="number" name="max_bookings" class="form-control" placeholder="Max bookings" value="<?= htmlspecialchars($max_bookings) ?>"></div>
  <div class="col-md-1"><input type="number" step="0.1" name="min_rating" class="form-control" placeholder="Min rating" value="<?= htmlspecialchars($min_rating) ?>"></div>
  <div class="col-md-1"><input type="number" step="0.1" name="max_rating" class="form-control" placeholder="Max rating" value="<?= htmlspecialchars($max_rating) ?>"></div>

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
  <td><?= htmlspecialchars($v['full_name']) ?></td>
  <td><?= htmlspecialchars($v['email']) ?></td>
  <td><?= htmlspecialchars($v['nationality']) ?></td>
  <td><?= htmlspecialchars($v['phone']) ?></td>
  <td><?= htmlspecialchars($v['created_at']) ?></td>
  <td><?= (int)$v['total_bookings'] ?></td>
  <td><?= $v['avg_rating'] === null ? '—' : htmlspecialchars($v['avg_rating']) ?></td>
  <td><?= (int)$v['total_reviews'] ?></td>
  <td>
    <a href="visitor_details.php?id=<?= (int)$v['visitor_id'] ?>" class="btn btn-sm btn-info">Details</a>
    <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editModal"
      data-id="<?= (int)$v['visitor_id'] ?>" data-name="<?= htmlspecialchars($v['full_name'], ENT_QUOTES) ?>"
      data-email="<?= htmlspecialchars($v['email'], ENT_QUOTES) ?>" data-nationality="<?= htmlspecialchars($v['nationality'], ENT_QUOTES) ?>"
      data-phone="<?= htmlspecialchars($v['phone'], ENT_QUOTES) ?>">Edit</button>
    <form method="post" class="d-inline" onsubmit="return confirm('Delete this visitor?')">
      <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
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

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post" class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Add Visitor</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
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
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
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
<script>
const editModal = document.getElementById('editModal');
if (editModal) {
  editModal.addEventListener('show.bs.modal', function (event) {
    const btn = event.relatedTarget;
    document.getElementById('edit_id').value = btn.getAttribute('data-id') || '';
    document.getElementById('edit_name').value = btn.getAttribute('data-name') || '';
    document.getElementById('edit_email').value = btn.getAttribute('data-email') || '';
    document.getElementById('edit_nationality').value = btn.getAttribute('data-nationality') || '';
    document.getElementById('edit_phone').value = btn.getAttribute('data-phone') || '';
    // clear password field
    document.getElementById('edit_password').value = '';
  });
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
