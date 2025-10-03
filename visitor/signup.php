<?php
require_once __DIR__ . '/../includes/db_connect.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $nationality = trim($_POST['nationality']);
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);

    $stmt = $pdo->prepare("INSERT INTO Visitors (name, email, phone, nationality, password_hash) VALUES (?,?,?,?,?)");
    try {
        $stmt->execute([$name, $email, $phone, $nationality, $password]);
        $_SESSION['visitor_id'] = $pdo->lastInsertId();
        header("Location: profile.php");
        exit;
    } catch (PDOException $e) {
        $error = "Email already exists or invalid data.";
    }
}
?>
<form method="POST">
  <input type="text" name="name" placeholder="Full Name" required>
  <input type="email" name="email" placeholder="Email" required>
  <input type="text" name="phone" placeholder="Phone">
  <input type="text" name="nationality" placeholder="Nationality">
  <input type="password" name="password" placeholder="Password" required>
  <button type="submit">Sign Up</button>
</form>
<?php if (!empty($error)) echo "<p>$error</p>"; ?>
