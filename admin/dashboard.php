<?php
// admin/dashboard.php
session_start();
require_once __DIR__ . '/../includes/db_connect.php';
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$stmt = $pdo->query('SELECT COUNT(*) AS total_sites FROM HeritageSites');
$total_sites = (int)$stmt->fetchColumn();
$stmt = $pdo->query("SELECT COUNT(DISTINCT visitor_id) FROM bookings");
$total_visitors = (int)$stmt->fetchColumn();
$stmt = $pdo->query('SELECT AVG(avg_rating) FROM (SELECT AVG(r.rating) AS avg_rating FROM reviews r GROUP BY r.site_id) t');
$avg_rating_overall = round((float)$stmt->fetchColumn(), 2);
$stmt = $pdo->query("SELECT DATE_FORMAT(booking_date, '%Y-%m') AS ym, COUNT(*) AS cnt FROM Bookings  WHERE booking_date > DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY ym ORDER BY ym");
$bookings_month = $stmt->fetchAll();
$stmt = $pdo->query('SELECT s.site_id, s.name, COUNT(b.booking_id) AS bookings_count FROM HeritageSites s LEFT JOIN bookings b ON s.site_id=b.site_id GROUP BY s.site_id ORDER BY bookings_count DESC LIMIT 6');
$top_sites = $stmt->fetchAll();
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
            <div class="ms-auto"><a class="btn btn-sm btn-outline-light" href="manage_sites.php">Manage Sites</a> <a class="btn btn-sm btn-outline-light" href="logout.php">Logout</a></div>
        </div>
    </nav>
    <div class="container py-4">
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
                <div class="card p-3"><small>Export</small>
                    <div class="mt-2"><a class="btn btn-sm btn-outline-primary" href="export_csv.php?type=sites">Export Sites CSV</a></div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-lg-8">
                <div class="card p-3">
                    <h5>Bookings (last 6 months)</h5>
                    <canvas id="bookingsChart" style="height:250px"></canvas>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card p-3">
                    <h5>Top Sites</h5>
                    <ul class="list-group mt-2">
                        <?php foreach ($top_sites as $s): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center"><?php echo htmlspecialchars($s['name']); ?> <span class="badge bg-primary rounded-pill"><?php echo $s['bookings_count']; ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>

        <div class="mt-4">
            <h5>Quick Links</h5>
            <div class="d-flex gap-2 mt-2">
                <a class="btn btn-outline-secondary" href="manage_sites.php">Manage Sites</a>
                <a class="btn btn-outline-secondary" href="../index.php">View site (public)</a>
            </div>
        </div>
    </div>

    <script>
        const bookingsData = <?php echo json_encode($bookings_month); ?>;
        const labels = bookingsData.map(r => r.ym);
        const values = bookingsData.map(r => Number(r.cnt));
        const ctx = document.getElementById('bookingsChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Bookings',
                    data: values,
                    backgroundColor: '#198754'
                }]
            },
            options: {}
        });
    </script>
</body>

</html>