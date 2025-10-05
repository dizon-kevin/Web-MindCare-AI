<?php
require_once '../config/dbcon.php';

// Validate and normalize ID
$id_get = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id_post = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
  $name = trim($_POST['name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $role = $_POST['role'] ?? '';

  if (!$id_post) {
    header("Location: manage_users.php?error=invalid_id");
    exit();
  }

  $stmt = $conn->prepare("UPDATE users SET name=?, email=?, role=? WHERE id=?");
  $stmt->bind_param("sssi", $name, $email, $role, $id_post);
  $stmt->execute();
  header("Location: manage_users.php?updated=1");
  exit();
}

// Initial page load: ensure we have a valid ID
if (!$id_get) {
  header("Location: manage_users.php?error=invalid_id");
  exit();
}

$stmt = $conn->prepare("SELECT id, name, email, role FROM users WHERE id=?");
$stmt->bind_param("i", $id_get);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
  header("Location: manage_users.php?error=user_not_found");
  exit();
}

// Helper: initials for avatar badge
function user_initials(string $name): string {
  $name = trim($name);
  if ($name === '') return 'U';
  $parts = preg_split('/\s+/', $name);
  $first = mb_substr($parts[0], 0, 1, 'UTF-8');
  $last = isset($parts[1]) ? mb_substr($parts[count($parts)-1], 0, 1, 'UTF-8') : '';
  return mb_strtoupper($first . $last, 'UTF-8');
}

$esc = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin • Edit User</title>
  <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <style>
    :root {
      --brand: #2d6cdf;         /* Primary brand accent */
      --brand-600: #1f54b5;
      --muted: #6b7280;         /* Neutral text */
      --surface: #ffffff;       /* Card surface */
      --bg: #f7f8fb;           /* Page background */
      --ring: rgba(45,108,223,.25);
      --radius: 12px;
    }

    * { box-sizing: border-box; }
    html, body { height: 100%; }
    body {
      font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
      background:
        radial-gradient(1200px 600px at 10% -10%, rgba(45,108,223,.08), transparent 60%),
        radial-gradient(800px 400px at 110% 10%, rgba(45,108,223,.06), transparent 60%),
        var(--bg);
      color: #111827;
      line-height: 1.5;
    }

    .page-wrap {
      min-height: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 32px 16px;
    }

    .card {
      border: 0;
      border-radius: var(--radius);
      box-shadow:
        0 10px 30px rgba(17, 24, 39, .06),
        0 4px 10px rgba(17, 24, 39, .04);
      overflow: hidden;
      background: var(--surface);
    }

    .card-header {
      background: linear-gradient(90deg, rgba(45,108,223,.08), transparent 60%), var(--surface);
      border-bottom: 1px solid #edf0f7;
      padding: 20px 24px;
    }

    .header-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
    }

    .title {
      display: flex;
      align-items: center;
      gap: 12px;
      margin: 0;
      font-weight: 700;
      letter-spacing: -.01em;
    }

    .title-badge {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 40px; height: 40px;
      border-radius: 10px;
      background: linear-gradient(180deg, var(--brand), var(--brand-600));
      color: #fff;
      font-weight: 700;
      font-size: 16px;
      box-shadow: 0 6px 16px rgba(45,108,223,.35);
      user-select: none;
    }

    .subtitle {
      margin: 2px 0 0;
      color: var(--muted);
      font-size: 14px;
    }

    .breadcrumb {
      margin: 0;
      padding: 0;
      background: transparent;
      font-size: 13px;
    }
    .breadcrumb a {
      color: var(--brand-600);
      text-decoration: none;
    }
    .breadcrumb .breadcrumb-item + .breadcrumb-item::before {
      content: '›';
    }

    .card-body { padding: 24px; }

    .form-label {
      font-weight: 600;
      margin-bottom: 6px;
    }
    .form-control, .form-select {
      padding: 10px 12px;
      border-radius: 10px;
      border-color: #e5e7eb;
    }
    .form-control:focus, .form-select:focus {
      border-color: var(--brand);
      box-shadow: 0 0 0 4px var(--ring);
    }

    .helper {
      font-size: 12px;
      color: var(--muted);
    }

    /* Align form fields consistently (pantay-pantay) */
    .row.g-3 > [class*="col-"] {
      display: flex;
      flex-direction: column;
      justify-content: flex-start;
    }

    .actions {
      display: flex;
      gap: 12px;
      justify-content: flex-end;
      padding-top: 4px;
    }
    .btn-primary {
      background: var(--brand);
      border-color: var(--brand);
      font-weight: 600;
    }
    .btn-primary:hover {
      background: var(--brand-600);
      border-color: var(--brand-600);
    }

    .btn-outline-secondary {
      border-color: #d1d5db;
      color: #374151;
      font-weight: 600;
    }

    @media (max-width: 575.98px) {
      .card-body { padding: 20px; }
      .card-header { padding: 18px 20px; }
    }
  </style>
</head>
<body>
  <div class="page-wrap">
    <div class="container">
      <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-6">
          <div class="card">
            <div class="card-header">
              <div class="header-row">
                <div>
                  <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="manage_users.php">Users</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Edit</li>
                  </ol>
                  <h1 class="title">
                    <span class="title-badge" aria-hidden="true"><?= $esc(user_initials($user['name'])) ?></span>
                    Edit User
                  </h1>
                  <p class="subtitle">Update the user’s profile and role. Keep everything neat and accurate.</p>
                </div>
              </div>
            </div>
            <div class="card-body">
              <form method="post" class="needs-validation" novalidate>
                <input type="hidden" name="id" value="<?= $esc($user['id']) ?>">
                <div class="row g-3">
                  <div class="col-12">
                    <label for="name" class="form-label">Full Name</label>
                    <input
                      type="text"
                      id="name"
                      name="name"
                      class="form-control"
                      value="<?= $esc($user['name']) ?>"
                      placeholder="e.g., Juan Dela Cruz"
                      required
                      autocomplete="name"
                      autofocus
                    />
                    <div class="invalid-feedback">Please enter the user's full name.</div>
                  </div>

                  <div class="col-12">
                    <label for="email" class="form-label">Email Address</label>
                    <input
                      type="email"
                      id="email"
                      name="email"
                      class="form-control"
                      value="<?= $esc($user['email']) ?>"
                      placeholder="name@company.com"
                      required
                      autocomplete="email"
                      inputmode="email"
                    />
                    <div class="invalid-feedback">Please provide a valid email address.</div>
                  </div>

                  <div class="col-12">
                    <label for="role" class="form-label">Role</label>
                    <select id="role" name="role" class="form-select" required>
                      <option value="" disabled>Choose a role</option>
                      <option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>User</option>
                      <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                    </select>
                    <div class="invalid-feedback">Please select a role for this user.</div>
                  </div>

                  <div class="col-12">
                    <div class="actions">
                      <a href="manage_users.php" class="btn btn-outline-secondary">Cancel</a>
                      <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                  </div>
                </div>
              </form>
            </div> <!-- /card-body -->
          </div> <!-- /card -->
        </div>
      </div>
    </div>
  </div>

  <script>
    // Client-side validation
    (function () {
      const form = document.querySelector('.needs-validation');
      form.addEventListener('submit', function (event) {
        if (!form.checkValidity()) {
          event.preventDefault();
          event.stopPropagation();
        }
        form.classList.add('was-validated');
      }, false);
    })();
  </script>
</body>
</html>