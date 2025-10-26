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

//  . .
// SAFE SQL RUNNER whitelist (read-only SELECTs)
//  . .
$safe_queries = [

    // ===== LAB 2: DDL/DML Concepts =====
    'guides_structure' => [
        'title' => 'Show Guides table structure (DDL info)',
        'sql' => "SHOW COLUMNS FROM Guides"
    ],
    'guides_sample_data' => [
        'title' => 'Sample guides data (DML select)',
        'sql' => "SELECT guide_id, full_name, language, specialization, salary FROM Guides ORDER BY guide_id LIMIT 100"
    ],

    // ===== LAB 3: Filtering, Range, Constraints, Set Membership, Ordering =====
    'guides_salary_range' => [
        'title' => 'Guides with salary between 30000 and 60000 (range filter)',
        'sql' => "SELECT full_name, language, salary
                  FROM Guides
                  WHERE salary BETWEEN 30000 AND 60000
                  ORDER BY salary DESC"
    ],
    'guides_language_in' => [
        'title' => 'Guides who speak selected languages (set membership)',
        'sql' => "SELECT guide_id, full_name, language
                  FROM Guides
                  WHERE language IN ('English', 'Bangla', 'French')
                  ORDER BY language, full_name"
    ],
    'high_salary_or_special' => [
        'title' => 'Guides with high salary OR cultural specialization (logical filtering)',
        'sql' => "SELECT guide_id, full_name, salary, specialization
                  FROM Guides
                  WHERE salary > 50000 OR specialization LIKE '%Cultural%'"
    ],

    // ===== LAB 4: Aggregates & Grouping =====
    'avg_salary_by_lang' => [
        'title' => 'Average guide salary by language (aggregate, GROUP BY)',
        'sql' => "SELECT language, COUNT(*) AS guide_count, AVG(salary) AS avg_salary
                  FROM Guides
                  GROUP BY language
                  HAVING COUNT(*) > 1
                  ORDER BY avg_salary DESC"
    ],
    'max_min_salary' => [
        'title' => 'Highest and lowest guide salary',
        'sql' => "SELECT MAX(salary) AS max_salary, MIN(salary) AS min_salary FROM Guides"
    ],

    // ===== LAB 5: Subqueries, Set Operations, Views =====
    'guides_above_avg' => [
        'title' => 'Guides earning above average salary (scalar subquery)',
        'sql' => "SELECT guide_id, full_name, salary
                  FROM Guides
                  WHERE salary > (SELECT AVG(salary) FROM Guides)
                  ORDER BY salary DESC"
    ],
    'guides_with_assignments' => [
        'title' => 'Guides having at least one assignment (EXISTS subquery)',
        'sql' => "SELECT full_name
                  FROM Guides g
                  WHERE EXISTS (SELECT 1 FROM Assignments a WHERE a.guide_id = g.guide_id)"
    ],
    'union_language_groups' => [
        'title' => 'Union of guides speaking Bangla or French',
        'sql' => "(SELECT guide_id, full_name, language FROM Guides WHERE language = 'Bangla')
                  UNION
                  (SELECT guide_id, full_name, language FROM Guides WHERE language = 'French')
                  ORDER BY full_name"
    ],
    'intersect_specialization' => [
        'title' => 'Guides who are both cultural and history specialists (INTERSECT simulation)',
        'sql' => "SELECT guide_id, full_name FROM Guides
                  WHERE specialization LIKE '%Cultural%'
                  AND guide_id IN (
                    SELECT guide_id FROM Guides WHERE specialization LIKE '%History%'
                  )"
    ],
    'minus_demo' => [
        'title' => 'Guides not assigned anywhere (MINUS via NOT IN)',
        'sql' => "SELECT g.guide_id, g.full_name
                  FROM Guides g
                  WHERE g.guide_id NOT IN (SELECT guide_id FROM Assignments)"
    ],
    'view_assignments_summary' => [
        'title' => 'View simulation: total assignments per guide (using GROUP BY)',
        'sql' => "SELECT g.guide_id, g.full_name, COUNT(a.assign_id) AS total_assignments
                  FROM Guides g
                  LEFT JOIN Assignments a ON g.guide_id = a.guide_id
                  GROUP BY g.guide_id
                  ORDER BY total_assignments DESC"
    ],

    // ===== LAB 6: Joins (inner, left, right, cross, natural, self, non-equi) =====
    'inner_join_assignments' => [
        'title' => 'Inner join: Guides with their assigned sites/events',
        'sql' => "SELECT g.full_name, COALESCE(s.name,e.name) AS target_name, a.shift_time
                  FROM Assignments a
                  INNER JOIN Guides g ON a.guide_id = g.guide_id
                  LEFT JOIN HeritageSites s ON a.site_id = s.site_id
                  LEFT JOIN Events e ON a.event_id = e.event_id
                  ORDER BY g.full_name"
    ],
    'left_join_sites' => [
        'title' => 'Left join: all sites and their assigned guides (if any)',
        'sql' => "SELECT s.name AS site_name, g.full_name AS guide_name
                  FROM HeritageSites s
                  LEFT JOIN Assignments a ON s.site_id = a.site_id
                  LEFT JOIN Guides g ON a.guide_id = g.guide_id
                  ORDER BY s.name"
    ],
    'right_join_demo' => [
        'title' => 'Right join: all guides with assigned site info',
        'sql' => "SELECT g.full_name, s.name AS site_name
                  FROM HeritageSites s
                  RIGHT JOIN Assignments a ON s.site_id = a.site_id
                  RIGHT JOIN Guides g ON g.guide_id = a.guide_id
                  ORDER BY g.full_name"
    ],
    'cross_join_lang_site' => [
        'title' => 'Cross join: All possible combinations of guides and sites (limited)',
        'sql' => "SELECT g.full_name, s.name AS site_name
                  FROM Guides g
                  CROSS JOIN HeritageSites s
                  LIMIT 200"
    ],
    'self_join_guides' => [
        'title' => 'Self join: Guides with same language',
        'sql' => "SELECT g1.full_name AS guide_1, g2.full_name AS guide_2, g1.language
                  FROM Guides g1
                  JOIN Guides g2 ON g1.language = g2.language AND g1.guide_id < g2.guide_id
                  ORDER BY g1.language"
    ],
    'non_equi_join_salary_band' => [
        'title' => 'Non-equi join: Guides grouped by salary ranges',
        'sql' => "SELECT g.full_name, g.salary,
                         CASE
                             WHEN g.salary < 30000 THEN 'Low'
                             WHEN g.salary BETWEEN 30000 AND 60000 THEN 'Medium'
                             ELSE 'High'
                         END AS salary_band
                  FROM Guides g
                  ORDER BY salary_band, g.salary DESC"
    ],
];

//  . .
// SQL Runner execution (read-only safe)
//  . .
$query_result = null;
$selected_query_key = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'run_query') {
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
                $messages[] = 'Query executed successfully.';
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
  <div class="card mb-3 p-3 shadow-sm">
      <form method="post" class="row g-2 align-items-center">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="action" value="run_query">
        <div class="col-md-8">
          <select name="query_key" class="form-select" required>
            <option value="">-- Select Demo Query --</option>
            <?php foreach ($safe_queries as $k => $q): ?>
              <option value="<?= htmlspecialchars($k) ?>" <?= ($selected_query_key === $k) ? 'selected' : '' ?>><?= htmlspecialchars($q['title']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <button class="btn btn-primary w-100">Run Selected Query</button>
        </div>
      </form>
    </div>

    <?php if ($query_result !== null): ?>
      <div class="card p-3 mb-4">
        <h6>Query Result: <?= htmlspecialchars($safe_queries[$selected_query_key]['title'] ?? '') ?> (<?= count($query_result) ?> rows)</h6>
        <?php if (count($query_result) === 0): ?>
          <div class="text-muted">No results found.</div>
        <?php else: ?>
          <div style="overflow:auto; max-height:480px;">
            <table class="table table-bordered table-sm">
              <thead class="table-dark">
                <tr>
                  <?php foreach (array_keys($query_result[0]) as $col): ?>
                    <th><?= htmlspecialchars($col) ?></th>
                  <?php endforeach; ?>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($query_result as $row): ?>
                  <tr>
                    <?php foreach ($row as $val): ?>
                      <td><?= htmlspecialchars((string)$val) ?></td>
                    <?php endforeach; ?>
                  </tr>
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
