<?php
// showcase/index.php
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Heritage DB – Lab Showcase</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background: #f4f6f9; font-family: 'Segoe UI', sans-serif; }
    header { background: #003366; color: white; padding: 20px; text-align: center; }
    footer { background: #003366; color: white; padding: 10px; text-align: center; margin-top: 30px; }
    .lab-card { 
      background: white; border-radius: 12px; 
      box-shadow: 0 3px 8px rgba(0,0,0,0.1); 
      transition: transform .2s;
    }
    .lab-card:hover { transform: scale(1.03); }
    a { text-decoration: none; }
  </style>
</head>
<body>
<header>
  <h1>🏛 Heritage Database Project – Lab Showcase</h1>
  <p class="mb-0">Demonstrating SQL Concepts using heritage_db schema</p>
</header>

<div class="container my-5">
  <div class="row g-4">

    <?php
    $labs = [
      2 => 'Basic SELECT Queries',
      3 => 'Aggregate & Group Functions',
      4 => 'Join Queries (INNER, LEFT)',
      5 => 'Subqueries & Nested SELECT',
      6 => 'DML Operations (INSERT/UPDATE/DELETE)',
      7 => 'Views & Indexes',
      8 => 'Triggers',
      9 => 'Stored Procedures & Functions'
    ];
    foreach ($labs as $num => $title): ?>
      <div class="col-md-6 col-lg-4">
        <a href="lab<?= $num ?>.php">
          <div class="lab-card p-4 h-100">
            <h4>Lab <?= $num ?></h4>
            <p><?= htmlspecialchars($title) ?></p>
            <span class="badge bg-primary">View Demo →</span>
          </div>
        </a>
      </div>
    <?php endforeach; ?>

  </div>

  <div class="text-center mt-5">
    <a href="../index.php" class="btn btn-secondary">← Back to Main Heritage Site</a>
  </div>
</div>

<footer>
  <p>&copy; <?= date('Y') ?> Heritage Explorer | DB Showcase Portal</p>
</footer>
</body>
</html>
