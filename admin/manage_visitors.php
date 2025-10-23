<?php
session_start();
require_once 'headerFooter.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdminLogin();

// CSRF
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// === Handle CRUD ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $csrf)) { http_response_code(400); exit('Invalid CSRF'); }

    if (isset($_POST['add'])) {
        $stmt = $pdo->prepare("INSERT INTO Visitors (name, email, nationality, phone, password_hash) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['name'], $_POST['email'], $_POST['nationality'], $_POST['phone'],
            password_hash($_POST['password'], PASSWORD_DEFAULT)
        ]);
        header("Location: manage_visitors.php"); exit;
    }

    if (isset($_POST['update_id'])) {
        if (!empty($_POST['password'])) {
            $stmt = $pdo->prepare("UPDATE Visitors SET name=?, email=?, nationality=?, phone=?, password_hash=? WHERE visitor_id=?");
            $stmt->execute([
                $_POST['name'], $_POST['email'], $_POST['nationality'], $_POST['phone'],
                password_hash($_POST['password'], PASSWORD_DEFAULT), $_POST['update_id']
            ]);
        } else {
            $stmt = $pdo->prepare("UPDATE Visitors SET name=?, email=?, nationality=?, phone=? WHERE visitor_id=?");
            $stmt->execute([
                $_POST['name'], $_POST['email'], $_POST['nationality'], $_POST['phone'], $_POST['update_id']
            ]);
        }
        header("Location: manage_visitors.php"); exit;
    }

    if (isset($_POST['delete_id'])) {
        $stmt = $pdo->prepare("DELETE FROM Visitors WHERE visitor_id=?");
        $stmt->execute([$_POST['delete_id']]);
        header("Location: manage_visitors.php"); exit;
    }
}

// === Filters & Sorting ===
$q = $_GET['q'] ?? '';
$nationality = $_GET['nationality'] ?? '';
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$sort = $_GET['sort'] ?? 'created_at';
$order = ($_GET['order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

$where = [];
$params = [];

if ($q) {
    $where[] = "(name LIKE :kw OR email LIKE :kw OR phone LIKE :kw)";
    $params['kw'] = "%$q%";
}
if ($nationality) {
    $where[] = "nationality = :nation";
    $params['nation'] = $nationality;
}
if ($from) {
    $where[] = "DATE(created_at) >= :from";
    $params['from'] = $from;
}
if ($to) {
    $where[] = "DATE(created_at) <= :to";
    $params['to'] = $to;
}

$sql = "SELECT * FROM Visitors";
if ($where) $sql .= " WHERE " . implode(" AND ", $where);
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
<body class="container py-4">
<h2 class="mb-4">👥 Manage Visitors</h2>

<form method="get" class="row g-3 mb-3">
  <div class="col-md-3">
    <input type="text" name="q" class="form-control" placeholder="Search name/email/phone" value="<?= htmlspecialchars($q) ?>">
  </div>
  <div class="col-md-2">
    <select name="nationality" class="form-select">
      <option value="">All Nationalities</option>
      <?php foreach ($nations as $n): ?>
        <option value="<?= htmlspecialchars($n) ?>" <?= $n === $nationality ? 'selected' : '' ?>><?= htmlspecialchars($n) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-2"><input type="date" name="from" class="form-control" value="<?= $from ?>"></div>
  <div class="col-md-2">
    <select name="sort" class="form-select">
      <option value="created_at" <?= $sort==='created_at'?'selected':'' ?>>Join Date</option>
      <option value="name" <?= $sort==='name'?'selected':'' ?>>Name</option>
      <option value="email" <?= $sort==='email'?'selected':'' ?>>Email</option>
    </select>
  </div>
  <div class="col-md-1">
    <select name="order" class="form-select">
      <option value="ASC" <?= $order==='ASC'?'selected':'' ?>>⬆️</option>
      <option value="DESC" <?= $order==='DESC'?'selected':'' ?>>⬇️</option>
    </select>
  </div>
  <div class="col-md-1">
    <button class="btn btn-primary w-100">Filter</button>
  </div>
</form>

<button class="btn btn-success mb-3" data-bs-toggle="modal" data-bs-target="#addModal">➕ Add Visitor</button>

<table class="table table-striped table-bordered">
<thead class="table-dark">
<tr>
  <th>ID</th>
  <th>Name</th>
  <th>Email</th>
  <th>Nationality</th>
  <th>Phone</th>
  <th>Joined</th>
  <th>Actions</th>
</tr>
</thead>
<tbody>
<?php if ($visitors): foreach ($visitors as $v): ?>
<tr>
  <td><?= $v['visitor_id'] ?></td>
  <td><?= htmlspecialchars($v['name']) ?></td>
  <td><?= htmlspecialchars($v['email']) ?></td>
  <td><?= htmlspecialchars($v['nationality']) ?></td>
  <td><?= htmlspecialchars($v['phone']) ?></td>
  <td><?= htmlspecialchars($v['created_at']) ?></td>
  <td>
    <a href="visitor_details.php?id=<?= $v['visitor_id'] ?>" class="btn btn-sm btn-info">Details</a>
    <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editModal"
      data-id="<?= $v['visitor_id'] ?>" data-name="<?= htmlspecialchars($v['name']) ?>"
      data-email="<?= htmlspecialchars($v['email']) ?>" data-nationality="<?= htmlspecialchars($v['nationality']) ?>"
      data-phone="<?= htmlspecialchars($v['phone']) ?>">Edit</button>
    <form method="post" class="d-inline" onsubmit="return confirm('Delete this visitor?')">
      <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
      <input type="hidden" name="delete_id" value="<?= $v['visitor_id'] ?>">
      <button class="btn btn-sm btn-danger">Delete</button>
    </form>
  </td>
</tr>
<?php endforeach; else: ?>
<tr><td colspan="7" class="text-center text-muted">No visitors found</td></tr>
<?php endif; ?>
</tbody>
</table>

<!-- Add/Edit Modals (same as before, unchanged for brevity) -->

<script>
var editModal = document.getElementById('editModal');
editModal.addEventListener('show.bs.modal', function (event) {
  const btn = event.relatedTarget;
  document.getElementById('edit_id').value = btn.getAttribute('data-id');
  document.getElementById('edit_name').value = btn.getAttribute('data-name');
  document.getElementById('edit_email').value = btn.getAttribute('data-email');
  document.getElementById('edit_nationality').value = btn.getAttribute('data-nationality');
  document.getElementById('edit_phone').value = btn.getAttribute('data-phone');
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
