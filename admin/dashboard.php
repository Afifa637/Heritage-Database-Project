<?php
// admin/dashboard.php
session_start();
require_once 'headerFooter.php';
require_once __DIR__ . '/../includes/db_connect.php';

if (empty($_SESSION['admin_logged_in'])) {
  header('Location: login.php');
  exit;
}

// ========== Input Filters ==========
$methodFilter = $_GET['method'] ?? '';
$guideFilter  = $_GET['guide_id'] ?? '';
$siteFilter   = $_GET['site_id'] ?? '';
$dateFilter   = $_GET['date'] ?? '';
$timeRange    = $_GET['range'] ?? '6m'; // optional: '6m', '12m', '3m', 'ytd'

// Helper: sanitize for HTML
function e($s)
{
  return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

// ========== Utility for date range ==========
switch ($timeRange) {
  case '3m':
    $interval = '3 MONTH';
    break;
  case '12m':
    $interval = '12 MONTH';
    break;
  case 'ytd':
    $interval = date('Y') . '-01-01';
    break;
  case '6m':
  default:
    $interval = '6 MONTH';
    break;
}

// ========== STATS ==========
try {
  // Total heritage sites
  $total_sites = (int) $pdo->query('SELECT COUNT(*) FROM HeritageSites')->fetchColumn();

  // Total unique visitors who have bookings
  $total_visitors = (int) $pdo->query('SELECT COUNT(DISTINCT visitor_id) FROM Bookings')->fetchColumn();

  // Average rating across sites (only consider sites with at least one review)
  $avg_rating_overall = $pdo->query("
        SELECT ROUND(AVG(avg_rating),2) FROM (
            SELECT AVG(rating) AS avg_rating FROM Reviews GROUP BY site_id
        ) t
    ")->fetchColumn();
  $avg_rating_overall = $avg_rating_overall !== null ? (float)$avg_rating_overall : 0.0;

  // Average ticket price (booked_ticket_price from Bookings)
  $avg_ticket_price = (float) $pdo->query("SELECT ROUND(AVG(booked_ticket_price),2) FROM Bookings WHERE booked_ticket_price > 0")->fetchColumn() ?: 0;

  // Total bookings count
  $total_bookings = (int) $pdo->query("SELECT COUNT(*) FROM Bookings")->fetchColumn();

  // Total revenue (successful payments)
  $total_revenue = (float) $pdo->query("
        SELECT IFNULL(SUM(amount),0) FROM Payments WHERE status IN ('successful','success')
    ")->fetchColumn();
  /* ===========================================================
   🔹 LAB 4 – Aggregates, Grouping, HAVING
   =========================================================== */
  $totalRevenue = $pdo->query("
SELECT method, SUM(amount) AS total 
FROM Payments 
GROUP BY method 
ORDER BY total DESC
")->fetchAll(PDO::FETCH_ASSOC);

  $avgGuideSalary = $pdo->query("SELECT ROUND(AVG(salary),2) AS avg_salary FROM Guides")->fetchColumn();

  $unassignedGuides = $pdo->query("
  SELECT full_name FROM Guides 
  LEFT JOIN Assignments ON Guides.guide_id = Assignments.guide_id 
  WHERE Assignments.guide_id IS NULL
  ")->fetchAll(PDO::FETCH_COLUMN);  

  $topSites = $pdo->query("
SELECT h.name, COUNT(b.booking_id) AS total_bookings 
FROM HeritageSites h
JOIN Bookings b ON h.site_id = b.site_id
GROUP BY h.name
ORDER BY total_bookings DESC
LIMIT 3
")->fetchAll(PDO::FETCH_ASSOC);

  $reviewsData = $pdo->query("
SELECT v.full_name AS visitor, s.name AS site, r.rating, r.comment
FROM Reviews r
JOIN Visitors v ON r.visitor_id = v.visitor_id
JOIN HeritageSites s ON r.site_id = s.site_id
ORDER BY r.review_id DESC
LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);
  $sitesWithEvents = $pdo->query("
    SELECT name FROM HeritageSites
    WHERE site_id IN (SELECT site_id FROM Events)
")->fetchAll(PDO::FETCH_COLUMN);
  $bookingsWithPayments = $pdo->query("
    SELECT b.booking_id, v.full_name, p.method, p.amount, p.status
    FROM Bookings b
    JOIN Visitors v ON b.visitor_id = v.visitor_id
    JOIN Payments p ON b.booking_id = p.booking_id
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);
  $guidesWithoutSite = $pdo->query("
    SELECT full_name FROM Guides
    WHERE guide_id NOT IN (SELECT guide_id FROM Assignments)
")->fetchAll(PDO::FETCH_COLUMN);
  $visitorsBookedNotReviewed = $pdo->query("
    SELECT full_name FROM Visitors
    WHERE visitor_id IN (SELECT visitor_id FROM Bookings)
    AND visitor_id NOT IN (SELECT visitor_id FROM Reviews)
")->fetchAll(PDO::FETCH_COLUMN);

  $sitesWithBookingsOrReviews = $pdo->query("
    (SELECT site_id, name FROM HeritageSites WHERE site_id IN (SELECT site_id FROM Bookings))
    UNION
    (SELECT site_id, name FROM HeritageSites WHERE site_id IN (SELECT site_id FROM Reviews))
")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $ex) {
  // If something goes wrong, set safe defaults
  $total_sites = $total_visitors = $total_bookings = 0;
  $avg_rating_overall = $avg_ticket_price = $total_revenue = 0;
}

/*
 * Bookings by month (chart) - respect timeRange when not 'ytd'
 */
try {
  if ($timeRange === 'ytd') {
    $stmt = $pdo->prepare("
            SELECT DATE_FORMAT(booking_date, '%Y-%m') AS ym, COUNT(*) AS cnt
            FROM Bookings
            WHERE booking_date >= :ytd_start
            GROUP BY ym
            ORDER BY ym
        ");
    $ytdStart = date('Y') . '-01-01';
    $stmt->execute([':ytd_start' => $ytdStart]);
  } else {
    $stmt = $pdo->prepare("
            SELECT DATE_FORMAT(booking_date, '%Y-%m') AS ym, COUNT(*) AS cnt
            FROM Bookings
            WHERE booking_date > DATE_SUB(CURDATE(), INTERVAL {$interval})
            GROUP BY ym
            ORDER BY ym
        ");
    $stmt->execute();
  }
  $bookings_month = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $ex) {
  $bookings_month = [];
}

/*
 * Monthly revenue (last 12 months)
 */
try {
  $stmt = $pdo->prepare("
        SELECT DATE_FORMAT(p.paid_at, '%Y-%m') AS ym, IFNULL(SUM(p.amount),0) AS revenue
        FROM Payments p
        WHERE p.status IN ('successful','success')
          AND p.paid_at > DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        GROUP BY ym
        ORDER BY ym
    ");
  $stmt->execute();
  $monthly_revenue = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $ex) {
  $monthly_revenue = [];
}

/*
 * Revenue by method (apply method filter if provided)
 */
try {
  $q = "SELECT p.method, IFNULL(SUM(p.amount),0) AS total
          FROM Payments p
          WHERE p.status IN ('successful','success')";
  if ($methodFilter) $q .= " AND p.method = :method";
  $q .= " GROUP BY p.method ORDER BY total DESC";
  $stmt = $pdo->prepare($q);
  if ($methodFilter) $stmt->execute([':method' => $methodFilter]);
  else $stmt->execute();
  $revenue_methods = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $ex) {
  $revenue_methods = [];
}

/*
 * Payment statuses distribution
 */
try {
  $stmt = $pdo->prepare("
      SELECT p.status, COUNT(*) AS cnt
      FROM Payments p
      GROUP BY p.status
    ");
  $stmt->execute();
  $payment_status = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $ex) {
  $payment_status = [];
}

/*
 * Top sites by bookings
 */
try {
  $stmt = $pdo->prepare("
        SELECT hs.site_id, hs.name, COUNT(b.booking_id) AS bookings_count
        FROM HeritageSites hs
        LEFT JOIN Bookings b ON hs.site_id = b.site_id
        GROUP BY hs.site_id
        ORDER BY bookings_count DESC
        LIMIT 10
    ");
  $stmt->execute();
  $top_sites = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $ex) {
  $top_sites = [];
}

/*
 * Site ratings (average)
 */
try {
  $stmt = $pdo->prepare("
        SELECT hs.site_id, hs.name, ROUND(AVG(r.rating),1) AS avg_rating, COUNT(r.review_id) AS reviews_count
        FROM HeritageSites hs
        LEFT JOIN Reviews r ON hs.site_id = r.site_id
        GROUP BY hs.site_id
        HAVING reviews_count > 0
        ORDER BY avg_rating DESC
        LIMIT 10
    ");
  $stmt->execute();
  $site_ratings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $ex) {
  $site_ratings = [];
}

try {
  $q = "
    SELECT g.guide_id, g.full_name AS name, g.language, g.specialization, g.salary,
           COUNT(a.assign_id) AS assignments_count
    FROM Guides g
    LEFT JOIN Assignments a ON g.guide_id = a.guide_id
      WHERE 1
    ";
  $params = [];
  if ($guideFilter) {
    $q .= " AND g.guide_id = :guide_id";
    $params[':guide_id'] = $guideFilter;
  }
  $q .= " GROUP BY g.guide_id ORDER BY assignments_count DESC LIMIT 20";
  $stmt = $pdo->prepare($q);
  $stmt->execute($params);
  $guide_workload = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $ex) {
  $guide_workload = [];
}

try {
  $q = "
      SELECT r.review_id, v.name AS visitor, COALESCE(hs.name, '—') AS site, r.rating, r.comment, r.review_date
      FROM Reviews r
      JOIN Visitors v ON r.visitor_id = v.visitor_id
      LEFT JOIN HeritageSites hs ON r.site_id = hs.site_id
      WHERE 1
    ";
  $params = [];
  if ($siteFilter) {
    $q .= " AND r.site_id = :site";
    $params[':site'] = $siteFilter;
  }
  if ($dateFilter) {
    $q .= " AND r.review_date = :date";
    $params[':date'] = $dateFilter;
  }
  $q .= " ORDER BY r.review_id DESC LIMIT 10";
  $stmt = $pdo->prepare($q);
  $stmt->execute($params);
  $reviewsData = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $ex) {
  $reviewsData = [];
}

/*
 * Bookings by visitor nationality (top nationalities)
 */
try {
  $stmt = $pdo->prepare("
      SELECT v.nationality, COUNT(b.booking_id) AS cnt
      FROM Bookings b
      JOIN Visitors v ON b.visitor_id = v.visitor_id
      GROUP BY v.nationality
      ORDER BY cnt DESC
      LIMIT 8
    ");
  $stmt->execute();
  $bookings_by_nationality = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $ex) {
  $bookings_by_nationality = [];
}

// ========== Render HTML ==========
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin Dashboard — Heritage</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <style>
    canvas {
      height: 220px !important;
    }

    .card-scroll {
      max-height: 420px;
      overflow: auto;
    }

    .small-stat {
      font-size: 0.85rem;
      color: #666;
    }

    .truncate {
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      max-width: 100%;
      display: block;
    }
  </style>
</head>

<body>
  <div class="container py-3">

    <!-- QUICK STATS -->
    <div class="row g-3 mb-4">
      <div class="col-md-3">
        <div class="card p-3">
          <small class="small-stat">Total Sites</small>
          <div class="h4"><?= e($total_sites) ?></div>
        </div>
      </div>

      <div class="col-md-3">
        <div class="card p-3">
          <small class="small-stat">Total Visitors (Booked)</small>
          <div class="h4"><?= e($total_visitors) ?></div>
        </div>
      </div>

      <div class="col-md-3">
        <div class="card p-3">
          <small class="small-stat">Average Rating</small>
          <div class="h4"><?= e(number_format($avg_rating_overall, 2)) ?></div>
        </div>
      </div>

      <div class="col-md-3">
        <div class="card p-3">
          <small class="small-stat">Total Revenue (successful)</small>
          <div class="h4"><?= e(number_format($total_revenue, 2)) ?> BDT</div>
        </div>
      </div>
    </div>

    <!-- CHARTS GRID -->
    <div class="row">
      <div class="col-lg-6 mb-3">
        <div class="card p-3">
          <h6>Bookings (by month)</h6>
          <canvas id="bookingsChart"></canvas>
        </div>
      </div>

      <div class="col-lg-6 mb-3">
        <div class="card p-3">
          <h6>Monthly Revenue (last 12 months)</h6>
          <canvas id="monthlyRevenueChart"></canvas>
        </div>
      </div>

      <div class="col-lg-6 mb-3">
        <div class="card p-3">
          <h6>Revenue by Method</h6>
          <canvas id="revenueChart"></canvas>
        </div>
      </div>

      <div class="col-lg-6 mb-3">
        <div class="card p-3">
          <h6>Payment Status Distribution</h6>
          <canvas id="paymentChart"></canvas>
        </div>
      </div>

      <div class="col-lg-6 mb-3">
        <div class="card p-3">
          <h6>Guide Workload (assignments)</h6>
          <canvas id="guideChart"></canvas>
        </div>
      </div>

      <div class="col-lg-6 mb-3">
        <div class="card p-3">
          <h6>Bookings by Visitor Nationality</h6>
          <canvas id="nationalityChart"></canvas>
        </div>
      </div>
    </div>

    <!-- TOP LISTS -->
    <div class="row mt-3">
      <div class="col-lg-4">
        <div class="card p-3 card-scroll">
          <h5>Top Sites (by bookings)</h5>
          <ul class="list-group mt-2">
            <?php foreach ($top_sites as $s): ?>
              <li class="list-group-item d-flex justify-content-between align-items-center">
                <span class="truncate"><?= e($s['name']) ?></span>
                <span class="badge bg-primary"><?= e($s['bookings_count']) ?></span>
              </li>
            <?php endforeach; ?>
            <?php if (empty($top_sites)): ?><li class="list-group-item">No data</li><?php endif; ?>
          </ul>
        </div>
      </div>

      <div class="col-lg-4">
        <div class="card p-3 card-scroll">
          <h5>Average Ratings</h5>
          <ul class="list-group mt-2">
            <?php foreach ($site_ratings as $r): ?>
              <li class="list-group-item d-flex justify-content-between align-items-center">
                <div>
                  <strong><?= e($r['name']) ?></strong><br><small class="small-stat"><?= e($r['reviews_count']) ?> reviews</small>
                </div>
                <span class="badge bg-success"><?= e($r['avg_rating']) ?></span>
              </li>
            <?php endforeach; ?>
            <?php if (empty($site_ratings)): ?><li class="list-group-item">No ratings yet</li><?php endif; ?>
          </ul>
        </div>
      </div>

      <div class="col-lg-4">
        <div class="card p-3 card-scroll">
          <h5>Guides & Workload</h5>
          <p class="mb-1 small-stat">Average salary: <strong><?= e(number_format($avgGuideSalary, 2)) ?> BDT</strong></p>
          <ul class="list-group mt-2">
            <?php foreach ($guide_workload as $g): ?>
              <li class="list-group-item">
                <div class="d-flex justify-content-between">
                  <div>
                    <strong><?= e($g['name']) ?></strong>
                    <div class="small-stat"><?= e($g['language']) ?> — <?= e($g['specialization']) ?></div>
                  </div>
                  <div class="text-end">
                    <div class="badge bg-info"><?= e($g['assignments_count']) ?></div>
                    <div class="small-stat mt-1"><?= e(number_format($g['salary'], 2)) ?> BDT</div>
                  </div>
                </div>
              </li>
            <?php endforeach; ?>
            <?php if (empty($guide_workload)): ?><li class="list-group-item">No guides found</li><?php endif; ?>
          </ul>

          <hr>
          <h6>Unassigned Guides</h6>
          <ul class="list-group mt-2">
          <?php foreach ($unassignedGuides as $u): ?>
  <li class="list-group-item"><?= e($u) ?></li>
<?php endforeach; ?>
            <?php if (empty($unassignedGuides)): ?><li class="list-group-item">None</li><?php endif; ?>
          </ul>
        </div>
      </div>
    </div>

  <script>
    // Data prepared server-side
    const bookingsData = <?= json_encode($bookings_month, JSON_THROW_ON_ERROR) ?>;
    const monthlyRevenueData = <?= json_encode($monthly_revenue, JSON_THROW_ON_ERROR) ?>;
    const revData = <?= json_encode($revenue_methods, JSON_THROW_ON_ERROR) ?>;
    const payData = <?= json_encode($payment_status, JSON_THROW_ON_ERROR) ?>;
    const guideData = <?= json_encode($guide_workload, JSON_THROW_ON_ERROR) ?>;
    const nationalityData = <?= json_encode($bookings_by_nationality, JSON_THROW_ON_ERROR) ?>;

    // Bookings chart
    const ctxB = document.getElementById('bookingsChart');
    if (ctxB) {
      new Chart(ctxB, {
        type: 'bar',
        data: {
          labels: bookingsData.map(r => r.ym),
          datasets: [{
            label: 'Bookings',
            data: bookingsData.map(r => +r.cnt),
            backgroundColor: undefined // Let Chart.js default palette choose colors (we avoid hardcoding)
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false
        }
      });
    }

    // Monthly revenue
    const ctxMR = document.getElementById('monthlyRevenueChart');
    if (ctxMR) {
      new Chart(ctxMR, {
        type: 'line',
        data: {
          labels: monthlyRevenueData.map(r => r.ym),
          datasets: [{
            label: 'Revenue (BDT)',
            data: monthlyRevenueData.map(r => +r.revenue),
            fill: true,
            tension: 0.2,
            backgroundColor: undefined,
            borderColor: undefined
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false
        }
      });
    }

    // Revenue by method (pie)
    const ctxR = document.getElementById('revenueChart');
    if (ctxR) {
      new Chart(ctxR, {
        type: 'pie',
        data: {
          labels: revData.map(r => r.method),
          datasets: [{
            data: revData.map(r => +r.total),
            backgroundColor: undefined
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false
        }
      });
    }

    // Payment status
    const ctxP = document.getElementById('paymentChart');
    if (ctxP) {
      new Chart(ctxP, {
        type: 'doughnut',
        data: {
          labels: payData.map(r => r.status),
          datasets: [{
            data: payData.map(r => +r.cnt),
            backgroundColor: undefined
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false
        }
      });
    }

    // Guide workload
    const ctxG = document.getElementById('guideChart');
    if (ctxG) {
      new Chart(ctxG, {
        type: 'bar',
        data: {
          labels: guideData.map(r => r.name),
          datasets: [{
            label: 'Assignments',
            data: guideData.map(r => +r.assignments_count),
            backgroundColor: undefined
          }]
        },
        options: {
          indexAxis: 'y',
          responsive: true,
          maintainAspectRatio: false
        }
      });
    }

    // Bookings by nationality
    const ctxN = document.getElementById('nationalityChart');
    if (ctxN) {
      new Chart(ctxN, {
        type: 'bar',
        data: {
          labels: nationalityData.map(r => r.nationality || 'Unknown'),
          datasets: [{
            label: 'Bookings',
            data: nationalityData.map(r => +r.cnt),
            backgroundColor: undefined
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false
        }
      });
    }
  </script>
</body>

</html>