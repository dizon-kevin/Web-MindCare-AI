<?php
session_start();
require_once '../config/dbcon.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
  header("Location: ../index.php");
  exit();
}

$name = $_SESSION['name'] ?? 'Admin';
$guest = false;
$mode = 'admin';
$parts = explode(" ", $name);
$initials = strtoupper(substr($parts[0], 0, 1) . ($parts[1] ?? substr($parts[0], 1, 1)));

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function e($str) { return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8'); }

// Flash message helpers
function set_flash($type, $message) {
  $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}
function get_flash() {
  if (isset($_SESSION['flash'])) {
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $f;
  }
  return null;
}

// Filters and pagination
$q = trim($_GET['q'] ?? '');
$roleFilter = trim($_GET['role'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = (int)($_GET['per_page'] ?? 10);
if ($perPage < 5) $perPage = 5;
if ($perPage > 50) $perPage = 50;
$offset = ($page - 1) * $perPage;

// Build WHERE clause
$where = [];
$params = [];
$types = '';

if ($q !== '') {
  $where[] = "(name LIKE CONCAT('%', ?, '%') OR email LIKE CONCAT('%', ?, '%'))";
  $params[] = $q;
  $params[] = $q;
  $types .= 'ss';
}
if ($roleFilter !== '' && in_array($roleFilter, ['admin', 'user'], true)) {
  $where[] = "role = ?";
  $params[] = $roleFilter;
  $types .= 's';
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// Count total
$total = 0;
$countSql = "SELECT COUNT(*) AS cnt FROM users $whereSql";
if ($stmt = $conn->prepare($countSql)) {
  if ($types) $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $res = $stmt->get_result();
  $total = (int)($res->fetch_assoc()['cnt'] ?? 0);
  $stmt->close();
}
$totalPages = max(1, (int)ceil($total / $perPage));
if ($page > $totalPages) { $page = $totalPages; $offset = ($page - 1) * $perPage; }

// Fetch page
$listSql = "SELECT id, name, email, role FROM users $whereSql ORDER BY id DESC LIMIT ? OFFSET ?";
$params2 = $params;
$types2 = $types . 'ii';
$params2[] = $perPage;
$params2[] = $offset;

$users = [];
if ($stmt = $conn->prepare($listSql)) {
  // For LIMIT/OFFSET, bind as integers
  if ($types) {
    $stmt->bind_param($types2, ...$params2);
  } else {
    $stmt->bind_param('ii', $perPage, $offset);
  }
  $stmt->execute();
  $res = $stmt->get_result();
  while ($row = $res->fetch_assoc()) $users[] = $row;
  $stmt->close();
}

$flash = get_flash();
?>
<!DOCTYPE html>
<html>
<head>
  <title>Manage Users</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    :root {
      --main-bg: #f6f7fb;
      --sidebar-bg: #fff;
      --card-bg: #ffffff;
      --text-color: #212529;
      --muted: #6c757d;
      --primary: #6C63FF;
      --primary-600: #5a53de;
      --success: #22c55e;
      --danger: #ef4444;
      --warning: #f59e0b;
      --border: #e9ecef;
    }
    body {
      background: var(--main-bg);
      font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
      color: var(--text-color);
      transition: background 0.3s ease, color 0.3s ease;
    }
    .dark-mode {
      --main-bg: #111218;
      --sidebar-bg: #171821;
      --card-bg: #1C1F2A;
      --text-color: #eaeef5;
      --muted: #a0a7b4;
      --border: #2a2f3b;
    }
    .wrapper { display: flex; min-height: 100vh; transition: all 0.3s ease; }

    /* Sidebar (unchanged) */
    .sidebar {
      position: fixed; height: 100vh; width: 250px; background: var(--sidebar-bg);
      transition: all 0.3s ease; box-shadow: 0 0 20px rgba(0,0,0,0.05); z-index: 99;
    }
    .sidebar.collapsed { width: 78px; }
    .sidebar .logo-details { height: 60px; display: flex; align-items: center; justify-content: space-between; padding: 0 15px; border-bottom: 1px solid var(--border); }
    .sidebar .logo_name { font-size: 20px; font-weight: 600; color: var(--text-color); transition: 0.3s ease; }
    .sidebar .toggle-btn { font-size: 24px; background: none; border: none; color: var(--text-color); cursor: pointer; }
    .sidebar .nav-links { margin-top: 20px; list-style: none; padding-left: 0; }
    .sidebar .nav-links li { width: 100%; margin: 10px 0; }
    .sidebar .nav-links li a { display: flex; align-items: center; text-decoration: none; padding: 12px 20px; color: var(--text-color); font-size: 16px; border-radius: 8px; transition: all 0.3s ease; }
    .sidebar .nav-links li a i { min-width: 28px; font-size: 18px; }
    .sidebar .nav-links li a:hover, .sidebar .nav-links li a.active { background: var(--primary); color: #fff; }
    .sidebar.collapsed .sidebar-text, .sidebar.collapsed .logo_name { display: none; }

    /* Main Content */
    .main-content {
      margin-left: 250px; flex-grow: 1; padding: 2rem; transition: all 0.3s ease;
    }
    .sidebar.collapsed + .main-content { margin-left: 78px; }

    /* Modern page header */
    .page-header {
      display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: 1.25rem;
    }
    .page-title {
      font-weight: 700; letter-spacing: -0.2px; display: flex; align-items: center; gap: .6rem;
    }
    .page-title .badge-count {
      background: rgba(108, 99, 255, .1); color: var(--primary); border: 1px solid rgba(108,99,255,.2);
      padding: .25rem .6rem; border-radius: 999px; font-size: .8rem;
    }

    /* Card */
    .elevated-card {
      background: var(--card-bg); border: 1px solid var(--border);
      border-radius: 16px; box-shadow: 0 6px 18px rgba(0,0,0,.06);
    }
    .card-header-bar {
      padding: 1rem 1.25rem; border-bottom: 1px solid var(--border);
      display: grid; grid-template-columns: 1fr auto; gap: 1rem; align-items: center;
    }
    .toolbar {
      display: grid; grid-template-columns: 1fr 140px 120px; gap: .75rem;
    }
    .search-input {
      position: relative;
    }
    .search-input input {
      padding-left: 2.2rem;
    }
    .search-input .bi-search {
      position: absolute; left: .75rem; top: 50%; transform: translateY(-50%); color: var(--muted);
    }
    .chip {
      border: 1px solid var(--border); background: transparent; color: var(--text-color);
      padding: .5rem .75rem; border-radius: 999px; display: inline-flex; align-items: center; gap: .5rem;
    }
    .chip.active {
      background: rgba(108, 99, 255, .1); border-color: rgba(108, 99, 255, .35); color: var(--primary);
    }

    /* Table styling */
    .table-wrap { overflow-x: auto; }
    table.user-table { margin: 0; }
    .user-table thead th {
      background: linear-gradient(180deg, rgba(0,0,0,.02), transparent);
      color: var(--muted); font-weight: 600; font-size: .9rem; letter-spacing: .02em; border-bottom: 1px solid var(--border);
      position: sticky; top: 0; z-index: 1;
    }
    .user-row:hover { background: rgba(108, 99, 255, .06); }
    .user-cell-muted { color: var(--muted); }
    .avatar {
      width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, #6C63FF, #9b8fff);
      color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700;
      box-shadow: 0 2px 8px rgba(108, 99, 255, .35);
    }
    .role-badge {
      padding: .25rem .6rem; border-radius: 999px; font-size: .8rem; font-weight: 600;
    }
    .role-admin { background: rgba(239,68,68,.12); color: #ef4444; border: 1px solid rgba(239,68,68,.25); }
    .role-user { background: rgba(34,197,94,.12); color: #22c55e; border: 1px solid rgba(34,197,94,.25); }

    /* Pagination + per-page */
    .card-footer-bar {
      padding: .9rem 1.25rem; border-top: 1px solid var(--border);
      display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: .75rem;
    }

    /* Buttons */
    .btn-primary { background: var(--primary); border-color: var(--primary); }
    .btn-primary:hover { background: var(--primary-600); border-color: var(--primary-600); }

    /* Toast/alerts */
    .flash {
      border-radius: 12px; padding: .9rem 1rem; display: flex; align-items: center; gap: .6rem; border: 1px solid transparent;
    }
    .flash-success { background: rgba(34,197,94,.12); color: #22c55e; border-color: rgba(34,197,94,.25); }
    .flash-error { background: rgba(239,68,68,.12); color: #ef4444; border-color: rgba(239,68,68,.25); }

    @media (max-width: 768px) {
      .toolbar { grid-template-columns: 1fr; }
      .card-header-bar { grid-template-columns: 1fr; }
    }
  /* Mobile: off-canvas sidebar and main adjustments */
    .sidebar-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,0.25); backdrop-filter: blur(2px); z-index: 90; display: none; }
    .sidebar-backdrop.show { display: block; }
    body.no-scroll { overflow: hidden; }

    @media (max-width: 992px) {
      .sidebar { transform: translateX(-100%); width: 260px; left: 0; top: 0; }
      .sidebar.open { transform: translateX(0); }
      .sidebar.collapsed { width: 260px; } /* ignore collapsed width on mobile */
      .main-content { margin-left: 0 !important; padding: 1rem; }
    }

    /* Table usability on mobile */
    .user-table th, .user-table td { white-space: nowrap; }
    @media (max-width: 576px) {
      .user-table .btn-group { display: flex; flex-wrap: wrap; gap: .5rem; }
      .user-table .btn-group .btn { width: auto; }
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
          <i class="bi bi-house"></i>
          <span class="sidebar-text">Dashboard</span>
        </a>
      </li>
      <li>
        <a href="view_journals.php" class=>
          <i class="bi bi-journal-text"></i>
          <span class="sidebar-text">Journals</span>
        </a>
      </li>
      <li>
        <a href="manage_users.php" class="active">
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
      <div class="page-title h3 mb-0">
        <i class="bi bi-people"></i>
        <span>Manage Users</span>
        <span class="badge-count"><?php echo (int)$total; ?> total</span>
      </div>
      <div class="d-flex gap-2">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
          <i class="bi bi-plus-lg me-1"></i> New User
        </button>
      </div>
    </div>

    <?php if ($flash): ?>
      <div class="flash <?php echo $flash['type']==='success' ? 'flash-success' : 'flash-error'; ?> mb-3">
        <i class="bi <?php echo $flash['type']==='success' ? 'bi-check-circle' : 'bi-exclamation-triangle'; ?>"></i>
        <div><?php echo e($flash['message']); ?></div>
      </div>
    <?php endif; ?>

    <div class="elevated-card">
      <div class="card-header-bar">
        <form id="filtersForm" method="get" class="toolbar">
          <div class="search-input">
            <i class="bi bi-search"></i>
            <input type="text" class="form-control" name="q" value="<?php echo e($q); ?>" placeholder="Search by name or email" />
          </div>
          <select name="role" class="form-select" onchange="this.form.submit()">
            <option value="">All roles</option>
            <option value="admin" <?php echo $roleFilter==='admin'?'selected':''; ?>>Admin</option>
            <option value="user" <?php echo $roleFilter==='user'?'selected':''; ?>>User</option>
          </select>
          <select name="per_page" class="form-select" onchange="this.form.submit()">
            <option value="10" <?php echo $perPage===10?'selected':''; ?>>10 / page</option>
            <option value="25" <?php echo $perPage===25?'selected':''; ?>>25 / page</option>
            <option value="50" <?php echo $perPage===50?'selected':''; ?>>50 / page</option>
          </select>
          <input type="hidden" name="page" value="<?php echo (int)$page; ?>" />
        </form>
        <div class="text-end small text-muted">
          <?php
            $from = $total ? $offset + 1 : 0;
            $to = min($offset + $perPage, $total);
            echo $from . '-' . $to . ' of ' . $total;
          ?>
        </div>
      </div>

      <div class="table-wrap">
        <table class="table align-middle user-table">
          <thead>
            <tr>
              <th style="width: 70px;">ID</th>
              <th>User</th>
              <th>Email</th>
              <th style="width: 120px;">Role</th>
              <th style="width: 160px;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$users): ?>
              <tr>
                <td colspan="5" class="text-center py-5 user-cell-muted">
                  <i class="bi bi-search fs-4 d-block mb-2"></i>
                  No users found. Try adjusting your search or filters.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($users as $row): ?>
                <?php
                  $uin = strtoupper(substr($row['name'] ?: $row['email'], 0, 1));
                ?>
                <tr class="user-row">
                  <td class="user-cell-muted">#<?php echo (int)$row['id']; ?></td>
                  <td>
                    <div class="d-flex align-items-center gap-2">
                      <div class="avatar"><?php echo e($uin); ?></div>
                      <div>
                        <div class="fw-semibold"><?php echo e($row['name']); ?></div>
                        <div class="small text-muted"><?php echo e($row['email']); ?></div>
                      </div>
                    </div>
                  </td>
                  <td class="text-muted"><?php echo e($row['email']); ?></td>
                  <td>
                    <?php if ($row['role'] === 'admin'): ?>
                      <span class="role-badge role-admin">Admin</span>
                    <?php else: ?>
                      <span class="role-badge role-user">User</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <div class="btn-group">
                      <button type="button"
                              class="btn btn-sm btn-outline-secondary"
                              data-bs-toggle="modal"
                              data-bs-target="#editUserModal"
                              data-user-id="<?php echo (int)$row['id']; ?>"
                              data-user-name="<?php echo e($row['name']); ?>"
                              data-user-email="<?php echo e($row['email']); ?>"
                              data-user-role="<?php echo e($row['role']); ?>">
                        <i class="bi bi-pencil-square"></i> Edit
                      </button>
                      <button type="button"
                              class="btn btn-sm btn-outline-danger"
                              data-bs-toggle="modal"
                              data-bs-target="#confirmDeleteModal"
                              data-user-id="<?php echo (int)$row['id']; ?>"
                              data-user-name="<?php echo e($row['name']); ?>">
                        <i class="bi bi-trash"></i> Delete
                      </button>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <div class="card-footer-bar">
        <nav aria-label="Users pagination">
          <ul class="pagination mb-0">
            <?php
              $queryBase = $_GET;
              $renderPageLink = function($p, $label = null, $disabled = false, $active = false) use ($queryBase) {
                $q = $queryBase;
                $q['page'] = $p;
                $href = '?' . http_build_query($q);
                $cls = 'page-link';
                $liCls = 'page-item';
                if ($disabled) $liCls .= ' disabled';
                if ($active) $liCls .= ' active';
                $label = $label ?? $p;
                echo '<li class="'.$liCls.'"><a class="'.$cls.'" href="'.$href.'">'.$label.'</a></li>';
              };
            ?>
            <?php $renderPageLink(max(1, $page-1), '&laquo;', $page<=1); ?>
            <?php
              // Show limited window of pages
              $start = max(1, $page - 2);
              $end = min($totalPages, $page + 2);
              if ($start > 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
              for ($p=$start; $p <= $end; $p++) $renderPageLink($p, (string)$p, false, $p===$page);
              if ($end < $totalPages) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
            ?>
            <?php $renderPageLink(min($totalPages, $page+1), '&raquo;', $page>=$totalPages); ?>
          </ul>
        </nav>
        <div class="small text-muted">
          Showing <?php echo $from; ?> - <?php echo $to; ?> of <?php echo $total; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post" action="add_user.php" class="modal-content needs-validation" novalidate>
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Add New User</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label for="addName" class="form-label">Full Name</label>
          <input type="text" class="form-control" id="addName" name="name" required />
          <div class="invalid-feedback">Please enter the user's full name.</div>
        </div>
        <div class="mb-3">
          <label for="addEmail" class="form-label">Email Address</label>
          <input type="email" class="form-control" id="addEmail" name="email" required />
          <div class="invalid-feedback">Please provide a valid email address.</div>
        </div>
        <div class="mb-3">
          <label for="addPassword" class="form-label">Password</label>
          <input type="password" class="form-control" id="addPassword" name="password" minlength="8" required />
          <div class="invalid-feedback">Password must be at least 8 characters.</div>
        </div>
        <div class="mb-3">
          <label for="addRole" class="form-label">Role</label>
          <select class="form-select" id="addRole" name="role" required>
            <option value="" selected disabled>Choose a role</option>
            <option value="user">User</option>
            <option value="admin">Admin</option>
          </select>
          <div class="invalid-feedback">Please select a role.</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Add User</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post" action="edit_user.php" class="modal-content needs-validation" novalidate>
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit User</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id" id="editId" />
        <div class="mb-3">
          <label for="editName" class="form-label">Full Name</label>
          <input type="text" class="form-control" id="editName" name="name" required />
          <div class="invalid-feedback">Please enter the user's full name.</div>
        </div>
        <div class="mb-3">
          <label for="editEmail" class="form-label">Email Address</label>
          <input type="email" class="form-control" id="editEmail" name="email" required />
          <div class="invalid-feedback">Please provide a valid email address.</div>
        </div>
        <div class="mb-3">
          <label for="editRole" class="form-label">Role</label>
          <select class="form-select" id="editRole" name="role" required>
            <option value="" disabled>Choose a role</option>
            <option value="user">User</option>
            <option value="admin">Admin</option>
          </select>
          <div class="invalid-feedback">Please select a role.</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post" action="delete_user.php" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="confirmDeleteModalLabel"><i class="bi bi-exclamation-triangle text-danger me-2"></i>Confirm Deletion</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        Are you sure you want to delete <strong id="modalUserName">this user</strong>? This action cannot be undone.
        <input type="hidden" name="id" id="modalUserId" />
        <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>" />
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-danger"><i class="bi bi-trash me-1"></i> Delete</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
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

  // Submit search on Enter
  const filtersForm = document.getElementById('filtersForm');
  filtersForm.querySelector('input[name="q"]').addEventListener('keydown', (e) => {
    if (e.key === 'Enter') filtersForm.submit();
  });

  // Populate delete modal
  const delModal = document.getElementById('confirmDeleteModal');
  delModal.addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    const userId = button.getAttribute('data-user-id');
    const userName = button.getAttribute('data-user-name') || 'this user';
    delModal.querySelector('#modalUserId').value = userId;
    delModal.querySelector('#modalUserName').textContent = userName;
  });

  // Add User Modal behavior
  const addModal = document.getElementById('addUserModal');
  if (addModal) {
    addModal.addEventListener('shown.bs.modal', () => {
      const form = addModal.querySelector('form');
      if (form) {
        form.reset();
        form.classList.remove('was-validated');
      }
      const nameInput = addModal.querySelector('#addName');
      if (nameInput) nameInput.focus();
    });
  }

  // Edit User Modal population
  const editModal = document.getElementById('editUserModal');
  if (editModal) {
    editModal.addEventListener('show.bs.modal', (event) => {
      const btn = event.relatedTarget;
      if (!btn) return;
      const id = btn.getAttribute('data-user-id');
      const name = btn.getAttribute('data-user-name') || '';
      const email = btn.getAttribute('data-user-email') || '';
      const role = btn.getAttribute('data-user-role') || '';
      editModal.querySelector('#editId').value = id || '';
      editModal.querySelector('#editName').value = name;
      editModal.querySelector('#editEmail').value = email;
      const roleSelect = editModal.querySelector('#editRole');
      if (roleSelect) roleSelect.value = role === 'admin' ? 'admin' : 'user';
    });
  }

  // Form validation for modals
  (function () {
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach((form) => {
      form.addEventListener('submit', function (event) {
        if (!form.checkValidity()) {
          event.preventDefault();
          event.stopPropagation();
        }
        form.classList.add('was-validated');
      }, false);
    });
  })();
</script>
</body>
</html>