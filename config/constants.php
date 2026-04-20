<?php
// ============================================================
// config/constants.php
//
// WHAT THIS FILE DOES:
//   Defines global constants used throughout the entire app.
//   Think of constants like settings that never change while
//   the app is running. We define them once here and use them
//   everywhere else.
// ============================================================

// --- Database credentials ---
// Change these to match YOUR Laragon MySQL setup.
// In Laragon, the default MySQL user is 'root' with no password.
define('DB_HOST',    'localhost');
define('DB_NAME',    'earist_coffeeshop');
define('DB_USER',    'root');
define('DB_PASS',    '');          // Laragon default: empty password
define('DB_CHARSET', 'utf8mb4');

// --- Application settings ---
define('APP_NAME',    'Kapehan ni Amang');
define('APP_TAGLINE', 'Swak sa Panlasa at Gawi ng Mag-aaral ni Amang');

// APP_URL auto-detects the host AND protocol so the app works on:
//   - localhost (http)
//   - local IP / LAN  (http, e.g. 192.168.x.x)
//   - ngrok / reverse proxies (https — detected via X-Forwarded-Proto header)
// The path '/earist-pos/public' must match your Laragon project folder name.
$_proto = 'http';
if (
  (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ||
  (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
  (isset($_SERVER['HTTP_X_FORWARDED_SSL'])   && $_SERVER['HTTP_X_FORWARDED_SSL']   === 'on')
) {
  $_proto = 'https';
}
define('APP_URL', $_proto . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/earist-pos/public');
unset($_proto);

// --- User roles ---
define('ROLE_ADMIN',   'admin');
define('ROLE_CASHIER', 'cashier');
define('ROLE_STUDENT', 'student');
define('ROLE_FACULTY',  'faculty');

// --- OTP Settings ---
define('OTP_LENGTH',          6);      // Number of digits in OTP
define('OTP_EXPIRY_MINUTES',  15);     // OTP valid for 15 minutes
define('OTP_RESEND_COOLDOWN', 60);     // Seconds before allowing resend
define('MAX_OTP_ATTEMPTS',    5);      // Max OTP attempts before lockout

// --- Order types ---
define('ORDER_WALKIN',   'walk-in');
define('ORDER_PREORDER', 'pre-order');

// --- Order statuses ---
define('STATUS_PENDING',   'pending');
define('STATUS_PREPARING', 'preparing');
define('STATUS_READY',     'ready');
define('STATUS_CLAIMED',   'claimed');
define('STATUS_CANCELLED', 'cancelled');

// --- Payment methods ---
define('PAY_CASH',   'cash');
define('PAY_ONLINE', 'online');

// --- Payment statuses ---
define('PAY_STATUS_PENDING',  'pending');
define('PAY_STATUS_PAID',     'paid');
define('PAY_STATUS_REFUNDED', 'refunded');
define('PAY_STATUS_FAILED',   'failed');

// --- File upload settings ---
define('UPLOAD_DIR',          __DIR__ . '/../uploads/products/');
define('UPLOAD_MAX_SIZE',     2 * 1024 * 1024);
define('UPLOAD_ALLOWED_TYPES', serialize(['image/jpeg', 'image/png', 'image/webp']));

// --- Session timeout: 1 hour ---
define('SESSION_TIMEOUT', 3600);
