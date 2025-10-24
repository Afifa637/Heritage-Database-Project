<?php
session_start();
require_once 'headerFooter.php';
require_once __DIR__ . '/../includes/db_connect.php';

if (empty($_SESSION['admin_logged_in'])) {
    header('Location: login.php'); exit;
}

if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }

// === Handle Add/Edit/Delete ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $csrf)) { http_response_code(400); exit('Invalid CSRF'); }

    // Add assignment
    if (isset($_POST['add'])) {
        $stmt = $pdo->prepare('INSERT INTO Assignments (guide_id, site_id, event_id) VALUES (:g, :s, NULL)');
        $stmt->execute(['g'=>$_POST['guide_id'], 's'=>$_POST['site_id']]);
        header('Location: manage_assignments.php'); exit;
    }

    // Edit assignment
    if (isset($_POST['update_id'])) {
        $stmt = $pdo->prepare('UPDATE Assignments SET guide_id=:g, site_id=:s WHERE assign_id=:id');
        $stmt->execute(['g'=>$_POST['guide_id'], 's'=>$_POST['site_id'], 'id'=>$_POST['update_id']]);
        header('Location: manage_assignments.php'); exit;
    }

    // Delete assignment
    if (isset($_POST['delete_id'])) {
        $stmt = $pdo->prepare('DELETE FROM Assignments WHERE assign_id=:id');
        $stmt->execute(['id'=>$_POST['delete_id']]);
        header('Location: manage_assignments.php'); exit;
    }
}

// === Fetch for filters and table ===
$guides = $pdo->query('SELECT guide_id, full_name FROM Guides ORDER BY full_name')->fetchAll(PDO::FETCH_ASSOC);
$sites = $pdo->query('SELECT site_id, name FROM HeritageSites ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);

// === Filters ===
$where = [];
$params = [];

if (!empty($_GET['guide_id'])) { $where[] = "a.guide_id=:g"; $params['g'] = $_GET['guide_id']; }
if (!empty($_GET['site_id'])) { $where[] = "a.site_id=:s"; $params['s'] = $_GET['site_id']; }

$sql = "SELECT a.assign_id, g.full_name, s.name AS site_name
        FROM Assignments a
        JOIN Guides g ON a.guide_id=g.guide_id
        JOIN HeritageSites s ON a.site_id=s.site_id";

if ($where) { $sql .= " WHERE " . implode(" AND ", $where); }
$sql .= " ORDER BY s.name, g.full_name";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Manage Assignments</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
.panel { background: #f8f9fa; padding: 1rem; border-radius: 10px; margin-bottom: 1rem; }
</style>
</head>
<body>
  <div class="container py-4">
<h2 class="mb-4">📝 Manage Assignments</h2>

<!-- Add Assignment -->
<div class="panel mb-3">
<form method="post" class="row g-2 align-items-end">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <div class="col-md-4">
        <label class="form-label">Guide</label>
        <select name="guide_id" class="form-select" required>
            <option value="">Select Guide</option>
            <?php foreach($guides as $g): ?>
                <option value="<?= $g['guide_id'] ?>"><?= htmlspecialchars($g['full_name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">Site</label>
        <select name="site_id" class="form-select" required>
            <option value="">Select Site</option>
            <?php foreach($sites as $s): ?>
                <option value="<?= $s['site_id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-4"><button class="btn btn-success w-100" name="add">➕ Assign</button></div>
</form>
</div>

<!-- Filter Panel -->
<form method="get" class="panel row g-3 align-items-end mb-3">
    <div class="col-md-4">
        <label class="form-label">Filter by Guide</label>
        <select name="guide_id" class="form-select">
            <option value="">All Guides</option>
            <?php foreach($guides as $g): ?>
            <option value="<?= $g['guide_id'] ?>" <?= ($_GET['guide_id']??'')==$g['guide_id']?'selected':'' ?>><?= htmlspecialchars($g['full_name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">Filter by Site</label>
        <select name="site_id" class="form-select">
            <option value="">All Sites</option>
            <?php foreach($sites as $s): ?>
            <option value="<?= $s['site_id'] ?>" <?= ($_GET['site_id']??'')==$s['site_id']?'selected':'' ?>><?= htmlspecialchars($s['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-4"><button class="btn btn-primary w-100">Apply Filter</button></div>
</form>

<!-- Executed Query -->
<div class="alert alert-secondary small">
    <strong>Executed SQL:</strong> <code><?= htmlspecialchars($sql) ?></code>
</div>

<!-- Assignments Table -->
<table class="table table-bordered table-striped">
<thead class="table-dark">
<tr>
    <th>Guide</th>
    <th>Site</th>
    <th>Actions</th>
</tr>
</thead>
<tbody>
<?php if($assignments): foreach($assignments as $a): ?>
<tr>
    <td><?= htmlspecialchars($a['full_name']) ?></td>
    <td><?= htmlspecialchars($a['site_name']) ?></td>
    <td>
        <!-- Edit Button -->
        <button class="btn btn-sm btn-warning" 
                data-bs-toggle="modal" 
                data-bs-target="#editModal"
                data-id="<?= $a['assign_id'] ?>"
                data-guide="<?= $a['full_name'] ?>"
                data-site="<?= $a['site_name'] ?>">Edit</button>

        <!-- Delete -->
        <form method="post" class="d-inline" onsubmit="return confirm('Remove assignment?');">
            <input type="hidden" name="delete_id" value="<?= $a['assign_id'] ?>">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <button class="btn btn-sm btn-danger">Remove</button>
        </form>
    </td>
</tr>
<?php endforeach; else: ?>
<tr><td colspan="3" class="text-center text-muted">No assignments found</td></tr>
<?php endif; ?>
</tbody>
</table>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
<div class="modal-dialog">
<form method="post" class="modal-content">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="update_id" id="edit_id">
    <div class="modal-header">
        <h5 class="modal-title">Edit Assignment</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body row g-2">
        <div class="col-md-6">
            <label class="form-label">Guide</label>
            <select name="guide_id" id="edit_guide" class="form-select" required>
                <?php foreach($guides as $g): ?>
                <option value="<?= $g['guide_id'] ?>"><?= htmlspecialchars($g['full_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label">Site</label>
            <select name="site_id" id="edit_site" class="form-select" required>
                <?php foreach($sites as $s): ?>
                <option value="<?= $s['site_id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary">Update</button>
    </div>
</form>
</div>
</div>
</div>

<script>
var editModal = document.getElementById('editModal');
editModal.addEventListener('show.bs.modal', function(event) {
    const button = event.relatedTarget;
    const assignId = button.getAttribute('data-id');
    const guideName = button.getAttribute('data-guide');
    const siteName = button.getAttribute('data-site');

    document.getElementById('edit_id').value = assignId;

    // Set guide selection
    const guideSelect = document.getElementById('edit_guide');
    for (let opt of guideSelect.options) {
        if (opt.text === guideName) { opt.selected = true; break; }
    }

    // Set site selection
    const siteSelect = document.getElementById('edit_site');
    for (let opt of siteSelect.options) {
        if (opt.text === siteName) { opt.selected = true; break; }
    }
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
