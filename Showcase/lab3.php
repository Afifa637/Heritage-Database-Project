<?php
include 'showcase_header.php';
require_once "../includes/db_connect.php";
echo "<h2>Lab 3: Aggregate & Group Queries</h2>";

$sqls = [
  "SELECT location, COUNT(*) AS total_sites FROM HeritageSites GROUP BY location;",
  "SELECT type, AVG(ticket_price) AS avg_price FROM HeritageSites GROUP BY type HAVING AVG(ticket_price)>100;",
  "SELECT unesco_status, COUNT(*) AS total FROM HeritageSites GROUP BY unesco_status;",
  "SELECT event_date, COUNT(event_id) AS total_events FROM Events GROUP BY event_date ORDER BY event_date;",
];

foreach ($sqls as $q) {
  echo "<h4>$q</h4>";
  $res = $pdo->query($q);
  echo "<table border=1 cellpadding=5>";
  $cols = array_keys($res->fetch(PDO::FETCH_ASSOC) ?? []);
  if ($cols) {
    echo "<tr><th>" . implode("</th><th>", $cols) . "</th></tr>";
    $res = $pdo->query($q);
    foreach ($res as $r)
      echo "<tr><td>" . implode("</td><td>", $r) . "</td></tr>";
  } else echo "<tr><td>No Data</td></tr>";
  echo "</table><br>";
}
?>
