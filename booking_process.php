<?php
session_start();
require_once __DIR__ . '/includes/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "Method not allowed.";
    exit;
}

// --- Get & validate input ---
$site_id  = isset($_POST['site_id']) && $_POST['site_id'] !== '' ? (int)$_POST['site_id'] : null;
$event_id = isset($_POST['event_id']) && $_POST['event_id'] !== '' ? (int)$_POST['event_id'] : null;
$tickets  = isset($_POST['no_of_tickets']) ? (int)$_POST['no_of_tickets'] : 1;
$tickets  = max(1, $tickets);

// Allowed payment methods
$allowed_methods = ['bkash','nagad','rocket','card', 'bank_transfer'];
$method = isset($_POST['method']) ? strtolower(trim($_POST['method'])) : 'bkash';
if (!in_array($method, $allowed_methods, true)) {
    $method = 'bkash'; // fallback
}

if ($site_id === null && $event_id === null) {
    die("Booking must reference a site or event.");
}

// --- If event_id provided ensure it exists ---
if ($event_id !== null) {
    $q = $pdo->prepare("SELECT event_id FROM Events WHERE event_id = ?");
    $q->execute([$event_id]);
    if (!$q->fetchColumn()) {
        die("Event not found.");
    }
}

// --- If site_id provided ensure it exists ---
if ($site_id !== null) {
    $q = $pdo->prepare("SELECT site_id FROM HeritageSites WHERE site_id = ?");
    $q->execute([$site_id]);
    if (!$q->fetchColumn()) {
        die("Site not found.");
    }
}

$pdo->beginTransaction();
try {
    // Determine visitor
    if (isset($_SESSION['visitor_id']) && is_numeric($_SESSION['visitor_id'])) {
        $visitor_id = (int)$_SESSION['visitor_id'];
    } else {
        // Guest must provide name & email
        $name  = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        if ($name === '' || $email === '') {
            throw new Exception("Name and Email required for guest booking.");
        }

        // Check if visitor exists by email
        $check = $pdo->prepare("SELECT visitor_id FROM Visitors WHERE email = ?");
        $check->execute([$email]);
        $visitor_id = $check->fetchColumn();

        if (!$visitor_id) {
            $ins = $pdo->prepare("INSERT INTO Visitors (name,email,phone,password_hash) VALUES (?,?,?,?)");
            // Empty password hash for guest, or generate a random one
            $ins->execute([$name, $email, $phone, password_hash(bin2hex(random_bytes(5)), PASSWORD_DEFAULT)]);
            $visitor_id = $pdo->lastInsertId();
        } else {
            $visitor_id = (int)$visitor_id;
        }
    }

    // Insert booking with pending status
    $stmt = $pdo->prepare("
        INSERT INTO Bookings 
        (visitor_id, site_id, event_id, no_of_tickets, booking_date, payment_method, payment_status)
        VALUES (?,?,?,?,NOW(),?, 'pending')
    ");
    $stmt->execute([$visitor_id, $site_id, $event_id, $tickets, $method]);
    $booking_id = $pdo->lastInsertId();

    $pdo->commit();

    // Redirect to payment page
    header("Location: payment_process.php?booking_id=" . (int)$booking_id);
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    die("Booking failed: " . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE));
}
