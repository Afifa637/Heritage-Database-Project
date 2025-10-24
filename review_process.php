<?php
session_start();
require __DIR__ . '/includes/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit("Method not allowed");
}

// Must be logged in
if (!isset($_SESSION['visitor_id'])) {
    header("Location: login.php?redirect=" . urlencode($_SERVER['HTTP_REFERER'] ?? 'index.php'));
    exit;
}

$visitor_id = (int)$_SESSION['visitor_id'];
$site_id    = isset($_POST['site_id']) && $_POST['site_id'] !== '' ? (int)$_POST['site_id'] : null;
$event_id   = isset($_POST['event_id']) && $_POST['event_id'] !== '' ? (int)$_POST['event_id'] : null;
$rating     = max(1, min(5, (int)($_POST['rating'] ?? 5)));
$comment    = trim($_POST['comment'] ?? '');

if ($site_id === null && $event_id === null) {
    die("Must reference site or event");
}

try {
    // Insert review
    $stmt = $pdo->prepare("
        INSERT INTO Reviews (visitor_id, site_id, event_id, rating, comment)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$visitor_id, $site_id, $event_id, $rating, $comment]);

    // Redirect to the site page associated
    if ($site_id) {
        header("Location: site_view.php?id={$site_id}&review=1");
    } else {
        $q = $pdo->prepare("SELECT site_id FROM Events WHERE event_id=?");
        $q->execute([$event_id]);
        $sid = $q->fetchColumn() ?: 0;
        header("Location: site_view.php?id={$sid}&review=1");
    }
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo "Error saving review: " . htmlspecialchars($e->getMessage());
}
