<?php
include 'showcase_header.php';
require_once "../includes/db_connect.php";
echo "<h2>Lab 7: Views and Indexes</h2>";

// Create a View
$pdo->exec("CREATE OR REPLACE VIEW vw_site_reviews AS
             SELECT h.name AS site_name, AVG(r.rating) AS avg_rating, COUNT(r.review_id) AS total_reviews
             FROM Reviews r
             JOIN HeritageSites h ON r.site_id = h.site_id
             GROUP BY h.name;");
echo "<p>✅ View created successfully.</p>";

// Query the View
$res = $pdo->query("SELECT * FROM vw_site_reviews;");
echo "<table border=1 cellpadding=5>";
$cols = array_keys($res->fetch(PDO::FETCH_ASSOC) ?? []);
if ($cols) {
  echo "<tr><th>" . implode("</th><th>", $cols) . "</th></tr>";
  $res = $pdo->query("SELECT * FROM vw_site_reviews;");
  foreach ($res as $r)
    echo "<tr><td>" . implode("</td><td>", $r) . "</td></tr>";
} else echo "<tr><td>No Data</td></tr>";
echo "</table>";
?>
