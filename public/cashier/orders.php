<?php
require_once __DIR__ . '/../../config/init.php';
requireRole(ROLE_CASHIER);
$db = Database::getInstance();

// ── Redirect legacy receipt calls to shared endpoint ─────────────────
if (isset($_GET['receipt'])) {
  header('Location: ' . APP_URL . '/receipt_view.php?order_id=' . (int)$_GET['receipt']);
  exit;
}

// ── Load this cashier's orders ────────────────────────────────
$filter = $_GET['status'] ?? 'all';
$params = [currentUserId()];
$where  = '';
if ($filter !== 'all' && in_array($filter, ['pending', 'preparing', 'ready', 'claimed', 'cancelled'])) {
  $where    = ' AND o.status = ?';
  $params[] = $filter;
}

$stmt = $db->prepare(
  "SELECT o.*, p.payment_method, p.payment_status,
          COALESCE(s.full_name, 'Walk-in') AS customer
   FROM orders o
   LEFT JOIN payments p ON o.id = p.order_id
   LEFT JOIN students s ON o.student_id = s.id
   WHERE o.cashier_id = ? $where
   ORDER BY o.created_at DESC
   LIMIT 200"
);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Tab badge counts
$countStmt = $db->prepare(
  "SELECT status, COUNT(*) AS cnt FROM orders WHERE cashier_id = ? GROUP BY status"
);
$countStmt->execute([currentUserId()]);
$counts = [];
foreach ($countStmt->fetchAll() as $r) $counts[$r['status']] = $r['cnt'];
$counts['all'] = array_sum($counts);

layoutHeader('Order History');
?>
<style>
  /* ── Table row hover ───────────────────────────────── */
  .orders-row {
    cursor: pointer;
    transition: background var(--transition-fast);
  }

  .orders-row:hover {
    background: var(--primary-subtle) !important;
  }

  .orders-row:hover td {
    color: var(--primary-color) !important;
  }

  /* ── Receipt Modal chrome ──────────────────────────── */
  .receipt-overlay {
    position: fixed;
    inset: 0;
    z-index: 300;
    background: rgba(0, 0, 0, 0.55);
    backdrop-filter: blur(3px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: var(--space-5);
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.2s;
  }

  .receipt-overlay.open {
    opacity: 1;
    pointer-events: all;
  }

  .receipt-modal {
    background: var(--surface-color);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-xl);
    width: 100%;
    max-width: 440px;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    transform: scale(0.95) translateY(10px);
    transition: transform 0.22s cubic-bezier(0.34, 1.1, 0.64, 1);
  }

  .receipt-overlay.open .receipt-modal {
    transform: scale(1) translateY(0);
  }

  .receipt-modal-head {
    padding: var(--space-4) var(--space-5);
    border-bottom: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-shrink: 0;
    background: var(--surface-raised);
  }

  .receipt-modal-title {
    font-size: 0.88rem;
    font-weight: 800;
    color: var(--text-color);
    display: flex;
    align-items: center;
    gap: var(--space-2);
  }

  .receipt-modal-title i {
    color: var(--primary-color);
  }

  .receipt-modal-actions {
    display: flex;
    gap: var(--space-2);
  }

  .receipt-close {
    width: 28px;
    height: 28px;
    border-radius: var(--radius-full);
    background: var(--surface-color);
    border: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    color: var(--text-muted);
    font-size: 12px;
    transition: all var(--transition-fast);
  }

  .receipt-close:hover {
    background: var(--status-cancelled-bg);
    color: var(--status-cancelled);
  }

  .receipt-modal-body {
    flex: 1;
    overflow-y: auto;
    padding: var(--space-5);
  }

  .receipt-loading {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: var(--space-8);
    color: var(--text-muted);
    font-size: 0.84rem;
    gap: var(--space-3);
  }
</style>

<div class="page-header">
  <div>
    <div class="page-header-title">Order History</div>
    <div class="page-header-sub">
      <?= $counts['all'] ?? 0 ?> total orders · Click any row to view receipt
    </div>
  </div>
  <div class="page-header-actions">
    <a href="<?= APP_URL ?>/cashier/walkin.php" class="btn btn-primary btn-sm">
      <i class="fa-solid fa-plus"></i> New Order
    </a>
  </div>
</div>

<?php showFlash('global'); ?>

<!-- Status filter tabs -->
<div class="tab-bar mb-4">
  <?php foreach (
    [
      'all'       => 'All',
      'pending'   => 'Pending',
      'preparing' => 'Preparing',
      'ready'     => 'Ready',
      'claimed'   => 'Claimed',
      'cancelled' => 'Cancelled',
    ] as $s => $lbl
  ): ?>
    <a href="?status=<?= $s ?>" class="tab-btn <?= $filter === $s ? 'active' : '' ?>">
      <?= $lbl ?>
      <?php if (($counts[$s] ?? 0) > 0): ?>
        <span style="background:<?= $s === 'pending' ? 'var(--status-pending)' : ($s === 'all' ? 'var(--text-muted)' : 'var(--border-strong)') ?>;color:#fff;font-size:0.60rem;font-weight:700;padding:1px 6px;border-radius:var(--radius-full);margin-left:4px">
          <?= $counts[$s] ?>
        </span>
      <?php endif; ?>
    </a>
  <?php endforeach; ?>
</div>

<div class="card">
  <div style="overflow-x:auto">
    <table class="data-table">
      <thead>
        <tr>
          <th>Order No.</th>
          <th>Customer</th>
          <th>Type</th>
          <th class="num">Total</th>
          <th>Payment</th>
          <th>Status</th>
          <th>Date</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($orders)): ?>
          <tr>
            <td colspan="7">
              <div class="empty-state">
                <i class="fa-solid fa-receipt"></i>
                <h3>No orders yet</h3>
                <p>Orders you process will appear here.</p>
              </div>
            </td>
          </tr>
          <?php else: foreach ($orders as $o): ?>
            <tr class="orders-row" onclick="openReceipt(<?= $o['id'] ?>)" title="Click to view receipt">
              <td><strong><?= e($o['order_number']) ?></strong></td>
              <td><?= e($o['customer']) ?></td>
              <td><span class="badge badge-<?= $o['order_type'] === 'walk-in' ? 'walkin' : 'preorder' ?>"><?= e($o['order_type']) ?></span></td>
              <td class="num"><?= peso($o['total_amount']) ?></td>
              <td><?= e($o['payment_method'] ?? '—') ?></td>
              <td><span class="badge badge-<?= e($o['status']) ?>"><?= e($o['status']) ?></span></td>
              <td class="text-muted"><?= date('M j, Y g:i A', strtotime($o['created_at'])) ?></td>
            </tr>
        <?php endforeach;
        endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ── Receipt Modal ──────────────────────────────────── -->
<div class="receipt-overlay" id="receipt-overlay">
  <div class="receipt-modal">
    <div class="receipt-modal-head">
      <div class="receipt-modal-title">
        <i class="fa-solid fa-receipt"></i>
        <span id="receipt-modal-order-no">Receipt</span>
      </div>
      <div class="receipt-modal-actions">
        <button class="btn btn-ghost btn-sm no-print" onclick="printModal()">
          <i class="fa-solid fa-print"></i> Print
        </button>
        <button class="receipt-close" onclick="closeReceipt()" title="Close">
          <i class="fa-solid fa-xmark"></i>
        </button>
      </div>
    </div>
    <div class="receipt-modal-body" id="receipt-modal-body">
      <div class="receipt-loading">
        <i class="fa-solid fa-circle-notch fa-spin"></i> Loading…
      </div>
    </div>
  </div>
</div>

<script>
  const RECEIPT_URL = '<?= APP_URL ?>/receipt_view.php?order_id=';
  let currentOrderId = null;

  function openReceipt(orderId) {
    currentOrderId = orderId;
    const body = document.getElementById('receipt-modal-body');
    const title = document.getElementById('receipt-modal-order-no');
    body.innerHTML = '<div class="receipt-loading"><i class="fa-solid fa-circle-notch fa-spin"></i> Loading…</div>';
    title.textContent = 'Receipt';
    document.getElementById('receipt-overlay').classList.add('open');
    document.body.style.overflow = 'hidden';

    fetch(RECEIPT_URL + orderId, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      })
      .then(r => r.json())
      .then(data => {
        if (!data.success) {
          throw new Error(data.error || 'Failed to load');
        }
        body.innerHTML = data.html;
        title.textContent = data.order?.order_number || 'Receipt';
      })
      .catch(err => {
        body.innerHTML = '<div class="receipt-loading" style="color:var(--status-cancelled)"><i class="fa-solid fa-circle-exclamation"></i> ' + (err.message || 'Failed to load receipt.') + '</div>';
      });
  }

  function closeReceipt() {
    document.getElementById('receipt-overlay').classList.remove('open');
    document.body.style.overflow = '';
    currentOrderId = null;
  }

  function printModal() {
    if (!currentOrderId) return;
    const printUrl = `<?= APP_URL ?>/receipt_view.php?order_id=${currentOrderId}&format=print`;
    const win = window.open(printUrl, '_blank', 'width=420,height=680');
    win?.focus();
  }

  document.getElementById('receipt-overlay').addEventListener('click', function(e) {
    if (e.target === this) closeReceipt();
  });
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeReceipt();
  });
</script>

<?php layoutFooter(); ?>
