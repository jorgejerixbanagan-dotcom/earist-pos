<?php
require_once __DIR__ . '/../../config/init.php';
requireRole(ROLE_CASHIER);
$db = Database::getInstance();

$orderId = (int)($_GET['id'] ?? $_SESSION['last_order_id'] ?? 0);
if (!$orderId) {
  redirect(APP_URL . '/cashier/walkin.php');
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
  redirect(APP_URL . '/cashier/walkin.php');
}

$stmt = $db->prepare(
  "SELECT od.quantity, od.price_at_time, od.subtotal, od.customization_note, pr.name
   FROM order_details od
   JOIN products pr ON od.product_id = pr.id
   WHERE od.order_id = ?"
);
$stmt->execute([$orderId]);
$items = $stmt->fetchAll();

// Pull in shared receipt styles as the extraHead argument
ob_start();
include __DIR__ . '/receipt_styles.php';
$receiptStyles = ob_get_clean();

layoutHeader('Receipt', $receiptStyles);
?>

<!-- Screen action bar (hidden when printing) -->
<div class="no-print" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:var(--space-5)">
  <div>
    <div style="font-size:1.15rem;font-weight:800;color:var(--text-color)">Order Receipt</div>
    <div style="font-size:0.78rem;color:var(--text-muted);margin-top:2px"><?= e($order['order_number']) ?></div>
  </div>
  <div style="display:flex;gap:10px">
    <button onclick="printReceipt()" class="btn btn-ghost">
      <i class="fa-solid fa-print"></i> Print
    </button>
    <a href="<?= APP_URL ?>/cashier/orders.php" class="btn btn-ghost">
      <i class="fa-solid fa-list"></i> Order History
    </a>
    <a href="<?= APP_URL ?>/cashier/walkin.php" class="btn btn-primary">
      <i class="fa-solid fa-plus"></i> New Order
    </a>
  </div>
</div>

<!-- Receipt paper — uses shared template -->
<div class="rcpt-print-root">
  <?php include __DIR__ . '/receipt_template.php'; ?>
</div>

<?php layoutFooter(); ?>

<script>
  function printReceipt() {
    const printUrl = '<?= APP_URL ?>/receipt_view.php?order_id=<?= $orderId ?>&format=print';
    const win = window.open(printUrl, '_blank', 'width=420,height=680');
    win?.focus();
  }
</script>
