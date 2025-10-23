<?php
include 'showcase_header.php';
require_once "../includes/db_connect.php";
echo "<h2>Lab 6: DML Operations</h2>";

// INSERT
$pdo->exec("INSERT INTO Guides(name, language, specialization, salary) VALUES('Test Guide','English','History',5000)");
echo "<p>✅ Inserted one sample guide record.</p>";

// UPDATE
$pdo->exec("UPDATE Guides SET salary = salary + 1000 WHERE name='Test Guide'");
echo "<p>✅ Updated guide salary.</p>";

// DELETE
$pdo->exec("DELETE FROM Guides WHERE name='Test Guide'");
echo "<p>✅ Deleted sample guide record.</p>";

echo "<h4>Final Guides Table:</h4>";
$res = $pdo->query("SELECT * FROM Guides");
echo "<table border=1 cellpadding=5>";
$cols = array_keys($res->fetch(PDO::FETCH_ASSOC) ?? []);
if ($cols) {
  echo "<tr><th>" . implode("</th><th>", $cols) . "</th></tr>";
  $res = $pdo->query("SELECT * FROM Guides");
  foreach ($res as $r)
    echo "<tr><td>" . implode("</td><td>", $r) . "</td></tr>";
} else echo "<tr><td>No Data</td></tr>";
echo "</table>";
?>
