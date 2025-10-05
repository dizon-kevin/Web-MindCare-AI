<?php
require_once '../config/dbcon.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = $_POST['name'];
  $email = $_POST['email'];
  $role = $_POST['role'];
  $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

  $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
  $stmt->bind_param("ssss", $name, $email, $password, $role);
  $stmt->execute();

  header("Location: manage_users.php");
  exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin • Add User</title>
  <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <style>
    :root {
      --brand: #2d6cdf;        /* Primary brand accent */
      --brand-600: #1f54b5;
      --muted: #6b7280;        /* Neutral text */
      --surface: #ffffff;      /* Card surface */
      --bg: #f7f8fb;          /* Page background */
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
      width: 34px; height: 34px;
      border-radius: 8px;
      background: linear-gradient(180deg, var(--brand), var(--brand-600));
      color: #fff;
      font-weight: 700;
      font-size: 18px;
      box-shadow: 0 6px 16px rgba(45,108,223,.35);
      user-select: none;
    }
    .subtitle {
      margin: 2px 0 0;
      color: var(--muted);
      font-size: 14px;
    }

    .card-body {
      padding: 24px;
    }

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
    .input-group .btn {
      border-radius: 0 10px 10px 0;
      border-color: #e5e7eb;
    }
    .input-group .btn:focus {
      box-shadow: 0 0 0 4px var(--ring);
      border-color: var(--brand);
    }

    .helper {
      font-size: 12px;
      color: var(--muted);
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

    /* Align form fields consistently (pantay-pantay) */
    .row.g-3 > [class*="col-"] {
      display: flex;
      flex-direction: column;
      justify-content: flex-start;
    }

    /* Invalid feedback spacing fix inside grid */
    .invalid-feedback {
      margin-top: 4px;
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
              <h1 class="title">
                <span class="title-badge">+</span>
                Add New User
              </h1>
              <p class="subtitle">Create a new account with a role and secure password.</p>
            </div>
            <div class="card-body">
              <form method="post" class="needs-validation" novalidate>
                <div class="row g-3">
                  <div class="col-12">
                    <label for="name" class="form-label">Full Name</label>
                    <input
                      type="text"
                      id="name"
                      name="name"
                      class="form-control"
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
                      placeholder="name@company.com"
                      required
                      autocomplete="email"
                      inputmode="email"
                    />
                    <div class="invalid-feedback">Please provide a valid email address.</div>
                  </div>

                  <div class="col-12">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                      <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-control"
                        placeholder="Enter a secure password"
                        minlength="8"
                        required
                        autocomplete="new-password"
                        aria-describedby="passwordHelp"
                      />
                      <button class="btn btn-outline-secondary" type="button" id="togglePassword" aria-label="Toggle password visibility">
                        <!-- Inline eye icon (no external dependency) -->
                        <svg id="eyeIcon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                          <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8z"/>
                          <path d="M8 5.5a2.5 2.5 0 1 1 0 5 2.5 2.5 0 0 1 0-5z" fill="#fff"/>
                        </svg>
                      </button>
                    </div>
                    <div id="passwordHelp" class="helper">Use at least 8 characters. Mix letters, numbers, and symbols for stronger security.</div>
                    <div class="invalid-feedback">Password must be at least 8 characters.</div>
                  </div>

                  <div class="col-12">
                    <label for="role" class="form-label">Role</label>
                    <select id="role" name="role" class="form-select" required>
                      <option value="" selected disabled>Choose a role</option>
                      <option value="user">User</option>
                      <option value="admin">Admin</option>
                    </select>
                    <div class="invalid-feedback">Please select a role for this user.</div>
                  </div>

                  <div class="col-12">
                    <div class="actions">
                      <a href="manage_users.php" class="btn btn-outline-secondary">Cancel</a>
                      <button type="submit" class="btn btn-primary">Add User</button>
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
    // Bootstrap-like client-side validation
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

    // Password visibility toggle
    (function () {
      const toggleBtn = document.getElementById('togglePassword');
      const pwdInput = document.getElementById('password');
      const icon = document.getElementById('eyeIcon');

      toggleBtn.addEventListener('click', () => {
        const isHidden = pwdInput.getAttribute('type') === 'password';
        pwdInput.setAttribute('type', isHidden ? 'text' : 'password');

        // Swap icon fill (simple visual cue)
        if (isHidden) {
          icon.querySelector('path').setAttribute('fill', '#e5e7eb');
        } else {
          icon.querySelector('path').setAttribute('fill', 'currentColor');
        }
      });
    })();
  </script>
</body>
</html>