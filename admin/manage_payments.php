<?php
// manage_payments.php
declare(strict_types=1);
session_start();
require_once 'headerFooter.php';
require_once __DIR__ . '/../includes/db_connect.php';
if (empty($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit;
}

// CSRF token
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// Utility
function h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_HTML5);
}

// Ensure PaymentMethods table exists (lookup table)
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS PaymentMethods (
          method_id INT AUTO_INCREMENT PRIMARY KEY,
          code VARCHAR(50) NOT NULL UNIQUE,
          label VARCHAR(150) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
} catch (Exception $e) {
}

// Allowed safe read-only queries (whitelist)
$safe_queries = [
    'distinct_methods' => [
        'title' => 'Distinct payment methods in Payments table',
        'sql' => "SELECT DISTINCT method FROM Payments ORDER BY method"
    ],
    'enum_definition' => [
        'title' => 'Payments.method ENUM definition (information_schema)',
        'sql' => "SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Payments' AND COLUMN_NAME = 'method' LIMIT 1"
    ],
    'payments_last_10' => [
        'title' => 'Last 10 Payments with visitor & target (site/event)',
        'sql' => "SELECT p.payment_id, p.amount, p.method, p.status, p.paid_at, b.booking_id, b.no_of_tickets, v.full_name AS visitor,
COALESCE(s.name, e.name) AS target_name
FROM Payments p
JOIN Bookings b ON p.booking_id = b.booking_id
JOIN Visitors v ON b.visitor_id = v.visitor_id
LEFT JOIN HeritageSites s ON b.site_id = s.site_id
LEFT JOIN Events e ON b.event_id = e.event_id
ORDER BY p.paid_at DESC LIMIT 10"
    ],
    'monthly_revenue' => [
        'title' => 'Monthly revenue (last 24 months)',
        'sql' => "SELECT DATE_FORMAT(paid_at, '%Y-%m') AS month, SUM(amount) AS revenue, COUNT(*) AS payments_count
FROM Payments
WHERE status = 'successful'
GROUP BY month
ORDER BY month DESC
LIMIT 24"
    ],
    'count_by_method' => [
        'title' => 'Count & total by method',
        'sql' => "SELECT method, COUNT(*) AS cnt, SUM(amount) AS total_amount FROM Payments GROUP BY method ORDER BY cnt DESC"
    ],
    'payments_without_booking' => [
        'title' => 'Payments with missing booking (should be none)',
        'sql' => "SELECT p.* FROM Payments p LEFT JOIN Bookings b ON p.booking_id = b.booking_id WHERE b.booking_id IS NULL"
    ],
    'recent_failed' => [
        'title' => 'Recent failed payments',
        'sql' => "SELECT p.payment_id, p.amount, p.method, p.status, p.paid_at, v.full_name AS visitor
FROM Payments p JOIN Bookings b ON p.booking_id = b.booking_id JOIN Visitors v ON b.visitor_id = v.visitor_id
WHERE p.status = 'failed' ORDER BY p.paid_at DESC LIMIT 50"
    ],
];

// POST handling: add method, update/delete payment, run query
$messages = [];
$errors = [];
$query_result = null;
$selected_query_key = '';
// Add payment method (admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_method') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $csrf)) {
        $errors[] = 'Invalid CSRF';
    } else {
        $code = trim((string)($_POST['method_code'] ?? ''));
        $label = trim((string)($_POST['method_label'] ?? ''));
        if ($code === '' || !preg_match('/^[a-z0-9_\-]{2,40}$/i', $code)) {
            $errors[] = 'Method code required (alphanum, underscore, dash; 2-40 chars).';
        }
        if ($label === '' || strlen($label) > 150) {
            $errors[] = 'Method label required (max 150 chars).';
        }
        if (empty($errors)) {
            try {
                // insert into PaymentMethods (ignore duplicate)
                $ins = $pdo->prepare("INSERT IGNORE INTO PaymentMethods (code,label) VALUES (?, ?)");
                $ins->execute([$code, $label]);

                // Now update Payments.method enum to include this new value (if not already present).
                // Read existing enum definition
                $row = $pdo->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Payments' AND COLUMN_NAME = 'method' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                $current_enum = $row['COLUMN_TYPE'] ?? null;
                if ($current_enum && preg_match("/^enum\((.*)\)$/i", $current_enum, $m)) {
                    // parse existing values
                    $vals = str_getcsv($m[1], ',', "'");
                    $vals = array_map(function ($v) {
                        return trim($v, " \t\n\r\0\x0B'");
                    }, $vals);
                    // if code not present, add it and alter table
                    if (!in_array($code, $vals, true)) {
                        $vals[] = $code;
                        $escaped = array_map(function ($v) {
                            return "'" . str_replace("'", "''", $v) . "'";
                        }, $vals);
                        $new_enum = "ENUM(" . implode(',', $escaped) . ") NOT NULL";
                        $alter_sql = "ALTER TABLE Payments MODIFY COLUMN method {$new_enum}";
                        $pdo->exec($alter_sql);
                        $messages[] = "Payments.method enum updated to include '{$code}'.";
                    }
                } else {
                    $messages[] = "PaymentMethods created/updated; Payments.method enum not found or not parsed — admin should verify DB enum manually.";
                }
                $messages[] = "Method '{$code}' added.";
            } catch (Exception $e) {
                $errors[] = "Failed to add method: " . $e->getMessage();
            }
        }
    }
}

// Update or delete payment (existing functionality)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'payment_modify') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $csrf)) {
        $errors[] = 'Invalid CSRF';
    } else {
        if (isset($_POST['update_id'], $_POST['status'])) {
            $stmt = $pdo->prepare("UPDATE Payments SET status=? WHERE payment_id=?");
            $stmt->execute([$_POST['status'], $_POST['update_id']]);
            $messages[] = "Payment status updated.";
        } elseif (isset($_POST['delete_id'])) {
            $stmt = $pdo->prepare("DELETE FROM Payments WHERE payment_id=?");
            $stmt->execute([$_POST['delete_id']]);
            $messages[] = "Payment deleted.";
        }
    }
}

// Run a safe read-only query (from whitelist)
$query_result = null;
$selected_query_key = $_GET['run_q'] ?? ''; // allow GET for convenience
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'run_query') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $csrf)) {
        $errors[] = 'Invalid CSRF';
    } else {
        $qkey = $_POST['query_key'] ?? '';
        if (!isset($safe_queries[$qkey])) {
            $errors[] = 'Invalid query selected.';
        } else {
            $selected_query_key = $qkey;
            try {
                $sql = $safe_queries[$qkey]['sql'];
                $stmtQ = $pdo->query($sql);
                $query_result = $stmtQ->fetchAll(PDO::FETCH_ASSOC);
                $messages[] = 'Query executed.';
            } catch (Exception $e) {
                $errors[] = 'Query failed: ' . $e->getMessage();
            }
        }
    }
}

// Filters & fetch payments (preserve your original logic, but use dynamic methods)
$where = [];
$params = [];

// Search by visitor id or site name
if (!empty($_GET['q'])) {
    $where[] = "(b.visitor_id LIKE :kw OR s.name LIKE :kw)";
    $params['kw'] = '%' . $_GET['q'] . '%';
}

// Filter by method
// methods list will come from PaymentMethods if present
$method_filter = $_GET['method'] ?? '';
if ($method_filter !== '') {
    $where[] = "p.method = :method";
    $params['method'] = $method_filter;
}

// Filter by status
if (!empty($_GET['status'])) {
    $where[] = "p.status = :status";
    $params['status'] = $_GET['status'];
}

// Filter by site name
if (!empty($_GET['site'])) {
    $where[] = "s.name LIKE :site";
    $params['site'] = '%' . $_GET['site'] . '%';
}

// Date range
if (!empty($_GET['from_date']) && !empty($_GET['to_date'])) {
    $where[] = "p.paid_at BETWEEN :from AND :to";
    $params['from'] = $_GET['from_date'] . ' 00:00:00';
    $params['to'] = $_GET['to_date'] . ' 23:59:59';
}

// Amount thresholds
if (isset($_GET['min_amount']) && $_GET['min_amount'] !== '') {
    $where[] = "p.amount >= :minA";
    $params['minA'] = $_GET['min_amount'];
}
if (isset($_GET['max_amount']) && $_GET['max_amount'] !== '') {
    $where[] = "p.amount <= :maxA";
    $params['maxA'] = $_GET['max_amount'];
}

$sql = "SELECT p.*, b.visitor_id, b.site_id, b.event_id,
        COALESCE(s.name, e.name) AS target_name
        FROM Payments p
        JOIN Bookings b ON p.booking_id = b.booking_id
        JOIN Visitors v ON b.visitor_id = v.visitor_id
        LEFT JOIN HeritageSites s ON b.site_id = s.site_id
        LEFT JOIN Events e ON b.event_id = e.event_id";

if ($where) $sql .= " WHERE " . implode(" AND ", $where);
$sql .= " ORDER BY p.paid_at DESC LIMIT 500"; // limit for safety

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Methods list: prefer PaymentMethods table
$methods = [];
try {
    $rows = $pdo->query("SELECT code, label FROM PaymentMethods ORDER BY method_id")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) $methods[$r['code']] = $r['label'];
} catch (Exception $e) {
    $methods = [];
}

// Fallback: parse enum
if (empty($methods)) {
    $row = $pdo->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Payments' AND COLUMN_NAME = 'method' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $enum = $row['COLUMN_TYPE'] ?? null;
    if ($enum && preg_match("/^enum\((.*)\)$/i", $enum, $m)) {
        $vals = str_getcsv($m[1], ',', "'");
        foreach ($vals as $v) {
            $v = trim($v, " \t\n\r\0\x0B'");
            if ($v !== '') $methods[$v] = ucfirst($v);
        }
    } else {
        // final fallback: distinct methods from Payments
        $rows2 = $pdo->query("SELECT DISTINCT method FROM Payments ORDER BY method")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($rows2 as $v) if ($v !== '') $methods[$v] = ucfirst($v);
    }
}

// Analytics
$totalPayments = (int)$pdo->query("SELECT COUNT(*) FROM Payments")->fetchColumn();
$totalAmount = (float)($pdo->query("SELECT SUM(amount) FROM Payments")->fetchColumn() ?: 0.0);
$methodCounts = $pdo->query("SELECT method, COUNT(*) AS cnt FROM Payments GROUP BY method")->fetchAll(PDO::FETCH_ASSOC);

?>
<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <title>Manage Payments</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .stat-box {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            text-align: center;
            margin-bottom: 1rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
        }

        pre.sql-block {
            background: #0b1220;
            color: #e6f0ff;
            padding: 10px;
            border-radius: 6px;
            overflow: auto;
            font-size: 0.9rem;
        }

        .small-muted {
            font-size: 0.85rem;
            color: #6c757d;
        }
    </style>
</head>

<body>
    <div class="container py-4">
        <h2 class="mb-3">💰 Manage Payments</h2>
        <?php foreach ($messages as $m): ?><div class="alert alert-success"><?= h($m) ?></div><?php endforeach; ?>
        <?php foreach ($errors as $e): ?><div class="alert alert-danger"><?= h($e) ?></div><?php endforeach; ?>

        <!-- Row: Add method + SQL runner -->
        <div class="row mb-4">
            <div class="col-md-5">
                <div class="card p-3">
                    <h5 class="mb-2">➕ Add Payment Method</h5>
                    <form method="post" class="row g-2">
                        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="action" value="add_method">
                        <div class="col-12">
                            <label class="form-label">Method code (alphanum, underscore, dash)</label>
                            <input name="method_code" class="form-control" placeholder="e.g. bkash" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Label (display name)</label>
                            <input name="method_label" class="form-control" placeholder="e.g. Bkash Wallet" required>
                        </div>
                        <div class="col-12 text-end">
                            <button class="btn btn-success">Add Method</button>
                        </div>
                    </form>
                    <div class="small-muted mt-2">Note: This will insert into <code>PaymentMethods</code> and attempt to add the code to the <code>Payments.method</code> ENUM.</div>
                </div>
            </div>

            <div class="col-md-7">
                <div class="card p-3">
                    <h5 class="mb-2">🧾 SQL Runner Safe Queries</h5>
                    <form method="post" class="d-flex gap-2 align-items-start">
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
                </div>
                <?php if ($query_result !== null): ?>
                    <div class="card mt-3 p-3">
                        <h6>Query Result (<?= count($query_result) ?> rows)</h6>
                        <?php if (count($query_result) === 0): ?>
                            <div class="text-muted">No rows returned.</div>
                        <?php else: ?>
                            <div style="overflow:auto; max-height:400px;">
                                <table class="table table-sm table-bordered">
                                    <thead class="table-dark">
                                        <tr>
                                            <?php foreach (array_keys($query_result[0]) as $col): ?>
                                                <th><?= h($col) ?></th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($query_result as $row): ?>
                                            <tr>
                                                <?php foreach ($row as $cell): ?>
                                                    <td><?= h($cell) ?></td>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

       <!-- Filters -->
  <form method="get" class="row g-3 mb-3">
    <div class="col-md-3"><input type="text" name="q" class="form-control" placeholder="Visitor ID / Site / Event" value="<?= h($_GET['q'] ?? '') ?>"></div>

    <div class="col-md-2">
      <select name="method" class="form-select">
        <option value="">All Methods</option>
        <?php foreach ($methods as $code => $label): ?>
          <option value="<?= h($code) ?>" <?= (($_GET['method'] ?? '') == $code) ? 'selected' : '' ?>><?= h($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-md-2">
      <select name="status" class="form-select">
        <option value="">All Status</option>
        <?php foreach (['initiated','successful','failed','refunded'] as $s): ?>
          <option value="<?= $s ?>" <?= (($_GET['status'] ?? '') == $s) ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-md-2"><input type="number" name="min_amount" class="form-control" placeholder="Min" value="<?= h($_GET['min_amount'] ?? '') ?>"></div>
    <div class="col-md-2"><input type="number" name="max_amount" class="form-control" placeholder="Max" value="<?= h($_GET['max_amount'] ?? '') ?>"></div>

    <div class="col-md-3"><label>From</label><input type="date" name="from_date" class="form-control" value="<?= h($_GET['from_date'] ?? '') ?>"></div>
    <div class="col-md-3"><label>To</label><input type="date" name="to_date" class="form-control" value="<?= h($_GET['to_date'] ?? '') ?>"></div>
    <div class="col-md-2 align-self-end"><button class="btn btn-primary w-100">Filter</button></div>
  </form>

  <!-- Analytics -->
  <div class="row mb-4">
    <div class="col-md-3"><div class="stat-box"><h6>Total Payments</h6><div><?= $totalPayments ?></div></div></div>
    <div class="col-md-3"><div class="stat-box"><h6>Total Amount</h6><div><?= number_format((float)$totalAmount, 2) ?></div></div></div>
    <div class="col-md-6"><div class="stat-box"><h6>By Method</h6>
      <?php foreach ($methodCounts as $mc): ?><small><?= h(ucfirst($mc['method'])) ?> (<?= (int)$mc['cnt'] ?>)</small>&nbsp;<?php endforeach; ?>
    </div></div>
  </div>

  <!-- Payments Table -->
  <div class="table-responsive">
    <table class="table table-striped table-bordered align-middle">
      <thead class="table-dark">
        <tr><th>ID</th><th>Visitor</th><th>Target (Site/Event)</th><th>Amount</th><th>Method</th><th>Status</th><th>Date</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php if ($payments): foreach ($payments as $p): ?>
          <tr>
            <td><?= (int)$p['payment_id'] ?></td>
            <td><?= h($p['visitor_id']) ?></td>
            <td><?= h($p['target_name'] ?? '—') ?></td>
            <td><?= number_format((float)$p['amount'], 2) ?></td>
            <td>
              <?php
                $m = (string)($p['method'] ?? '');
                if ($m === '') echo '—';
                else echo h($methods[$m] ?? ucfirst($m));
              ?>
            </td>
            <td><?= h(ucfirst($p['status'])) ?></td>
            <td><?= h($p['paid_at']) ?></td>
            <td>
              <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editModal"
                data-id="<?= (int)$p['payment_id'] ?>"
                data-status="<?= h($p['status']) ?>"
                data-method="<?= h($p['method'] ?? '') ?>">Edit</button>

              <form method="post" class="d-inline" onsubmit="return confirm('Delete this payment?');">
                <input type="hidden" name="action" value="payment_modify">
                <input type="hidden" name="delete_id" value="<?= (int)$p['payment_id'] ?>">
                <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                <button class="btn btn-sm btn-danger">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; else: ?>
          <tr><td colspan="8" class="text-center text-muted">No payments found</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Edit modal: update status and method -->
  <div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
      <form method="post" class="modal-content">
        <input type="hidden" name="action" value="payment_modify">
        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="update_id" id="edit_id">
        <div class="modal-header"><h5 class="modal-title">Edit Payment</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <label class="form-label">Status</label>
          <select name="status" id="edit_status" class="form-select">
            <?php foreach (['initiated','successful','failed','refunded'] as $s): ?><option value="<?= $s ?>"><?= ucfirst($s) ?></option><?php endforeach; ?>
          </select>
          <label class="form-label mt-2">Method</label>
          <select name="method" id="edit_method" class="form-select">
            <option value="">-- blank / unset --</option>
            <?php foreach ($methods as $code => $label): ?><option value="<?= h($code) ?>"><?= h($label) ?></option><?php endforeach; ?>
          </select>
          <div class="small text-muted mt-2">Set or clear the payment method for this row (helps fix blanks).</div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save</button></div>
      </form>
    </div>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
var editModal = document.getElementById('editModal');
if (editModal) {
  editModal.addEventListener('show.bs.modal', function(event){
    const btn = event.relatedTarget;
    document.getElementById('edit_id').value = btn.getAttribute('data-id') || '';
    document.getElementById('edit_status').value = btn.getAttribute('data-status') || 'initiated';
    document.getElementById('edit_method').value = btn.getAttribute('data-method') || '';
  });
}
</script>
</body>
</html>