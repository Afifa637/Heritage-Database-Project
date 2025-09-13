<?php
// api/site.php
require_once __DIR__ . '/db.php';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { http_response_code(400); echo json_encode(['error'=>'Missing id']); exit; }
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $pdo->prepare("SELECT s.*, IFNULL(AVG(r.rating),0) AS avg_rating, COUNT(r.review_id) AS review_count
                           FROM heritage_sites s
                           LEFT JOIN reviews r ON s.site_id = r.site_id
                           WHERE s.site_id = ?
                           GROUP BY s.site_id");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) { http_response_code(404); echo json_encode(['error'=>'Not found']); exit; }
    echo json_encode($row);
    exit;
}
if ($method === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) { http_response_code(400); echo json_encode(['error'=>'Invalid JSON']); exit; }
    $fields = [];
    $values = [];
    foreach (['name','location','type','opening_hours','ticket_price','unesco_status','description'] as $f) {
        if (array_key_exists($f,$data)) { $fields[] = "$f = ?"; $values[] = ($f === 'unesco_status' ? ($data[$f] ? 1 : 0) : $data[$f]); }
    }
    if (!$fields) { http_response_code(400); echo json_encode(['error'=>'No updatable fields']); exit; }
    $values[] = $id;
    $sql = 'UPDATE heritage_sites SET ' . implode(',', $fields) . ' WHERE site_id = ?';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);
    echo json_encode(['success'=>true]);
    exit;
}
if ($method === 'DELETE') {
    $stmt = $pdo->prepare('DELETE FROM heritage_sites WHERE site_id = ?');
    $stmt->execute([$id]);
    echo json_encode(['success'=>true]);
    exit;
}
http_response_code(405); echo json_encode(['error'=>'Method not allowed']);
