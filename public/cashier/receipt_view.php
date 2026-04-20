<?php
/**
 * receipt_view.php — Print-only receipt page
 *
 * Used by:
 *   - orders.php modal print button (opens in new window)
 *   - Can also be accessed directly for clean printing
 *
 * Query params:
 *   - order_id: required, the order ID to display
 *   - format: optional, 'print' to auto-trigger print dialog
 */
require_once __DIR__ . '/../../config/init.php';
requireRole(ROLE_CASHIER);

$db = Database::getInstance();

$orderId = (int)($_GET['order_id'] ?? 0);
if (!$orderId) {
  die('Order ID required');
}

$stmt = $db->prepare(
  "SELECT o.*, p.payment_method, p.amount_paid, p.change_given, p.reference_number,
          c.full_name AS cashier_name,
          COALESCE(s.full_name, 'Walk-in') AS customer_name
   FROM orders o
   LEFT JOIN payments p ON o.id = p.order_id
   LEFT JOIN cashiers c ON o.cashier_id = c.id
   LEFT JOIN students s ON o.student_id = s.id
   WHERE o.id = ?"
);
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
  die('Order not found');
}

$stmt = $db->prepare(
  "SELECT od.quantity, od.price_at_time, od.subtotal, od.customization_note, pr.name
   FROM order_details od
   JOIN products pr ON od.product_id = pr.id
   WHERE od.order_id = ?"
);
$stmt->execute([$orderId]);
$items = $stmt->fetchAll();

// Return JSON for modal loading
if (isset($_GET['json'])) {
  header('Content-Type: application/json');
  ob_start();
  include __DIR__ . '/receipt_template.php';
  $html = ob_get_clean();
  echo json_encode(['success' => true, 'html' => $html, 'order' => $order]);
  exit;
}

// Include styles
include __DIR__ . '/receipt_styles.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Receipt <?= e($order['order_number']) ?> — <?= e(APP_NAME) ?></title>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    /* Reset for print-only page */
    * { box-sizing: border-box; }
    html, body {
      margin: 0;
      padding: 0;
      background: #fff;
    }
    body {
      display: flex;
      justify-content: center;
      padding: 20px;
      min-height: 100vh;
    }
  </style>
</head>
<body>
  <div class="rcpt-print-root">
    <?php include __DIR__ . '/receipt_template.php'; ?>
  </div>

  <?php if (isset($_GET['format']) && $_GET['format'] === 'print'): ?>
  <script>
    window.onload = function() {
      window.print();
    };
  </script>
  <?php endif; ?>
</body>
</html>