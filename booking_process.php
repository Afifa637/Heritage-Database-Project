<?php
require_once __DIR__ . '/includes/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }

$site_id  = isset($_POST['site_id']) ? (int)$_POST['site_id'] : null;
$event_id = isset($_POST['event_id']) ? (int)$_POST['event_id'] : null;
$name     = trim($_POST['name'] ?? '');
$email    = trim($_POST['email'] ?? '');
$phone    = trim($_POST['phone'] ?? '');
$tickets  = max(1, (int)($_POST['no_of_tickets'] ?? 1));
$method   = $_POST['method'] ?? 'cash';

if ($site_id === null && $event_id === null) die("Booking must reference a site or event");
if ($name === '') die("Name required");

// Visitor (insert if new)
$pdo->beginTransaction();
try {
    $visitor_id = null;
    if ($email) {
        $check = $pdo->prepare("SELECT visitor_id FROM Visitors WHERE email=?");
        $check->execute([$email]);
        $visitor_id = $check->fetchColumn();
    }
    if (!$visitor_id) {
        $ins = $pdo->prepare("INSERT INTO Visitors (name,email,phone) VALUES (?,?,?)");
        $ins->execute([$name,$email,$phone]);
        $visitor_id = $pdo->lastInsertId();
    }

    // Insert booking
    $stmt = $pdo->prepare("INSERT INTO Bookings (visitor_id, site_id, event_id, no_of_tickets, booking_date) VALUES (?,?,?,?,NOW())");
    $stmt->execute([$visitor_id, $site_id, $event_id, $tickets]);
    $booking_id = $pdo->lastInsertId();

    // Redirect to payment page
    $pdo->commit();
    header("Location: payment_process.php?booking_id=$booking_id&method=" . urlencode($method));
    exit;
} catch (Exception $e) {
    $pdo->rollBack();
    die("Booking failed: " . $e->getMessage());
}
