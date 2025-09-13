<?php
require __DIR__ . '/api/db.php';

// Basic POST-only handler
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "Method not allowed";
    exit;
}

// sanitize
$name = trim($_POST['name'] ?? '');
$email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL) ?: null;
$phone = trim($_POST['phone'] ?? '');
$site_id = isset($_POST['site_id']) ? (int)$_POST['site_id'] : null;
$event_id = isset($_POST['event_id']) ? (int)$_POST['event_id'] : null;
$no_of_tickets = max(1, (int)($_POST['no_of_tickets'] ?? 1));
$method = in_array($_POST['method'] ?? '', ['cash','card','mobile','bank_transfer','online']) ? $_POST['method'] : 'online';

if ($site_id === null && $event_id === null) {
    die("Booking must reference either a site or an event.");
}

if ($name === '') {
    die("Name is required.");
}

$pdo->beginTransaction();
try {
    // check if visitor exists by email
    if ($email) {
        $vs = $pdo->prepare("SELECT visitor_id FROM Visitors WHERE email = ?");
        $vs->execute([$email]);
        $visitor = $vs->fetchColumn();
    } else {
        $visitor = null;
    }

    if (!$visitor) {
        $insV = $pdo->prepare("INSERT INTO Visitors (name, nationality, email, phone) VALUES (?, ?, ?, ?)");
        $insV->execute([$name, null, $email, $phone]);
        $visitor = $pdo->lastInsertId();
    }

    // create booking
    $insB = $pdo->prepare("INSERT INTO Bookings (visitor_id, site_id, event_id, no_of_tickets, payment_status) VALUES (?, ?, ?, ?, 'pending')");
    $insB->execute([$visitor, $site_id, $event_id, $no_of_tickets]);
    $booking_id = $pdo->lastInsertId();

    // create payment record (simulate)
    $amount = 0.00;
    if ($event_id) {
        $p = $pdo->prepare("SELECT ticket_price FROM Events WHERE event_id = ?");
        $p->execute([$event_id]);
        $amount = (float)$p->fetchColumn();
    } else {
        $p = $pdo->prepare("SELECT ticket_price FROM HeritageSites WHERE site_id = ?");
        $p->execute([$site_id]);
        $amount = (float)$p->fetchColumn();
    }
    $total = $amount * $no_of_tickets;

    $insPay = $pdo->prepare("INSERT INTO Payments (booking_id, amount, method, status) VALUES (?, ?, ?, ?)");
    $insPay->execute([$booking_id, $total, $method, 'initiated']);

    $pdo->commit();
    header("Location: site.php?id=" . ($site_id ?: $pdo->query("SELECT site_id FROM Events WHERE event_id = ".(int)$event_id)->fetchColumn()) . "&booked=1");
    exit;
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo "Error: " . $e->getMessage();
    exit;
}
