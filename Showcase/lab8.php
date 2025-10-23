<?php
include 'showcase_header.php';
require_once "../includes/db_connect.php";
echo "<h2>Lab 8: Triggers</h2>";

$pdo->exec("DROP TRIGGER IF EXISTS trg_after_review_insert;");
$pdo->exec("
CREATE TRIGGER trg_after_review_insert
AFTER INSERT ON Reviews
FOR EACH ROW
BEGIN
  INSERT INTO Logs (action_type, description, created_at)
  VALUES ('New Review', CONCAT('Visitor ', NEW.visitor_id, ' reviewed site/event'), NOW());
END;
");

echo "<p>✅ Trigger 'trg_after_review_insert' created successfully.</p>";
?>
