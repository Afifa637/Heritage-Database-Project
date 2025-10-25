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