<?php
// ============================================================
// public/register-faculty.php
//
// WHAT THIS FILE DOES:
//   Self-registration page for FACULTY (Professors).
//   Similar flow to student registration but with Faculty ID format.
//
// FLOW:
//   GET  → show the form
//   POST → validate all fields → check faculty ID format & uniqueness
//         → hash password → insert into faculty table → send OTP
//         → redirect to verify-email.php for verification
// ============================================================

require_once __DIR__ . '/../config/init.php';

if (isLoggedIn()) redirectByRole();

$errors = [];
$old    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  verifyCsrf();

  // Collect and sanitize all inputs
  $old['full_name']     = sanitizeString($_POST['full_name']     ?? '');
  $old['faculty_id_no'] = sanitizeString($_POST['faculty_id_no'] ?? '');
  $old['email']         = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
  $password            = $_POST['password']                     ?? '';
  $confirm             = $_POST['confirm_password']             ?? '';
  $declared             = isset($_POST['id_declaration']);

  // Validation rules
  if (empty($old['full_name']))                                    $errors[] = 'Full name is required.';
  if (empty($old['faculty_id_no']))                                $errors[] = 'Faculty ID number is required.';
  if (empty($old['email']))                                        $errors[] = 'Email address is required.';
  if (!$declared)                                                  $errors[] = 'You must confirm that the ID provided is correct.';

  // Validate Faculty ID format (2023-0001)
  if (empty($errors) && !empty($old['faculty_id_no'])) {
    $idError = validateFacultyId($old['faculty_id_no']);
    if ($idError) $errors[] = $idError;
  }

  // Validate email format
  if (empty($errors) && !empty($old['email'])) {
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
      $errors[] = 'Please enter a valid email address.';
    }
  }

  // Validate password
  $pwError = validatePassword($password);
  if ($pwError)                                                    $errors[] = $pwError;
  if ($password !== $confirm)                                      $errors[] = 'Passwords do not match.';

  // Check if Faculty ID is already registered
  if (empty($errors)) {
    $db   = Database::getInstance();
    $stmt = $db->prepare("SELECT id FROM faculty WHERE faculty_id_no = ? LIMIT 1");
    $stmt->execute([$old['faculty_id_no']]);
    if ($stmt->fetch()) {
      $errors[] = 'That Faculty ID is already registered. Please log in.';
    }
  }

  // Check if email is already registered
  if (empty($errors) && !empty($old['email'])) {
    $db   = Database::getInstance();
    $stmt = $db->prepare("SELECT id FROM faculty WHERE email = ? LIMIT 1");
    $stmt->execute([$old['email']]);
    if ($stmt->fetch()) {
      $errors[] = 'That email is already registered. Please use a different email.';
    }
  }

  // All good — save to database and send OTP
  if (empty($errors)) {
    $db   = Database::getInstance();
    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    try {
      $stmt = $db->prepare(
        "INSERT INTO faculty (full_name, faculty_id_no, email, password, id_declaration, email_verified)
               VALUES (?, ?, ?, ?, 1, 0)"
      );
      $stmt->execute([
        $old['full_name'],
        $old['faculty_id_no'],
        $old['email'],
        $hash,
      ]);

      $newId = (int)$db->lastInsertId();

      // Generate and send OTP
      $otp = generateOtp();
      storeOtp(ROLE_FACULTY, $newId, $old['email'], $otp, 'verification');
      $emailResult = sendOtpEmail($old['email'], $otp, 'verification');

      if (!$emailResult['success']) {
        // Email failed, but account was created. Show warning.
        flash('global', 'Account created but email could not be sent. Please contact support.', 'warning');
      }

      // Store pending verification in session
      $_SESSION['pending_verification'] = [
        'user_type' => ROLE_FACULTY,
        'user_id'   => $newId,
        'email'     => $old['email'],
        'purpose'   => 'verification'
      ];

      auditLog(ROLE_FACULTY, $newId, 'register');
      redirect(APP_URL . '/verify-email.php');

    } catch (PDOException $e) {
      $errors[] = 'Registration failed. Please try again.';
      error_log('Faculty registration error: ' . $e->getMessage());
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Faculty Registration — <?= APP_NAME ?></title>
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

    body::after {
      content: '';
      position: fixed;
      top: 20%;
      left: 50%;
      transform: translate(-50%, -50%);
      width: 600px;
      height: 500px;
      background: radial-gradient(ellipse, rgba(192, 57, 43, 0.04) 0%, transparent 70%);
      pointer-events: none;
      z-index: 0;
    }

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

    .nav-back {
      font-size: 0.78rem;
      font-weight: 500;
      color: var(--land-muted);
      display: inline-flex;
      align-items: center;
      gap: 7px;
      padding: 6px 12px;
      border-radius: 7px;
      border: 1px solid var(--land-border);
      transition: all 0.15s;
    }

    .nav-back:hover {
      color: var(--land-text);
      background: rgba(107, 62, 38, 0.06);
      border-color: rgba(107, 62, 38, 0.28);
    }

    .page-wrap {
      flex: 1;
      display: flex;
      align-items: flex-start;
      justify-content: center;
      padding: 40px 24px 60px;
      position: relative;
      z-index: 1;
    }

    .auth-card {
      width: 100%;
      max-width: 480px;
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

    .auth-top {
      padding: 32px 36px 26px;
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
      background: radial-gradient(circle, rgba(192, 57, 43, 0.06), transparent 70%);
      pointer-events: none;
    }

    .auth-logo {
      width: 50px;
      height: 50px;
      background: var(--primary-color);
      border-radius: 14px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 20px;
      color: #fff;
      margin-bottom: 14px;
      box-shadow: 0 6px 20px rgba(192, 57, 43, 0.45);
      position: relative;
      z-index: 1;
    }

    .auth-title {
      font-family: 'Instrument Serif', serif;
      font-size: 1.35rem;
      font-weight: 400;
      color: var(--land-text);
      letter-spacing: -0.01em;
      margin-bottom: 5px;
      position: relative;
      z-index: 1;
    }

    .auth-title em {
      font-style: italic;
      color: var(--primary-color);
    }

    .auth-sub {
      font-size: 0.75rem;
      color: var(--land-muted);
      font-weight: 300;
      position: relative;
      z-index: 1;
    }

    .auth-body {
      padding: 26px 32px 32px;
    }

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
      margin-top: 2px;
    }

    .auth-alert ul {
      margin: 0;
      padding-left: 16px;
    }

    .auth-alert-danger {
      background: rgba(239, 68, 68, 0.07);
      color: #b91c1c;
      border-color: rgba(239, 68, 68, 0.20);
    }

    .field-group {
      margin-bottom: 16px;
    }

    .field-label {
      display: block;
      font-size: 0.73rem;
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
      font-size: 0.85rem;
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

    .field-hint {
      font-size: 0.7rem;
      color: var(--land-muted);
      margin-top: 4px;
    }

    .two-col {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 14px;
    }

    .password-wrapper {
      position: relative;
      display: flex;
      align-items: center;
    }

    .password-wrapper .field-input {
      padding-right: 40px;
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

    .declaration-box {
      background: rgba(240, 180, 41, 0.08);
      border: 1.5px solid rgba(240, 180, 41, 0.25);
      border-radius: 10px;
      padding: 14px 16px;
      display: flex;
      gap: 12px;
      margin-bottom: 20px;
      margin-top: 4px;
    }

    .declaration-box input[type="checkbox"] {
      width: 16px;
      height: 16px;
      flex-shrink: 0;
      margin-top: 2px;
      accent-color: var(--primary-color);
      cursor: pointer;
    }

    .declaration-text {
      font-size: 0.78rem;
      color: var(--land-muted);
      line-height: 1.6;
      font-weight: 300;
    }

    .declaration-text strong {
      color: var(--land-text);
      font-weight: 600;
    }

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

    .auth-footer {
      text-align: center;
      margin-top: 18px;
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

    @media (max-width: 520px) {
      .nav {
        padding: 14px 20px;
      }

      .auth-top {
        padding: 26px 22px 20px;
      }

      .auth-body {
        padding: 20px 22px 28px;
      }

      .two-col {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>

<body>

  <nav class="nav">
    <a href="<?= APP_URL ?>/landing.php" class="nav-logo">
      <div class="nav-logo-icon"><img src="../assets/images/logo.png" alt="Kapehan ni Amang"></div>
      <div>
        <div class="nav-logo-name"><?= APP_NAME ?></div>
      </div>
    </a>
    <a href="<?= APP_URL ?>/login.php" class="nav-back">
      <i class="fa-solid fa-arrow-left"></i> Sign In
    </a>
  </nav>

  <div class="page-wrap">
    <div class="auth-card">

      <div class="auth-top">
        <div class="auth-logo"><i class="fa-solid fa-chalkboard-user"></i></div>
        <div class="auth-title">Create your <em>faculty account</em></div>
        <div class="auth-sub">EARIST Cavite Campus &mdash; Faculty Registration</div>
      </div>

      <div class="auth-body">

        <?php if (!empty($errors)): ?>
          <div class="auth-alert auth-alert-danger">
            <i class="fa-solid fa-circle-xmark"></i>
            <ul>
              <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <form method="POST" action="">
          <?= csrfField() ?>

          <div class="field-group">
            <label class="field-label">Full Name</label>
            <input type="text" name="full_name" class="field-input"
              placeholder="Prof. Juan Dela Cruz" required
              value="<?= e($old['full_name'] ?? '') ?>">
          </div>

          <div class="field-group">
            <label class="field-label">Faculty ID Number</label>
            <input type="text" name="faculty_id_no" class="field-input"
              placeholder="e.g. 2023-0001" required
              pattern="\d{4}-\d{4}"
              title="Format: 2023-0001 (4 digits, hyphen, 4 digits)"
              value="<?= e($old['faculty_id_no'] ?? '') ?>">
            <div class="field-hint">Format: 2023-0001 (4 digits, hyphen, 4 digits)</div>
          </div>

          <div class="field-group">
            <label class="field-label">Email Address</label>
            <input type="email" name="email" class="field-input"
              placeholder="your.email@earist.edu.ph" required
              value="<?= e($old['email'] ?? '') ?>">
            <div class="field-hint">Used for verification and password recovery</div>
          </div>

          <div class="two-col">
            <div class="field-group">
              <label class="field-label">Password</label>
              <div class="password-wrapper">
                <input type="password" name="password" id="reg-password" class="field-input"
                  placeholder="At least 8 characters" required>
                <button type="button" class="btn-toggle-password" onclick="togglePassword('reg-password', this)" tabindex="-1">
                  <i class="fa-solid fa-eye"></i>
                </button>
              </div>
            </div>
            <div class="field-group">
              <label class="field-label">Confirm Password</label>
              <div class="password-wrapper">
                <input type="password" name="confirm_password" id="reg-confirm" class="field-input"
                  placeholder="Repeat password" required>
                <button type="button" class="btn-toggle-password" onclick="togglePassword('reg-confirm', this)" tabindex="-1">
                  <i class="fa-solid fa-eye"></i>
                </button>
              </div>
            </div>
          </div>

          <div class="declaration-box">
            <input type="checkbox" name="id_declaration" id="id_declaration"
              <?= isset($_POST['id_declaration']) ? 'checked' : '' ?>>
            <label class="declaration-text" for="id_declaration">
              I confirm that the ID provided is correct.
              <strong>Incorrect IDs will prevent product claiming.</strong>
            </label>
          </div>

          <button type="submit" class="btn-submit">
            <i class="fa-solid fa-user-plus"></i> Create Account
          </button>
        </form>

        <div class="auth-footer">
          Already have an account? <a href="<?= APP_URL ?>/login.php">Sign in here</a>
        </div>

      </div>
    </div>
  </div>

  <script>
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
  </script>

</body>

</html>