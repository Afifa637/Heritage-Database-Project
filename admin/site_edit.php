<?php
session_start();
require_once __DIR__ . '/../includes/db_connect.php';
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$editing = ($id > 0);
$name = $location = $type = $opening_hours = $description = '';
$ticket_price = 0;
$unesco = 0;
if ($editing) {
    $stmt = $pdo->prepare('SELECT * FROM HeritageSites WHERE site_id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        header('Location: manage_sites.php');
        exit;
    }
    $name = $row['name'];
    $location = $row['location'];
    $type = $row['type'];
    $opening_hours = $row['opening_hours'];
    $ticket_price = $row['ticket_price'];
    $unesco = $row['unesco_status'];
    $description = $row['description'];
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $location = trim($_POST['location']);
    $type = trim($_POST['type']);
    $opening_hours = trim($_POST['opening_hours']);
    $ticket_price = (float)$_POST['ticket_price'];
    $unesco = isset($_POST['unesco']) ? 1 : 0;
    $description = $_POST['description'];
    if ($editing) {
        $stmt = $pdo->prepare('UPDATE HeritageSites SET name=?, location=?, type=?, opening_hours=?, ticket_price=?, unesco_status=?, description=? WHERE site_id=?');
        $stmt->execute([$name, $location, $type, $opening_hours, $ticket_price, $unesco, $description, $id]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO HeritageSites (name, location, type, opening_hours, ticket_price, unesco_status, description) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$name, $location, $type, $opening_hours, $ticket_price, $unesco, $description]);
    }
    header('Location: manage_sites.php');
    exit;
}
?>
<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?php echo $editing ? 'Edit' : 'Add'; ?> Site</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <div class="container py-4">
        <h4><?php echo $editing ? 'Edit' : 'Add'; ?> Site</h4>
        <form method="post">
            <div class="mb-2"><input name="name" class="form-control" placeholder="Name" required value="<?php echo htmlspecialchars($name); ?>"></div>
            <div class="mb-2"><input name="location" class="form-control" placeholder="Location" required value="<?php echo htmlspecialchars($location); ?>"></div>
            <div class="mb-2"><input name="type" class="form-control" placeholder="Type" value="<?php echo htmlspecialchars($type); ?>"></div>
            <div class="mb-2"><input name="opening_hours" class="form-control" placeholder="Opening hours" value="<?php echo htmlspecialchars($opening_hours); ?>"></div>
            <div class="mb-2"><input name="ticket_price" type="number" step="0.01" class="form-control" placeholder="Ticket price" value="<?php echo htmlspecialchars($ticket_price); ?>"></div>
            <div class="mb-2 form-check"><input type="checkbox" name="unesco" id="unesco" class="form-check-input" <?php if ($unesco) echo 'checked'; ?>><label for="unesco" class="form-check-label">UNESCO</label></div>
            <div class="mb-2"><textarea name="description" class="form-control" rows="4" placeholder="Description"><?php echo htmlspecialchars($description); ?></textarea></div>
            <div><button class="btn btn-primary"><?php echo $editing ? 'Save changes' : 'Create site'; ?></button> <a class="btn btn-secondary" href="manage_sites.php">Cancel</a></div>
        </form>
    </div>
</body>

</html>