<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$visitor_logged_in = isset($_SESSION['visitor_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= $page_title ?? 'Heritage Explorer' ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Merriweather:wght@400;700&family=Poppins:wght@300;400;500&display=swap" rel="stylesheet">
  <style>
    :root {
      --sand: #e6d5b8;
      --brown: #5a3825;
      --dark-brown: #3e2718;
      --cream: #f8f3e9;
      --accent: #c49b63;
    }
    body { 
      background-color: var(--cream);
      font-family: 'Poppins', sans-serif; 
      color: var(--dark-brown);
    }
    header {
      background: linear-gradient(90deg, var(--brown), var(--dark-brown));
      color: var(--cream);
      box-shadow: 0 2px 6px rgba(0,0,0,.3);
    }
    header a {
      color: var(--cream);
      text-decoration: none;
      font-weight: 500;
      transition: color 0.2s ease;
    }
    header a:hover {
      color: var(--accent);
    }
    h2, h3, h4, h5 {
      font-family: 'Merriweather', serif;
      color: var(--brown);
    }
    .btn-outline-light {
      border-color: var(--cream);
      color: var(--cream);
    }
    .btn-outline-light:hover {
      background-color: var(--cream);
      color: var(--dark-brown);
    }
    .btn-warning {
      background-color: var(--accent);
      border: none;
      color: var(--dark-brown);
    }
    footer {
      background: var(--dark-brown);
      color: var(--cream);
      font-size: 0.85rem;
      padding: 20px 0;
      margin-top: 60px;
    }
    footer a {
      color: #d4b48c;
      text-decoration: none;
    }
    footer a:hover { text-decoration: underline; }
  </style>
</head>
<body>

<header class="py-3 shadow-sm">
  <div class="container d-flex justify-content-between align-items-center">
    <h2 class="m-0" style="color: var(--sand)">🏛 Heritage Explorer</h2>
    <nav>
      <a href="/Heritage-Database-Project/index.php" class="me-3">Home</a>
      <a href="/Heritage-Database-Project/contact.php" class="me-3">Contact</a>
      <?php if ($visitor_logged_in): ?>
        <a href="/Heritage-Database-Project/visitor/profile.php" class="me-3">Profile</a>
        <a href="/Heritage-Database-Project/visitor/logout.php" class="btn btn-sm btn-outline-light">Logout</a>
      <?php else: ?>
        <a href="/Heritage-Database-Project/visitor/login.php" class="btn btn-sm btn-outline-light me-2">Login</a>
        <a href="/Heritage-Database-Project/visitor/signup.php" class="btn btn-sm btn-warning">Sign Up</a>
      <?php endif; ?>
    </nav>
  </div>
</header>

<div class="container my-4">
</div>