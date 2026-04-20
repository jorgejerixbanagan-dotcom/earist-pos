<?php
// ============================================================
// public/login.php
//
// WHAT THIS FILE DOES:
//   The login page for ALL four roles (Admin, Cashier, Faculty, Student).
//   The user selects their role, enters credentials, and submits.
//
// FLOW:
//   GET  request → show the login form
//   POST request → validate credentials → check email verified → log in → redirect
// ============================================================

require_once __DIR__ . '/../config/init.php';

// If already logged in, no need to be here
if (isLoggedIn()) {
  redirectByRole();
}

$error    = '';
$selected = $_GET['role'] ?? ROLE_STUDENT; // Pre-select role from URL if given

// ---- Handle form submission ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  verifyCsrf(); // Security check first

  $role       = $_POST['role']       ?? '';
  $identifier = sanitizeString($_POST['identifier'] ?? ''); // username or ID
  $password   = $_POST['password']   ?? '';

  // Basic validation
  if (empty($role) || empty($identifier) || empty($password)) {
    $error = 'Please fill in all fields.';
  } elseif (!in_array($role, [ROLE_ADMIN, ROLE_CASHIER, ROLE_FACULTY, ROLE_STUDENT], true)) {
    $error = 'Invalid role selected.';
  } elseif (!checkLoginAttempts($identifier)) {
    $error = 'Too many failed attempts. Please wait 15 minutes.';
  } else {
    // Look up the user in the correct table based on role
    $db   = Database::getInstance();
    $user = null;

    if ($role === ROLE_ADMIN) {
      $stmt = $db->prepare("SELECT * FROM admins WHERE username = ? LIMIT 1");
      $stmt->execute([$identifier]);
      $user = $stmt->fetch();
    } elseif ($role === ROLE_CASHIER) {
      $stmt = $db->prepare("SELECT * FROM cashiers WHERE username = ? AND is_active = 1 LIMIT 1");
      $stmt->execute([$identifier]);
      $user = $stmt->fetch();
    } elseif ($role === ROLE_FACULTY) {
      $stmt = $db->prepare("SELECT * FROM faculty WHERE faculty_id_no = ? AND is_active = 1 LIMIT 1");
      $stmt->execute([$identifier]);
      $user = $stmt->fetch();
    } elseif ($role === ROLE_STUDENT) {
      $stmt = $db->prepare("SELECT * FROM students WHERE student_id_no = ? AND is_active = 1 LIMIT 1");
      $stmt->execute([$identifier]);
      $user = $stmt->fetch();
    }

    if ($user && password_verify($password, $user['password'])) {
      // Check email verification for students and faculty
      $emailField = $role === ROLE_FACULTY ? 'faculty_id_no' : 'student_id_no';

      if (in_array($role, [ROLE_STUDENT, ROLE_FACULTY])) {
        if (empty($user['email_verified']) || $user['email_verified'] != 1) {
          // Email not verified - redirect to verification
          $_SESSION['pending_verification'] = [
            'user_type' => $role,
            'user_id'   => $user['id'],
            'email'     => $user['email'],
            'purpose'   => 'verification'
          ];
          flash('global', 'Please verify your email address to continue.', 'warning');
          redirect(APP_URL . '/verify-email.php');
        }
      }

      // Password matches — log them in
      clearLoginAttempts($identifier);
      loginUser($user, $role);
      auditLog($role, $user['id'], 'login');
      redirectByRole();
    } else {
      // Wrong credentials
      recordFailedLogin($identifier);
      $error = 'Incorrect credentials. Please try again.';
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign In — <?= APP_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;0,9..40,800&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/../assets/css/variables.css">
  <style>
    *,
    *::before,
    *::after {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    :root {
      --land-bg: #faf8f5;
      --land-surface: #f2ede8;
      --land-card: #ffffff;
      --land-border: rgba(107, 62, 38, 0.12);
      --land-text: #1a1008;
      --land-muted: rgba(26, 16, 8, 0.50);
      --land-dim: rgba(26, 16, 8, 0.28);
    }

    /* Hide Microsoft Edge's native password reveal and clear icons */
    input::-ms-reveal,
    input::-ms-clear {
      display: none;
    }

    html {
      font-size: 16px;
    }

    body {
      font-family: 'DM Sans', system-ui, sans-serif;
      background: var(--land-bg);
      color: var(--land-text);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      -webkit-font-smoothing: antialiased;
    }

    a {
      color: inherit;
      text-decoration: none;
    }

    /* Grain overlay */
    body::before {
      content: '';
      position: fixed;
      inset: 0;
      background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.035'/%3E%3C/svg%3E");
      background-size: 200px 200px;
      pointer-events: none;
      z-index: 1000;
      opacity: 0.25;
    }

    /* Radial glow */
    body::after {
      content: '';
      position: fixed;
      top: 30%;
      left: 50%;
      transform: translate(-50%, -50%);
      width: 600px;
      height: 500px;
      background: radial-gradient(ellipse, rgba(192, 57, 43, 0.05) 0%, transparent 70%);
      pointer-events: none;
      z-index: 0;
    }

    /* ---- Navbar ---- */
    .nav {
      padding: 18px 40px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      position: relative;
      z-index: 10;
      border-bottom: 1px solid rgba(107, 62, 38, 0.12);
      background: rgba(250, 248, 245, 0.80);
      backdrop-filter: blur(12px);
    }

    .nav-logo {
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .nav-logo-icon {
      width: 32px;
      height: 32px;
      background: var(--primary-color);
      border-radius: var(--radius-full);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 13px;
      color: #fff;
      box-shadow: 0 3px 10px rgba(192, 57, 43, 0.38);
      flex-shrink: 0;
    }

    .nav-logo-icon img {
      width: 32px;
      height: 32px;
      object-fit: contain;
    }

    .nav-logo-name {
      font-size: 0.84rem;
      font-weight: 700;
      color: var(--land-text);
    }

    .nav-logo-sub {
      font-size: 0.60rem;
      color: var(--land-muted);
    }

    .nav-back {
      font-size: 0.78rem;
      font-weight: 500;
      color: var(--land-muted);
      display: inline-flex;
      align-items: center;
      gap: 7px;
      padding: 6px 12px;
      border-radius: 7px;
      border: 1px solid rgba(107, 62, 38, 0.15);
      transition: all 0.15s;
    }

    .nav-back:hover {
      color: var(--land-text);
      background: rgba(107, 62, 38, 0.06);
      border-color: rgba(107, 62, 38, 0.30);
    }

    /* ---- Main layout ---- */
    .page-wrap {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 48px 24px;
      position: relative;
      z-index: 1;
    }

    /* ---- Auth card ---- */
    .auth-card {
      width: 100%;
      max-width: 420px;
      background: var(--land-card);
      border: 1px solid rgba(107, 62, 38, 0.12);
      border-radius: 20px;
      box-shadow: 0 12px 48px rgba(107, 62, 38, 0.12);
      overflow: hidden;
      animation: cardIn 0.45s cubic-bezier(0.34, 1.1, 0.64, 1) both;
    }

    @keyframes cardIn {
      from {
        opacity: 0;
        transform: translateY(24px) scale(0.97);
      }

      to {
        opacity: 1;
        transform: translateY(0) scale(1);
      }
    }

    /* Card top */
    .auth-top {
      padding: 36px 36px 28px;
      text-align: center;
      border-bottom: 1px solid rgba(107, 62, 38, 0.12);
      position: relative;
      overflow: hidden;
    }

    .auth-top::before {
      content: '';
      position: absolute;
      top: -40px;
      left: 50%;
      transform: translateX(-50%);
      width: 200px;
      height: 200px;
      background: radial-gradient(circle, rgba(192, 57, 43, 0.07), transparent 70%);
      pointer-events: none;
    }

    .auth-logo {
      width: 52px;
      height: 52px;
      background: var(--primary-color);
      border-radius: var(--radius-full);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 22px;
      color: #fff;
      margin-bottom: 16px;
      box-shadow: 0 6px 20px rgba(192, 57, 43, 0.45);
      position: relative;
      z-index: 1;
    }

    .auth-logo img {
      width: 52px;
      height: 52px;
      object-fit: contain;
    }

    .auth-title {
      font-family: 'Instrument Serif', serif;
      font-size: 1.45rem;
      font-weight: 400;
      color: var(--land-text);
      letter-spacing: -0.01em;
      margin-bottom: 6px;
      position: relative;
      z-index: 1;
    }

    .auth-title em {
      font-style: italic;
      color: var(--primary-color);
    }

    .auth-sub {
      font-size: 0.76rem;
      color: var(--land-muted);
      font-weight: 300;
      position: relative;
      z-index: 1;
    }

    /* Card body */
    .auth-body {
      padding: 28px 32px 32px;
    }

    /* Alerts */
    .auth-alert {
      padding: 12px 16px;
      border-radius: 10px;
      font-size: 0.82rem;
      display: flex;
      align-items: flex-start;
      gap: 10px;
      margin-bottom: 20px;
      border: 1px solid transparent;
      line-height: 1.5;
    }

    .auth-alert i {
      flex-shrink: 0;
      margin-top: 1px;
    }

    .auth-alert-danger {
      background: rgba(239, 68, 68, 0.08);
      color: #fca5a5;
      border-color: rgba(239, 68, 68, 0.18);
    }

    .auth-alert-success {
      background: rgba(16, 185, 129, 0.07);
      color: #065f46;
      border-color: rgba(16, 185, 129, 0.20);
    }

    .auth-alert-warning {
      background: rgba(245, 158, 11, 0.07);
      color: #92400e;
      border-color: rgba(245, 158, 11, 0.20);
    }

    /* Role tabs */
    .role-label {
      font-size: 0.72rem;
      font-weight: 700;
      color: var(--land-muted);
      text-transform: uppercase;
      letter-spacing: 0.08em;
      margin-bottom: 10px;
    }

    .role-tabs {
      display: grid;
      grid-template-columns: 1fr 1fr 1fr;
      gap: 8px;
      margin-bottom: 24px;
    }

    .role-tab {
      padding: 10px 6px;
      border-radius: 10px;
      border: 1.5px solid var(--land-border);
      background: rgba(107, 62, 38, 0.04);
      cursor: pointer;
      font-size: 0.74rem;
      font-weight: 600;
      text-align: center;
      color: var(--land-muted);
      transition: all 0.15s;
      font-family: inherit;
    }

    .role-tab i {
      display: block;
      font-size: 16px;
      margin-bottom: 5px;
      opacity: 0.7;
    }

    .role-tab:hover:not(.active) {
      border-color: rgba(107, 62, 38, 0.25);
      color: var(--land-text);
      background: rgba(107, 62, 38, 0.07);
    }

    .role-tab.active {
      border-color: var(--primary-color);
      background: rgba(192, 57, 43, 0.12);
      color: var(--land-text);
      box-shadow: 0 0 0 1px rgba(192, 57, 43, 0.3);
    }

    .role-tab.active i {
      opacity: 1;
    }

    /* Form fields */
    .field-group {
      margin-bottom: 18px;
    }

    .field-label {
      display: block;
      font-size: 0.74rem;
      font-weight: 600;
      color: var(--land-muted);
      margin-bottom: 7px;
      letter-spacing: 0.01em;
    }

    .field-input {
      width: 100%;
      height: 42px;
      padding: 0 14px;
      background: rgba(107, 62, 38, 0.04);
      border: 1.5px solid rgba(107, 62, 38, 0.15);
      border-radius: 10px;
      font-size: 0.86rem;
      font-family: inherit;
      color: var(--land-text);
      outline: none;
      transition: border-color 0.15s, background 0.15s;
    }

    .field-input::placeholder {
      color: var(--land-dim);
    }

    .field-input:hover {
      border-color: rgba(107, 62, 38, 0.28);
    }

    .field-input:focus {
      border-color: var(--primary-color);
      background: rgba(192, 57, 43, 0.06);
      box-shadow: 0 0 0 3px rgba(192, 57, 43, 0.12);
    }

    /* Password Toggle Wrapper */
    .password-wrapper {
      position: relative;
      display: flex;
      align-items: center;
    }

    .password-wrapper .field-input {
      padding-right: 40px;
      /* Prevent typing text under the eye icon */
    }

    .btn-toggle-password {
      position: absolute;
      right: 12px;
      background: none;
      border: none;
      color: var(--land-dim);
      cursor: pointer;
      font-size: 0.95rem;
      padding: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: color 0.15s;
    }

    .btn-toggle-password:hover {
      color: var(--land-text);
    }

    /* Submit button */
    .btn-submit {
      width: 100%;
      height: 44px;
      border-radius: 10px;
      border: none;
      background: var(--primary-color);
      color: #fff;
      font-size: 0.88rem;
      font-weight: 700;
      font-family: inherit;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 9px;
      box-shadow: 0 6px 20px rgba(192, 57, 43, 0.42);
      transition: all 0.15s;
      margin-top: 8px;
      letter-spacing: 0.01em;
    }

    .btn-submit:hover {
      background: var(--primary-dark);
      transform: translateY(-1px);
      box-shadow: 0 8px 26px rgba(192, 57, 43, 0.50);
    }

    .btn-submit:active {
      transform: translateY(0);
    }

    /* Footer link */
    .auth-footer {
      text-align: center;
      margin-top: 20px;
      font-size: 0.78rem;
      color: var(--land-muted);
    }

    .auth-footer a {
      color: var(--accent-color);
      font-weight: 600;
    }

    .auth-footer a:hover {
      text-decoration: underline;
    }

    /* Divider */
    .auth-divider {
      display: flex;
      align-items: center;
      gap: 12px;
      margin: 20px 0;
      color: var(--land-dim);
      font-size: 0.72rem;
    }

    .auth-divider::before,
    .auth-divider::after {
      content: '';
      flex: 1;
      height: 1px;
      background: rgba(107, 62, 38, 0.12);
    }

    @media (max-width: 480px) {
      .nav {
        padding: 14px 20px;
      }

      .auth-top {
        padding: 28px 24px 22px;
      }

      .auth-body {
        padding: 22px 24px 28px;
      }
    }
  </style>
</head>

<body>

  <nav class="nav">
    <a href="<?= APP_URL ?>/landing.php" class="nav-logo">
      <div class="nav-logo-icon"><img src="../assets/images/logo.png" alt=""></div>
      <div>
        <div class="nav-logo-name"><?= APP_NAME ?></div>
      </div>
    </a>
    <a href="<?= APP_URL ?>/landing.php" class="nav-back">
      <i class="fa-solid fa-arrow-left"></i> Back to Home
    </a>
  </nav>

  <div class="page-wrap">
    <div class="auth-card">

      <div class="auth-top">
        <div class="auth-logo"><img src="../assets/images/logo.png" alt=""></div>
        <div class="auth-title">Welcome <em>back</em></div>
        <div class="auth-sub">Sign in to your <?= APP_NAME ?> account</div>
      </div>

      <div class="auth-body">

        <?php
        // Flash message from verify-email, password reset, etc.
        $flash = getFlash('global');
        if ($flash): ?>
          <div class="auth-alert auth-alert-<?= $flash['type'] === 'error' ? 'danger' : ($flash['type'] === 'warning' ? 'warning' : 'success') ?>">
            <i class="fa-solid fa-<?= $flash['type'] === 'error' ? 'circle-xmark' : ($flash['type'] === 'warning' ? 'triangle-exclamation' : 'circle-check') ?>"></i> <?= e($flash['message']) ?>
          </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
          <div class="auth-alert auth-alert-danger">
            <i class="fa-solid fa-circle-xmark"></i> <?= e($error) ?>
          </div>
        <?php endif; ?>

        <?php if (isset($_GET['logout'])): ?>
          <div class="auth-alert auth-alert-success">
            <i class="fa-solid fa-circle-check"></i> You have been logged out successfully.
          </div>
        <?php endif; ?>

        <?php if (isset($_GET['reason']) && $_GET['reason'] === 'timeout'): ?>
          <div class="auth-alert auth-alert-warning">
            <i class="fa-solid fa-clock"></i> Your session expired. Please log in again.
          </div>
        <?php endif; ?>

        <form method="POST" action="">
          <?= csrfField() ?>

          <div class="role-label">I am a&hellip;</div>
          <div class="role-tabs" style="grid-template-columns: repeat(4, 1fr);">
            <button type="button" class="role-tab <?= $selected === ROLE_STUDENT  ? 'active' : '' ?>"
              onclick="selectRole('<?= ROLE_STUDENT ?>')">
              <i class="fa-solid fa-graduation-cap"></i>Student
            </button>
            <button type="button" class="role-tab <?= $selected === ROLE_FACULTY  ? 'active' : '' ?>"
              onclick="selectRole('<?= ROLE_FACULTY ?>')">
              <i class="fa-solid fa-chalkboard-user"></i>Faculty
            </button>
            <button type="button" class="role-tab <?= $selected === ROLE_CASHIER ? 'active' : '' ?>"
              onclick="selectRole('<?= ROLE_CASHIER ?>')">
              <i class="fa-solid fa-cash-register"></i>Cashier
            </button>
            <button type="button" class="role-tab <?= $selected === ROLE_ADMIN   ? 'active' : '' ?>"
              onclick="selectRole('<?= ROLE_ADMIN ?>')">
              <i class="fa-solid fa-user-shield"></i>Admin
            </button>
          </div>
          <input type="hidden" name="role" id="role-input" value="<?= e($selected) ?>">

          <div class="field-group">
            <label class="field-label" id="identifier-label">Student ID Number</label>
            <input type="text" name="identifier" class="field-input"
              placeholder="e.g. 2023-00001" required autocomplete="username"
              value="<?= e($identifier ?? '') ?>">
          </div>

          <div class="field-group">
            <label class="field-label">Password</label>
            <div class="password-wrapper">
              <input type="password" name="password" id="login-password" class="field-input"
                placeholder="Your password" required autocomplete="current-password">
              <button type="button" class="btn-toggle-password" onclick="togglePassword('login-password', this)" tabindex="-1">
                <i class="fa-solid fa-eye"></i>
              </button>
            </div>
          </div>

          <button type="submit" class="btn-submit">
            <i class="fa-solid fa-right-to-bracket"></i> Sign In
          </button>
        </form>

        <div class="auth-divider">or</div>

        <div class="auth-footer">
          New student? <a href="<?= APP_URL ?>/register.php">Create an account</a>
        </div>

        <div class="auth-footer" style="margin-top:6px">
          <a href="<?= APP_URL ?>/register-faculty.php" style="color:var(--land-muted)">
            <i class="fa-solid fa-chalkboard-user" style="margin-right:4px"></i>Faculty registration
          </a>
        </div>

        <div class="auth-footer" style="margin-top:10px">
          <a href="<?= APP_URL ?>/forgot-password.php" style="color:var(--primary-color)">
            <i class="fa-solid fa-key" style="margin-right:4px"></i>Forgot password?
          </a>
        </div>

        <div class="auth-footer" style="margin-top:10px">
          <a href="<?= APP_URL ?>/menu.php" style="color:var(--land-muted)">
            <i class="fa-solid fa-list" style="margin-right:4px"></i>Browse menu without signing in
          </a>
        </div>

      </div>
    </div>
  </div>

  <script>
    const labels = {
      student: {
        label: 'Student ID Number',
        placeholder: 'e.g. 2316-00001C'
      },
      faculty: {
        label: 'Faculty ID',
        placeholder: 'e.g. 2023-0001'
      },
      cashier: {
        label: 'Username',
        placeholder: 'Your cashier username'
      },
      admin: {
        label: 'Username',
        placeholder: 'Your admin username'
      },
    };

    function togglePassword(inputId, btn) {
      const input = document.getElementById(inputId);
      const icon = btn.querySelector('i');

      if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
      } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
      }
    }

    function selectRole(role) {
      document.getElementById('role-input').value = role;
      document.querySelectorAll('.role-tab').forEach(t => t.classList.remove('active'));
      event.currentTarget.classList.add('active');
      const info = labels[role];
      document.getElementById('identifier-label').textContent = info.label;
      document.querySelector('input[name="identifier"]').placeholder = info.placeholder;
    }
    selectRole(document.getElementById('role-input').value);
  </script>
</body>

</html>