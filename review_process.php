<?php
require __DIR__ . '/includes/db_connect.php'; // use the same PDO as frontend

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit("Method not allowed");
}

// ----------------- Collect & validate inputs -----------------
$name     = trim($_POST['name'] ?? '');
$email    = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL) ?: null;
$site_id  = isset($_POST['site_id']) ? (int)$_POST['site_id'] : null;
$event_id = isset($_POST['event_id']) ? (int)$_POST['event_id'] : null;
$rating   = max(1, min(5, (int)($_POST['rating'] ?? 5)));
$comment  = trim($_POST['comment'] ?? '');

if ($site_id === null && $event_id === null) {
    die("Must reference site or event");
}
if ($name === '') {
    die("Name required");
}

// ----------------- Database transaction -----------------
$pdo->beginTransaction();

try {
    // 1. Find existing visitor by email (if given)
    $visitor = null;
    if ($email) {
        $stmt = $pdo->prepare("SELECT visitor_id FROM Visitors WHERE email = ?");
        $stmt->execute([$email]);
        $visitor = $stmt->fetchColumn();
    }

    // 2. If not found, insert a new visitor
    if (!$visitor) {
        $stmt = $pdo->prepare("
            INSERT INTO Visitors (name, nationality, email, phone)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$name, null, $email, null]);
        $visitor = $pdo->lastInsertId();
    }

    // 3. Insert the review
    $stmt = $pdo->prepare("
        INSERT INTO Reviews (visitor_id, site_id, event_id, rating, comment)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$visitor, $site_id, $event_id, $rating, $comment]);

    $pdo->commit();

    // ----------------- Redirect back -----------------
    if ($site_id) {
        header("Location: site_view.php?id={$site_id}&review=1");
    } else {
        // if review was tied to an event, get its site_id for redirect
        $stmt = $pdo->prepare("SELECT site_id FROM Events WHERE event_id = ?");
        $stmt->execute([$event_id]);
        $sid = $stmt->fetchColumn();
        header("Location: site_view.php?id={$sid}&review=1");
    }
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo "Error saving review: " . htmlspecialchars($e->getMessage());
}
