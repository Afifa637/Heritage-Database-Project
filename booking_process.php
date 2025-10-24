<?php
session_start();
require_once __DIR__ . '/includes/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit("Method not allowed");
}

// --- Collect & Validate Input ---
$site_id  = isset($_POST['site_id']) && $_POST['site_id'] !== '' ? (int)$_POST['site_id'] : null;
$event_id = isset($_POST['event_id']) && $_POST['event_id'] !== '' ? (int)$_POST['event_id'] : null;
$no_of_tickets = isset($_POST['no_of_tickets']) ? max(1, (int)$_POST['no_of_tickets']) : 1;

if ($site_id === null && $event_id === null) {
    die("Booking must reference a site or event.");
}

// Payment method (align with ENUM)
$allowed_methods = ['bkash', 'nagad', 'rocket', 'card', 'bank_transfer'];
$method = strtolower(trim($_POST['method'] ?? 'bkash'));
if (!in_array($method, $allowed_methods, true)) {
    $method = 'bkash';
}

// --- Validate site or event existence ---
if ($site_id) {
    $q = $pdo->prepare("SELECT ticket_price FROM HeritageSites WHERE site_id = ?");
    $q->execute([$site_id]);
    $ticket_price = $q->fetchColumn();
    if (!$ticket_price) die("Site not found.");
} else {
    $q = $pdo->prepare("SELECT ticket_price FROM Events WHERE event_id = ?");
    $q->execute([$event_id]);
    $ticket_price = $q->fetchColumn();
    if (!$ticket_price) die("Event not found.");
}

$total_price = $ticket_price * $no_of_tickets;

$pdo->beginTransaction();
try {
    // --- Determine Visitor (logged-in or guest) ---
    if (isset($_SESSION['visitor_id']) && is_numeric($_SESSION['visitor_id'])) {
        $visitor_id = (int)$_SESSION['visitor_id'];
    } else {
        $name  = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if ($name === '' || $email === '') {
            throw new Exception("Name and Email required for guest booking.");
        }

        // Check existing visitor
        $check = $pdo->prepare("SELECT visitor_id FROM Visitors WHERE email = ?");
        $check->execute([$email]);
        $visitor_id = $check->fetchColumn();

        if (!$visitor_id) {
            $ins = $pdo->prepare("INSERT INTO Visitors (name,email,phone,password_hash) VALUES (?,?,?,?)");
            $ins->execute([$name, $email, $phone, password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT)]);
            $visitor_id = $pdo->lastInsertId();
        }
    }

    // --- Create Booking ---
    $stmt = $pdo->prepare("
        INSERT INTO Bookings (visitor_id, site_id, event_id, no_of_tickets, booked_ticket_price, payment_status, booking_date)
        VALUES (?, ?, ?, ?, ?, 'pending', NOW())
    ");
    $stmt->execute([$visitor_id, $site_id, $event_id, $no_of_tickets, $total_price]);
    $booking_id = $pdo->lastInsertId();

    // --- Create Payment record (initiated) ---
    $pmt = $pdo->prepare("
        INSERT INTO Payments (booking_id, amount, method, status)
        VALUES (?, ?, ?, 'initiated')
    ");
    $pmt->execute([$booking_id, $total_price, $method]);

    $pdo->commit();

    // Redirect to payment page
    header("Location: payment_process.php?booking_id={$booking_id}");
    exit;
} catch (Exception $e) {
    $pdo->rollBack();
    die("Booking failed: " . htmlspecialchars($e->getMessage()));
}
