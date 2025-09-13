<?php
require_once 'db.php';

// Top 5 most visited sites (by bookings)
$res = $mysqli->query("
SELECT s.site_id, s.name, COUNT(b.booking_id) AS bookings_count
FROM HeritageSites s
LEFT JOIN Bookings b ON s.site_id = b.site_id
GROUP BY s.site_id
ORDER BY bookings_count DESC
LIMIT 5
");
$top_sites = $res->fetch_all(MYSQLI_ASSOC);

// Top 5 rated sites
$res = $mysqli->query("
SELECT s.site_id, s.name, AVG(r.rating) AS avg_rating, COUNT(r.review_id) AS review_count
FROM HeritageSites s
LEFT JOIN Reviews r ON s.site_id = r.site_id
GROUP BY s.site_id
HAVING review_count > 0
ORDER BY avg_rating DESC
LIMIT 5
");
$top_rated = $res->fetch_all(MYSQLI_ASSOC);

// Revenue summary last 12 months
$res = $mysqli->query("
SELECT DATE_FORMAT(p.paid_at, '%Y-%m') AS ym, SUM(p.amount) AS revenue
FROM Payments p
GROUP BY ym
ORDER BY ym DESC
LIMIT 12
");
$revenue = $res->fetch_all(MYSQLI_ASSOC);

echo json_encode(['top_sites' => $top_sites, 'top_rated' => $top_rated, 'revenue' => $revenue]);
