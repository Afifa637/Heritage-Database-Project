<?php
session_start();
require_once 'headerFooter.php';
require_once __DIR__ . '/../includes/db_connect.php';
if (empty($_SESSION['admin_logged_in'])) { header("Location: login.php"); exit; }

$id = $_GET['id'] ?? null;
$editing = false;
$site = [
    'name' => '',
    'location' => '',
    'type' => '',
    'opening_hours' => '',
    'ticket_price' => '',
    'unesco_status' => '',
    'description' => ''
];

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM HeritageSites WHERE site_id=?");
    $stmt->execute([$id]);
    $site = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($site) $editing = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        $_POST['name'], $_POST['location'], $_POST['type'],
        $_POST['hours'], $_POST['price'], $_POST['unesco'], $_POST['desc']
    ];

    if ($editing) {
        $data[] = $id;
        $stmt = $pdo->prepare("UPDATE HeritageSites SET name=?,location=?,type=?,opening_hours=?,ticket_price=?,unesco_status=?,description=? WHERE site_id=?");
        $stmt->execute($data);
    } else {
        $stmt = $pdo->prepare("INSERT INTO HeritageSites (name,location,type,opening_hours,ticket_price,unesco_status,description) VALUES (?,?,?,?,?,?,?)");
        $stmt->execute($data);
    }
    header("Location: manage_sites.php");
    exit;
}
?>
<!doctype html>
<html>
<head><title><?= $editing ? 'Edit' : 'Add' ?> Site</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container py-4">
<h3><?= $editing ? 'Edit' : 'Add' ?> Heritage Site</h3>
<form method="post">
  <input class="form-control mb-2" name="name" placeholder="Name" value="<?= htmlspecialchars($site['name']) ?>" required>
  <input class="form-control mb-2" name="location" placeholder="Location" value="<?= htmlspecialchars($site['location']) ?>">
  <input class="form-control mb-2" name="type" placeholder="Type" value="<?= htmlspecialchars($site['type']) ?>">
  <input class="form-control mb-2" name="hours" placeholder="Opening Hours" value="<?= htmlspecialchars($site['opening_hours']) ?>">
  <input type="number" class="form-control mb-2" name="price" placeholder="Ticket Price" value="<?= htmlspecialchars($site['ticket_price']) ?>">
  <input class="form-control mb-2" name="unesco" placeholder="UNESCO status" value="<?= htmlspecialchars($site['unesco_status']) ?>">
  <textarea class="form-control mb-2" name="desc" placeholder="Description"><?= htmlspecialchars($site['description']) ?></textarea>
  <button class="btn btn-success">Save</button>
  <a class="btn btn-secondary" href="manage_sites.php">Cancel</a>
</form>
</div>
</body>
</html>
