<?php
session_start();
require_once __DIR__ . '/../includes/db_connect.php';
if (empty($_SESSION['admin_logged_in'])) { header("Location: login.php"); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add'])) {
        $stmt = $pdo->prepare("INSERT INTO Guides (full_name,phone,specialization) VALUES (?,?,?)");
        $stmt->execute([$_POST['name'], $_POST['phone'], $_POST['spec']]);
    }
    if (isset($_POST['delete'])) {
        $pdo->prepare("DELETE FROM Guides WHERE guide_id=?")->execute([$_POST['delete']]);
    }
}
$guides = $pdo->query("SELECT * FROM Guides")->fetchAll();
?>
<!doctype html>
<html>
<head><title>Manage Guides</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container py-4">
<h3>Manage Guides</h3>
<form method="post" class="mb-3">
  <input name="name" class="form-control mb-2" placeholder="Full Name">
  <input name="phone" class="form-control mb-2" placeholder="Phone">
  <input name="spec" class="form-control mb-2" placeholder="Specialization">
  <button class="btn btn-primary" name="add">Add Guide</button>
</form>
<table class="table table-bordered">
<tr><th>Name</th><th>Phone</th><th>Specialization</th><th>Action</th></tr>
<?php foreach ($guides as $g): ?>
<tr>
  <td><?= htmlspecialchars($g['full_name']) ?></td>
  <td><?= htmlspecialchars($g['phone']) ?></td>
  <td><?= htmlspecialchars($g['specialization']) ?></td>
  <td>
    <form method="post" style="display:inline">
      <button class="btn btn-sm btn-danger" name="delete" value="<?= $g['guide_id'] ?>">Delete</button>
    </form>
  </td>
</tr>
<?php endforeach; ?>
</table>
</body>
</html>
