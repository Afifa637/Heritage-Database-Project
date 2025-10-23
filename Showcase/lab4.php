<?php
include 'showcase_header.php';
require_once "../includes/db_connect.php";
echo "<h2>Lab 4: JOIN Queries</h2>";

$sqls = [
  "SELECT e.name AS event_name, h.name AS site_name, e.event_date 
   FROM Events e JOIN HeritageSites h ON e.site_id = h.site_id;",
  
  "SELECT b.booking_id, v.name AS visitor, h.name AS site, b.no_of_tickets 
   FROM Bookings b 
   JOIN Visitors v ON b.visitor_id = v.visitor_id 
   LEFT JOIN HeritageSites h ON b.site_id = h.site_id;",
  
  "SELECT r.review_id, v.name AS visitor, h.name AS site, r.rating 
   FROM Reviews r 
   JOIN Visitors v ON r.visitor_id = v.visitor_id 
   LEFT JOIN HeritageSites h ON r.site_id = h.site_id;",
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
