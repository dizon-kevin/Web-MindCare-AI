<?php
session_start();
require_once '../config/dbcon.php';

// Optional: Only allow admin access
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
  header("Location: ../auth/login.php");
  exit;
}

// Main logs query
$logsQuery = "SELECT mood_logs.*, users.name 
              FROM mood_logs 
              LEFT JOIN users ON mood_logs.user_id = users.id 
              ORDER BY created_at DESC";
$logsRes = $conn->query($logsQuery);

// Fetch all logs for rendering
$logs = [];
if ($logsRes) {
  while ($row = $logsRes->fetch_assoc()) {
    $logs[] = $row;
  }
}

// Stats: total logs
$totalLogs = 0;
$res = $conn->query("SELECT COUNT(*) AS cnt FROM mood_logs");
if ($res && $row = $res->fetch_assoc()) {
  $totalLogs = (int)$row['cnt'];
}

// Stats: unique users
$uniqueUsers = 0;
$res = $conn->query("SELECT COUNT(DISTINCT user_id) AS cnt FROM mood_logs");
if ($res && $row = $res->fetch_assoc()) {
  $uniqueUsers = (int)$row['cnt'];
}

// Stats: date range
$firstLog = null;
$lastLog = null;
$res = $conn->query("SELECT MIN(created_at) AS first_log, MAX(created_at) AS last_log FROM mood_logs");
if ($res && $row = $res->fetch_assoc()) {
  $firstLog = $row['first_log'] ?? null;
  $lastLog = $row['last_log'] ?? null;
}

// Stats: most common emotion
$topEmotion = ['emotion' => null, 'cnt' => 0];
$res = $conn->query("SELECT emotion, COUNT(*) AS cnt FROM mood_logs GROUP BY emotion ORDER BY cnt DESC LIMIT 1");
if ($res && $row = $res->fetch_assoc()) {
  $topEmotion = ['emotion' => $row['emotion'], 'cnt' => (int)$row['cnt']];
}

// Chart: emotion breakdown
$emotionBreakdown = []; // ['happy' => 10, 'sad' => 5, ...]
$res = $conn->query("SELECT emotion, COUNT(*) AS cnt FROM mood_logs GROUP BY emotion ORDER BY cnt DESC");
if ($res) {
  while ($row = $res->fetch_assoc()) {
    $emotionBreakdown[$row['emotion']] = (int)$row['cnt'];
  }
}

// Chart: last 14 days trend
$daysBack = 13; // includes today (total 14 days)
$trendData = []; // 'YYYY-MM-DD' => count
for ($i = $daysBack; $i >= 0; $i--) {
  $d = date('Y-m-d', strtotime("-$i days"));
  $trendData[$d] = 0;
}
$res = $conn->query("
  SELECT DATE(created_at) AS d, COUNT(*) AS cnt
  FROM mood_logs
  WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL $daysBack DAY)
  GROUP BY DATE(created_at)
  ORDER BY d ASC
");
if ($res) {
  while ($row = $res->fetch_assoc()) {
    $trendData[$row['d']] = (int)$row['cnt'];
  }
}

// Distinct names for user suggestions (from logs)
$distinctUsers = [];
$res = $conn->query("
  SELECT DISTINCT COALESCE(users.name, 'User') AS name
  FROM mood_logs
  LEFT JOIN users ON mood_logs.user_id = users.id
  WHERE users.name IS NOT NULL AND users.name <> ''
  ORDER BY name ASC
");
if ($res) {
  while ($row = $res->fetch_assoc()) {
    $distinctUsers[] = $row['name'];
  }
}

// Emotion badge color mapping (Bootstrap 5)
function emotionBadgeClass($emotion) {
  $map = [
    'happy' => 'bg-success',
    'joy' => 'bg-success',
    'sad' => 'bg-secondary',
    'angry' => 'bg-danger',
    'anger' => 'bg-danger',
    'neutral' => 'bg-dark',
    'surprised' => 'bg-warning text-dark',
    'surprise' => 'bg-warning text-dark',
    'fear' => 'bg-info text-dark',
    'disgust' => 'bg-primary'
  ];
  $key = strtolower((string)$emotion);
  return $map[$key] ?? 'bg-secondary';
}

// Emotion icon mapping (Bootstrap Icons)
function emotionIconClass($emotion) {
  $map = [
    'happy' => 'bi-emoji-smile',
    'joy' => 'bi-emoji-laughing',
    'sad' => 'bi-emoji-frown',
    'angry' => 'bi-emoji-angry',
    'anger' => 'bi-emoji-angry',
    'neutral' => 'bi-emoji-neutral',
    'surprised' => 'bi-emoji-dizzy',
    'surprise' => 'bi-emoji-dizzy',
    'fear' => 'bi-emoji-frown',
    'disgust' => 'bi-emoji-expressionless'
  ];
  $key = strtolower((string)$emotion);
  return $map[$key] ?? 'bi-emoji-neutral';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Admin - Mood Logs</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <!-- Bootstrap CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

  <style>
    :root {
      --brand: #4f46e5;            /* Indigo 600 */
      --brand-600: #4f46e5;
      --brand-500: #6366f1;
      --brand-100: #eef2ff;
      --muted-bg: #f6f8fb;
      --card-radius: 14px;
      --elev-1: 0 2px 12px rgba(0,0,0,.06);
      --elev-2: 0 8px 24px rgba(0,0,0,.08);
      --anim-fast: .25s;
      --anim-med: .4s;
    }
    body { background-color: var(--muted-bg); }

    /* Header aligned with emotional_analysis hero */
    .app-header {
      position: sticky; top: 0; z-index: 1030;
      background: linear-gradient(180deg, rgba(79,70,229,.95) 0%, rgba(79,70,229,.92) 60%, rgba(79,70,229,.88) 100%);
      color: #fff;
      backdrop-filter: saturate(140%) blur(8px);
      box-shadow: 0 1px 0 rgba(255,255,255,.05);
    }
    .app-header .brand {
      font-weight: 600;
      display: flex; align-items: center; gap: .6rem;
    }
    .app-header .brand i {
      font-size: 1.25rem;
    }
    .app-header .toolbar .btn {
      color: #fff; border-color: rgba(255,255,255,.35);
    }
    .app-header .toolbar .btn:hover {
      background: rgba(255,255,255,.08);
    }

    .content-wrap { padding: 1.2rem; }
    @media (min-width: 992px) {
      .content-wrap { padding: 1.8rem; }
    }

    .card-stat, .table-card {
      border: none;
      box-shadow: var(--elev-1);
      border-radius: var(--card-radius);
      transition: transform var(--anim-fast) ease, box-shadow var(--anim-fast) ease;
      background: #fff;
    }
    .card-hover-lift:hover {
      transform: translateY(-3px);
      box-shadow: var(--elev-2);
    }
    .card-stat .icon {
      width: 48px; height: 48px; border-radius: 12px;
      display: inline-flex; align-items: center; justify-content: center;
      font-size: 1.25rem;
    }
    .card-header.align {
      background: #fff;
      display: flex; align-items: center; justify-content: space-between;
      border-bottom: 1px solid rgba(0,0,0,.05);
    }

    /* Sticky action/filter bar */
    .sticky-actions {
      position: sticky; top: 76px; z-index: 10; background: var(--muted-bg);
      padding-top: 8px;
    }

    /* Inputs with icons */
    .input-icon {
      position: relative;
    }
    .input-icon > i {
      position: absolute; top: 50%; left: 12px; transform: translateY(-50%);
      color: #6c757d; pointer-events: none;
    }
    .input-icon > input, .input-icon > select {
      padding-left: 38px;
    }

    /* Pills for quick emotion filters */
    .emo-pill {
      border-radius: 999px; border: 1px solid rgba(0,0,0,.08);
      background: #fff; padding: .35rem .6rem; font-size: .85rem;
      display: inline-flex; align-items: center; gap: .35rem;
      cursor: pointer; user-select: none;
      transition: background var(--anim-fast) ease, transform var(--anim-fast) ease, box-shadow var(--anim-fast) ease;
    }
    .emo-pill i { font-size: 1rem; }
    .emo-pill.active {
      background: var(--brand-100);
      color: var(--brand-600);
      border-color: rgba(99,102,241,.35);
      box-shadow: 0 0 0 0.2rem rgba(99,102,241,.15);
    }
    .emo-pill:hover { transform: translateY(-1px); }

    /* Table polish */
    .table thead th { white-space: nowrap; font-size: .9rem; color: #495057; }
    .table tbody td { vertical-align: middle; }
    .table-responsive { max-height: 60vh; overflow: auto; }
    .table thead th.sticky-head {
      position: sticky; top: 0; background: #f8f9fa; z-index: 1;
    }

    .badge-emo {
      margin-right: 6px; margin-bottom: 4px; display: inline-flex; align-items: center; gap: .35rem;
      transition: transform .15s ease;
    }
    .badge-emo:hover { transform: scale(1.04); }

    /* Percentage mini progress */
    .pct-wrap {
      display: flex; align-items: center; gap: .5rem;
      min-width: 160px;
    }
    .pct-bar {
      height: 6px; border-radius: 4px; flex: 1; background: #e9ecef; overflow: hidden;
    }
    .pct-fill {
      height: 100%; border-radius: 4px; transition: width .5s ease;
    }

    /* Section entrances */
    [data-animate] {
      opacity: 0; transform: translateY(8px);
      animation: riseIn .6s var(--animate-delay, 0s) ease-out forwards;
    }
    @keyframes riseIn { from { opacity: 0; transform: translateY(8px);} to { opacity: 1; transform: translateY(0);} }

    /* Row reveal on filter */
    .row-fade { animation: rowIn .35s ease-out both; }
    @keyframes rowIn { from { opacity: 0; transform: translateY(4px);} to { opacity: 1; transform: translateY(0);} }

    /* Button ripple */
    .btn { position: relative; overflow: hidden; }
    .btn:hover { transform: translateY(-1px); }
    .btn:active { transform: translateY(0); }
    .ripple {
      position: absolute; border-radius: 50%; transform: scale(0);
      animation: ripple .6s linear; background: rgba(0,0,0,0.15); pointer-events: none;
    }
    @keyframes ripple { to { transform: scale(4); opacity: 0; } }

    /* Empty states */
    .empty {
      color: #6c757d;
    }

    /* Reduce motion */
    @media (prefers-reduced-motion: reduce) {
      * { animation: none !important; transition: none !important; }
    }
  </style>
</head>
<body>
  <!-- Top Header aligned to emotional_analysis -->
  <header class="app-header">
    <div class="container-fluid py-2">
      <div class="d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
          <a href="../dashboard/admin_dashboard.php" class="btn btn-outline-light btn-sm" title="Back to dashboard">
            <i class="bi bi-arrow-left"></i>
          </a>
          <div class="brand">
            <i class="bi bi-activity"></i>
            <span>Admin — Mood Logs</span>
          </div>
        </div>
        <div class="toolbar d-flex align-items-center gap-2">
          <button class="btn btn-outline-light btn-sm" onclick="location.reload()" title="Refresh">
            <i class="bi bi-arrow-clockwise"></i>
          </button>
        </div>
      </div>
      <div class="mt-2 small opacity-75">
        Overview of recorded emotions and trends across users
      </div>
    </div>
  </header>

  <div class="content-wrap">
    <div class="container-fluid">
      <!-- Stats Row -->
      <div class="row g-3">
        <div class="col-12 col-sm-6 col-xl-3" data-animate style="--animate-delay:.05s">
          <div class="card card-stat p-3 card-hover-lift h-100">
            <div class="d-flex align-items-center">
              <div class="icon bg-primary-subtle text-primary me-3"><i class="bi bi-collection"></i></div>
              <div>
                <div class="text-muted small">Total Logs</div>
                <div class="h4 mb-0"><span class="count-up" data-count-to="<?php echo (int)$totalLogs; ?>" aria-live="polite">0</span></div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3" data-animate style="--animate-delay:.1s">
          <div class="card card-stat p-3 card-hover-lift h-100">
            <div class="d-flex align-items-center">
              <div class="icon bg-success-subtle text-success me-3"><i class="bi bi-people"></i></div>
              <div>
                <div class="text-muted small">Unique Users</div>
                <div class="h4 mb-0"><span class="count-up" data-count-to="<?php echo (int)$uniqueUsers; ?>" aria-live="polite">0</span></div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3" data-animate style="--animate-delay:.15s">
          <div class="card card-stat p-3 card-hover-lift h-100">
            <div class="d-flex align-items-center">
              <div class="icon bg-warning-subtle text-warning me-3"><i class="bi bi-calendar2-week"></i></div>
              <div>
                <div class="text-muted small">Date Range</div>
                <div class="mb-0 text-nowrap">
                  <span class="fw-semibold"><?php echo $firstLog ? htmlspecialchars(date('M d, Y', strtotime($firstLog))) : '—'; ?></span>
                  <span class="text-muted">to</span>
                  <span class="fw-semibold"><?php echo $lastLog ? htmlspecialchars(date('M d, Y', strtotime($lastLog))) : '—'; ?></span>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3" data-animate style="--animate-delay:.2s">
          <div class="card card-stat p-3 card-hover-lift h-100">
            <div class="d-flex align-items-center">
              <div class="icon bg-danger-subtle text-danger me-3"><i class="bi bi-bar-chart"></i></div>
              <div>
                <div class="text-muted small">Top Emotion</div>
                <div class="mb-0">
                  <?php if ($topEmotion['emotion']): ?>
                    <span class="badge <?php echo emotionBadgeClass($topEmotion['emotion']); ?> badge-emo">
                      <i class="bi <?php echo emotionIconClass($topEmotion['emotion']); ?>"></i>
                      <?php echo htmlspecialchars(ucfirst($topEmotion['emotion'])); ?>
                    </span>
                    <span class="small text-muted">
                      <span class="count-up" data-count-to="<?php echo (int)$topEmotion['cnt']; ?>" aria-live="polite">0</span>
                    </span>
                  <?php else: ?>
                    —
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Charts Row -->
      <div class="row g-3 mt-1">
        <div class="col-12 col-lg-6" data-animate style="--animate-delay:.05s">
          <div class="card table-card card-hover-lift h-100">
            <div class="card-header align">
              <div class="fw-semibold"><i class="bi bi-pie-chart me-1"></i> Emotion Distribution</div>
              <button class="btn btn-outline-secondary btn-sm" id="downloadEmotionChart" title="Download chart">
                <i class="bi bi-download"></i>
              </button>
            </div>
            <div class="card-body">
              <?php if (count($emotionBreakdown) === 0): ?>
                <div class="text-center empty py-5">
                  <i class="bi bi-emoji-neutral mb-2" style="font-size:1.6rem;"></i>
                  <div>No data available to display.</div>
                </div>
              <?php else: ?>
                <canvas id="emotionChart" height="180" aria-label="Emotion distribution chart" role="img"></canvas>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <div class="col-12 col-lg-6" data-animate style="--animate-delay:.1s">
          <div class="card table-card card-hover-lift h-100">
            <div class="card-header align">
              <div class="fw-semibold"><i class="bi bi-graph-up me-1"></i> Logs: Last 14 Days</div>
              <button class="btn btn-outline-secondary btn-sm" id="downloadTrendChart" title="Download chart">
                <i class="bi bi-download"></i>
              </button>
            </div>
            <div class="card-body">
              <?php if ($totalLogs === 0): ?>
                <div class="text-center empty py-5">
                  <i class="bi bi-emoji-neutral mb-2" style="font-size:1.6rem;"></i>
                  <div>No data available to display.</div>
                </div>
              <?php else: ?>
                <canvas id="trendChart" height="180" aria-label="Logs trend chart" role="img"></canvas>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Filters -->
      <div class="sticky-actions mt-4" data-animate style="--animate-delay:.15s">
        <div class="card table-card card-hover-lift">
          <div class="card-body">
            <!-- Quick emotion pills -->
            <?php if (!empty($emotionBreakdown)): ?>
            <div class="d-flex flex-wrap gap-2 mb-3" id="emotionPills">
              <span class="emo-pill active" data-emo="">
                <i class="bi bi-ui-checks-grid"></i> All
              </span>
              <?php foreach ($emotionBreakdown as $emo => $cnt): ?>
              <span class="emo-pill" data-emo="<?php echo htmlspecialchars(strtolower($emo)); ?>">
                <i class="bi <?php echo emotionIconClass($emo); ?>"></i>
                <?php echo htmlspecialchars(ucfirst($emo)); ?>
                <span class="badge text-bg-light border ms-1"><?php echo (int)$cnt; ?></span>
              </span>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="row g-3 align-items-end">
              <div class="col-12 col-md-4 col-lg-3">
                <label class="form-label small text-muted">Search (User or Emotion)</label>
                <div class="input-icon">
                  <i class="bi bi-search"></i>
                  <input type="text" id="searchInput" class="form-control" placeholder="Type to filter..." aria-label="Search user or emotion">
                </div>
              </div>
              <div class="col-6 col-md-4 col-lg-2">
                <label class="form-label small text-muted">Emotion</label>
                <div class="input-icon">
                  <i class="bi bi-emoji-smile"></i>
                  <select id="emotionFilter" class="form-select" aria-label="Select emotion">
                    <option value="">All</option>
                    <?php foreach (array_keys($emotionBreakdown) as $emo): ?>
                      <option value="<?php echo htmlspecialchars(strtolower($emo)); ?>">
                        <?php echo htmlspecialchars(ucfirst($emo)); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <div class="col-6 col-md-4 col-lg-2">
                <label class="form-label small text-muted">User</label>
                <div class="input-icon">
                  <i class="bi bi-person"></i>
                  <input list="userList" id="userFilter" class="form-control" placeholder="Any user" aria-label="Filter by user">
                  <datalist id="userList">
                    <?php foreach ($distinctUsers as $name): ?>
                      <option value="<?php echo htmlspecialchars($name); ?>"></option>
                    <?php endforeach; ?>
                  </datalist>
                </div>
              </div>
              <div class="col-6 col-md-3 col-lg-2">
                <label class="form-label small text-muted">From</label>
                <div class="input-icon">
                  <i class="bi bi-calendar-event"></i>
                  <input type="date" id="fromDate" class="form-control" aria-label="From date">
                </div>
              </div>
              <div class="col-6 col-md-3 col-lg-2">
                <label class="form-label small text-muted">To</label>
                <div class="input-icon">
                  <i class="bi bi-calendar2-check"></i>
                  <input type="date" id="toDate" class="form-control" aria-label="To date">
                </div>
              </div>
              <div class="col-12 col-md-6 col-lg-1 d-grid">
                <button id="clearFilters" class="btn btn-outline-secondary"><i class="bi bi-x-circle"></i> Clear</button>
              </div>
            </div>
            <div class="d-flex justify-content-between align-items-center mt-3">
              <div class="small text-muted">
                Showing <span id="visibleCount">0</span> of <?php echo number_format(count($logs)); ?> records
              </div>
              <div class="d-flex gap-2">
                <button id="exportCsv" class="btn btn-outline-primary btn-sm" title="Export visible rows to CSV">
                  <i class="bi bi-filetype-csv"></i> Export CSV (visible)
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Logs Table -->
      <div class="card table-card mt-3 card-hover-lift" data-animate style="--animate-delay:.2s">
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-hover align-middle" id="logsTable">
              <thead class="table-light">
                <tr>
                  <th class="sticky-head">User</th>
                  <th class="sticky-head">Emotion</th>
                  <th class="sticky-head">Percentage</th>
                  <th class="sticky-head">Recorded At</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($logs)): ?>
                  <tr><td colspan="4" class="text-center empty py-4"><i class="bi bi-inbox me-2"></i>No mood logs found.</td></tr>
                <?php else: ?>
                  <?php foreach ($logs as $row): ?>
                    <?php
                      $userName = $row['name'] ?? 'User';
                      $emotion = $row['emotion'] ?? '';
                      $percent = isset($row['percentage']) ? (float)$row['percentage'] : null;
                      $createdAt = $row['created_at'] ?? '';
                      $dateOnly = $createdAt ? date('Y-m-d', strtotime($createdAt)) : '';
                      $pctInt = $percent !== null ? (int)round($percent) : null;
                    ?>
                    <tr
                      data-user="<?php echo htmlspecialchars(mb_strtolower($userName)); ?>"
                      data-emotion="<?php echo htmlspecialchars(mb_strtolower($emotion)); ?>"
                      data-date="<?php echo htmlspecialchars($dateOnly); ?>"
                    >
                      <td class="fw-medium">
                        <i class="bi bi-person-circle text-muted me-1"></i>
                        <?php echo htmlspecialchars($userName); ?>
                      </td>
                      <td>
                        <span class="badge <?php echo emotionBadgeClass($emotion); ?> badge-emo" title="<?php echo htmlspecialchars(ucfirst($emotion)); ?>">
                          <i class="bi <?php echo emotionIconClass($emotion); ?>"></i>
                          <?php echo htmlspecialchars(ucfirst($emotion)); ?>
                        </span>
                      </td>
                      <td>
                        <?php if ($pctInt !== null): ?>
                          <div class="pct-wrap" title="<?php echo $pctInt; ?>%">
                            <div class="pct-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo $pctInt; ?>">
                              <div class="pct-fill <?php echo emotionBadgeClass($emotion); ?>" style="width: <?php echo max(0, min(100, $pctInt)); ?>%;"></div>
                            </div>
                            <span class="text-muted small"><?php echo htmlspecialchars(number_format($pctInt, 0)); ?>%</span>
                          </div>
                        <?php else: ?>
                          —
                        <?php endif; ?>
                      </td>
                      <td>
                        <span class="text-nowrap" title="<?php echo htmlspecialchars(date('c', strtotime($createdAt))); ?>">
                          <i class="bi bi-clock-history text-muted me-1"></i>
                          <?php echo htmlspecialchars(date('M d, Y H:i', strtotime($createdAt))); ?>
                        </span>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    </div>
  </div>

  <!-- Dependencies -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js" defer></script>

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      // Button ripple effect for all .btn
      document.body.addEventListener('click', function (e) {
        const btn = e.target.closest('.btn');
        if (!btn) return;
        const rect = btn.getBoundingClientRect();
        const ripple = document.createElement('span');
        const size = Math.max(rect.width, rect.height);
        ripple.className = 'ripple';
        ripple.style.width = ripple.style.height = size + 'px';
        ripple.style.left = (e.clientX - rect.left - size / 2) + 'px';
        ripple.style.top  = (e.clientY - rect.top  - size / 2) + 'px';
        btn.appendChild(ripple);
        ripple.addEventListener('animationend', () => ripple.remove());
      });

      // Count-up animation for numbers (synchronized behavior with emotional_analysis)
      const counters = document.querySelectorAll('.count-up[data-count-to]');
      const easeOutCubic = t => 1 - Math.pow(1 - t, 3);
      const runCounter = (el) => {
        const end = parseInt(el.getAttribute('data-count-to'), 10) || 0;
        const duration = 800;
        const startTime = performance.now();
        const fmt = new Intl.NumberFormat();
        function tick(now) {
          const p = Math.min(1, (now - startTime) / duration);
          const val = Math.round(end * easeOutCubic(p));
          el.textContent = fmt.format(val);
          if (p < 1) requestAnimationFrame(tick);
        }
        requestAnimationFrame(tick);
      };
      const io = new IntersectionObserver((entries) => {
        entries.forEach(ent => {
          if (ent.isIntersecting) {
            runCounter(ent.target);
            io.unobserve(ent.target);
          }
        });
      }, { threshold: 0.6 });
      counters.forEach(el => io.observe(el));

      // Charts data from PHP
      const emotionLabels = <?php echo json_encode(array_map('ucfirst', array_keys($emotionBreakdown))); ?>;
      const emotionValues = <?php echo json_encode(array_values($emotionBreakdown), JSON_NUMERIC_CHECK); ?>;

      const trendLabels = <?php echo json_encode(array_map(function($d){ return date('M d', strtotime($d)); }, array_keys($trendData))); ?>;
      const trendValues = <?php echo json_encode(array_values($trendData), JSON_NUMERIC_CHECK); ?>;

      // Use the same palette as dashboard/admin_dashboard.php so all circle charts match
      const emotionColors = ['#6C63FF', '#00C9A7', '#f59e0b', '#ef4444', '#3b82f6', '#10b981', '#a855f7', '#f97316'];

      // Emotion Chart
      let emotionChart = null;
      const emotionCanvas = document.getElementById('emotionChart');
      if (emotionCanvas && emotionLabels.length > 0) {
        emotionChart = new Chart(emotionCanvas, {
          type: 'doughnut',
          data: {
            labels: emotionLabels,
            datasets: [{
              data: emotionValues,
              backgroundColor: emotionLabels.map((_, i) => emotionColors[i % emotionColors.length]),
              borderWidth: 0
            }]
          },
          options: {
            animation: { duration: 900, easing: 'easeInOutQuart' },
            plugins: {
              legend: { position: 'bottom' },
              tooltip: { callbacks: { label: ctx => `${ctx.label}: ${ctx.formattedValue}` } }
            },
            cutout: '60%'
          }
        });
      }

      // Trend Chart
      let trendChart = null;
      const trendCanvas = document.getElementById('trendChart');
      if (trendCanvas) {
        trendChart = new Chart(trendCanvas, {
          type: 'line',
          data: {
            labels: trendLabels,
            datasets: [{
              label: 'Logs',
              data: trendValues,
              tension: 0.35,
              borderColor: '#0d6efd',
              backgroundColor: 'rgba(13,110,253,.12)',
              fill: true,
              pointRadius: 3,
              pointHoverRadius: 4
            }]
          },
          options: {
            animation: { duration: 900, easing: 'easeInOutQuart' },
            plugins: { legend: { display: false } },
            scales: {
              y: { beginAtZero: true, ticks: { precision: 0 } }
            }
          }
        });
      }

      // Download chart buttons
      const dlEmotionBtn = document.getElementById('downloadEmotionChart');
      if (dlEmotionBtn && emotionChart) {
        dlEmotionBtn.addEventListener('click', () => downloadChart(emotionChart, 'emotion-distribution.png'));
      }
      const dlTrendBtn = document.getElementById('downloadTrendChart');
      if (dlTrendBtn && trendChart) {
        dlTrendBtn.addEventListener('click', () => downloadChart(trendChart, 'logs-trend-14-days.png'));
      }
      function downloadChart(chart, filename) {
        const link = document.createElement('a');
        link.href = chart.toBase64Image('image/png', 1.0);
        link.download = filename;
        link.click();
      }

      // Table filtering with row reveal animation
      const table = document.getElementById('logsTable');
      const rows = Array.from(table.querySelectorAll('tbody tr'));
      const searchInput = document.getElementById('searchInput');
      const emotionFilter = document.getElementById('emotionFilter');
      const userFilter = document.getElementById('userFilter');
      const fromDate = document.getElementById('fromDate');
      const toDate = document.getElementById('toDate');
      const clearFilters = document.getElementById('clearFilters');
      const visibleCountEl = document.getElementById('visibleCount');

      function normalize(s) { return (s || '').toString().trim().toLowerCase(); }

      function applyFilters() {
        const q = normalize(searchInput.value);
        const emo = normalize(emotionFilter.value);
        const user = normalize(userFilter.value);
        const from = fromDate.value ? new Date(fromDate.value) : null;
        const to = toDate.value ? new Date(toDate.value) : null;
        if (to) { to.setHours(23,59,59,999); }

        let visible = 0;
        rows.forEach(tr => {
          // Skip "no data" row
          if (tr.children.length === 1) { tr.style.display = ''; return; }

          const trUser = tr.getAttribute('data-user') || '';
          const trEmotion = tr.getAttribute('data-emotion') || '';
          const trDateStr = tr.getAttribute('data-date') || '';
          const trDate = trDateStr ? new Date(trDateStr) : null;

          let match = true;

          // Search query across user and emotion
          if (q) {
            const target = (trUser + ' ' + trEmotion);
            if (!target.includes(q)) match = false;
          }

          // Emotion exact filter
          if (emo && trEmotion !== emo) match = false;

          // User filter contains
          if (user && !trUser.includes(user)) match = false;

          // Date range filter
          if (from && (!trDate || trDate < from)) match = false;
          if (to && (!trDate || trDate > to)) match = false;

          if (match) {
            const wasHidden = tr.style.display === 'none';
            tr.style.display = '';
            if (wasHidden) {
              tr.classList.remove('row-fade');
              void tr.offsetWidth; // reflow to restart animation
              tr.classList.add('row-fade');
              setTimeout(() => tr.classList.remove('row-fade'), 400);
            }
            visible++;
          } else {
            tr.style.display = 'none';
          }
        });

        visibleCountEl.textContent = visible.toLocaleString();
      }

      [searchInput, emotionFilter, userFilter, fromDate, toDate].forEach(el => {
        if (el) el.addEventListener('input', applyFilters);
        if (el && (el.tagName === 'SELECT' || el.type === 'date')) {
          el.addEventListener('change', applyFilters);
        }
      });

      if (clearFilters) {
        clearFilters.addEventListener('click', () => {
          searchInput.value = '';
          emotionFilter.value = '';
          userFilter.value = '';
          fromDate.value = '';
          toDate.value = '';
          // reset pills
          document.querySelectorAll('#emotionPills .emo-pill').forEach(p => p.classList.toggle('active', p.dataset.emo === ''));
          applyFilters();
        });
      }

      // Emotion quick pills interaction
      const pillWrap = document.getElementById('emotionPills');
      if (pillWrap) {
        pillWrap.addEventListener('click', (e) => {
          const pill = e.target.closest('.emo-pill');
          if (!pill) return;
          const value = (pill.dataset.emo || '').toLowerCase();
          document.querySelectorAll('#emotionPills .emo-pill').forEach(p => p.classList.remove('active'));
          pill.classList.add('active');
          // sync select
          const current = (document.getElementById('emotionFilter') || {});
          if (current && current.tagName === 'SELECT') current.value = value;
          applyFilters();
        });
      }

      // CSV export (visible rows)
      const exportBtn = document.getElementById('exportCsv');
      if (exportBtn) {
        exportBtn.addEventListener('click', () => {
          const header = ['User','Emotion','Percentage','Recorded At'];
          const data = [];
          rows.forEach(tr => {
            if (tr.style.display === 'none' || tr.children.length === 1) return;
            const tds = tr.querySelectorAll('td');
            const user = tds[0]?.innerText.trim() || '';
            const emo = tds[1]?.innerText.trim() || '';
            const pct = tds[2]?.innerText.trim() || '';
            const date = tds[3]?.innerText.trim() || '';
            data.push([user, emo, pct, date]);
          });
          if (data.length === 0) {
            alert('No visible rows to export.');
            return;
          }
          const csv = [header, ...data].map(row =>
            row.map(val => {
              const v = (val ?? '').toString().replace(/"/g, '""');
              return /[",\n]/.test(v) ? `"${v}"` : v;
            }).join(',')
          ).join('\n');
          const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
          const link = document.createElement('a');
          link.href = URL.createObjectURL(blob);
          link.download = `mood-logs-visible-${new Date().toISOString().slice(0,10)}.csv`;
          document.body.appendChild(link);
          link.click();
          link.remove();
        });
      }

      // Initialize visible count
      applyFilters();
    });
  </script>
</body>
</html>