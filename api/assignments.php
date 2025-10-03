<?php
require_once __DIR__ . '/../includes/db_connect.php';
header("Content-Type: application/json");
$sql = "SELECT a.assignment_id,g.full_name,s.name,a.assignment_date
        FROM GuideAssignments a
        JOIN Guides g ON a.guide_id=g.guide_id
        JOIN HeritageSites s ON a.site_id=s.site_id";
echo json_encode($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC));
