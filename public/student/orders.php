<?php
require_once __DIR__ . '/../../config/init.php';
requireRole(ROLE_STUDENT);
$db = Database::getInstance();

// ── AJAX endpoint: return order items as JSON ─────────────────
if (isset($_GET['get_order_items'])) {
  header('Content-Type: application/json');
  
  $oid  = (int)$_GET['get_order_items'];
  // Verify order belongs to current student
  $chk = $db->prepare("SELECT id FROM orders WHERE id=? AND student_id=?");
  $chk->execute([$oid, currentUserId()]);
  if (!$chk->fetch()) {
    echo json_encode(['items' => []]);
    exit;
  }

  $stmt = $db->prepare(
    "SELECT od.product_id, p.name, p.image_path, od.customization_note AS note
     FROM order_details od
     JOIN products p ON od.product_id = p.id
     WHERE od.order_id = ?"
  );
  $stmt->execute([$oid]);
  echo json_encode(['items' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
  exit;
}

// ── Handle feedback submission ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
  verifyCsrf();
  $orderId       = (int)($_POST['order_id']     ?? 0);
  $cashierRating = (int)($_POST['rating']        ?? 0);
  $comment       = sanitizeString($_POST['comment'] ?? '', 500);
  $productRatings = $_POST['product_rating'] ?? [];   // array: [product_id => rating]

  if ($cashierRating < 1 || $cashierRating > 5) {
    flash('global', 'Please select an overall star rating.', 'error');
    redirect(APP_URL . '/student/orders.php');
  }

  // Verify order belongs to student and is claimed
  $stmt = $db->prepare(
    "SELECT o.id, o.cashier_id FROM orders o
      WHERE o.id = ? AND o.student_id = ? AND o.status = 'claimed'"
  );
  $stmt->execute([$orderId, currentUserId()]);
  $order = $stmt->fetch();
  if (!$order) {
    flash('global', 'Order not found or not yet claimed.', 'error');
    redirect(APP_URL . '/student/orders.php');
  }

  // Prevent double-rating
  $stmt = $db->prepare("SELECT id FROM order_feedback WHERE order_id = ?");
  $stmt->execute([$orderId]);
  if ($stmt->fetch()) {
    flash('global', 'You have already rated this order.', 'error');
    redirect(APP_URL . '/student/orders.php');
  }

  // Load the products in this order for validation
  $stmt = $db->prepare(
    "SELECT od.product_id FROM order_details od WHERE od.order_id = ?"
  );
  $stmt->execute([$orderId]);
  $validProductIds = array_column($stmt->fetchAll(), 'product_id');

  $db->beginTransaction();
  try {
    // 1. Save order-level (cashier) feedback
    $db->prepare(
      "INSERT INTO order_feedback (order_id, student_id, cashier_id, rating, comment)
       VALUES (?, ?, ?, ?, ?)"
    )->execute([$orderId, currentUserId(), $order['cashier_id'] ?: null, $cashierRating, $comment ?: null]);
    $feedbackId = (int)$db->lastInsertId();

    // 2. Save per-product ratings (only for products actually in this order)
    if (!empty($productRatings)) {
      $pstmt = $db->prepare(
        "INSERT INTO product_ratings (feedback_id, order_id, product_id, student_id, rating)
         VALUES (?, ?, ?, ?, ?)"
      );
      foreach ($productRatings as $pid => $prating) {
        $pid     = (int)$pid;
        $prating = (int)$prating;
        if (!in_array($pid, $validProductIds, true)) continue;
        if ($prating < 1 || $prating > 5) continue;
        $pstmt->execute([$feedbackId, $orderId, $pid, currentUserId(), $prating]);
      }
    }

    $db->commit();
    flash('global', 'Thank you for your feedback!', 'success');
  } catch (\Throwable $e) {
    $db->rollBack();
    error_log($e->getMessage());
    flash('global', 'Could not save feedback. Please try again.', 'error');
  }
  redirect(APP_URL . '/student/orders.php');
}

// ── Load orders ───────────────────────────────────────────────
$filter = $_GET['status'] ?? 'all';
$params = [currentUserId()];
$where  = '';
if ($filter !== 'all') {
  $where = ' AND o.status = ?';
  $params[] = $filter;
}

$stmt = $db->prepare(
  "SELECT o.*, p.payment_method, p.payment_status,
          f.id AS feedback_id, f.rating AS feedback_rating
   FROM orders o
   LEFT JOIN payments p       ON o.id = p.order_id
   LEFT JOIN order_feedback f ON o.id = f.order_id
   WHERE o.student_id = ? $where
   ORDER BY o.created_at DESC"
);
$stmt->execute($params);
$orders = $stmt->fetchAll();

layoutHeader('My Orders');
?>
<style>
  /* ── Star rating input (overall) ─────────────────────────── */
  .star-group {
    display: flex;
    flex-direction: row-reverse;
    gap: 4px;
    justify-content: flex-end;
  }

  .star-group input {
    display: none;
  }

  .star-group label {
    font-size: 1.80rem;
    color: var(--border-strong);
    cursor: pointer;
    transition: color var(--transition-fast);
    line-height: 1;
  }

  .star-group input:checked~label,
  .star-group label:hover,
  .star-group label:hover~label {
    color: var(--accent-color);
  }

  .star-group input:checked~label {
    color: var(--accent-color);
  }

  /* ── Per-product star rating ─────────────────────────────── */
  .product-star-group {
    display: flex;
    flex-direction: row-reverse;
    gap: 2px;
    justify-content: flex-end;
  }

  .product-star-group input {
    display: none;
  }

  .product-star-group label {
    font-size: 1.20rem;
    color: var(--border-strong);
    cursor: pointer;
    transition: color var(--transition-fast);
    line-height: 1;
  }

  .product-star-group input:checked~label,
  .product-star-group label:hover,
  .product-star-group label:hover~label {
    color: var(--accent-color);
  }

  .product-star-group input:checked~label {
    color: var(--accent-color);
  }

  /* Product rating card inside modal */
  .product-rate-card {
    display: flex;
    align-items: center;
    gap: var(--space-3);
    padding: var(--space-3) var(--space-3);
    border: 1.5px solid var(--border-color);
    border-radius: var(--radius-sm);
    background: var(--surface-raised);
    margin-bottom: var(--space-2);
    transition: border-color var(--transition-fast);
  }

  .product-rate-card:has(.product-star-group input:checked) {
    border-color: var(--accent-color);
    background: var(--accent-subtle);
  }

  .product-rate-thumb {
    width: 44px;
    height: 44px;
    flex-shrink: 0;
    border-radius: var(--radius-xs);
    overflow: hidden;
    background: var(--surface-sunken);
    border: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .product-rate-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }

  .product-rate-thumb i {
    font-size: 16px;
    color: var(--border-strong);
  }

  .product-rate-info {
    flex: 1;
    min-width: 0;
  }

  .product-rate-name {
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--text-color);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .product-rate-note {
    font-size: 0.68rem;
    color: var(--text-muted);
    margin-top: 1px;
  }

  /* Section dividers in modal */
  .fb-section-label {
    font-size: 0.68rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.10em;
    color: var(--text-muted);
    margin: var(--space-4) 0 var(--space-2);
    display: flex;
    align-items: center;
    gap: var(--space-3);
  }

  .fb-section-label::after {
    content: '';
    flex: 1;
    height: 1px;
    background: var(--border-color);
  }

  /* Star display (read-only) */
  .star-display {
    color: var(--accent-color);
    font-size: 0.80rem;
    letter-spacing: 1px;
  }

  .star-display .empty {
    color: var(--border-strong);
  }

  /* Rate button */
  .btn-rate {
    height: 28px;
    padding: 0 10px;
    border: 1.5px solid var(--accent-color);
    border-radius: var(--radius-full);
    background: var(--accent-subtle);
    color: var(--accent-dark);
    font-size: 0.72rem;
    font-weight: 700;
    font-family: inherit;
    cursor: pointer;
    transition: all var(--transition-fast);
    display: inline-flex;
    align-items: center;
    gap: 4px;
  }

  .btn-rate:hover {
    background: var(--accent-color);
    color: var(--text-on-accent);
  }
</style>

<div class="page-header">
  <div>
    <div class="page-header-title">My Orders</div>
    <div class="page-header-sub"><?= count($orders) ?> order<?= count($orders) !== 1 ? 's' : '' ?> found</div>
  </div>
  <div class="page-header-actions">
    <a href="<?= APP_URL ?>/student/menu.php" class="btn btn-primary btn-sm">
      <i class="fa-solid fa-plus"></i> New Order
    </a>
  </div>
</div>
<?php showFlash('global'); ?>

<div class="tab-bar mb-4">
  <?php foreach (['all', 'pending', 'preparing', 'ready', 'claimed', 'cancelled'] as $s): ?>
    <a href="?status=<?= $s ?>" class="tab-btn <?= $filter === $s ? 'active' : '' ?>"><?= ucfirst($s) ?></a>
  <?php endforeach; ?>
</div>

<div class="card">
  <div style="overflow-x:auto">
    <table class="data-table">
      <thead>
        <tr>
          <th>Order No.</th>
          <th class="num">Total</th>
          <th>Payment</th>
          <th>Status</th>
          <th>Date</th>
          <th>Rating</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($orders)): ?>
          <tr>
            <td colspan="7">
              <div class="empty-state">
                <i class="fa-solid fa-receipt"></i>
                <h3>No orders yet</h3>
                <p><a href="<?= APP_URL ?>/student/menu.php" style="color:var(--primary-color);font-weight:600">Browse the menu →</a></p>
              </div>
            </td>
          </tr>
          <?php else: foreach ($orders as $o): ?>
            <tr>
              <td><strong><?= e($o['order_number']) ?></strong></td>
              <td class="num"><?= peso($o['total_amount']) ?></td>
              <td><?= e($o['payment_method'] ?? '—') ?></td>
              <td><span class="badge badge-<?= e($o['status']) ?>"><?= e($o['status']) ?></span></td>
              <td class="text-muted"><?= date('M j, Y', strtotime($o['created_at'])) ?></td>
              <td>
                <?php if ($o['feedback_rating']): ?>
                  <span class="star-display">
                    <?php for ($s = 1; $s <= 5; $s++): ?>
                      <span class="<?= $s <= $o['feedback_rating'] ? '' : 'empty' ?>">★</span>
                    <?php endfor; ?>
                  </span>
                <?php else: ?>
                  <span style="color:var(--text-muted);font-size:0.74rem">—</span>
                <?php endif; ?>
              </td>
              <td style="display:flex;gap:6px;align-items:center">
                <?php if ($o['status'] === STATUS_CLAIMED && !$o['feedback_id']): ?>
                  <button class="btn-rate" onclick="openFeedback(<?= $o['id'] ?>, '<?= e($o['order_number']) ?>')">
                    <i class="fa-solid fa-star"></i> Rate
                  </button>
                <?php endif; ?>
                <?php if ($o['payment_status'] === PAY_STATUS_PAID && $o['status'] === STATUS_CANCELLED): ?>
                  <a href="refund.php?order=<?= $o['id'] ?>" class="btn btn-ghost btn-sm">
                    <i class="fa-solid fa-rotate-left"></i> Refund
                  </a>
                <?php endif; ?>
              </td>
            </tr>
        <?php endforeach;
        endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ── Feedback Modal ──────────────────────────────────────── -->
<div class="modal-overlay hidden" id="feedback-modal">
  <div class="modal" style="max-width:480px">
    <div class="modal-header">
      <div class="modal-title"><i class="fa-solid fa-star"></i> Rate Your Order</div>
      <button class="modal-close" onclick="closeFeedback()"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="POST" id="feedback-form">
      <?= csrfField() ?>
      <input type="hidden" name="submit_feedback" value="1">
      <input type="hidden" name="order_id" id="fb-order-id">
      <div class="modal-body" style="max-height:70vh;overflow-y:auto">

        <p id="fb-order-label" style="font-size:0.84rem;color:var(--text-muted);margin-bottom:var(--space-4)"></p>

        <!-- Per-product ratings (populated by JS) -->
        <div id="fb-products-section" style="display:none">
          <div class="fb-section-label">Rate the Products</div>
          <div id="fb-product-cards"></div>
        </div>

        <!-- Overall / cashier rating -->
        <div class="fb-section-label">Overall Experience</div>
        <div class="form-group" style="text-align:center">
          <label class="form-label" style="display:block;margin-bottom:var(--space-3)">
            How was the service?
          </label>
          <div class="star-group" id="star-group">
            <?php for ($s = 5; $s >= 1; $s--): ?>
              <input type="radio" name="rating" id="star<?= $s ?>" value="<?= $s ?>" required>
              <label for="star<?= $s ?>" title="<?= $s ?> star<?= $s > 1 ? 's' : '' ?>">★</label>
            <?php endfor; ?>
          </div>
          <div id="star-label" style="font-size:0.78rem;color:var(--text-muted);margin-top:8px;min-height:18px"></div>
        </div>

        <div class="form-group">
          <label class="form-label">Comment <span style="font-weight:400;color:var(--text-muted)">(optional)</span></label>
          <textarea name="comment" class="form-control" rows="2"
            placeholder="Tell us about your order — quality, speed, service…"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeFeedback()">Cancel</button>
        <button type="submit" class="btn btn-primary">
          <i class="fa-solid fa-paper-plane"></i> Submit Feedback
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Product image map for the rating modal thumbnails -->
<script>
  const productImgBase = '<?= $imgBase = APP_URL . "/../uploads/products/" ?>';
</script>

<script>
  const starLabels = ['', 'Terrible 😞', 'Poor 😕', 'Okay 😐', 'Good 😊', 'Excellent 🤩'];

  // Fetch order's products from server to populate the modal
  async function openFeedback(orderId, orderNo) {
    document.getElementById('fb-order-id').value = orderId;
    document.getElementById('fb-order-label').textContent = 'Order ' + orderNo;
    document.querySelectorAll('#star-group input').forEach(r => r.checked = false);
    document.getElementById('star-label').textContent = '';
    document.getElementById('fb-product-cards').innerHTML = '';
    document.getElementById('fb-products-section').style.display = 'none';
    document.getElementById('feedback-modal').classList.remove('hidden');

    // Load products for this order via inline PHP endpoint (same page, GET param)
    try {
      const res = await fetch('<?= APP_URL ?>/student/orders.php?get_order_items=' + orderId + '&csrf=<?= csrfToken() ?>');
      const data = await res.json();
      if (data.items && data.items.length > 0) {
        renderProductCards(data.items);
        document.getElementById('fb-products-section').style.display = 'block';
      }
    } catch (e) {
      /* silently skip product cards on error */ }
  }

  function renderProductCards(items) {
    const container = document.getElementById('fb-product-cards');
    container.innerHTML = '';
    items.forEach(item => {
      const pid = item.product_id;
      const name = item.name;
      const note = item.note || '';
      const img = item.image_path ?
        `<img src="${productImgBase}${item.image_path}" alt="${name}">` :
        `<i class="fa-solid fa-mug-hot"></i>`;

      // Build 5-star input set for this product
      let stars = '';
      for (let s = 5; s >= 1; s--) {
        stars += `<input type="radio" name="product_rating[${pid}]" id="pstar-${pid}-${s}" value="${s}">`;
        stars += `<label for="pstar-${pid}-${s}" title="${s} star">★</label>`;
      }

      container.innerHTML +=
        `<div class="product-rate-card">
        <div class="product-rate-thumb">${img}</div>
        <div class="product-rate-info">
          <div class="product-rate-name">${name}</div>
          ${note ? `<div class="product-rate-note">${note}</div>` : ''}
          <div class="product-star-group">${stars}</div>
        </div>
      </div>`;
    });
  }

  function closeFeedback() {
    document.getElementById('feedback-modal').classList.add('hidden');
  }

  document.querySelectorAll('#star-group input').forEach(radio => {
    radio.addEventListener('change', () => {
      document.getElementById('star-label').textContent = starLabels[radio.value] || '';
    });
  });

  document.getElementById('feedback-modal').addEventListener('click', e => {
    if (e.target === e.currentTarget) closeFeedback();
  });
</script>

<?php 
layoutFooter(); 
?>