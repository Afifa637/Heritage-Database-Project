<?php
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background-color: #f8f9fa; }
    .sidebar {
      width: 220px; height: 100vh; position: fixed;
      background: #343a40; color: white; padding-top: 20px;
    }
    .sidebar a {
      color: white; display: block; padding: 10px 20px; text-decoration: none;
    }
    .sidebar a:hover, .sidebar a.active {
      background: #495057;
    }
    .content {
      margin-left: 230px; padding: 20px;
    }
  </style>
</head>
<body>

<div class="sidebar">
  <h5 class="text-center mb-3">Admin Panel</h5>
  <a href="dashboard.php" class="<?= basename($_SERVER['PHP_SELF'])=='dashboard.php'?'active':'' ?>">🏠 Dashboard</a>
  <a href="manage_sites.php" class="<?= basename($_SERVER['PHP_SELF'])=='manage_sites.php'?'active':'' ?>">🏛 Sites</a>
  <a href="manage_events.php" class="<?= basename($_SERVER['PHP_SELF'])=='manage_events.php'?'active':'' ?>">🎟 Events</a>
  <a href="manage_guides.php" class="<?= basename($_SERVER['PHP_SELF'])=='manage_guides.php'?'active':'' ?>">🧭 Guides</a>
  <a href="manage_assignments.php" class="<?= basename($_SERVER['PHP_SELF'])=='manage_assignments.php'?'active':'' ?>">📋 Assignments</a>
  <a href="manage_visitors.php" class="<?= basename($_SERVER['PHP_SELF'])=='manage_visitors.php'?'active':'' ?>">👥 Visitors</a>
  <a href="manage_payments.php" class="<?= basename($_SERVER['PHP_SELF'])=='manage_payments.php'?'active':'' ?>">💳 Payments</a>
  <hr>
  <a href="logout.php" class="text-danger">🚪 Logout</a>
</div>

<div class="content">
