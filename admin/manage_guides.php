<?php
session_start();
require_once 'headerFooter.php';
require_once __DIR__ . '/../includes/db_connect.php';

if (empty($_SESSION['admin_logged_in'])) {
  header("Location: login.php");
  exit;
}

// --- CSRF Token ---
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ========================================================
// LAB DEMO: SAFE SQL RUNNER (READ-ONLY) – LAB 2–6 Coverage
// ========================================================
$safe_queries = [
  //  . LAB 2: DDL + DML + UPDATE/ALTER Examples  .
  'guides_basic_info' => [
    'title' => 'All guides (basic DML SELECT example)',
    'sql' => "SELECT guide_id, full_name, language, specialization, salary FROM Guides ORDER BY full_name ASC LIMIT 200"
  ],
  'guides_salary_raise_preview' => [
    'title' => 'Preview: salary increase simulation (UPDATE concept)',
    'sql' => "SELECT guide_id, full_name, salary AS current_salary, (salary * 1.10) AS increased_salary
              FROM Guides
              ORDER BY salary DESC LIMIT 100"
  ],
  'guides_table_structure' => [
    'title' => 'DDL info: Guides table columns from INFORMATION_SCHEMA',
    'sql' => "SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT
              FROM INFORMATION_SCHEMA.COLUMNS
              WHERE TABLE_NAME = 'Guides' AND TABLE_SCHEMA = DATABASE()"
  ],

  //  . LAB 3: Filtering, Constraints, Range Search, Set Membership  .
  'guides_salary_range' => [
    'title' => 'Guides within mid-range salary (BETWEEN)',
    'sql' => "SELECT guide_id, full_name, salary
              FROM Guides
              WHERE salary BETWEEN 20000 AND 60000
              ORDER BY salary DESC"
  ],
  'guides_language_in_set' => [
    'title' => 'Guides speaking selected languages (IN set membership)',
    'sql' => "SELECT full_name, language
              FROM Guides
              WHERE language IN ('English','French','Spanish')
              ORDER BY language"
  ],
  'guides_constraints_check' => [
    'title' => 'Constraint check — Guides with valid positive salary only',
    'sql' => "SELECT guide_id, full_name, salary
              FROM Guides
              WHERE salary >= 0
              ORDER BY salary DESC"
  ],

  //  . LAB 4: Aggregates  .
  'salary_aggregates' => [
    'title' => 'Salary statistics (AVG, MAX, MIN, COUNT)',
    'sql' => "SELECT COUNT(*) AS total_guides,
                     ROUND(AVG(salary),2) AS avg_salary,
                     MAX(salary) AS max_salary,
                     MIN(salary) AS min_salary
              FROM Guides"
  ],
  'guides_per_language' => [
    'title' => 'Number of guides per language (GROUP BY + HAVING)',
    'sql' => "SELECT language, COUNT(*) AS total_guides
              FROM Guides
              GROUP BY language
              HAVING COUNT(*) > 1
              ORDER BY total_guides DESC"
  ],

  //  . LAB 5: Subqueries + Set Operations + Views  .
  'above_average_guides' => [
    'title' => 'Guides earning above average salary (Subquery)',
    'sql' => "SELECT guide_id, full_name, salary
              FROM Guides
              WHERE salary > (SELECT AVG(salary) FROM Guides)
              ORDER BY salary DESC"
  ],
  'guides_event_or_site' => [
    'title' => 'Guides assigned to either sites or events (UNION Set Operation)',
    'sql' => "(SELECT g.full_name AS guide_name, s.name AS assigned_to, 'Site' AS type
               FROM Guides g
               JOIN Assignments a ON g.guide_id = a.guide_id
               JOIN HeritageSites s ON a.site_id = s.site_id)
              UNION
              (SELECT g.full_name, e.name, 'Event'
               FROM Guides g
               JOIN Assignments a ON g.guide_id = a.guide_id
               JOIN Events e ON a.event_id = e.event_id)
              ORDER BY guide_name"
  ],
  'guides_with_no_assignment' => [
    'title' => 'Guides not assigned anywhere (MINUS / NOT IN)',
    'sql' => "SELECT g.full_name
              FROM Guides g
              WHERE g.guide_id NOT IN (SELECT guide_id FROM Assignments)"
  ],
  'guides_view_example' => [
    'title' => 'Example of using a view (guides_high_salary)',
    'sql' => "SELECT * FROM (
                SELECT full_name, language, salary
                FROM Guides
                WHERE salary > (SELECT AVG(salary) FROM Guides)
              ) AS guides_high_salary
              ORDER BY salary DESC"
  ],

  //  . LAB 6: Joins  .
  'guides_with_sites' => [
    'title' => 'Guides assigned to sites (INNER JOIN)',
    'sql' => "SELECT g.full_name, g.language, s.name AS site_name
              FROM Guides g
              INNER JOIN Assignments a ON g.guide_id = a.guide_id
              INNER JOIN HeritageSites s ON a.site_id = s.site_id
              ORDER BY g.full_name"
  ],
  'guides_with_events' => [
    'title' => 'Guides with event assignments (LEFT JOIN + IS NULL check)',
    'sql' => "SELECT g.full_name, e.name AS event_name, e.event_date
              FROM Guides g
              LEFT JOIN Assignments a ON g.guide_id = a.guide_id
              LEFT JOIN Events e ON a.event_id = e.event_id
              WHERE e.event_id IS NOT NULL
              ORDER BY e.event_date DESC"
  ],
  'guides_without_events' => [
    'title' => 'Guides not assigned to any event (LEFT JOIN with NULL)',
    'sql' => "SELECT g.full_name, g.language
              FROM Guides g
              LEFT JOIN Assignments a ON g.guide_id = a.guide_id
              WHERE a.event_id IS NULL
              ORDER BY g.full_name"
  ],
  'guides_cross_join_demo' => [
    'title' => 'Cross join demo (Guide × Language lookup)',
    'sql' => "SELECT g.full_name, l.language AS potential_language
              FROM Guides g
              CROSS JOIN (SELECT DISTINCT language FROM Guides WHERE language IS NOT NULL) AS l
              LIMIT 200"
  ],
  'guides_self_join_demo' => [
    'title' => 'Self Join – Guides with same specialization',
    'sql' => "SELECT g1.full_name AS Guide_A, g2.full_name AS Guide_B, g1.specialization
              FROM Guides g1
              JOIN Guides g2 ON g1.specialization = g2.specialization AND g1.guide_id <> g2.guide_id
              ORDER BY g1.specialization"
  ]
];

// --- Safe Query Runner ---
$query_result = null;
$selected_query_key = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'run_query') {
  $csrf = $_POST['csrf_token'] ?? '';
  if (!hash_equals($_SESSION['csrf_token'], $csrf)) {
    $errors[] = 'Invalid CSRF token for SQL runner.';
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
        $errors[] = 'Query failed: ' . htmlspecialchars($e->getMessage());
      }
    }
  }
}
// === Handle Add / Update / Delete ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $csrf = $_POST['csrf_token'] ?? '';
  if (!hash_equals($_SESSION['csrf_token'], $csrf)) {
    http_response_code(400);
    exit("Invalid CSRF token.");
  }

  // --- Add ---
  if (isset($_POST['add'])) {
    $stmt = $pdo->prepare("INSERT INTO Guides (name, language, specialization, salary) VALUES (?, ?, ?, ?)");
    $stmt->execute([
      $_POST['name'],
      $_POST['language'],
      $_POST['specialization'],
      $_POST['salary']
    ]);
    header("Location: manage_guides.php");
    exit;
  }

  // --- Update ---
  if (isset($_POST['update_id'])) {
    $stmt = $pdo->prepare("UPDATE Guides 
                           SET name=?, language=?, specialization=?, salary=? 
                           WHERE guide_id=?");
    $stmt->execute([
      $_POST['name'],
      $_POST['language'],
      $_POST['specialization'],
      $_POST['salary'],
      $_POST['update_id']
    ]);
    header("Location: manage_guides.php");
    exit;
  }

  // --- Delete ---
  if (isset($_POST['delete_id'])) {
    $pdo->prepare("DELETE FROM Guides WHERE guide_id = ?")->execute([$_POST['delete_id']]);
    header("Location: manage_guides.php");
    exit;
  }
}

// === Dynamic Ranges ===
$salRange = $pdo->query("SELECT MIN(salary) AS min_sal, MAX(salary) AS max_sal FROM Guides")->fetch(PDO::FETCH_ASSOC);
$minSal = $salRange['min_sal'] ?? 0;
$maxSal = $salRange['max_sal'] ?? 0;

// === Filters ===
$where = [];
$params = [];

if (!empty($_GET['q'])) {
  $where[] = "(g.full_name LIKE :kw OR g.specialization LIKE :kw)";
  $params['kw'] = '%' . $_GET['q'] . '%';
}

if (!empty($_GET['language'])) {
  $where[] = "g.language = :lang";
  $params['lang'] = $_GET['language'];
}

if (!empty($_GET['assigned_status'])) {
  if ($_GET['assigned_status'] === 'assigned') {
    $where[] = "a.guide_id IS NOT NULL";
  } elseif ($_GET['assigned_status'] === 'unassigned') {
    $where[] = "a.guide_id IS NULL";
  }
}

if (!empty($_GET['min_salary']) && !empty($_GET['max_salary'])) {
  $where[] = "g.salary BETWEEN :minS AND :maxS";
  $params['minS'] = $_GET['min_salary'];
  $params['maxS'] = $_GET['max_salary'];
}

$sql = "SELECT g.*, 
        CASE WHEN a.guide_id IS NULL THEN 'Unassigned' ELSE 'Assigned' END AS status
        FROM Guides g
        LEFT JOIN Assignments a ON g.guide_id = a.guide_id";

if ($where) $sql .= " WHERE " . implode(" AND ", $where);
$sql .= " GROUP BY g.guide_id ORDER BY g.full_name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$guides = $stmt->fetchAll(PDO::FETCH_ASSOC);

// === Dropdown Options ===
$languages = $pdo->query("SELECT DISTINCT language FROM Guides WHERE language IS NOT NULL ORDER BY language")->fetchAll(PDO::FETCH_COLUMN);

// === Analytics ===
$avgSal = $pdo->query("SELECT AVG(salary) AS avg FROM Guides")->fetchColumn() ?: 0;
$totalGuides = $pdo->query("SELECT COUNT(*) FROM Guides")->fetchColumn();
$unassigned = $pdo->query("SELECT COUNT(*) FROM Guides g LEFT JOIN Assignments a ON g.guide_id = a.guide_id WHERE a.guide_id IS NULL")->fetchColumn();
$langCounts = $pdo->query("SELECT language, COUNT(*) AS cnt FROM Guides GROUP BY language ORDER BY cnt DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html>

<head>
  <meta charset="utf-8">
  <title>Manage Guides</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <style>
    .panel {
      background: #f8f9fa;
      padding: 1rem;
      border-radius: 10px;
      margin-bottom: 1rem;
    }

    .stat-box {
      background: #fff;
      border-radius: 10px;
      padding: 1rem;
      text-align: center;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }
  </style>
</head>

<body>
  <div class="container py-4">
    <h2 class="mb-4">👨‍🏫 Manage Guides</h2>
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

  <!-- ✅ Add New Guide -->
  <div class="panel mb-4">
    <form method="post" class="row g-2">
      <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
      <div class="col-md-3"><input name="name" class="form-control" placeholder="Full Name" required></div>
      <div class="col-md-2"><input name="language" class="form-control" placeholder="Language" required></div>
      <div class="col-md-3"><input name="specialization" class="form-control" placeholder="Specialization" required></div>
      <div class="col-md-2"><input name="salary" type="number" class="form-control" step="1" placeholder="Salary (Tk)" required></div>
      <div class="col-md-2"><button class="btn btn-success w-100" name="add">➕ Add Guide</button></div>
    </form>
  </div>

  <!-- 🔍 Filter Section -->
  <form method="get" class="panel row g-3 align-items-end">
    <div class="col-md-3">
      <label class="form-label fw-bold">Search</label>
      <input type="text" name="q" class="form-control" placeholder="Name or specialization" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
    </div>

    <div class="col-md-2">
      <label class="form-label fw-bold">Language</label>
      <select name="language" class="form-select">
        <option value="">All</option>
        <?php foreach ($languages as $lang): ?>
          <option value="<?= $lang ?>" <?= ($lang == ($_GET['language'] ?? '')) ? 'selected' : '' ?>><?= htmlspecialchars($lang) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-md-2">
      <label class="form-label fw-bold">Status</label>
      <select name="assigned_status" class="form-select">
        <option value="">All</option>
        <option value="assigned" <?= (($_GET['assigned_status'] ?? '') == 'assigned') ? 'selected' : '' ?>>Assigned</option>
        <option value="unassigned" <?= (($_GET['assigned_status'] ?? '') == 'unassigned') ? 'selected' : '' ?>>Unassigned</option>
      </select>
    </div>

    <div class="col-md-2">
      <label class="form-label fw-bold">Min Salary</label>
      <input type="number" name="min_salary" class="form-control" step="1" min="<?= $minSal ?>" max="<?= $maxSal ?>" value="<?= htmlspecialchars($_GET['min_salary'] ?? $minSal) ?>">
    </div>

    <div class="col-md-2">
      <label class="form-label fw-bold">Max Salary</label>
      <input type="number" name="max_salary" class="form-control" step="1" min="<?= $minSal ?>" max="<?= $maxSal ?>" value="<?= htmlspecialchars($_GET['max_salary'] ?? $maxSal) ?>">
    </div>

    <div class="col-md-1">
      <button class="btn btn-primary w-100">Filter</button>
    </div>
  </form>

  <!-- 📊 Quick Stats -->
  <div class="row text-center mb-4">
    <div class="col-md-3">
      <div class="stat-box">
        <h5>👥 Total Guides</h5>
        <p><?= $totalGuides ?></p>
      </div>
    </div>
    <div class="col-md-3">
      <div class="stat-box">
        <h5>📉 Unassigned</h5>
        <p><?= $unassigned ?></p>
      </div>
    </div>
    <div class="col-md-3">
      <div class="stat-box">
        <h5>💰 Avg Salary</h5>
        <p><?= number_format($avgSal, 2) ?> BDT</p>
      </div>
    </div>
    <div class="col-md-3">
      <div class="stat-box">
        <h5>🌐 Languages</h5>
        <?php foreach ($langCounts as $lc): ?>
          <small><?= htmlspecialchars($lc['language']) ?> (<?= $lc['cnt'] ?>)</small><br>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- 📋 Guides Table -->
  <table class="table table-bordered table-striped align-middle">
    <thead class="table-dark">
      <tr>
        <th>Name</th>
        <th>Language</th>
        <th>Specialization</th>
        <th>Salary</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($guides): foreach ($guides as $g): ?>
          <tr>
            <td><?= htmlspecialchars($g['full_name']) ?></td>
            <td><?= htmlspecialchars($g['language']) ?></td>
            <td><?= htmlspecialchars($g['specialization']) ?></td>
            <td><?= number_format($g['salary'], 2) ?></td>
            <td><?= htmlspecialchars($g['status']) ?></td>
            <td>
              <!-- Edit Button -->
              <button class="btn btn-sm btn-warning"
                data-bs-toggle="modal"
                data-bs-target="#editModal"
                data-id="<?= $g['guide_id'] ?>"
                data-name="<?= htmlspecialchars($g['name'] ?? $g['full_name']) ?>"
                data-language="<?= htmlspecialchars($g['language']) ?>"
                data-specialization="<?= htmlspecialchars($g['specialization']) ?>"
                data-salary="<?= $g['salary'] ?>">Edit</button>

              <!-- Delete -->
              <form method="post" class="d-inline" onsubmit="return confirm('Delete this guide?');">
                <input type="hidden" name="delete_id" value="<?= $g['guide_id'] ?>">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <button class="btn btn-sm btn-danger">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach;
      else: ?>
        <tr>
          <td colspan="6" class="text-center text-muted">No guides found</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>

  <!-- ✏️ Edit Modal -->
  <div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
      <form method="post" class="modal-content">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="hidden" name="update_id" id="edit_id">

        <div class="modal-header">
          <h5 class="modal-title">Edit Guide</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Full Name</label>
            <input type="text" name="name" id="edit_name" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Language</label>
            <input type="text" name="language" id="edit_language" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Specialization</label>
            <input type="text" name="specialization" id="edit_specialization" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Salary</label>
            <input type="number" name="salary" id="edit_salary" class="form-control" required>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-primary">Update</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    document.getElementById('editModal').addEventListener('show.bs.modal', function(event) {
      const button = event.relatedTarget;
      document.getElementById('edit_id').value = button.getAttribute('data-id');
      document.getElementById('edit_name').value = button.getAttribute('data-name');
      document.getElementById('edit_language').value = button.getAttribute('data-language');
      document.getElementById('edit_specialization').value = button.getAttribute('data-specialization');
      document.getElementById('edit_salary').value = button.getAttribute('data-salary');
    });
  </script>
  </div>
</body>

</html>