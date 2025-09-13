<?php
require_once 'db.php';

// Expect JSON body
$body = json_decode(file_get_contents('php://input'), true);
if (!$body) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

// Required: visitor info (name,email,phone) OR visitor_id, and either site_id XOR event_id, and no_of_tickets
$name = isset($body['name']) ? trim($body['name']) : '';
$email = isset($body['email']) ? trim($body['email']) : '';
$phone = isset($body['phone']) ? trim($body['phone']) : '';
$visitor_id = isset($body['visitor_id']) ? (int)$body['visitor_id'] : 0;
$site_id = isset($body['site_id']) ? ($body['site_id'] !== '' ? (int)$body['site_id'] : null) : null;
$event_id = isset($body['event_id']) ? ($body['event_id'] !== '' ? (int)$body['event_id'] : null) : null;
$no_of_tickets = isset($body['no_of_tickets']) ? (int)$body['no_of_tickets'] : 1;
$payment_method = isset($body['payment_method']) ? $body['payment_method'] : 'online';

if (($site_id === null && $event_id === null) || ($site_id !== null && $event_id !== null)) {
    http_response_code(400);
    echo json_encode(['error' => 'Provide exactly one of site_id or event_id']);
    exit;
}
if ($visitor_id <= 0 && ($name=='' || $email=='')) {
    http_response_code(400);
    echo json_encode(['error' => 'Provide visitor information (name and email)']);
    exit;
}

$mysqli->begin_transaction();
try {
    // If visitor_id not provided, create visitor
    if ($visitor_id <= 0) {
        $stmt = $mysqli->prepare("INSERT INTO Visitors (name, nationality, email, phone) VALUES (?, '', ?, ?)");
        $stmt->bind_param('sss', $name, $email, $phone);
        $stmt->execute();
        $visitor_id = $stmt->insert_id;
    }

    // If booking for event, check capacity
    if ($event_id !== null) {
        $stmt = $mysqli->prepare("SELECT capacity FROM Events WHERE event_id = ?");
        $stmt->bind_param('i', $event_id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        if (!$res) throw new Exception('Event not found');

        $capacity = (int)$res['capacity'];
        // sum current booked tickets (including pending)
        $stmt = $mysqli->prepare("SELECT COALESCE(SUM(no_of_tickets),0) AS booked FROM Bookings WHERE event_id = ?");
        $stmt->bind_param('i', $event_id);
        $stmt->execute();
        $booked = (int)$stmt->get_result()->fetch_assoc()['booked'];
        if (($booked + $no_of_tickets) > $capacity) {
            throw new Exception('Not enough seats available. Remaining: '.($capacity - $booked));
        }
    }

    // Insert booking
    $stmt = $mysqli->prepare("INSERT INTO Bookings (visitor_id, site_id, event_id, no_of_tickets, payment_status) VALUES (?, ?, ?, ?, 'pending')");
    // handle NULLs
    $site_param = $site_id===null ? null : $site_id;
    $event_param = $event_id===null ? null : $event_id;
    $stmt->bind_param('iiii', $visitor_id, $site_param, $event_param, $no_of_tickets);
    $stmt->execute();
    $booking_id = $stmt->insert_id;

    // Simulate payment record (for demo we mark as successful)
    $amount = 0.00;
    if ($event_id !== null) {
        $stmt = $mysqli->prepare("SELECT ticket_price FROM Events WHERE event_id = ?");
        $stmt->bind_param('i', $event_id);
        $stmt->execute();
        $amount = (float)$stmt->get_result()->fetch_assoc()['ticket_price'];
    } else {
        $stmt = $mysqli->prepare("SELECT ticket_price FROM HeritageSites WHERE site_id = ?");
        $stmt->bind_param('i', $site_id);
        $stmt->execute();
        $amount = (float)$stmt->get_result()->fetch_assoc()['ticket_price'];
    }
    $amount = $amount * $no_of_tickets;

    $stmt = $mysqli->prepare("INSERT INTO Payments (booking_id, amount, method, status) VALUES (?, ?, ?, 'successful')");
    $stmt->bind_param('ids', $booking_id, $amount, $payment_method);
    $stmt->execute();
    $payment_id = $stmt->insert_id;

    // Update booking payment_status
    $stmt = $mysqli->prepare("UPDATE Bookings SET payment_status = 'paid' WHERE booking_id = ?");
    $stmt->bind_param('i', $booking_id);
    $stmt->execute();

    $mysqli->commit();
    echo json_encode(['success' => true, 'booking_id' => $booking_id, 'payment_id' => $payment_id, 'amount' => $amount]);
} catch (Exception $e) {
    $mysqli->rollback();
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
