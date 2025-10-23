<?php
include 'showcase_header.php';
require_once "../includes/db_connect.php";
echo "<h2>Lab 5: Subqueries</h2>";

$sqls = [
  "SELECT name, ticket_price FROM HeritageSites WHERE ticket_price = (SELECT MAX(ticket_price) FROM HeritageSites);",
  "SELECT name FROM HeritageSites WHERE site_id IN (SELECT site_id FROM Events WHERE event_date > CURDATE());",
  "SELECT name FROM Visitors WHERE visitor_id IN (SELECT visitor_id FROM Bookings WHERE payment_status='paid');",
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
