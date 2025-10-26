<?php
include __DIR__ . '/../includes/headerFooter.php';
require_once __DIR__ . '/../includes/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email']));
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT visitor_id, password_hash, full_name FROM Visitors WHERE email=?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['visitor_id'] = $user['visitor_id'];
        $_SESSION['visitor_name'] = $user['full_name'];
        header("Location: profile.php");
        exit;
    } else {
        $error = "Invalid email or password.";
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Visitor Login | Heritage Explorer</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background: linear-gradient(to bottom right, #f5e6cc, #d4c6a3);
      font-family: 'Poppins', sans-serif;
    }
    .heritage-card {
      background-color: #fffaf0;
      border: 2px solid #c5a572;
      border-radius: 15px;
      box-shadow: 0 5px 15px rgba(60, 50, 20, 0.2);
    }
    .heritage-header {
      font-family: 'Merriweather', serif;
      color: #7b4b27;
      text-shadow: 1px 1px #e6d2a5;
    }
    .btn-heritage {
      background-color: #7b4b27;
      border: none;
      color: #fff;
      font-weight: 600;
      transition: background-color 0.3s;
    }
    .btn-heritage:hover {
      background-color: #5e3a1e;
    }
    .heritage-link {
      color: #7b4b27;
      text-decoration: none;
      font-weight: 500;
    }
    .heritage-link:hover {
      text-decoration: underline;
      color: #5e3a1e;
    }
    .heritage-logo {
      width: 80px;
      margin-bottom: 10px;
    }
  </style>
</head>
<body>

<div class="container d-flex justify-content-center align-items-center min-vh-100">
  <div class="heritage-card p-4 shadow-lg" style="max-width: 420px; width: 100%;">
    <div class="text-center mb-4">
      
      <h2 class="heritage-header">Visitor Login</h2>
      <p class="text-muted small">Explore Bangladesh’s timeless heritage sites</p>
    </div>

    <?php if (!empty($error)): ?>
      <div class="alert alert-danger text-center"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="mb-3">
        <label class="form-label">Email Address</label>
        <input type="email" name="email" class="form-control border-secondary" placeholder="Enter your email" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-control border-secondary" placeholder="Enter your password" required>
      </div>
      <button type="submit" class="btn btn-heritage w-100 py-2 mt-2">Login</button>
    </form>

    <p class="mt-3 text-center">
      Don’t have an account? <a href="signup.php" class="heritage-link">Sign up here</a>
    </p>
  </div>
</div>

</body>
</html>
