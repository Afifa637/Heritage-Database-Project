<?php
session_start();
require_once 'headerFooter.php';
require_once __DIR__ . '/../includes/db_connect.php';
if (empty($_SESSION['admin_logged_in'])) { header("Location: login.php"); exit; }

// CSRF token
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// === Handle Update/Delete ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $csrf)) { http_response_code(400); exit('Invalid CSRF'); }

    // Update status
    if (isset($_POST['update_id'], $_POST['status'])) {
        $stmt = $pdo->prepare("UPDATE Payments SET status=? WHERE payment_id=?");
        $stmt->execute([$_POST['status'], $_POST['update_id']]);
        header("Location: manage_payments.php"); exit;
    }

    // Delete payment
    if (isset($_POST['delete_id'])) {
        $stmt = $pdo->prepare("DELETE FROM Payments WHERE payment_id=?");
        $stmt->execute([$_POST['delete_id']]);
        header("Location: manage_payments.php"); exit;
    }
}

// === Filters ===
$where = [];
$params = [];

// Search by visitor id or site name
if (!empty($_GET['q'])) {
    $where[] = "(b.visitor_id LIKE :kw OR s.name LIKE :kw)";
    $params['kw'] = '%'.$_GET['q'].'%';
}

// Filter by method
if (!empty($_GET['method'])) {
    $where[] = "p.method = :method";
    $params['method'] = $_GET['method'];
}

// Filter by status
if (!empty($_GET['status'])) {
    $where[] = "p.status = :status";
    $params['status'] = $_GET['status'];
}

// Filter by site name
if (!empty($_GET['site'])) {
  $where[] = "s.name LIKE :site";
  $params['site'] = '%'.$_GET['site'].'%';
}

// Filter by date range
if (!empty($_GET['from_date']) && !empty($_GET['to_date'])) {
    $where[] = "p.paid_at BETWEEN :from AND :to";
    $params['from'] = $_GET['from_date'].' 00:00:00';
    $params['to'] = $_GET['to_date'].' 23:59:59';
}

// Filter by ticket amount threshold (min and max optional)
if (isset($_GET['min_amount']) && $_GET['min_amount'] !== '') {
  $where[] = "p.amount >= :minA";
  $params['minA'] = $_GET['min_amount'];
}
if (isset($_GET['max_amount']) && $_GET['max_amount'] !== '') {
  $where[] = "p.amount <= :maxA";
  $params['maxA'] = $_GET['max_amount'];
}
// === Fetch Payments ===
$sql = "SELECT p.*, b.visitor_id, s.name AS site_name
        FROM Payments p
        JOIN Bookings b ON p.booking_id = b.booking_id
        JOIN HeritageSites s ON b.site_id = s.site_id";

if ($where) $sql .= " WHERE ".implode(" AND ", $where);
$sql .= " ORDER BY p.paid_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Payment method list
$methods = ['bkash','nagad','rocket','card','bank_transfer'];
$statuses = ['initiated','successful','failed','refunded'];

// Analytics
$totalPayments = $pdo->query("SELECT COUNT(*) FROM Payments")->fetchColumn();
$totalAmount = $pdo->query("SELECT SUM(amount) FROM Payments")->fetchColumn() ?: 0;
$methodCounts = $pdo->query("SELECT method, COUNT(*) AS cnt FROM Payments GROUP BY method")->fetchAll(PDO::FETCH_ASSOC);

?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Manage Payments</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
.stat-box { background:#f8f9fa; border-radius:10px; padding:1rem; text-align:center; margin-bottom:1rem; box-shadow:0 1px 3px rgba(0,0,0,0.1);}
</style>
</head>
<body class="container py-4">

<h2 class="mb-4">💰 Manage Payments</h2>

<!-- Filters -->
<form method="get" class="row g-3 mb-3">
    <div class="col-md-3">
        <input type="text" name="q" class="form-control" placeholder="Visitor ID or Site" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
    </div>
    <div class="col-md-2">
        <select name="method" class="form-select">
            <option value="">All Methods</option>
            <?php foreach($methods as $m): ?>
                <option value="<?= $m ?>" <?= (($_GET['method']??'')==$m)?'selected':'' ?>><?= ucfirst($m) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2">
        <select name="status" class="form-select">
            <option value="">All Status</option>
            <?php foreach($statuses as $s): ?>
                <option value="<?= $s ?>" <?= (($_GET['status']??'')==$s)?'selected':'' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2">
        <input type="number" name="min_amount" class="form-control" placeholder="Min Amount" value="<?= htmlspecialchars($_GET['min_amount'] ?? '') ?>">
    </div>
    <div class="col-md-2">
        <input type="number" name="max_amount" class="form-control" placeholder="Max Amount" value="<?= htmlspecialchars($_GET['max_amount'] ?? '') ?>">
    </div>
    <div class="col-md-3">
        <label class="form-label">From Date</label>
        <input type="date" name="from_date" class="form-control" value="<?= htmlspecialchars($_GET['from_date'] ?? '') ?>">
    </div>
    <div class="col-md-3">
        <label class="form-label">To Date</label>
        <input type="date" name="to_date" class="form-control" value="<?= htmlspecialchars($_GET['to_date'] ?? '') ?>">
    </div>
    <div class="col-md-2 align-self-end">
        <button class="btn btn-primary w-100">Filter</button>
    </div>
</form>

<!-- Analytics -->
<div class="row mb-4">
    <div class="col-md-3"><div class="stat-box"><h5>Total Payments</h5><p><?= $totalPayments ?></p></div></div>
    <div class="col-md-3"><div class="stat-box"><h5>Total Amount</h5><p><?= number_format($totalAmount,2) ?></p></div></div>
    <div class="col-md-6"><div class="stat-box"><h5>By Method</h5>
        <?php foreach($methodCounts as $mc): ?>
            <small><?= ucfirst($mc['method']) ?> (<?= $mc['cnt'] ?>)</small>&nbsp;
        <?php endforeach; ?>
    </div></div>
</div>

<!-- Payments Table -->
<table class="table table-bordered table-striped align-middle">
<thead class="table-dark">
<tr>
    <th>ID</th>
    <th>Visitor</th>
    <th>Site</th>
    <th>Amount</th>
    <th>Method</th>
    <th>Status</th>
    <th>Date</th>
    <th>Actions</th>
</tr>
</thead>
<tbody>
<?php if($payments): foreach($payments as $p): ?>
<tr>
    <td><?= $p['payment_id'] ?></td>
    <td><?= $p['visitor_id'] ?></td>
    <td><?= htmlspecialchars($p['site_name']) ?></td>
    <td><?= number_format($p['amount'],2) ?></td>
    <td><?= htmlspecialchars($p['method']) ?></td>
    <td><?= ucfirst($p['status']) ?></td>
    <td><?= htmlspecialchars($p['paid_at']) ?></td>
    <td>
        <!-- Edit Status -->
        <button class="btn btn-sm btn-warning" 
                data-bs-toggle="modal" 
                data-bs-target="#editModal"
                data-id="<?= $p['payment_id'] ?>"
                data-status="<?= $p['status'] ?>">Edit</button>
        <!-- Delete -->
        <form method="post" class="d-inline" onsubmit="return confirm('Delete this payment?');">
            <input type="hidden" name="delete_id" value="<?= $p['payment_id'] ?>">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <button class="btn btn-sm btn-danger">Delete</button>
        </form>
    </td>
</tr>
<?php endforeach; else: ?>
<tr><td colspan="8" class="text-center text-muted">No payments found</td></tr>
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
        <h5 class="modal-title">Edit Payment Status</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
        <select name="status" id="edit_status" class="form-select" required>
            <?php foreach($statuses as $s): ?>
                <option value="<?= $s ?>"><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary">Update</button>
    </div>
</form>
</div>
</div>

<script>
var editModal = document.getElementById('editModal');
editModal.addEventListener('show.bs.modal', function(event){
    const btn = event.relatedTarget;
    document.getElementById('edit_id').value = btn.getAttribute('data-id');
    document.getElementById('edit_status').value = btn.getAttribute('data-status');
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
