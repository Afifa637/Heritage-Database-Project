<?php
// admin/dashboard.php
session_start();
require_once __DIR__ . '/../includes/db_connect.php';
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

// === STATS ===
$stmt = $pdo->query('SELECT COUNT(*) FROM HeritageSites');
$total_sites = (int)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(DISTINCT visitor_id) FROM Bookings");
$total_visitors = (int)$stmt->fetchColumn();

$stmt = $pdo->query('SELECT AVG(avg_rating) 
    FROM (SELECT AVG(r.rating) AS avg_rating FROM Reviews r GROUP BY r.site_id) t');
$avg_rating_overall = round((float)$stmt->fetchColumn(), 2);

$stmt = $pdo->query("SELECT DATE_FORMAT(booking_date, '%Y-%m') AS ym, COUNT(*) AS cnt
    FROM Bookings 
    WHERE booking_date > DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY ym ORDER BY ym");
$bookings_month = $stmt->fetchAll();

$stmt = $pdo->query("SELECT s.name, COUNT(b.booking_id) AS bookings_count 
    FROM HeritageSites s 
    LEFT JOIN Bookings b ON s.site_id=b.site_id 
    GROUP BY s.site_id 
    ORDER BY bookings_count DESC LIMIT 6");
$top_sites = $stmt->fetchAll();

$stmt = $pdo->query("SELECT method, SUM(amount) as total 
    FROM Payments WHERE status='success' GROUP BY method");
$revenue_methods = $stmt->fetchAll();

$stmt = $pdo->query("SELECT status, COUNT(*) as cnt 
    FROM Payments GROUP BY status");
$payment_status = $stmt->fetchAll();

$stmt = $pdo->query("SELECT g.name, COUNT(a.assignment_id) as cnt 
    FROM Guides g 
    LEFT JOIN Assignments a ON g.guide_id=a.guide_id 
    GROUP BY g.guide_id ORDER BY cnt DESC LIMIT 8");
$guide_workload = $stmt->fetchAll();

$stmt = $pdo->query("SELECT s.name, ROUND(AVG(r.rating),1) as avg_rating 
    FROM HeritageSites s 
    LEFT JOIN Reviews r ON s.site_id=r.site_id 
    GROUP BY s.site_id 
    HAVING avg_rating IS NOT NULL 
    ORDER BY avg_rating DESC LIMIT 6");
$site_ratings = $stmt->fetchAll();
?>
<!doctype html>
<html>

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
  <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container"><a class="navbar-brand" href="../">Heritage Admin</a>
      <div class="ms-auto">
        <a class="btn btn-sm btn-outline-light" href="manage_sites.php">Manage Sites</a>
        <a class="btn btn-sm btn-outline-light" href="logout.php">Logout</a>
      </div>
    </div>
  </nav>

  <div class="container py-4">

    <!-- Top Cards -->
    <div class="row g-3">
      <div class="col-md-3">
        <div class="card p-3"><small>Total sites</small>
          <div class="h4"><?php echo $total_sites; ?></div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card p-3"><small>Total visitors (booked)</small>
          <div class="h4"><?php echo $total_visitors; ?></div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card p-3"><small>Avg rating</small>
          <div class="h4"><?php echo $avg_rating_overall; ?></div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card p-3"><small>Exports</small>
          <div class="mt-2 d-grid gap-2">
            <a class="btn btn-sm btn-outline-primary" href="export_csv.php?type=sites">Sites CSV</a>
            <a class="btn btn-sm btn-outline-primary" href="export_csv.php?type=bookings">Bookings CSV</a>
            <a class="btn btn-sm btn-outline-primary" href="export_csv.php?type=payments">Payments CSV</a>
            <a class="btn btn-sm btn-outline-primary" href="export_csv.php?type=guides">Guides CSV</a>
          </div>
        </div>
      </div>
    </div>

    <!-- Charts Row -->
    <div class="row mt-4">
      <div class="col-lg-6">
        <div class="card p-3">
          <h5>Bookings (last 6 months)</h5>
          <canvas id="bookingsChart" style="height:250px"></canvas>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="card p-3">
          <h5>Revenue by Payment Method</h5>
          <canvas id="revenueChart" style="height:250px"></canvas>
        </div>
      </div>
    </div>

    <div class="row mt-4">
      <div class="col-lg-6">
        <div class="card p-3">
          <h5>Payment Success vs Failed</h5>
          <canvas id="paymentChart" style="height:250px"></canvas>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="card p-3">
          <h5>Guide Workload</h5>
          <canvas id="guideChart" style="height:250px"></canvas>
        </div>
      </div>
    </div>

    <!-- Top sites and Ratings -->
    <div class="row mt-4">
      <div class="col-lg-6">
        <div class="card p-3">
          <h5>Top Sites</h5>
          <ul class="list-group mt-2">
            <?php foreach ($top_sites as $s): ?>
              <li class="list-group-item d-flex justify-content-between align-items-center">
                <?php echo htmlspecialchars($s['name']); ?>
                <span class="badge bg-primary rounded-pill"><?php echo $s['bookings_count']; ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="card p-3">
          <h5>Average Ratings</h5>
          <ul class="list-group mt-2">
            <?php foreach ($site_ratings as $r): ?>
              <li class="list-group-item d-flex justify-content-between align-items-center">
                <?php echo htmlspecialchars($r['name']); ?>
                <span class="badge bg-success rounded-pill"><?php echo $r['avg_rating']; ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
    </div>

    <!-- Quick Links -->
    <div class="mt-4">
      <h5>Quick Links</h5>
      <div class="d-flex gap-2 mt-2">
        <a class="btn btn-outline-secondary" href="manage_sites.php">Manage Sites</a>
        <a class="btn btn-outline-secondary" href="manage_events.php">Manage Events</a>
        <a class="btn btn-outline-secondary" href="../index.php">View site (public)</a>
      </div>
    </div>
  </div>

  <script>
    // Bookings chart
    const bookingsData = <?php echo json_encode($bookings_month); ?>;
    new Chart(document.getElementById('bookingsChart'), {
      type: 'bar',
      data: {
        labels: bookingsData.map(r => r.ym),
        datasets: [{ label: 'Bookings', data: bookingsData.map(r => Number(r.cnt)), backgroundColor: '#198754' }]
      }
    });

    // Revenue by method
    const revData = <?php echo json_encode($revenue_methods); ?>;
    new Chart(document.getElementById('revenueChart'), {
      type: 'pie',
      data: {
        labels: revData.map(r => r.method),
        datasets: [{ data: revData.map(r => Number(r.total)), backgroundColor: ['#0d6efd','#198754','#dc3545','#ffc107'] }]
      }
    });

    // Payment success vs failed
    const payData = <?php echo json_encode($payment_status); ?>;
    new Chart(document.getElementById('paymentChart'), {
      type: 'doughnut',
      data: {
        labels: payData.map(r => r.status),
        datasets: [{ data: payData.map(r => Number(r.cnt)), backgroundColor: ['#198754','#dc3545'] }]
      }
    });

    // Guide workload
    const guideData = <?php echo json_encode($guide_workload); ?>;
    new Chart(document.getElementById('guideChart'), {
      type: 'bar',
      data: {
        labels: guideData.map(r => r.name),
        datasets: [{ label: 'Assignments', data: guideData.map(r => Number(r.cnt)), backgroundColor: '#0d6efd' }]
      }
    });
  </script>
</body>
</html>
