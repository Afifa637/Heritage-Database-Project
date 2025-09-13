<?php
require_once 'db.php';

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

$visitor_id = isset($body['visitor_id']) ? (int)$body['visitor_id'] : 0;
$site_id = isset($body['site_id']) ? ($body['site_id']!=='' ? (int)$body['site_id'] : null) : null;
$event_id = isset($body['event_id']) ? ($body['event_id']!=='' ? (int)$body['event_id'] : null) : null;
$rating = isset($body['rating']) ? (int)$body['rating'] : 0;
$comment = isset($body['comment']) ? trim($body['comment']) : '';

if (($site_id === null && $event_id === null) || ($site_id !== null && $event_id !== null)) {
    http_response_code(400);
    echo json_encode(['error' => 'Provide exactly one of site_id or event_id']);
    exit;
}
if ($visitor_id <= 0 || $rating < 1 || $rating > 5) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid visitor or rating']);
    exit;
}

$stmt = $mysqli->prepare("INSERT INTO Reviews (visitor_id, site_id, event_id, rating, comment) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param('iiiss', $visitor_id, $site_id, $event_id, $rating, $comment);
$ok = $stmt->execute();
if (!$ok) {
    http_response_code(500);
    echo json_encode(['error' => $stmt->error]);
} else {
    echo json_encode(['success' => true, 'review_id' => $stmt->insert_id]);
}
