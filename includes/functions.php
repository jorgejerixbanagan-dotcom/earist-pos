<?php
// ============================================================
// includes/functions.php
//
// WHAT THIS FILE DOES:
//   A collection of small, reusable helper functions used across
//   the entire application. Think of these as your toolbox.
// ============================================================

// ---- OUTPUT SAFETY ----

/**
 * e($value)
 *
 * Safely outputs a value in HTML by escaping special characters.
 * ALWAYS use this when displaying user-supplied data on screen.
 * This prevents XSS (Cross-Site Scripting) attacks.
 *
 * Example:
 *   echo e($user['full_name']);   // Safe: shows text as-is
 *   echo $user['full_name'];      // UNSAFE: could inject HTML/JS
 */
function e(mixed $value): string {
  return htmlspecialchars((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// ---- MONEY FORMATTING ----

/**
 * peso($amount)
 *
 * Formats a number as Philippine Peso currency.
 * Example: peso(1234.5) → "₱1,234.50"
 */
function peso(float|int|string $amount): string {
  return '₱' . number_format((float)$amount, 2);
}

// ---- ORDER NUMBER GENERATION ----

/**
 * generateOrderNumber()
 *
 * Creates a human-readable order number like: ORD-20240602-0047
 * Format: ORD-{YYYYMMDD}-{4-digit sequential number for today}
 *
 * Uses the database to count today's orders so the number is always
 * accurate even if the app restarts.
 */
function generateOrderNumber(): string {
  $db   = Database::getInstance();
  $stmt = $db->prepare(
    "SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()"
  );
  $stmt->execute();
  $count = (int)$stmt->fetchColumn() + 1;

  return sprintf('ORD-%s-%04d', date('Ymd'), $count);
}

// ---- INPUT VALIDATION ----

/**
 * sanitizeString($input, $maxLength)
 *
 * Trims whitespace, strips HTML tags, and enforces a max length.
 * Use on text fields like names, descriptions, etc.
 */
function sanitizeString(string $input, int $maxLength = 255): string {
  $clean = strip_tags(trim($input));
  return mb_substr($clean, 0, $maxLength);
}

/**
 * validatePassword($password)
 *
 * Returns an error message string if the password is too weak,
 * or null if it's acceptable.
 * Rules: at least 8 characters.
 * (Add more rules if needed: uppercase, numbers, symbols)
 */
function validatePassword(string $password): ?string {
  if (strlen($password) < 8) {
    return 'Password must be at least 8 characters long.';
  }
  return null; // null means "no error" = password is valid
}

// ---- AJAX / API HELPERS ----

/**
 * jsonResponse($data, $statusCode)
 *
 * Sends a JSON response and stops execution.
 * Used in all api/ endpoint files.
 *
 * Example:
 *   jsonResponse(['success' => true, 'message' => 'Order created']);
 *   jsonResponse(['success' => false, 'message' => 'Not found'], 404);
 */
function jsonResponse(array $data, int $statusCode = 200): never {
  http_response_code($statusCode);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data);
  exit;
}

/**
 * isAjax()
 *
 * Returns true if the request was made via AJAX (fetch/XMLHttpRequest).
 */
function isAjax(): bool {
  return isset($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

// ---- REDIRECT ----

/**
 * redirect($url)
 *
 * Redirects to a URL and stops execution.
 */
function redirect(string $url): never {
  header('Location: ' . $url);
  exit;
}

/**
 * redirectBack($fallback)
 *
 * Redirects to the page the user came from (using Referer header).
 * If no Referer is available, redirects to $fallback.
 */
function redirectBack(string $fallback = ''): never {
  $fallback = $fallback ?: APP_URL . '/login.php';
  $back = $_SERVER['HTTP_REFERER'] ?? $fallback;
  redirect($back);
}

// ---- FLASH MESSAGES ----
// Flash messages are one-time messages shown after a redirect.
// Example: after saving a product → flash "Product saved!" → redirect → show message → clear it.

/**
 * flash($key, $message)
 *
 * Stores a message in the session to be shown once.
 * $type can be: 'success', 'error', 'warning', 'info'
 */
function flash(string $key, string $message, string $type = 'success'): void {
  $_SESSION['flash'][$key] = ['message' => $message, 'type' => $type];
}

/**
 * getFlash($key)
 *
 * Retrieves and DELETES a flash message.
 * Returns null if no message with that key exists.
 */
function getFlash(string $key): ?array {
  if (isset($_SESSION['flash'][$key])) {
    $msg = $_SESSION['flash'][$key];
    unset($_SESSION['flash'][$key]);
    return $msg;
  }
  return null;
}

/**
 * showFlash($key)
 *
 * Outputs the flash message as an HTML toast notification.
 * Call this in pages where you want to display feedback.
 */
function showFlash(string $key): void {
  $flash = getFlash($key);
  if (!$flash) return;

  $type = e($flash['type']);
  $msg  = e($flash['message']);

  $icons = [
    'success' => 'fa-circle-check',
    'error'   => 'fa-circle-xmark',
    'warning' => 'fa-triangle-exclamation',
    'info'    => 'fa-circle-info',
  ];
  $icon = $icons[$type] ?? 'fa-circle-info';

  echo "<div class=\"toast toast-{$type}\" role=\"alert\">
          <i class=\"fa-solid {$icon}\"></i>
          <span>{$msg}</span>
          <button class=\"toast-close\" onclick=\"this.parentElement.remove()\">
            <i class=\"fa-solid fa-xmark\"></i>
          </button>
        </div>";
}

// ---- LOGIN ATTEMPT THROTTLE ----

/**
 * checkLoginAttempts($identifier)
 *
 * Blocks login after 5 failed attempts for 15 minutes.
 * $identifier is usually the username or student ID being attempted.
 */
function checkLoginAttempts(string $identifier): bool {
  $key     = 'login_attempts_' . md5($identifier);
  $lockKey = 'login_locked_'   . md5($identifier);

  // Check if currently locked
  if (!empty($_SESSION[$lockKey])) {
    $remaining = ($_SESSION[$lockKey] + 900) - time(); // 900s = 15 min
    if ($remaining > 0) {
      return false; // Still locked
    }
    // Lock expired — reset
    unset($_SESSION[$lockKey], $_SESSION[$key]);
  }
  return true; // Allowed
}

/**
 * recordFailedLogin($identifier)
 *
 * Increments the failed attempt counter. Locks after 5 attempts.
 */
function recordFailedLogin(string $identifier): void {
  $key     = 'login_attempts_' . md5($identifier);
  $lockKey = 'login_locked_'   . md5($identifier);

  $_SESSION[$key] = ($_SESSION[$key] ?? 0) + 1;

  if ($_SESSION[$key] >= 5) {
    $_SESSION[$lockKey] = time();
  }
}

/**
 * clearLoginAttempts($identifier)
 *
 * Called after a successful login to reset the counter.
 */
function clearLoginAttempts(string $identifier): void {
  $key     = 'login_attempts_' . md5($identifier);
  $lockKey = 'login_locked_'   . md5($identifier);
  unset($_SESSION[$key], $_SESSION[$lockKey]);
}

// ---- AUDIT LOG ----

/**
 * auditLog($actorType, $actorId, $action, $target, $targetId)
 *
 * Writes a record to the audit_log table.
 * Call this whenever something important happens:
 * login, order created, product deleted, refund approved, etc.
 */
function auditLog(
  string $actorType,
  int    $actorId,
  string $action,
  string $target   = '',
  ?int   $targetId = null
): void {
  try {
    $db   = Database::getInstance();
    $stmt = $db->prepare(
      "INSERT INTO audit_log (actor_type, actor_id, action, target, target_id, ip_address)
             VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
      $actorType,
      $actorId,
      $action,
      $target   ?: null,
      $targetId,
      $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
  } catch (PDOException $e) {
    // Audit log failure should never crash the app — just log silently
    error_log('Audit log failed: ' . $e->getMessage());
  }
}

// ---- ID VALIDATION ----

/**
 * validateStudentId($id)
 *
 * Validates Student ID format: NNNN-NNNNNL (e.g., 2316-00001C)
 * Format: 4 digits, hyphen, 5 digits, 1 uppercase letter
 *
 * Returns null if valid, error message string if invalid.
 */
function validateStudentId(string $id): ?string {
  $id = trim($id);
  if (!preg_match('/^\d{4}-\d{5}[A-Z]$/', $id)) {
    return 'Student ID must be in format: 2316-00001C (4 digits, hyphen, 5 digits, 1 uppercase letter)';
  }
  return null; // null means valid
}

/**
 * validateFacultyId($id)
 *
 * Validates Faculty ID format: YYYY-NNNN (e.g., 2023-0001)
 * Format: 4 digits, hyphen, 4 digits
 *
 * Returns null if valid, error message string if invalid.
 */
function validateFacultyId(string $id): ?string {
  $id = trim($id);
  if (!preg_match('/^\d{4}-\d{4}$/', $id)) {
    return 'Faculty ID must be in format: 2023-0001 (4 digits, hyphen, 4 digits)';
  }
  return null; // null means valid
}

/**
 * maskEmail($email)
 *
 * Masks an email address for display: j***n@gmail.com
 * Used in OTP verification screens.
 */
function maskEmail(string $email): string {
  $parts = explode('@', $email);
  if (count($parts) !== 2) {
    return '***@***.***';
  }
  $local = $parts[0];
  $domain = $parts[1];

  if (strlen($local) <= 2) {
    $masked = $local[0] . '***';
  } else {
    $masked = $local[0] . str_repeat('*', strlen($local) - 2) . $local[strlen($local) - 1];
  }

  return $masked . '@' . $domain;
}
