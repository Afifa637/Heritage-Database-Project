<?php
require __DIR__ . '/api/db.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }

$name = trim($_POST['name'] ?? '');
$email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL) ?: null;
$site_id = isset($_POST['site_id']) ? (int)$_POST['site_id'] : null;
$event_id = isset($_POST['event_id']) ? (int)$_POST['event_id'] : null;
$rating = max(1, min(5, (int)($_POST['rating'] ?? 5)));
$comment = trim($_POST['comment'] ?? '');

if ($site_id === null && $event_id === null) die("Must reference site or event");
if ($name === '') die("Name required");

$pdo->beginTransaction();
try {
    // visitor
    if ($email) {
        $vs = $pdo->prepare("SELECT visitor_id FROM Visitors WHERE email = ?");
        $vs->execute([$email]);
        $visitor = $vs->fetchColumn();
    } else {
        $visitor = null;
    }
    if (!$visitor) {
        $insV = $pdo->prepare("INSERT INTO Visitors (name, nationality, email, phone) VALUES (?, ?, ?, ?)");
        $insV->execute([$name, null, $email, null]);
        $visitor = $pdo->lastInsertId();
    }
    // insert review
    $insR = $pdo->prepare("INSERT INTO Reviews (visitor_id, site_id, event_id, rating, comment) VALUES (?, ?, ?, ?, ?)");
    $insR->execute([$visitor, $site_id, $event_id, $rating, $comment]);

    $pdo->commit();
    header("Location: site.php?id=" . ($site_id ?: $pdo->query("SELECT site_id FROM Events WHERE event_id = ".(int)$event_id)->fetchColumn()) . "&review=1");
    exit;
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo "Error: " . $e->getMessage();
}
