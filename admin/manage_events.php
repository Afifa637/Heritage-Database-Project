<?php
session_start();
require_once 'headerFooter.php';
require_once __DIR__ . '/../includes/db_connect.php';

if (empty($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $del = (int)$_POST['delete_id'];
    $csrf = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $csrf)) {
        http_response_code(400);
        echo "Invalid CSRF token.";
        exit;
    }
    $pdo->prepare('DELETE FROM Events WHERE event_id = :id')->execute(['id' => $del]);
    header('Location: manage_events.php');
    exit;
}

// ✅ Corrected query — use event_date instead of start_date
$events = $pdo->query('SELECT * FROM Events ORDER BY event_date DESC')->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Manage Events</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container py-4">
    <h2>Events</h2>
    <a href="event_edit.php" class="btn btn-primary mb-2">Add New Event</a>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Event Name</th>
                <th>Site ID</th>
                <th>Date</th>
                <th>Time</th>
                <th>Description</th>
                <th>Ticket Price</th>
                <th>Capacity</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($events as $e): ?>
            <tr>
                <td><?= htmlspecialchars($e['name']) ?></td>
                <td><?= htmlspecialchars($e['site_id']) ?></td>
                <td><?= htmlspecialchars($e['event_date']) ?></td>
                <td><?= htmlspecialchars($e['event_time']) ?></td>
                <td><?= htmlspecialchars($e['description']) ?></td>
                <td><?= number_format($e['ticket_price'], 2) ?></td>
                <td><?= htmlspecialchars($e['capacity']) ?></td>
                <td>
                    <a href="event_edit.php?id=<?= $e['event_id'] ?>" class="btn btn-sm btn-secondary">Edit</a>
                    <form method="post" class="d-inline" onsubmit="return confirm('Delete event?');">
                        <input type="hidden" name="delete_id" value="<?= $e['event_id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>
