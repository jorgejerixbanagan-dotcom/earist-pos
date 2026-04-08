<?php
// ============================================================
// includes/layout.php
//
// Defines layoutHeader() and layoutFooter().
// Loaded by config/init.php — do NOT include this file directly.
//
// Usage in every protected page:
//
//   layoutHeader('Page Title');
//   ... your HTML ...
//   layoutFooter();
//
// Optional extra CSS/JS per page:
//   layoutHeader('Dashboard', '<script src="chart.js"></script>');
// ============================================================

// -----------------------------------------------------------------
// layoutHeader() — outputs <html>, <head>, sidebar, topbar, <main>
// -----------------------------------------------------------------
function layoutHeader(string $pageTitle = '', string $extraHead = ''): void {
  $role     = currentRole();
  $userName = e($_SESSION['full_name'] ?? 'User');
  $appUrl   = APP_URL;

  // Build the <head> block
  echo '<!DOCTYPE html>' . "\n";
  echo '<html lang="en">' . "\n";
  echo '<head>' . "\n";
  echo '  <meta charset="UTF-8">' . "\n";
  echo '  <meta name="viewport" content="width=device-width, initial-scale=1.0">' . "\n";
  echo '  <meta name="csrf-token" content="' . csrfToken() . '">' . "\n";
  echo '  <title>' . e($pageTitle) . ' — ' . APP_NAME . '</title>' . "\n";
  echo '  <link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
  echo '  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;0,9..40,800;1,9..40,400&display=swap" rel="stylesheet">' . "\n";
  echo '  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">' . "\n";
  echo '  <link rel="stylesheet" href="' . $appUrl . '/../assets/css/variables.css">' . "\n";
  echo '  <link rel="stylesheet" href="' . $appUrl . '/../assets/css/layout.css">' . "\n";
  echo '  <link rel="stylesheet" href="' . $appUrl . '/../assets/css/components.css">' . "\n";
  if ($extraHead) echo '  ' . $extraHead . "\n";
  echo '</head>' . "\n";
  echo '<body>' . "\n";

  // ---- Sidebar role icon ----
  if ($role === ROLE_ADMIN) {
    $roleIcon = 'fa-user-shield';
  } elseif ($role === ROLE_CASHIER) {
    $roleIcon = 'fa-cash-register';
  } else {
    $roleIcon = 'fa-graduation-cap';
  }

  // ---- Build sidebar nav HTML ----
  $nav = '';

  if ($role === ROLE_ADMIN) {
    $nav .= '<div class="nav-section-label">Overview</div>';
    $nav .= navItem('fa-chart-pie',    'Dashboard',       $appUrl . '/admin/dashboard.php', $pageTitle);
    $nav .= navItem('fa-chart-bar',    'Reports',         $appUrl . '/admin/reports.php',   $pageTitle);
    $nav .= '<div class="nav-section-label">Management</div>';
    $nav .= navItem('fa-box-open',     'Products',        $appUrl . '/admin/products.php',  $pageTitle);
    $nav .= navItem('fa-users',        'Cashiers',        $appUrl . '/admin/cashiers.php',  $pageTitle);
    $nav .= navItem('fa-list-check',   'All Orders',      $appUrl . '/admin/orders.php',    $pageTitle);
    $nav .= navItem('fa-star',         'Feedback',        $appUrl . '/admin/feedback.php',  $pageTitle);
    $nav .= '<div class="nav-section-label">System</div>';
  } elseif ($role === ROLE_CASHIER) {
    $nav .= '<div class="nav-section-label">Operations</div>';
    $nav .= navItem('fa-chart-simple', 'Dashboard',   $appUrl . '/cashier/dashboard.php',  $pageTitle);
    $nav .= navItem('fa-store',        'Walk-in POS', $appUrl . '/cashier/walkin.php',      $pageTitle);
    $nav .= navItem('fa-clock',        'Pre-orders',  $appUrl . '/cashier/preorders.php',   $pageTitle, pendingPreorderCount());
    $nav .= navItem('fa-clock-rotate-left', 'Order History', $appUrl . '/cashier/orders.php',    $pageTitle);
    $nav .= '<div class="nav-section-label">Session</div>';
  } elseif ($role === ROLE_STUDENT) {
    $nav .= '<div class="nav-section-label">My Account</div>';
    $nav .= navItem('fa-house',              'Dashboard',  $appUrl . '/student/dashboard.php', $pageTitle);
    $nav .= navItem('fa-utensils',           'Order Now',  $appUrl . '/student/menu.php',      $pageTitle);
    $nav .= navItem('fa-cart-shopping',      'My Cart',    $appUrl . '/student/cart.php',      $pageTitle, studentCartCount());
    $nav .= navItem('fa-clock-rotate-left',  'My Orders',  $appUrl . '/student/orders.php',    $pageTitle);
    $nav .= '<div class="nav-section-label">Account</div>';
  }

  // Logout always last
  $nav .= '<a href="' . $appUrl . '/logout.php" class="nav-item logout">'
    . '<i class="fa-solid fa-right-from-bracket"></i>'
    . '<span>Logout</span>'
    . '</a>';

  // ---- Output full sidebar ----
  echo '
<div class="sidebar-backdrop" id="sidebar-backdrop"></div>

<aside class="sidebar" id="sidebar">

  <div class="sidebar-brand">
    <div class="sidebar-brand-icon">
      <img src="' . $appUrl . '/../assets/images/logo.png" alt="' . APP_NAME . '"
           onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\'">
      <i class="fa-solid fa-mug-hot" style="display:none"></i>
    </div>
    <div class="sidebar-brand-text">
      <div class="sidebar-brand-name">EARIST Coffee</div>
      <div class="sidebar-brand-tag">POS System</div>
    </div>
  </div>

  <div class="sidebar-user">
    <div class="sidebar-user-avatar"><i class="fa-solid ' . $roleIcon . '"></i></div>
    <div class="sidebar-user-info">
      <div class="sidebar-user-name">' . $userName . '</div>
      <div class="sidebar-user-role">' . e(ucfirst($role ?? '')) . '</div>
    </div>
  </div>

  <nav class="sidebar-nav">
    ' . $nav . '
  </nav>

  <div class="sidebar-footer">
    <div class="sidebar-toggle" onclick="toggleSidebar()">
      <i class="fa-solid fa-angles-left toggle-arrow"></i>
      <span class="toggle-label">Collapse</span>
    </div>
  </div>

</aside>

<div class="main-wrapper" id="main-wrapper">

  <header class="topbar">
    <div class="topbar-left">
      <button class="topbar-hamburger" onclick="openMobileSidebar()" aria-label="Open menu">
        <i class="fa-solid fa-bars"></i>
      </button>
      <div class="topbar-title">' . e($pageTitle) . '</div>
    </div>
    <div class="topbar-actions">
      <a href="' . $appUrl . '/logout.php" class="topbar-user" title="Logout">
        <span class="topbar-user-dot"><i class="fa-solid ' . $roleIcon . '"></i></span>
        <span class="topbar-user-name">' . $userName . '</span>
        <i class="fa-solid fa-right-from-bracket" style="font-size:11px;opacity:0.5"></i>
      </a>
    </div>
  </header>
';

  // Flash messages
  if (!empty($_SESSION['flash'])) {
    echo '<div style="padding:12px 28px 0">';
    foreach (array_keys($_SESSION['flash']) as $k) {
      showFlash($k);
    }
    echo '</div>';
  }

  echo '<main class="page-content">' . "\n";
}


// -----------------------------------------------------------------
// layoutFooter() — closes <main>, loads JS, closes </body></html>
// -----------------------------------------------------------------
function layoutFooter(string $extraScripts = ''): void {
  $appUrl = APP_URL;

  echo "\n" . '  </main>' . "\n";
  echo '</div><!-- /main-wrapper -->' . "\n\n";
  echo '<script src="' . $appUrl . '/../assets/js/sidebar.js"></script>' . "\n";
  echo '<script src="' . $appUrl . '/../assets/js/utils.js"></script>' . "\n";
  if ($extraScripts) {
    echo $extraScripts . "\n";
  }
  echo '</body>' . "\n";
  echo '</html>' . "\n";
}


// -----------------------------------------------------------------
// navItem() — builds a single <a> sidebar link
// -----------------------------------------------------------------
function navItem(
  string $icon,
  string $label,
  string $href,
  string $currentPage = '',
  int    $badge       = 0
): string {
  $active    = ($currentPage === $label) ? ' active' : '';
  $badgeHtml = ($badge > 0) ? '<span class="nav-badge">' . $badge . '</span>' : '';

  return '<a href="' . $href . '" class="nav-item' . $active . '">'
    . '<i class="fa-solid ' . $icon . '"></i>'
    . '<span>' . e($label) . '</span>'
    . $badgeHtml
    . '</a>' . "\n";
}


// -----------------------------------------------------------------
// Badge count helpers used by the sidebar
// -----------------------------------------------------------------

function pendingRefundCount(): int {
  try {
    $db   = Database::getInstance();
    $stmt = $db->query("SELECT COUNT(*) FROM refund_requests WHERE status = 'pending'");
    return (int) $stmt->fetchColumn();
  } catch (Throwable $e) {
    return 0;
  }
}

function pendingPreorderCount(): int {
  try {
    $db   = Database::getInstance();
    $stmt = $db->prepare(
      "SELECT COUNT(*) FROM orders
             WHERE order_type = 'pre-order'
               AND status IN ('pending', 'preparing', 'ready')"
    );
    $stmt->execute();
    return (int) $stmt->fetchColumn();
  } catch (Throwable $e) {
    return 0;
  }
}

function studentCartCount(): int {
  // Student cart lives in sessionStorage (JS), not PHP session.
  // This returns 0 — the JS side updates the badge count dynamically.
  return 0;
}
