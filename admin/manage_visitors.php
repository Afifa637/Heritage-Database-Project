<?php
require_once 'headerFooter.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdminLogin();

$stmt = $pdo->query("SELECT * FROM Visitors ORDER BY created_at DESC");
$visitors = $stmt->fetchAll();
?>
<h2>All Visitors</h2>
<table border="1">
<tr><th>ID</th><th>Name</th><th>Email</th><th>Nationality</th><th>Phone</th><th>Actions</th></tr>
<?php foreach ($visitors as $v): ?>
  <tr>
    <td><?= $v['visitor_id'] ?></td>
    <td><?= htmlspecialchars($v['name']) ?></td>
    <td><?= htmlspecialchars($v['email']) ?></td>
    <td><?= htmlspecialchars($v['nationality']) ?></td>
    <td><?= htmlspecialchars($v['phone']) ?></td>
    <td><a href="visitor_details.php?id=<?= $v['visitor_id'] ?>">View Details</a></td>
  </tr>
<?php endforeach; ?>
</table>
</div>