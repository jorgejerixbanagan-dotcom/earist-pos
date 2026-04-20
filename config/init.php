<?php
// ============================================================
// config/init.php
//
// THE MASTER BOOTSTRAP FILE.
// Every protected PHP page includes ONLY this one file at the top:
//
//   require_once __DIR__ . '/../../config/init.php';
//
// This file then loads everything else in the correct order.
// ============================================================

// Show errors during development — change to 0 before going live
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// 1. Constants (DB credentials, role names, status names, etc.)
require_once __DIR__ . '/constants.php';

// 2. Mail configuration (SMTP settings for email)
require_once __DIR__ . '/mail.php';

// 3. Session configuration + session_start()
require_once __DIR__ . '/session.php';

// 4. Database class (PDO singleton)
require_once __DIR__ . '/database.php';

// 5. General helper functions (peso(), e(), flash(), auditLog(), etc.)
require_once __DIR__ . '/../includes/functions.php';

// 6. Authentication helpers (requireRole(), loginUser(), logoutUser(), etc.)
require_once __DIR__ . '/../includes/auth.php';

// 7. CSRF helpers (csrfToken(), csrfField(), verifyCsrf(), etc.)
require_once __DIR__ . '/../includes/csrf.php';

// 8. Email helpers (sendEmail(), sendOtpEmail(), etc.)
require_once __DIR__ . '/../includes/mail.php';

// 9. OTP helpers (generateOtp(), storeOtp(), validateOtp(), etc.)
require_once __DIR__ . '/../includes/otp.php';

// 10. Layout helpers (layoutHeader(), layoutFooter(), navItem())
require_once __DIR__ . '/../includes/layout.php';
