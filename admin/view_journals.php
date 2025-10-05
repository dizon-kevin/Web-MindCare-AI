<?php
session_start();
require_once '../config/dbcon.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
  header("Location: ../index.php");
  exit();
}

$name = $_SESSION['name'] ?? 'Admin';

/**
 * AJAX endpoint: return journals for a specific user in JSON
 */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'user_journals') {
  header('Content-Type: application/json; charset=utf-8');

  // Ensure 'is_locked' column exists (privacy feature)
  $colRes = $conn->query("SHOW COLUMNS FROM journals LIKE 'is_locked'");
  if ($colRes && $colRes->num_rows === 0) {
    $conn->query("ALTER TABLE journals ADD COLUMN is_locked TINYINT(1) NOT NULL DEFAULT 0 AFTER category");
  }

  $userId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
  if ($userId <= 0) {
    echo json_encode(['ok' => false, 'message' => 'Invalid user_id']);
    exit();
  }

  // Verify that the user exists and has journals
  $stmt = $conn->prepare("
    SELECT j.title, j.content, j.created_at, j.is_locked
    FROM journals j
    WHERE j.user_id = ?
    ORDER BY j.created_at DESC
  ");
  if (!$stmt) {
    echo json_encode(['ok' => false, 'message' => 'SQL prepare error']);
    exit();
  }
  $stmt->bind_param('i', $userId);
  $stmt->execute();
  $res = $stmt->get_result();

  $journals = [];
  while ($row = $res->fetch_assoc()) {
    $isLocked = !empty($row['is_locked']);
    $journals[] = [
      'title' => $isLocked ? '(Private Entry)' : $row['title'],
      'content' => $isLocked ? 'This entry is private.' : $row['content'],
      'created_at' => $row['created_at'],
      'is_locked' => (int)$isLocked,
    ];
  }
  $stmt->close();

  echo json_encode(['ok' => true, 'journals' => $journals]);
  exit();
}

/**
 * Fetch list of users with journals (aggregated)
 */
$sqlUsers = "
  SELECT u.id, u.name, COUNT(j.id) AS journal_count, MAX(j.created_at) AS last_entry
  FROM users u
  JOIN journals j ON j.user_id = u.id
  GROUP BY u.id, u.name
  ORDER BY last_entry DESC
";
$users = $conn->query($sqlUsers);
if (!$users) {
  die("SQL Error: " . $conn->error);
}

/**
 * Helper: get initials from name
 */
function get_initials($fullName) {
  $fullName = trim($fullName);
  if ($fullName === '') return 'U';
  // Get first letters of words
  preg_match_all('/\b\p{L}/u', $fullName, $matches);
  $letters = $matches[0] ?? [];
  if (count($letters) === 0) return 'U';
  $first = mb_strtoupper($letters[0]);
  $last = mb_strtoupper($letters[count($letters)-1]);
  // Prefer 2 letters, fallback to 1
  return $first . ($last !== $first ? $last : '');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Journal Logs | MindCare Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    :root {
      --main-bg: #f9f9fb;
      --sidebar-bg: #fff;
      --card-bg: #ffffff;
      --text-color: #212529;
      --primary: #6C63FF;
      --muted: #6c757d;
      --border: #e9ecef;
      --soft: #f3f4f7;
      --accent: #E9E7FF;
    }

    body {
      background: var(--main-bg);
      font-family: 'Segoe UI', sans-serif;
      color: var(--text-color);
      transition: background 0.3s ease, color 0.3s ease;
    }

    .dark-mode {
      --main-bg: #181818;
      --sidebar-bg: #1f1f2e;
      --card-bg: #24243a;
      --text-color: #f1f1f1;
      --muted: #b9c0ca;
      --border: #33374a;
      --soft: #1d1d2b;
      --accent: #2f2f55;
    }

    .wrapper {
      display: flex;
      min-height: 100vh;
      transition: all 0.3s ease;
    }

    /* Sidebar (unchanged) */
    .sidebar {
      position: fixed;
      height: 100vh;
      width: 250px;
      background: var(--sidebar-bg);
      transition: all 0.3s ease;
      box-shadow: 0 0 20px rgba(0,0,0,0.05);
      z-index: 99;
    }
    .sidebar.collapsed { width: 78px; }
    .sidebar .logo-details {
      height: 60px; display: flex; align-items: center; justify-content: space-between;
      padding: 0 15px; border-bottom: 1px solid #eee;
    }
    .sidebar .logo_name { font-size: 20px; font-weight: 600; color: var(--text-color); }
    .sidebar .toggle-btn { font-size: 24px; background: none; border: none; color: var(--text-color); cursor: pointer; }
    .sidebar .nav-links { margin-top: 20px; list-style: none; padding-left: 0; }
    .sidebar .nav-links li { width: 100%; margin: 10px 0; }
    .sidebar .nav-links li a {
      display: flex; align-items: center; text-decoration: none; padding: 12px 20px;
      color: var(--text-color); font-size: 16px; border-radius: 8px; transition: all 0.3s ease;
    }
    .sidebar .nav-links li a i { min-width: 28px; font-size: 18px; }
    .sidebar .nav-links li a:hover, .sidebar .nav-links li a.active { background: var(--primary); color: #fff; }
    .sidebar.collapsed .sidebar-text, .sidebar.collapsed .logo_name { display: none; }

    .main-content {
      margin-left: 250px;
      flex-grow: 1;
      padding: 2rem;
      transition: all 0.3s ease;
    }
    .sidebar.collapsed + .main-content { margin-left: 78px; }

    /* Page Header */
    .page-header {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      margin-bottom: 1.25rem;
    }
    .page-title {
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .page-title h3 {
      margin: 0;
      font-weight: 700;
      letter-spacing: 0.2px;
    }

    /* Toolbar */
    .toolbar {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      align-items: center;
    }
    .searchbar {
      position: relative;
      min-width: 260px;
    }
    .searchbar input {
      padding-left: 38px;
      background: var(--card-bg);
      border: 1px solid var(--border);
      color: var(--text-color);
    }
    .searchbar .bi-search {
      position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--muted);
    }

    /* User Grid */
    .users-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
      gap: 18px;
    }
    .user-card {
      background: var(--card-bg);
      border: 1px solid var(--border);
      border-radius: 14px;
      padding: 16px;
      box-shadow: 0 6px 14px rgba(0,0,0,0.05);
      transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
      cursor: pointer;
      display: flex;
      flex-direction: column;
      gap: 10px;
    }
    .user-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 24px rgba(0,0,0,0.08);
      border-color: var(--primary);
    }
    .user-head {
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .avatar {
      width: 44px;
      height: 44px;
      border-radius: 50%;
      display: grid;
      place-items: center;
      background: linear-gradient(135deg, var(--accent), #ffffff00);
      color: var(--primary);
      font-weight: 700;
      border: 1px dashed var(--primary);
      user-select: none;
    }
    .user-info .name {
      margin: 0;
      font-weight: 700;
      font-size: 1.02rem;
    }
    .user-info .meta {
      color: var(--muted);
      font-size: 0.9rem;
    }
    .pill {
      background: rgba(108,99,255,0.12);
      color: var(--primary);
      padding: 2px 8px;
      border-radius: 999px;
      font-size: 12px;
      font-weight: 600;
    }
    .user-foot {
      display: flex;
      justify-content: space-between;
      align-items: center;
      border-top: 1px dashed var(--border);
      padding-top: 10px;
    }
    .view-link {
      color: var(--primary);
      display: inline-flex;
      align-items: center;
      gap: 6px;
      text-decoration: none;
      font-weight: 600;
    }
    .view-link:hover { text-decoration: underline; }

    .no-results {
      text-align: center;
      color: var(--muted);
      padding: 2rem 0;
      display: none;
    }

    /* Modal styles */
    .modal-content {
      background: var(--card-bg);
      color: var(--text-color);
      border: 1px solid var(--border);
    }
    .modal-header {
      border-bottom-color: var(--border);
    }
    .modal-footer {
      border-top-color: var(--border);
    }
    .journal-summary {
      color: var(--muted);
      font-size: 0.92rem;
    }
    .timeline {
      position: relative;
      margin-left: 10px;
      padding-left: 24px;
    }
    .timeline::before {
      content: "";
      position: absolute;
      left: 10px;
      top: 0;
      bottom: 0;
      width: 2px;
      background: var(--border);
    }
    .timeline-item {
      position: relative;
      margin-bottom: 18px;
      padding-left: 8px;
    }
    .timeline-item::before {
      content: "";
      position: absolute;
      left: -2px;
      top: 6px;
      width: 10px;
      height: 10px;
      background: var(--primary);
      border-radius: 50%;
      box-shadow: 0 0 0 4px rgba(108,99,255,0.15);
    }
    .entry-card {
      background: var(--soft);
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 12px 14px;
    }
    .entry-card h6 {
      margin: 0 0 6px 0;
      font-weight: 700;
    }
    .entry-meta {
      color: var(--muted);
      font-size: 0.85rem;
    }
    .entry-content {
      margin-top: 8px;
      white-space: pre-wrap;
      line-height: 1.5;
    }

    /* Back button spacing on smaller screens */
    @media (max-width: 575px) {
      .toolbar { width: 100%; }
      .searchbar { flex: 1; min-width: 0; }
    }
  /* Mobile: off-canvas sidebar and main adjustments */
    .sidebar-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,0.25); backdrop-filter: blur(2px); z-index: 90; display: none; }
    .sidebar-backdrop.show { display: block; }
    body.no-scroll { overflow: hidden; }

    @media (max-width: 992px) {
      .sidebar { transform: translateX(-100%); width: 260px; left: 0; top: 0; }
      .sidebar.open { transform: translateX(0); }
      .sidebar.collapsed { width: 260px; } /* ignore collapsed on mobile */
      .main-content { margin-left: 0 !important; padding: 1rem; }
      .searchbar { min-width: 0; flex: 1; }
    }
  </style>
</head>
<body>

<div class="wrapper">
  <!-- Sidebar (unchanged) -->
  <div id="sidebar" class="sidebar">
    <div class="logo-details">
      <span class="logo_name sidebar-text">MindCare</span>
      <button class="toggle-btn" onclick="toggleSidebar()">
        <i id="sidebarIcon" class="bi bi-list"></i>
      </button>
    </div>
    <ul class="nav-links">
      <li>
        <a href="../dashboard/admin_dashboard.php">
          <i class="bi bi-speedometer2"></i>
          <span class="sidebar-text">Dashboard</span>
        </a>
      </li>
      <li>
        <a href="view_journals.php" class="active">
          <i class="bi bi-journal-text"></i>
          <span class="sidebar-text">Journals</span>
        </a>
      </li>
      <li>
        <a href="manage_users.php">
          <i class="bi bi-people"></i>
          <span class="sidebar-text">Manage Users</span>
        </a>
      </li>
      <li>
        <a href="../auth/logout.php">
          <i class="bi bi-box-arrow-right"></i>
          <span class="sidebar-text">Logout</span>
        </a>
      </li>
      <li>
        <a href="#" onclick="toggleDarkMode()">
          <i class="bi bi-moon-stars"></i>
          <span class="sidebar-text">Dark Mode</span>
        </a>
      </li>
    </ul>
  </div>
  <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

  <!-- Main Content -->
  <div class="main-content">
    <div class="page-header">
      <button class="btn btn-light border d-lg-none" onclick="toggleSidebar()" aria-label="Open menu"><i class="bi bi-list"></i></button>
      <div class="page-title">
        <h3><i class="bi bi-journal-text"></i> Journal Logs</h3>
      </div>
      <div class="toolbar">
        <a href="../dashboard/admin_dashboard.php" class="btn btn-outline-secondary">
          <i class="bi bi-arrow-left"></i> Back
        </a>
        <div class="searchbar">
          <i class="bi bi-search"></i>
          <input id="userSearch" type="text" class="form-control" placeholder="Search user..." oninput="filterUsers()" />
        </div>
      </div>
    </div>

    <div class="mb-3">
      <span class="journal-summary">
        <i class="bi bi-people"></i>
        Showing <?= (int)$users->num_rows ?> users with journals
      </span>
    </div>

    <div id="usersGrid" class="users-grid">
      <?php while ($row = $users->fetch_assoc()): ?>
        <?php
          $initials = htmlspecialchars(get_initials($row['name']));
          $displayName = htmlspecialchars($row['name']);
          $count = (int)$row['journal_count'];
          $last = $row['last_entry'] ? date("M j, Y g:i A", strtotime($row['last_entry'])) : '—';
        ?>
        <div class="user-card" data-user-id="<?= (int)$row['id'] ?>" data-user-name="<?= $displayName ?>" onclick="openUserModal(this)">
          <div class="user-head">
            <div class="avatar"><?= $initials ?></div>
            <div class="user-info">
              <p class="name"><?= $displayName ?></p>
              <div class="meta">
                <span class="pill"><i class="bi bi-journal-bookmark"></i> <?= $count ?> <?= $count === 1 ? 'entry' : 'entries' ?></span>
                <span class="ms-2"><i class="bi bi-clock"></i> Last: <?= htmlspecialchars($last) ?></span>
              </div>
            </div>
          </div>
          <div class="user-foot">
            <span class="text-muted small">Click to view journals</span>
            <span class="view-link">View <i class="bi bi-arrow-right-short"></i></span>
          </div>
        </div>
      <?php endwhile; ?>
    </div>

    <div id="noResults" class="no-results">
      <i class="bi bi-emoji-neutral"></i> No users found.
    </div>
  </div>
</div>

<!-- Modal: User Journals -->
<div class="modal fade" id="userJournalsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title">
            <i class="bi bi-person-lines-fill"></i>
            <span id="modalUserName">User</span>
          </h5>
          <div class="journal-summary">
            <span id="modalEntryCount">0</span> total entries
          </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="modalLoading" class="text-center py-5">
          <div class="spinner-border text-primary" role="status"></div>
          <div class="mt-2 text-muted">Loading journals...</div>
        </div>
        <div id="modalError" class="alert alert-danger d-none" role="alert"></div>
        <div id="modalContent" class="d-none">
          <div class="timeline" id="timelineContainer">
            <!-- Timeline entries injected here -->
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button id="copyAllBtn" type="button" class="btn btn-outline-secondary btn-sm" onclick="copyAllEntries()" title="Copy all entries">
          <i class="bi bi-clipboard"></i> Copy All
        </button>
        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Done</button>
      </div>
    </div>
  </div>
</div>

<script>
  function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    const isMobile = window.matchMedia('(max-width: 992px)').matches;
    if (isMobile) {
      const willOpen = !sidebar.classList.contains('open');
      sidebar.classList.toggle('open', willOpen);
      document.body.classList.toggle('no-scroll', willOpen);
      if (backdrop) backdrop.classList.toggle('show', willOpen);
    } else {
      sidebar.classList.toggle('collapsed');
    }
  }

  // Backdrop interactions and responsive reset on resize
  (function(){
    const backdrop = document.getElementById('sidebarBackdrop');
    if (backdrop) {
      backdrop.addEventListener('click', () => {
        const sb = document.getElementById('sidebar');
        sb.classList.remove('open');
        document.body.classList.remove('no-scroll');
        backdrop.classList.remove('show');
      });
    }
    window.addEventListener('resize', () => {
      const sb = document.getElementById('sidebar');
      const isMobile = window.matchMedia('(max-width: 992px)').matches;
      if (!isMobile) {
        sb.classList.remove('open');
        document.body.classList.remove('no-scroll');
        if (backdrop) backdrop.classList.remove('show');
      }
    });
  })();

  function toggleDarkMode() {
    document.body.classList.toggle('dark-mode');
    localStorage.setItem('dark-mode', document.body.classList.contains('dark-mode') ? 'on' : 'off');
  }

  if (localStorage.getItem('dark-mode') === 'on') {
    document.body.classList.add('dark-mode');
  }

  // Bootstrap modal instance
  let modalInstance = null;

  function openUserModal(el) {
    const userId = el.dataset.userId;
    const userName = el.dataset.userName || 'User';

    document.getElementById('modalUserName').textContent = userName;
    document.getElementById('modalEntryCount').textContent = '0';
    document.getElementById('modalError').classList.add('d-none');
    document.getElementById('modalContent').classList.add('d-none');
    document.getElementById('modalLoading').classList.remove('d-none');

    if (!modalInstance) {
      modalInstance = new bootstrap.Modal(document.getElementById('userJournalsModal'));
    }
    modalInstance.show();

    fetchUserJournals(userId);
  }

  function fetchUserJournals(userId) {
    const url = window.location.pathname + '?ajax=user_journals&user_id=' + encodeURIComponent(userId);
    fetch(url, { credentials: 'same-origin' })
      .then(r => r.json())
      .then(data => {
        const loading = document.getElementById('modalLoading');
        const error = document.getElementById('modalError');
        const content = document.getElementById('modalContent');
        const container = document.getElementById('timelineContainer');

        if (!data.ok) {
          loading.classList.add('d-none');
          content.classList.add('d-none');
          error.textContent = data.message || 'Failed to load journals.';
          error.classList.remove('d-none');
          return;
        }

        // Render list
        container.innerHTML = '';
        const journals = Array.isArray(data.journals) ? data.journals : [];
        document.getElementById('modalEntryCount').textContent = journals.length;

        if (journals.length === 0) {
          container.innerHTML = '<div class="text-muted">No journal entries found for this user.</div>';
        } else {
          journals.forEach(j => {
            const item = document.createElement('div');
            item.className = 'timeline-item';
            item.innerHTML = `
              <div class="entry-card">
                <div class="entry-meta"><i class="bi bi-clock"></i> ${formatDate(j.created_at)} ${j.is_locked ? ' • Private' : ''}</div>
                <h6>${escapeHtml(j.title || '(Untitled)')}</h6>
                <div class="entry-content">${escapeHtml(j.content || '').replace(/\n/g, '<br>')}</div>
              </div>
            `;
            container.appendChild(item);
          });
        }

        document.getElementById('modalError').classList.add('d-none');
        loading.classList.add('d-none');
        content.classList.remove('d-none');
      })
      .catch(() => {
        const loading = document.getElementById('modalLoading');
        const error = document.getElementById('modalError');
        loading.classList.add('d-none');
        error.textContent = 'Network error while loading journals.';
        error.classList.remove('d-none');
      });
  }

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function formatDate(iso) {
    try {
      const d = new Date(iso.replace(' ', 'T'));
      const opts = { year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' };
      return d.toLocaleString(undefined, opts);
    } catch {
      return iso;
    }
  }

  function filterUsers() {
    const q = (document.getElementById('userSearch').value || '').toLowerCase().trim();
    const cards = document.querySelectorAll('.user-card');
    let shown = 0;
    cards.forEach(card => {
      const name = (card.dataset.userName || '').toLowerCase();
      const match = name.includes(q);
      card.style.display = match ? '' : 'none';
      if (match) shown++;
    });
    document.getElementById('noResults').style.display = shown === 0 ? 'block' : 'none';
  }

  function copyAllEntries() {
    const container = document.getElementById('timelineContainer');
    if (!container) return;
    // Build plain text
    let text = '';
    container.querySelectorAll('.timeline-item').forEach((item, idx) => {
      const when = item.querySelector('.entry-meta')?.innerText || '';
      const title = item.querySelector('h6')?.innerText || '';
      const content = item.querySelector('.entry-content')?.innerText || '';
      text += `#${idx + 1} • ${when}\n${title}\n${content}\n\n`;
    });
    if (!navigator.clipboard) {
      // Fallback
      const ta = document.createElement('textarea');
      ta.value = text;
      document.body.appendChild(ta);
      ta.select();
      document.execCommand('copy');
      document.body.removeChild(ta);
      return;
    }
    navigator.clipboard.writeText(text);
  }
</script>

<!-- Bootstrap JS bundle for modal functionality -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>