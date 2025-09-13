<?php
session_start();
require_once __DIR__ . '/../includes/db_connect.php';
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}
if (isset($_GET['delete_id'])) {
    $del = (int)$_GET['delete_id'];
    $stmt = $pdo->prepare('DELETE FROM HeritageSites WHERE site_id = ?');
    $stmt->execute([$del]);
    header('Location: manage_sites.php');
    exit;
}
$stmt = $pdo->query('SELECT * FROM HeritageSites ORDER BY created_at DESC');
$sites = $stmt->fetchAll();
?>
<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Manage Sites</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container"><a class="navbar-brand" href="dashboard.php">Admin</a></div>
    </nav>
    <div class="container py-4">
        <div class="d-flex justify-content-between mb-3">
            <h4>Sites</h4><a class="btn btn-primary" href="site_edit.php">Add Site</a>
        </div>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Location</th>
                    <th>Type</th>
                    <th>Price</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sites as $s): ?>
                    <tr>
                        <td><?php echo $s['site_id']; ?></td>
                        <td><?php echo htmlspecialchars($s['name']); ?></td>
                        <td><?php echo htmlspecialchars($s['location']); ?></td>
                        <td><?php echo htmlspecialchars($s['type']); ?></td>
                        <td><?php echo number_format($s['ticket_price'], 2); ?></td>
                        <td>
                            <a class="btn btn-sm btn-outline-secondary" href="site_edit.php?id=<?php echo $s['site_id']; ?>">Edit</a>
                            <button class="btn btn-sm btn-danger" onclick="if(confirm('Delete site?')) window.location='manage_sites.php?delete_id=<?php echo $s['site_id']; ?>';">Delete</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>

</html>