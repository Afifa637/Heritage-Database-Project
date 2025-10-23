<?php
include 'showcase_header.php';
require_once "../includes/db_connect.php";
echo "<h2>Lab 9: Stored Procedures and Functions</h2>";

// Create Procedure
$pdo->exec("DROP PROCEDURE IF EXISTS GetSiteStats;");
$pdo->exec("
CREATE PROCEDURE GetSiteStats(IN siteName VARCHAR(150))
BEGIN
  SELECT h.name, COUNT(e.event_id) AS total_events, AVG(r.rating) AS avg_rating
  FROM HeritageSites h
  LEFT JOIN Events e ON h.site_id = e.site_id
  LEFT JOIN Reviews r ON h.site_id = r.site_id
  WHERE h.name = siteName
  GROUP BY h.name;
END;
");
echo "<p>✅ Procedure 'GetSiteStats' created successfully.</p>";

// Call Procedure
$stmt = $pdo->query("CALL GetSiteStats('Lalbagh Fort');");
echo "<table border=1 cellpadding=5>";
$cols = array_keys($stmt->fetch(PDO::FETCH_ASSOC) ?? []);
if ($cols) {
  echo "<tr><th>" . implode("</th><th>", $cols) . "</th></tr>";
  $stmt = $pdo->query("CALL GetSiteStats('Lalbagh Fort');");
  foreach ($stmt as $r)
    echo "<tr><td>" . implode("</td><td>", $r) . "</td></tr>";
} else echo "<tr><td>No Data</td></tr>";
echo "</table>";
?>
