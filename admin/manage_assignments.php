<?php
// manage_assignments.php (with SQL Runner)
declare(strict_types=1);
session_start();
require_once 'headerFooter.php';
require_once __DIR__ . '/../includes/db_connect.php';

// Auth
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: login.php'); exit;
}

// CSRF
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_HTML5); }

$messages = [];
$errors = [];

// Basic input helpers
function int_post($k){ return isset($_POST[$k]) ? (int)$_POST[$k] : null; }
function int_get($k){ return isset($_GET[$k]) ? (int)$_GET[$k] : null; }

// === Handle Add/Edit/Delete ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $csrf)) { http_response_code(400); exit('Invalid CSRF'); }

    // Add assignment (guide -> site) (ensures XOR constraint by inserting site not event)
    if (isset($_POST['add'])) {
        $guide_id = (int)($_POST['guide_id'] ?? 0);
        $site_id  = isset($_POST['site_id']) && $_POST['site_id'] !== '' ? (int)$_POST['site_id'] : null;
        $event_id = isset($_POST['event_id']) && $_POST['event_id'] !== '' ? (int)$_POST['event_id'] : null;

        // require exactly one of site_id XOR event_id
        if (!$guide_id || (!is_null($site_id) && !is_null($event_id)) || (is_null($site_id) && is_null($event_id))) {
            $errors[] = 'Provide guide and either site OR event.';
        } else {
            $stmt = $pdo->prepare('INSERT INTO Assignments (guide_id, site_id, event_id, shift_time) VALUES (:g, :s, :e, :shift)');
            $stmt->execute([
                'g' => $guide_id,
                's' => $site_id,
                'e' => $event_id,
                'shift' => trim((string)($_POST['shift_time'] ?? ''))
            ]);
            $messages[] = 'Assignment added.';
            header('Location: manage_assignments.php'); exit;
        }
    }

    // Update assignment
    if (isset($_POST['update_id'])) {
        $id = (int)$_POST['update_id'];
        $guide_id = (int)($_POST['guide_id'] ?? 0);
        $site_id  = isset($_POST['site_id']) && $_POST['site_id'] !== '' ? (int)$_POST['site_id'] : null;
        $event_id = isset($_POST['event_id']) && $_POST['event_id'] !== '' ? (int)$_POST['event_id'] : null;
        $shift = trim((string)($_POST['shift_time'] ?? ''));

        if (!$id || !$guide_id || ((!is_null($site_id) && !is_null($event_id)) || (is_null($site_id) && is_null($event_id)))) {
            $errors[] = 'Invalid update parameters (provide guide and either site OR event).';
        } else {
            $stmt = $pdo->prepare('UPDATE Assignments SET guide_id=:g, site_id=:s, event_id=:e, shift_time=:shift WHERE assign_id=:id');
            $stmt->execute(['g'=>$guide_id, 's'=>$site_id, 'e'=>$event_id, 'shift'=>$shift, 'id'=>$id]);
            $messages[] = 'Assignment updated.';
            header('Location: manage_assignments.php'); exit;
        }
    }

    // Delete assignment
    if (isset($_POST['delete_id'])) {
        $id = (int)$_POST['delete_id'];
        if ($id > 0) {
            $pdo->prepare('DELETE FROM Assignments WHERE assign_id=:id')->execute(['id'=>$id]);
            $messages[] = 'Assignment removed.';
            header('Location: manage_assignments.php'); exit;
        } else {
            $errors[] = 'Invalid assignment id.';
        }
    }

    // SQL Runner (handled below) - if action=run_query
    if (isset($_POST['action']) && $_POST['action'] === 'run_query') {
        // guarded later
    }
}

// === Data lists (guides, sites, events) ===
$guides = $pdo->query('SELECT guide_id, full_name FROM Guides ORDER BY full_name')->fetchAll(PDO::FETCH_ASSOC);
$sites  = $pdo->query('SELECT site_id, name FROM HeritageSites ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
$events = $pdo->query('SELECT event_id, name, site_id, event_date FROM Events ORDER BY event_date DESC')->fetchAll(PDO::FETCH_ASSOC);

// ----------------------
// SAFE SQL RUNNER whitelist (read-only SELECTs)
// ----------------------
$safe_queries = [
    'assignments_recent' => [
        'title' => 'Recent assignments (last 200)',
        'sql' => "SELECT a.assign_id, g.full_name AS guide, COALESCE(s.name,e.name) AS target, a.shift_time
FROM Assignments a
JOIN Guides g ON a.guide_id=g.guide_id
LEFT JOIN HeritageSites s ON a.site_id=s.site_id
LEFT JOIN Events e ON a.event_id=e.event_id
ORDER BY a.assign_id DESC
LIMIT 200"
    ],
    'assignments_per_guide' => [
        'title' => 'Assignments per guide (count)',
        'sql' => "SELECT g.guide_id, g.full_name, COUNT(a.assign_id) AS assignments_count
FROM Guides g
LEFT JOIN Assignments a ON g.guide_id = a.guide_id
GROUP BY g.guide_id
ORDER BY assignments_count DESC
LIMIT 200"
    ],
    'guides_unassigned' => [
        'title' => 'Guides without any assignment',
        'sql' => "SELECT g.guide_id, g.full_name, g.language
FROM Guides g
LEFT JOIN Assignments a ON g.guide_id = a.guide_id
WHERE a.assign_id IS NULL
ORDER BY g.full_name
LIMIT 200"
    ],
    'assignments_by_site' => [
        'title' => 'Assignments grouped by site (with guide names)',
        'sql' => "SELECT s.site_id, s.name AS site_name, GROUP_CONCAT(g.full_name SEPARATOR ', ') AS guides
FROM HeritageSites s
LEFT JOIN Assignments a ON s.site_id = a.site_id
LEFT JOIN Guides g ON a.guide_id = g.guide_id
GROUP BY s.site_id
ORDER BY s.name
LIMIT 200"
    ],
    'assignments_for_event' => [
        'title' => 'Assignments for upcoming events (next 90 days)',
        'sql' => "SELECT e.event_id, e.name AS event_name, e.event_date, g.full_name AS guide, a.shift_time
FROM Events e
LEFT JOIN Assignments a ON a.event_id = e.event_id
LEFT JOIN Guides g ON a.guide_id = g.guide_id
WHERE e.event_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)
ORDER BY e.event_date, e.name
LIMIT 500"
    ],
    'workload_by_guide_shift' => [
        'title' => 'Workload by guide and shift (count of assignments per shift)',
        'sql' => "SELECT g.guide_id, g.full_name, a.shift_time, COUNT(*) AS cnt
FROM Assignments a
JOIN Guides g ON a.guide_id = g.guide_id
GROUP BY g.guide_id, a.shift_time
ORDER BY cnt DESC
LIMIT 200"
    ],
    'sites_with_no_guides' => [
        'title' => 'Sites that currently have no assigned guide',
        'sql' => "SELECT s.site_id, s.name
FROM HeritageSites s
LEFT JOIN Assignments a ON s.site_id = a.site_id
WHERE a.assign_id IS NULL
ORDER BY s.name
LIMIT 200"
    ],
    'guide_languages_counts' => [
        'title' => 'Guide counts by language',
        'sql' => "SELECT language, COUNT(*) AS cnt FROM Guides GROUP BY language ORDER BY cnt DESC"
    ],
];

// Run SQL Runner if requested
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
                $stmtQ = $pdo->query($safe_queries[$qkey]['sql']);
                $query_result = $stmtQ->fetchAll(PDO::FETCH_ASSOC);
                $messages[] = 'Query executed.';
            } catch (Exception $e) {
                $errors[] = 'Query failed: ' . $e->getMessage();
            }
        }
    }
}

// === Filters ===
$where = []; $params = [];
if (!empty($_GET['guide_id'])) { $where[] = "a.guide_id=:g"; $params['g'] = (int)$_GET['guide_id']; }
if (!empty($_GET['site_id']))  { $where[] = "a.site_id=:s";  $params['s'] = (int)$_GET['site_id']; }
if (!empty($_GET['event_id'])) { $where[] = "a.event_id=:e";  $params['e'] = (int)$_GET['event_id']; }

$sql = "SELECT a.assign_id, g.full_name AS guide, s.name AS site_name, e.name AS event_name, a.shift_time
        FROM Assignments a
        JOIN Guides g ON a.guide_id=g.guide_id
        LEFT JOIN HeritageSites s ON a.site_id=s.site_id
        LEFT JOIN Events e ON a.event_id=e.event_id";

if ($where) $sql .= " WHERE " . implode(" AND ", $where);
$sql .= " ORDER BY COALESCE(s.name, e.name), g.full_name";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// For UI selects (preload)
$guide_filter = (int)($_GET['guide_id'] ?? 0);
$site_filter  = (int)($_GET['site_id'] ?? 0);
$event_filter = (int)($_GET['event_id'] ?? 0);

?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Manage Assignments</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
.panel { background: #f8f9fa; padding: 1rem; border-radius: 10px; margin-bottom: 1rem; }
.sql-card { background:#fff7eb; padding:0.8rem; border-radius:8px; margin-bottom:1rem; }
</style>
</head>
<body>
<div class="container py-4">
  <h2 class="mb-4">📝 Manage Assignments</h2>

  <?php foreach ($messages as $m): ?><div class="alert alert-success"><?= h($m) ?></div><?php endforeach; ?>
  <?php foreach ($errors as $e): ?><div class="alert alert-danger"><?= h($e) ?></div><?php endforeach; ?>

  <!-- Add Assignment -->
  <div class="panel mb-3">
  <form method="post" class="row g-2 align-items-end">
      <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
      <div class="col-md-3">
          <label class="form-label">Guide</label>
          <select name="guide_id" class="form-select" required>
              <option value="">Select Guide</option>
              <?php foreach($guides as $g): ?>
                  <option value="<?= (int)$g['guide_id'] ?>"><?= h($g['full_name']) ?></option>
              <?php endforeach; ?>
          </select>
      </div>
      <div class="col-md-3">
          <label class="form-label">Site (choose either Site OR Event)</label>
          <select name="site_id" class="form-select">
              <option value="">-- Site (optional) --</option>
              <?php foreach($sites as $s): ?>
                  <option value="<?= (int)$s['site_id'] ?>"><?= h($s['name']) ?></option>
              <?php endforeach; ?>
          </select>
      </div>
      <div class="col-md-3">
          <label class="form-label">Event (choose either Event OR Site)</label>
          <select name="event_id" class="form-select">
              <option value="">-- Event (optional) --</option>
              <?php foreach($events as $ev): ?>
                  <option value="<?= (int)$ev['event_id'] ?>"><?= h($ev['name']) ?> (<?= h($ev['event_date']) ?>)</option>
              <?php endforeach; ?>
          </select>
      </div>
      <div class="col-md-2">
          <label class="form-label">Shift (optional)</label>
          <input name="shift_time" class="form-control" placeholder="e.g. Morning">
      </div>
      <div class="col-md-1"><button class="btn btn-success w-100" name="add">➕ Assign</button></div>
  </form>
  <div class="small text-muted mt-2">Pick either a Site or an Event — not both. Shift is optional.</div>
  </div>

  <!-- SQL Runner -->
  <div class="sql-card">
    <form method="post" class="row g-2 align-items-center">
      <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
      <input type="hidden" name="action" value="run_query">
      <div class="col-md-6">
        <strong>🧾 Safe SQL Runner</strong>
        <div class="small text-muted">Choose a predefined read-only query to demonstrate SQL patterns (joins, group by, aggregates).</div>
      </div>
      <div class="col-md-4">
        <select name="query_key" class="form-select" required>
          <option value="">-- Select query --</option>
          <?php foreach ($safe_queries as $k=>$qi): ?>
            <option value="<?= h($k) ?>" <?= $selected_query_key === $k ? 'selected' : '' ?>><?= h($qi['title']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2 text-end"><button class="btn btn-primary">Run</button></div>
    </form>
  </div>

  <?php if ($query_result !== null): ?>
    <div class="card mb-3 p-3">
      <h6>Query Result: <?= h($safe_queries[$selected_query_key]['title'] ?? '') ?> (<?= count($query_result) ?> rows)</h6>
      <?php if (count($query_result) === 0): ?>
        <div class="text-muted">No rows returned</div>
      <?php else: ?>
        <div style="overflow:auto; max-height:420px;">
          <table class="table table-sm table-bordered">
            <thead class="table-dark"><tr><?php foreach (array_keys($query_result[0]) as $col): ?><th><?= h($col) ?></th><?php endforeach; ?></tr></thead>
            <tbody>
              <?php foreach ($query_result as $r): ?>
                <tr><?php foreach ($r as $c): ?><td><?= h($c) ?></td><?php endforeach; ?></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <!-- Filter Panel -->
  <form method="get" class="panel row g-3 align-items-end mb-3">
      <div class="col-md-3">
          <label class="form-label">Filter by Guide</label>
          <select name="guide_id" class="form-select">
              <option value="">All Guides</option>
              <?php foreach($guides as $g): ?>
              <option value="<?= (int)$g['guide_id'] ?>" <?= $guide_filter === (int)$g['guide_id'] ? 'selected' : '' ?>><?= h($g['full_name']) ?></option>
              <?php endforeach; ?>
          </select>
      </div>
      <div class="col-md-3">
          <label class="form-label">Filter by Site</label>
          <select name="site_id" class="form-select">
              <option value="">All Sites</option>
              <?php foreach($sites as $s): ?>
              <option value="<?= (int)$s['site_id'] ?>" <?= $site_filter === (int)$s['site_id'] ? 'selected' : '' ?>><?= h($s['name']) ?></option>
              <?php endforeach; ?>
          </select>
      </div>
      <div class="col-md-3">
          <label class="form-label">Filter by Event</label>
          <select name="event_id" class="form-select">
              <option value="">All Events</option>
              <?php foreach($events as $ev): ?>
                <option value="<?= (int)$ev['event_id'] ?>" <?= $event_filter === (int)$ev['event_id'] ? 'selected' : '' ?>><?= h($ev['name']) ?> (<?= h($ev['event_date']) ?>)</option>
              <?php endforeach; ?>
          </select>
      </div>
      <div class="col-md-3"><button class="btn btn-primary w-100">Apply Filter</button></div>
  </form>

  <!-- Executed SQL (for teaching) -->
  <div class="alert alert-secondary small">
      <strong>Executed SQL:</strong> <code><?= h($sql) ?></code>
  </div>

  <!-- Assignments Table -->
  <table class="table table-bordered table-striped">
  <thead class="table-dark">
  <tr>
      <th>Guide</th>
      <th>Target (Site / Event)</th>
      <th>Shift</th>
      <th>Actions</th>
  </tr>
  </thead>
  <tbody>
  <?php if($assignments): foreach($assignments as $a): ?>
  <tr>
      <td><?= h($a['guide']) ?></td>
      <td><?= h($a['site_name'] ?? $a['event_name'] ?? '—') ?></td>
      <td><?= h($a['shift_time']) ?></td>
      <td>
          <button class="btn btn-sm btn-warning"
                  data-bs-toggle="modal"
                  data-bs-target="#editModal"
                  data-id="<?= (int)$a['assign_id'] ?>"
                  data-guide="<?= h($a['guide']) ?>"
                  data-site="<?= h($a['site_name'] ?? '') ?>"
                  data-event="<?= h($a['event_name'] ?? '') ?>"
                  data-shift="<?= h($a['shift_time']) ?>">Edit</button>

          <form method="post" class="d-inline" onsubmit="return confirm('Remove assignment?');">
              <input type="hidden" name="delete_id" value="<?= (int)$a['assign_id'] ?>">
              <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
              <button class="btn btn-sm btn-danger">Remove</button>
          </form>
      </td>
  </tr>
  <?php endforeach; else: ?>
  <tr><td colspan="4" class="text-center text-muted">No assignments found</td></tr>
  <?php endif; ?>
  </tbody>
  </table>

  <!-- Edit Modal -->
  <div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog">
  <form method="post" class="modal-content">
      <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
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
                  <option value="<?= (int)$g['guide_id'] ?>"><?= h($g['full_name']) ?></option>
                  <?php endforeach; ?>
              </select>
          </div>
          <div class="col-md-6">
              <label class="form-label">Shift</label>
              <input name="shift_time" id="edit_shift" class="form-control" placeholder="e.g. Morning">
          </div>
          <div class="col-md-6">
              <label class="form-label">Site (choose either site OR event)</label>
              <select name="site_id" id="edit_site" class="form-select">
                  <option value="">-- Site (optional) --</option>
                  <?php foreach($sites as $s): ?>
                      <option value="<?= (int)$s['site_id'] ?>"><?= h($s['name']) ?></option>
                  <?php endforeach; ?>
              </select>
          </div>
          <div class="col-md-6">
              <label class="form-label">Event (choose either event OR site)</label>
              <select name="event_id" id="edit_event" class="form-select">
                  <option value="">-- Event (optional) --</option>
                  <?php foreach($events as $ev): ?>
                      <option value="<?= (int)$ev['event_id'] ?>"><?= h($ev['name']) ?> (<?= h($ev['event_date']) ?>)</option>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('editModal').addEventListener('show.bs.modal', function (event) {
    const btn = event.relatedTarget;
    const id = btn.getAttribute('data-id');
    const guideName = btn.getAttribute('data-guide');
    const siteName = btn.getAttribute('data-site');
    const eventName = btn.getAttribute('data-event');
    const shift = btn.getAttribute('data-shift');

    document.getElementById('edit_id').value = id;
    // select guide by matching visible name (safer to match ID if you included it)
    const guideSel = document.getElementById('edit_guide');
    for (let i=0;i<guideSel.options.length;i++){
        if (guideSel.options[i].text === guideName) { guideSel.selectedIndex = i; break; }
    }
    document.getElementById('edit_shift').value = shift || '';

    // select site and event by matching visible text
    const siteSel = document.getElementById('edit_site');
    for (let i=0;i<siteSel.options.length;i++){
        siteSel.options[i].selected = siteSel.options[i].text === siteName;
    }
    const eventSel = document.getElementById('edit_event');
    for (let i=0;i<eventSel.options.length;i++){
        eventSel.options[i].selected = eventSel.options[i].text.startsWith(eventName);
    }
});
</script>
</body>
</html>
