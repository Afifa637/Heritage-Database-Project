<?php
include 'showcase_header.php';
require_once "../includes/db_connect.php";
echo "<h2>Lab 2: Basic SELECT Queries</h2>";

$sqls = [
  "SELECT * FROM HeritageSites;",
  "SELECT name, location, ticket_price FROM HeritageSites WHERE ticket_price > 100;",
  "SELECT name, unesco_status FROM HeritageSites WHERE unesco_status != 'None';",
  "SELECT name, type FROM HeritageSites ORDER BY name ASC;",
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
