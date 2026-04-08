<?php
require_once __DIR__ . '/../../config/init.php';
requireRole(ROLE_CASHIER);
$db = Database::getInstance();

// ── AJAX: return receipt data as JSON ────────────────────────
if (isset($_GET['receipt'])) {
  $oid  = (int)$_GET['receipt'];
  // Cashier can only view their own orders
  $stmt = $db->prepare(
    "SELECT o.*, p.payment_method, p.amount_paid, p.change_given,
            c.full_name AS cashier_name,
            COALESCE(s.full_name, 'Walk-in') AS customer_name
     FROM orders o
     LEFT JOIN payments p  ON o.id = p.order_id
     LEFT JOIN cashiers c  ON o.cashier_id = c.id
     LEFT JOIN students s  ON o.student_id = s.id
     WHERE o.id = ? AND o.cashier_id = ?"
  );
  $stmt->execute([$oid, currentUserId()]);
  $order = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$order) {
    echo json_encode(['error' => 'Not found']);
    exit;
  }

  $stmt2 = $db->prepare(
    "SELECT od.quantity, od.price_at_time, od.subtotal,
            od.customization_note, pr.name
     FROM order_details od
     JOIN products pr ON od.product_id = pr.id
     WHERE od.order_id = ?"
  );
  $stmt2->execute([$oid]);
  $items = $stmt2->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode(['order' => $order, 'items' => $items]);
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

// Counts per status for the tab badges
$countStmt = $db->prepare(
  "SELECT status, COUNT(*) AS cnt FROM orders
   WHERE cashier_id = ? GROUP BY status"
);
$countStmt->execute([currentUserId()]);
$counts = [];
foreach ($countStmt->fetchAll() as $r) $counts[$r['status']] = $r['cnt'];
$counts['all'] = array_sum($counts);

layoutHeader('Order History');
?>
<style>
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

  /* ── Receipt Modal ─────────────────────────────────────────── */
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
    max-width: 400px;
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
  }

  .receipt-modal-head-title {
    font-size: 0.90rem;
    font-weight: 800;
    color: var(--text-color);
    display: flex;
    align-items: center;
    gap: var(--space-2);
  }

  .receipt-modal-head-title i {
    color: var(--primary-color);
  }

  .receipt-modal-head-actions {
    display: flex;
    gap: var(--space-2);
    align-items: center;
  }

  .receipt-close {
    width: 28px;
    height: 28px;
    border-radius: var(--radius-full);
    background: var(--surface-raised);
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

  .receipt-body {
    flex: 1;
    overflow-y: auto;
    padding: var(--space-5);
  }

  /* Receipt paper look */
  .receipt-paper {
    max-width: 320px;
    margin: 0 auto;
    font-family: 'Courier New', Courier, monospace;
  }

  .receipt-shop-name {
    text-align: center;
    font-family: 'DM Sans', sans-serif;
    font-size: 1.05rem;
    font-weight: 800;
    color: var(--primary-color);
    margin-bottom: 2px;
  }

  .receipt-tagline {
    text-align: center;
    font-size: 0.68rem;
    color: var(--text-muted);
    font-style: italic;
    margin-bottom: 12px;
  }

  .receipt-divider {
    border: none;
    border-top: 1px dashed var(--border-color);
    margin: 10px 0;
  }

  .receipt-meta {
    font-size: 0.76rem;
    color: var(--text-secondary);
    margin-bottom: 2px;
  }

  .receipt-meta strong {
    color: var(--text-color);
  }

  .receipt-item-row {
    display: flex;
    gap: 6px;
    font-size: 0.80rem;
    padding: 2px 0;
    align-items: baseline;
  }

  .receipt-item-name {
    flex: 1;
    color: var(--text-color);
  }

  .receipt-item-qty {
    color: var(--text-muted);
    font-size: 0.72rem;
    flex-shrink: 0;
  }

  .receipt-item-sub {
    font-weight: 600;
    flex-shrink: 0;
    text-align: right;
    min-width: 72px;
  }

  .receipt-item-note {
    font-size: 0.68rem;
    color: var(--text-muted);
    padding-left: 8px;
    margin-top: -1px;
  }

  .receipt-total-row {
    display: flex;
    justify-content: space-between;
    padding: 3px 0;
    font-size: 0.82rem;
  }

  .receipt-grand-total {
    display: flex;
    justify-content: space-between;
    padding: 8px 0 4px;
    font-size: 1.00rem;
    font-weight: 800;
    border-top: 1.5px solid var(--border-color);
    margin-top: 4px;
    color: var(--text-color);
  }

  .receipt-footer {
    text-align: center;
    font-size: 0.68rem;
    color: var(--text-muted);
    margin-top: 12px;
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

  @media print {

    .receipt-overlay,
    .no-print {
      display: none !important;
    }
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
  <?php foreach (['all' => 'All', 'pending' => 'Pending', 'preparing' => 'Preparing', 'ready' => 'Ready', 'claimed' => 'Claimed', 'cancelled' => 'Cancelled'] as $s => $lbl): ?>
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

<!-- ── Receipt Modal ──────────────────────────────────────────── -->
<div class="receipt-overlay" id="receipt-overlay">
  <div class="receipt-modal">
    <div class="receipt-modal-head">
      <div class="receipt-modal-head-title">
        <i class="fa-solid fa-receipt"></i> Receipt
      </div>
      <div class="receipt-modal-head-actions">
        <button class="btn btn-ghost btn-sm no-print" onclick="printReceipt()">
          <i class="fa-solid fa-print"></i> Print
        </button>
        <button class="receipt-close" onclick="closeReceipt()">
          <i class="fa-solid fa-xmark"></i>
        </button>
      </div>
    </div>
    <div class="receipt-body" id="receipt-body">
      <div class="receipt-loading">
        <i class="fa-solid fa-circle-notch fa-spin"></i> Loading…
      </div>
    </div>
  </div>
</div>

<script>
  const RECEIPT_URL = '<?= APP_URL ?>/cashier/orders.php?receipt=';

  function openReceipt(orderId) {
    document.getElementById('receipt-body').innerHTML =
      '<div class="receipt-loading"><i class="fa-solid fa-circle-notch fa-spin"></i> Loading…</div>';
    document.getElementById('receipt-overlay').classList.add('open');
    document.body.style.overflow = 'hidden';

    fetch(RECEIPT_URL + orderId)
      .then(r => r.json())
      .then(data => {
        if (data.error) {
          document.getElementById('receipt-body').innerHTML =
            '<div class="receipt-loading" style="color:var(--status-cancelled)">' +
            '<i class="fa-solid fa-circle-exclamation"></i> ' + data.error + '</div>';
          return;
        }
        renderReceipt(data.order, data.items);
      })
      .catch(() => {
        document.getElementById('receipt-body').innerHTML =
          '<div class="receipt-loading" style="color:var(--status-cancelled)">Failed to load receipt.</div>';
      });
  }

  function closeReceipt() {
    document.getElementById('receipt-overlay').classList.remove('open');
    document.body.style.overflow = '';
  }

  function printReceipt() {
    const body = document.getElementById('receipt-body').innerHTML;
    const win = window.open('', '_blank', 'width=420,height=680');
    win.document.write(`<!DOCTYPE html><html><head>
    <title>Receipt</title>
    <style>
      body { font-family: 'Courier New', monospace; padding: 16px; max-width: 320px; margin: 0 auto; }
      hr   { border: none; border-top: 1px dashed #ccc; margin: 10px 0; }
      .receipt-item-row { display: flex; gap: 6px; font-size: 13px; padding: 2px 0; }
      .receipt-item-name { flex: 1; }
      .receipt-item-qty  { color: #888; font-size: 11px; }
      .receipt-item-sub  { font-weight: 600; min-width: 72px; text-align: right; }
      .receipt-item-note { font-size: 11px; color: #888; padding-left: 8px; }
      .receipt-total-row { display: flex; justify-content: space-between; font-size: 13px; padding: 3px 0; }
      .receipt-grand-total { display: flex; justify-content: space-between; font-size: 16px; font-weight: 800; border-top: 1.5px solid #333; padding-top: 8px; margin-top: 4px; }
      .receipt-shop-name { text-align: center; font-size: 16px; font-weight: 800; color: #c0392b; }
      .receipt-tagline { text-align: center; font-size: 11px; color: #888; font-style: italic; }
      .receipt-meta { font-size: 12px; color: #555; margin-bottom: 2px; }
      .receipt-footer { text-align: center; font-size: 11px; color: #888; margin-top: 12px; }
    </style></head><body>${body}</body></html>`);
    win.document.close();
    win.focus();
    setTimeout(() => {
      win.print();
      win.close();
    }, 400);
  }

  function renderReceipt(o, items) {
    const fmt = n => '₱' + parseFloat(n || 0).toFixed(2);
    const esc = s => String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

    let itemsHtml = '';
    items.forEach(item => {
      itemsHtml += `
      <div class="receipt-item-row">
        <span class="receipt-item-name">${esc(item.name)}</span>
        <span class="receipt-item-qty">×${item.quantity}</span>
        <span class="receipt-item-sub">${fmt(item.subtotal)}</span>
      </div>`;
      if (item.customization_note) {
        itemsHtml += `<div class="receipt-item-note">${esc(item.customization_note)}</div>`;
      }
    });

    const paySection = o.payment_method === 'cash' ?
      `<div class="receipt-total-row">
         <span style="color:var(--text-secondary)">Cash Received</span>
         <span style="font-weight:600">${fmt(o.amount_paid)}</span>
       </div>
       <div class="receipt-total-row">
         <span style="color:var(--text-secondary)">Change</span>
         <span style="font-weight:600">${fmt(o.change_given)}</span>
       </div>` :
      `<div class="receipt-total-row">
         <span style="color:var(--text-secondary)">Ref. No.</span>
         <span>${esc(o.reference_number || '—')}</span>
       </div>`;

    document.getElementById('receipt-body').innerHTML = `
    <div class="receipt-paper">
      <div style="text-align:center;font-size:24px;color:var(--primary-color);margin-bottom:6px">
        <i class="fa-solid fa-mug-hot"></i>
      </div>
      <div class="receipt-shop-name"><?= e(APP_NAME) ?></div>
      <div class="receipt-tagline"><?= e(APP_TAGLINE) ?></div>
      <hr class="receipt-divider">
      <div class="receipt-meta"><strong>Order No.:</strong> ${esc(o.order_number)}</div>
      <div class="receipt-meta"><strong>Type:</strong> ${esc(o.order_type)}</div>
      <div class="receipt-meta"><strong>Customer:</strong> ${esc(o.customer_name)}</div>
      <div class="receipt-meta"><strong>Cashier:</strong> ${esc(o.cashier_name || '—')}</div>
      <div class="receipt-meta"><strong>Date:</strong> ${new Date(o.created_at).toLocaleString('en-PH',{dateStyle:'medium',timeStyle:'short'})}</div>
      <hr class="receipt-divider">
      ${itemsHtml}
      <hr class="receipt-divider">
      <div class="receipt-grand-total">
        <span>TOTAL</span><span>${fmt(o.total_amount)}</span>
      </div>
      ${paySection}
      <div class="receipt-total-row">
        <span style="color:var(--text-secondary)">Payment</span>
        <span>${esc((o.payment_method||'').replace(/^\w/,c=>c.toUpperCase()))}</span>
      </div>
      <hr class="receipt-divider">
      <div class="receipt-footer">
        <div>Thank you for your purchase!</div>
        <div style="margin-top:4px">EARIST Cavite Campus — GMA, Cavite</div>
      </div>
    </div>`;
  }

  // Close on backdrop click
  document.getElementById('receipt-overlay').addEventListener('click', function(e) {
    if (e.target === this) closeReceipt();
  });

  // Close on Escape
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeReceipt();
  });
</script>

<?php layoutFooter(); ?>