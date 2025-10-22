<?php
session_start();
require_once 'headerFooter.php';
require_once __DIR__ . '/../includes/db_connect.php';

if (empty($_SESSION['admin_logged_in'])) {
    header('Location: login.php'); exit;
}

if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }

// Add assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guide_id'], $_POST['site_id'])) {
    $guide_id = (int)$_POST['guide_id'];
    $site_id = (int)$_POST['site_id'];
    $csrf = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $csrf)) { http_response_code(400); exit; }

    $stmt = $pdo->prepare('INSERT INTO Assignments (guide_id, site_id) VALUES (:g, :s)');
    $stmt->execute(['g'=>$guide_id, 's'=>$site_id]);
    header('Location: manage_assignments.php'); exit;
}

// Remove assignment
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['delete_id'])) {
    $del = (int)$_POST['delete_id'];
    $csrf = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $csrf)) { http_response_code(400); exit; }
    $pdo->prepare('DELETE FROM Assignments WHERE assignment_id=:id')->execute(['id'=>$del]);
    header('Location: manage_assignments.php'); exit;
}

// Fetch assignments
$assignments = $pdo->query('SELECT a.assign_id, g.full_name, s.name AS site_name 
                            FROM Assignments a 
                            JOIN Guides g ON a.guide_id=g.guide_id 
                            JOIN HeritageSites s ON a.site_id=s.site_id
                            ORDER BY s.name')->fetchAll(PDO::FETCH_ASSOC);

$guides = $pdo->query('SELECT * FROM Guides ORDER BY full_name')->fetchAll(PDO::FETCH_ASSOC);
$sites = $pdo->query('SELECT * FROM HeritageSites ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Manage Assignments</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container py-4">
<h2>Assign Guides to Sites</h2>

<form method="post" class="row g-3 mb-3">
<div class="col-md-4">
<select name="guide_id" class="form-select" required>
<option value="">Select Guide</option>
<?php foreach($guides as $g): ?>
<option value="<?= $g['guide_id'] ?>"><?= htmlspecialchars($g['full_name']) ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="col-md-4">
<select name="site_id" class="form-select" required>
<option value="">Select Site</option>
<?php foreach($sites as $s): ?>
<option value="<?= $s['site_id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
<?php endforeach; ?>
</select>
</div>
<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
<div class="col-md-4">
<button type="submit" class="btn btn-success">Assign</button>
</div>
</form>

<table class="table table-bordered">
<thead>
<tr>
<th>Guide</th>
<th>Site</th>
<th>Actions</th>
</tr>
</thead>
<tbody>
<?php foreach($assignments as $a): ?>
<tr>
<td><?= htmlspecialchars($a['full_name']) ?></td>
<td><?= htmlspecialchars($a['site_name']) ?></td>
<td>
<form method="post" class="d-inline" onsubmit="return confirm('Remove assignment?');">
<input type="hidden" name="delete_id" value="<?= $a['assign_id'] ?>">
<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
<button type="submit" class="btn btn-sm btn-danger">Remove</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
</body>
</html>
