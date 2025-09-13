<?php
// api/sites.php
require_once __DIR__ . '/db.php';
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'GET') {
    $params = [];
    $sql = "SELECT s.*, IFNULL(AVG(r.rating),0) AS avg_rating, COUNT(r.review_id) AS review_count
            FROM heritage_sites s
            LEFT JOIN reviews r ON s.site_id = r.site_id";
    $where = [];
    if (!empty($_GET['search'])) {
        $where[] = "(s.name LIKE :search OR s.description LIKE :search)";
        $params[':search'] = '%' . $_GET['search'] . '%';
    }
    if (!empty($_GET['type'])) {
        $where[] = "s.type = :type";
        $params[':type'] = $_GET['type'];
    }
    if (!empty($_GET['min_price'])) {
        $where[] = "s.ticket_price >= :min_price";
        $params[':min_price'] = $_GET['min_price'];
    }
    if (!empty($_GET['max_price'])) {
        $where[] = "s.ticket_price <= :max_price";
        $params[':max_price'] = $_GET['max_price'];
    }
    if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
    $sql .= ' GROUP BY s.site_id ORDER BY avg_rating DESC, s.name LIMIT 200';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $sites = $stmt->fetchAll();
    echo json_encode($sites);
    exit;
}
if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        exit;
    }
    $stmt = $pdo->prepare("INSERT INTO heritage_sites (name, location, type, opening_hours, ticket_price, unesco_status, description) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$data['name'], $data['location'], $data['type'] ?? 'Monument', $data['opening_hours'] ?? null, $data['ticket_price'] ?? 0, $data['unesco_status'] ? 1 : 0, $data['description'] ?? null]);
    echo json_encode(['success' => true, 'site_id' => $pdo->lastInsertId()]);
    exit;
}
http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
