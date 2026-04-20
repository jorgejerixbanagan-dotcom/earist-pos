<?php
require_once __DIR__ . '/../../config/init.php';
requireRole(ROLE_ADMIN);

$db = Database::getInstance();

// ---- Handle DELETE ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_method']) && $_POST['_method'] === 'DELETE') {
  verifyCsrf();
  $id = (int)$_POST['product_id'];

  // **NEW: Delete related order details first**
  $db->prepare("DELETE od FROM order_details od WHERE od.product_id = ?")->execute([$id]);

  // Delete the old image file if it exists
  $stmt = $db->prepare("SELECT image_path FROM products WHERE id=?");
  $stmt->execute([$id]);
  $row = $stmt->fetch();
  if ($row && $row['image_path'] && file_exists(UPLOAD_DIR . $row['image_path'])) {
    unlink(UPLOAD_DIR . $row['image_path']);
  }
  $db->prepare("DELETE FROM products WHERE id=?")->execute([$id]);
  auditLog(ROLE_ADMIN, currentUserId(), 'delete_product', 'products', $id);
  flash('global', 'Product deleted.', 'success');
  redirect(APP_URL . '/admin/products.php');
}

// ---- Handle TOGGLE availability ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_id'])) {
  verifyCsrf();
  $id = (int)$_POST['toggle_id'];
  $db->prepare("UPDATE products SET is_available = NOT is_available WHERE id=?")->execute([$id]);
  flash('global', 'Product availability updated.', 'success');
  redirect(APP_URL . '/admin/products.php');
}

// ---- Handle ADD / EDIT ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_product'])) {
  verifyCsrf();

  $pid      = (int)($_POST['product_id'] ?? 0);
  $name     = sanitizeString($_POST['name'] ?? '');
  $catId    = (int)($_POST['category_id'] ?? 0);
  $price    = round((float)($_POST['price'] ?? 0), 2);
  $desc     = sanitizeString($_POST['description'] ?? '', 500);
  $hasSizes  = isset($_POST['has_sizes'])  ? 1 : 0;
  $hasSugar  = isset($_POST['has_sugar'])  ? 1 : 0;
  $hasAddons = isset($_POST['has_addons']) ? 1 : 0;

  if (empty($name) || $catId < 1 || $price <= 0) {
    flash('global', 'Please fill in all required fields.', 'error');
    redirect(APP_URL . '/admin/products.php');
  }

  // ---- Handle image upload ----
  $newImageName = null; // null means "no change"

  if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
    $file     = $_FILES['product_image'];
    $maxSize  = UPLOAD_MAX_SIZE; // 2MB defined in constants.php
    $allowed  = ['image/jpeg', 'image/png', 'image/webp'];

    // Validate file size
    if ($file['size'] > $maxSize) {
      flash('global', 'Image is too large. Maximum size is 2MB.', 'error');
      redirect(APP_URL . '/admin/products.php');
    }

    // Validate MIME type using finfo (more reliable than just checking extension)
    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    if (!in_array($mimeType, $allowed, true) || !getimagesize($file['tmp_name'])) {
      flash('global', 'Invalid image type. Only JPG, PNG, and WEBP are allowed.', 'error');
      redirect(APP_URL . '/admin/products.php');
    }

    // Generate a safe random filename — never use the original filename
    $ext          = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mimeType];
    $newImageName = bin2hex(random_bytes(16)) . '.' . $ext;

    // Make sure the uploads directory exists
    if (!is_dir(UPLOAD_DIR)) {
      mkdir(UPLOAD_DIR, 0755, true);
    }

    // Move the uploaded file from the temp folder to our uploads folder
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $newImageName)) {
      flash('global', 'Failed to save image. Please try again.', 'error');
      redirect(APP_URL . '/admin/products.php');
    }

    // If editing, delete the OLD image so we don't accumulate unused files
    if ($pid > 0) {
      $stmt = $db->prepare("SELECT image_path FROM products WHERE id=?");
      $stmt->execute([$pid]);
      $old = $stmt->fetchColumn();
      if ($old && file_exists(UPLOAD_DIR . $old)) {
        unlink(UPLOAD_DIR . $old);
      }
    }
  }

  if ($pid > 0) {
    // Edit existing product
    if ($newImageName) {
      // Update with new image
      $db->prepare("UPDATE products SET name=?,category_id=?,price=?,description=?,image_path=?,has_sizes=?,has_sugar=?,has_addons=? WHERE id=?")
        ->execute([$name, $catId, $price, $desc, $newImageName, $hasSizes, $hasSugar, $hasAddons, $pid]);
    } else {
      // Keep the existing image
      $db->prepare("UPDATE products SET name=?,category_id=?,price=?,description=?,has_sizes=?,has_sugar=?,has_addons=? WHERE id=?")
        ->execute([$name, $catId, $price, $desc, $hasSizes, $hasSugar, $hasAddons, $pid]);
    }
    auditLog(ROLE_ADMIN, currentUserId(), 'edit_product', 'products', $pid);
    flash('global', 'Product updated.', 'success');
  } else {
    // Add new product
    $db->prepare("INSERT INTO products (name,category_id,price,description,image_path,has_sizes,has_sugar,has_addons) VALUES (?,?,?,?,?,?,?,?)")
      ->execute([$name, $catId, $price, $desc, $newImageName, $hasSizes, $hasSugar, $hasAddons]);
    $newId = (int)$db->lastInsertId();
    auditLog(ROLE_ADMIN, currentUserId(), 'add_product', 'products', $newId);
    flash('global', 'Product added.', 'success');
  }

  redirect(APP_URL . '/admin/products.php');
}

// ---- Handle ADD CATEGORY WITH PRODUCTS ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_category_with_products'])) {
  verifyCsrf();

  $catName = sanitizeString($_POST['category_name'] ?? '');
  $parentId = null;

  // Handle parent category selection
  $parentSelection = $_POST['parent_id_select'] ?? '';
  $customParentName = sanitizeString($_POST['custom_parent_name'] ?? '');

  if (!empty($parentSelection) && $parentSelection !== 'custom') {
    // User selected an existing category
    $parentId = (int)$parentSelection;
  } elseif ($parentSelection === 'custom' && !empty($customParentName)) {
    // User wants to create a new top-level menu
    // Check if it exists
    $stmt = $db->prepare("SELECT id FROM categories WHERE name = ? AND parent_id IS NULL");
    $stmt->execute([$customParentName]);
    $existing = $stmt->fetchColumn();

    if ($existing) {
      $parentId = $existing;
    } else {
      // Create the custom menu category
      $stmt = $db->prepare("SELECT COALESCE(MAX(sort_order), 0) FROM categories WHERE parent_id IS NULL");
      $sort = (int)$stmt->fetchColumn() + 1;
      $db->prepare("INSERT INTO categories (name, parent_id, sort_order) VALUES (?, NULL, ?)")
        ->execute([$customParentName, $sort]);
      $parentId = (int)$db->lastInsertId();
    }
  }

  // Require category name
  if (empty($catName)) {
    flash('global', 'Category Menu name is required.', 'error');
    redirect(APP_URL . '/admin/products.php');
  }

  // Get sort order for the new subcategory
  $stmt = $db->prepare("SELECT COALESCE(MAX(sort_order), 0) FROM categories WHERE parent_id = ?");
  $stmt->execute([$parentId]);
  $sortOrder = (int)$stmt->fetchColumn() + 1;

  // Insert category (allow empty name)
  $db->prepare("INSERT INTO categories (name, parent_id, sort_order) VALUES (?, ?, ?)")
    ->execute([$catName, $parentId, $sortOrder]);
  $catId = (int)$db->lastInsertId();
  auditLog(ROLE_ADMIN, currentUserId(), 'add_category', 'categories', $catId);

  // Now handle products
  $productNames = $_POST['product_name'] ?? [];
  if (empty(array_filter($productNames))) {
    flash('global', 'Please add at least one product.', 'error');
    redirect(APP_URL . '/admin/products.php');
  }
  $productPrices = $_POST['product_price'] ?? [];
  $productDescs = $_POST['product_desc'] ?? [];
  $hasSizesArr = $_POST['product_has_sizes'] ?? [];
  $hasSugarArr = $_POST['product_has_sugar'] ?? [];
  $hasAddonsArr = $_POST['product_has_addons'] ?? [];

  $numProducts = count($productNames);
  for ($i = 0; $i < $numProducts; $i++) {
    $pName = sanitizeString($productNames[$i] ?? '');
    $pPrice = round((float)($productPrices[$i] ?? 0), 2);
    $pDesc = sanitizeString($productDescs[$i] ?? '', 500);
    $pHasSizes = isset($_POST["product_has_sizes_$i"]) ? 1 : 0;
    $pHasSugar = isset($_POST["product_has_sugar_$i"]) ? 1 : 0;
    $pHasAddons = isset($_POST["product_has_addons_$i"]) ? 1 : 0;

    if (!empty($pName) && $pPrice > 0) {
      // Handle image for this product
      $pImageName = null;
      if (isset($_FILES['product_image']['name'][$i]) && $_FILES['product_image']['error'][$i] === UPLOAD_ERR_OK) {
        $file = [
          'name' => $_FILES['product_image']['name'][$i],
          'type' => $_FILES['product_image']['type'][$i],
          'tmp_name' => $_FILES['product_image']['tmp_name'][$i],
          'error' => $_FILES['product_image']['error'][$i],
          'size' => $_FILES['product_image']['size'][$i]
        ];
        $maxSize = UPLOAD_MAX_SIZE;
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        if ($file['size'] > $maxSize) {
          flash('global', 'One of the images is too large (max 2MB).', 'error');
          redirect(APP_URL . '/admin/products.php');
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        if (!in_array($mimeType, $allowed, true) || !getimagesize($file['tmp_name'])) {
          flash('global', 'One of the product images is invalid.', 'error');
          redirect(APP_URL . '/admin/products.php');
        }
        $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mimeType];
        $pImageName = bin2hex(random_bytes(16)) . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $pImageName)) {
          $pImageName = null;
        }
      }

      $db->prepare("INSERT INTO products (name, category_id, price, description, image_path, has_sizes, has_sugar, has_addons) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
        ->execute([$pName, $catId, $pPrice, $pDesc, $pImageName, $pHasSizes, $pHasSugar, $pHasAddons]);
      $pId = (int)$db->lastInsertId();
      auditLog(ROLE_ADMIN, currentUserId(), 'add_product', 'products', $pId);
    }
  }

  flash('global', 'Category and products added successfully.', 'success');
  redirect(APP_URL . '/admin/products.php');
}

// ---- Handle DELETE CATEGORY ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_method']) && $_POST['_method'] === 'DELETE_CATEGORY') {
  verifyCsrf();
  $catId = (int)($_POST['category_id'] ?? 0);

  if ($catId < 1) {
    flash('global', 'Invalid category selected.', 'error');
    redirect(APP_URL . '/admin/products.php');
  }

  $categoryIds = [$catId];
  $queue = [$catId];
  while (!empty($queue)) {
    $current = array_shift($queue);
    $stmt = $db->prepare("SELECT id FROM categories WHERE parent_id = ?");
    $stmt->execute([$current]);
    $children = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($children as $childId) {
      $categoryIds[] = $childId;
      $queue[] = $childId;
    }
  }

  $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
  $stmt = $db->prepare("SELECT image_path FROM products WHERE category_id IN ($placeholders)");
  $stmt->execute($categoryIds);
  $images = $stmt->fetchAll(PDO::FETCH_COLUMN);

  foreach ($images as $img) {
    if ($img && file_exists(UPLOAD_DIR . $img)) {
      unlink(UPLOAD_DIR . $img);
    }
  }

  // **NEW: Delete order details FIRST**
  $stmt = $db->prepare("DELETE od FROM order_details od 
                      JOIN products p ON od.product_id = p.id 
                      WHERE p.category_id IN ($placeholders)");
  $stmt->execute($categoryIds);

  // Delete product images
  $stmt = $db->prepare("SELECT image_path FROM products WHERE category_id IN ($placeholders)");
  $stmt->execute($categoryIds);
  $images = $stmt->fetchAll(PDO::FETCH_COLUMN);
  foreach ($images as $img) {
    if ($img && file_exists(UPLOAD_DIR . $img)) {
      unlink(UPLOAD_DIR . $img);
    }
  }

  // Now delete products (safe after order details are gone)
  $db->prepare("DELETE FROM products WHERE category_id IN ($placeholders)")->execute($categoryIds);

  // Finally delete categories
  $db->prepare("DELETE FROM categories WHERE id IN ($placeholders)")->execute($categoryIds);

  auditLog(ROLE_ADMIN, currentUserId(), 'delete_category', 'categories', $catId);
  flash('global', 'Category and its contents deleted.', 'success');
  redirect(APP_URL . '/admin/products.php');
}

// ---- Load products ----
$catFilter = (int)($_GET['cat'] ?? 0);
$params    = [];
$where     = '';

if ($catFilter > 0) {
  // Check if this is a parent category or a child category
  $stmt = $db->prepare("SELECT parent_id FROM categories WHERE id = ?");
  $stmt->execute([$catFilter]);
  $category = $stmt->fetch();

  if ($category && $category['parent_id'] === null) {
    // This is a parent category - get products from all its children
    $stmt = $db->prepare("SELECT id FROM categories WHERE parent_id = ?");
    $stmt->execute([$catFilter]);
    $childIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $childIds[] = $catFilter; // Include the parent itself in case it has direct products
    $placeholders = implode(',', array_fill(0, count($childIds), '?'));
    $where = "WHERE p.category_id IN ($placeholders)";
    $params = $childIds;
  } else {
    // This is a child category - get only its products
    $where = 'WHERE p.category_id = ?';
    $params[] = $catFilter;
  }
}

$stmt = $db->prepare("SELECT p.*, c.name AS cat_name FROM products p JOIN categories c ON p.category_id=c.id $where ORDER BY c.sort_order, p.name");
$stmt->execute($params);
$products = $stmt->fetchAll();

// Load category tree: parents first, then children grouped under them
$allCats = $db->query(
  "SELECT * FROM categories ORDER BY sort_order"
)->fetchAll();
// Separate into groups and sub-categories
$catGroups = array_filter($allCats, fn($c) => $c['parent_id'] === null);
$catSubs   = array_filter($allCats, fn($c) => $c['parent_id'] !== null);
// Index sub-cats by parent_id
$catsByParent = [];
foreach ($catSubs as $sub) {
  $catsByParent[$sub['parent_id']][] = $sub;
}
// Flat list for backward compat (used in table)
$categories = $allCats;
// Only assignable categories = leaf nodes (sub-cats) OR groups with no children
$assignableCats = array_filter($allCats, function ($c) use ($catsByParent) {
  // A category is assignable if it has no children (it's a leaf)
  return empty($catsByParent[$c['id']]);
});

// Base URL for product images
$imgBase = APP_URL . '/../uploads/products/';

layoutHeader('Products');
?>

<div class="page-header">
  <div>
    <div class="page-header-title">Products</div>
    <div class="page-header-sub"><?= count($products) ?> product<?= count($products) !== 1 ? 's' : '' ?> · Manage your menu items</div>
  </div>
  <div class="page-header-actions">
    <button class="btn btn-primary" onclick="openAddModal()">
      <i class="fa-solid fa-plus"></i> Add Product
    </button>
    <button class="btn btn-secondary" onclick="openCategoryModal()">
      <i class="fa-solid fa-plus"></i> Add Category
    </button>
  </div>
</div>
<?php showFlash('global'); ?>

<style>
  .category-delete-card {
    margin-bottom: 1.5rem;
  }

  .category-delete-card .card-header {
    gap: var(--space-4);
  }

  .category-delete-table th,
  .category-delete-table td {
    padding: 1rem 0.5rem;
    vertical-align: middle;
    border-bottom: 1px solid var(--border-color-light);
  }

  .category-delete-table th {
    background: var(--surface-raised);
    font-weight: 600;
    color: var(--text-strong);
  }

  .category-delete-table tbody tr:hover {
    background: var(--surface-hover);
  }

  .category-parent-row td {
    font-size: 0.95rem;
    border-bottom: 2px solid var(--border-color);
  }

  .category-child-row {
    background: var(--surface-raised);
  }

  .category-child-row:hover {
    background: var(--surface-hover);
  }

  .category-delete-table .btn-danger {
    min-width: 80px;
  }
</style>

<!-- Category Filter Tabs -->
<div class="tab-bar mb-4">
  <a href="?cat=0" class="tab-btn <?= $catFilter === 0 ? 'active' : '' ?>">All</a>
  <?php foreach ($catGroups as $group): ?>
    <?php if (!empty($catsByParent[$group['id']])): ?>
      <a href="?cat=<?= $group['id'] ?>" class="tab-btn tab-parent" style="font-weight:600; color:var(--primary-color); <?= $catFilter === $group['id'] ? 'background:var(--primary-color); color:white;' : '' ?>"><?= e($group['name']) ?></a>
      <?php foreach ($catsByParent[$group['id']] as $sub): ?>
        <a href="?cat=<?= $sub['id'] ?>" class="tab-btn <?= $catFilter === $sub['id'] ? 'active' : '' ?>"><?= e($sub['name']) ?></a>
      <?php endforeach; ?>
    <?php else: ?>
      <a href="?cat=<?= $group['id'] ?>" class="tab-btn tab-parent" style="font-weight:600; color:var(--primary-color); <?= $catFilter === $group['id'] ? 'background:var(--primary-color); color:white;' : '' ?>"><?= e($group['name']) ?></a>
    <?php endif; ?>
  <?php endforeach; ?>
</div>
<div class="card mb-4">
  <div style="overflow-x:auto">
    <table class="data-table">
      <thead>
        <tr>
          <th>Product</th>
          <th>Category</th>
          <th>Price</th>
          <th>Available</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($products)): ?>
          <tr>
            <td colspan="4" style="text-align:center;padding:30px;color:var(--text-muted)">No products found.</td>
          </tr>
          <?php else: foreach ($products as $p): ?>
            <tr onclick="editProduct(<?= htmlspecialchars(json_encode($p)) ?>)"
              style="cursor:pointer">
              <td style="display:flex;align-items:center;gap:12px">
                <!-- Product thumbnail -->
                <?php if ($p['image_path'] && file_exists(UPLOAD_DIR . $p['image_path'])): ?>
                  <img src="<?= $imgBase . e($p['image_path']) ?>"
                    style="width:44px;height:44px;object-fit:cover;border-radius:var(--radius-sm);border:1px solid var(--border-color);flex-shrink:0"
                    alt="<?= e($p['name']) ?>">
                <?php else: ?>
                  <div style="width:44px;height:44px;background:var(--surface-raised);border-radius:var(--radius-sm);border:1px solid var(--border-color);display:flex;align-items:center;justify-content:center;color:var(--text-muted);flex-shrink:0">
                    <i class="fa-solid fa-mug-hot"></i>
                  </div>
                <?php endif; ?>
                <div>
                  <strong><?= e($p['name']) ?></strong>
                  <?php if ($p['description']): ?>
                    <div class="text-muted"><?= e($p['description']) ?></div>
                  <?php endif; ?>
                </div>
              </td>
              <td><?= e($p['cat_name']) ?></td>
              <td><?= peso($p['price']) ?></td>
              <td onclick="event.stopPropagation()">
                <form method="POST" style="display:inline">
                  <?= csrfField() ?>
                  <input type="hidden" name="toggle_id" value="<?= $p['id'] ?>">
                  <label class="switch">
                    <input type="checkbox" onchange="handleToggle(this)" <?= $p['is_available'] ? 'checked' : '' ?>>
                    <span class="switch-slider"></span>
                  </label>
                </form>
              </td>
            </tr>
        <?php endforeach;
        endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card mb-4 category-delete-card">
  <div style="overflow-x:auto">
    <table class="data-table category-delete-table">
      <thead>
        <tr>
          <th style="width: 60%; padding-left: 1rem;">Category</th>
          <th style="width: 40%; text-align: right; padding-right: 1rem;">Products</th>
          <th style="width: 120px;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($allCats)): ?>
          <tr>
            <td colspan="3" style="text-align:center;padding:30px;color:var(--text-muted)">
              <i class="fa-solid fa-folder-open" style="font-size: 2rem; opacity: 0.5; display: block; margin-bottom: 0.5rem;"></i>
              No categories found
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($catGroups as $group): ?>
            <?php
            // Count products in this group and its subcategories
            $groupProductCount = 0;
            $groupChildren = $catsByParent[$group['id']] ?? [];
            $allGroupCatIds = [$group['id']];
            foreach ($groupChildren as $sub) {
              $allGroupCatIds[] = $sub['id'];
            }
            if (!empty($allGroupCatIds)) {
              $placeholders = implode(',', array_fill(0, count($allGroupCatIds), '?'));
              $stmt = $db->prepare("SELECT COUNT(*) FROM products WHERE category_id IN ($placeholders)");
              $stmt->execute($allGroupCatIds);
              $groupProductCount = $stmt->fetchColumn();
            }
            ?>
            <tr class="category-parent-row">
              <td style="padding-left: 1rem;">
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                  <i class="fa-solid fa-folder" style="color: var(--primary-color); font-size: 1.1rem;"></i>
                  <div>
                    <strong><?= e($group['name']) ?></strong>
                    <?php if (!empty($groupChildren)): ?>
                      <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 2px;">
                        <?= count($groupChildren) ?> submenu<?= count($groupChildren) !== 1 ? 's' : '' ?>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              </td>
              <td style="text-align: right; padding-right: 1rem; color: var(--text-muted); font-size: 0.9rem;">
                <?= number_format($groupProductCount) ?> product<?= $groupProductCount !== 1 ? 's' : '' ?>
              </td>
              <td style="text-align: right; padding-right: 1rem;">
                <?php if (!empty($groupChildren)): ?>
                  <span class="text-muted" style="font-size: 0.8rem;">Contains submenus</span>
                <?php else: ?>
                  <form method="POST" onsubmit="return confirm('Delete <?= e($group['name']) ?> category and its <?= number_format($groupProductCount) ?> product<?= $groupProductCount !== 1 ? 's' : '' ?>? This cannot be undone.')" style="display:inline">
                    <?= csrfField() ?>
                    <input type="hidden" name="_method" value="DELETE_CATEGORY">
                    <input type="hidden" name="category_id" value="<?= $group['id'] ?>">
                    <button type="submit" class="btn btn-danger btn-sm">
                      <i class="fa-solid fa-trash"></i> Delete
                    </button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
            <?php if (!empty($groupChildren)): ?>
              <?php foreach ($groupChildren as $sub): ?>
                <?php
                // Count products in this subcategory
                $subProductCount = 0;
                $stmt = $db->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
                $stmt->execute([$sub['id']]);
                $subProductCount = $stmt->fetchColumn();
                ?>
                <tr class="category-child-row">
                  <td style="padding-left: 3rem;">
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                      <i class="fa-solid fa-folder-open" style="color: var(--success-color); font-size: 1rem;"></i>
                      <span><?= e($sub['name']) ?></span>
                    </div>
                  </td>
                  <td style="text-align: right; padding-right: 1rem; color: var(--text-muted); font-size: 0.9rem;">
                    <?= number_format($subProductCount) ?> product<?= $subProductCount !== 1 ? 's' : '' ?>
                  </td>
                  <td style="text-align: right; padding-right: 1rem;">
                    <form method="POST" onsubmit="return confirm('Delete <?= e($sub['name']) ?> category and its <?= number_format($subProductCount) ?> product<?= $subProductCount !== 1 ? 's' : '' ?>? This cannot be undone.')" style="display:inline">
                      <?= csrfField() ?>
                      <input type="hidden" name="_method" value="DELETE_CATEGORY">
                      <input type="hidden" name="category_id" value="<?= $sub['id'] ?>">
                      <button type="submit" class="btn btn-danger btn-sm">
                        <i class="fa-solid fa-trash"></i> Delete
                      </button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Hidden delete form — separate from the save form (forms cannot be nested) -->
<form method="POST" id="delete-form" onsubmit="return confirmDelete()">
  <?= csrfField() ?>
  <input type="hidden" name="_method" value="DELETE">
  <input type="hidden" name="product_id" id="delete-product-id" value="">
</form>

<!-- ===================== ADD / EDIT MODAL ===================== -->
<!--
  IMPORTANT: The form uses enctype="multipart/form-data"
  This is required whenever a form uploads a file.
  Without it, PHP never receives the uploaded file.
-->
<div class="modal-overlay hidden" id="product-modal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title" id="modal-title">
        <i class="fa-solid fa-plus"></i>Add Product
      </div>
      <button class="modal-close" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></button>
    </div>

    <form method="POST" id="product-form" enctype="multipart/form-data">
      <?= csrfField() ?>
      <input type="hidden" name="save_product" value="1">
      <input type="hidden" name="product_id" id="f-product-id" value="0">

      <div class="modal-body">

        <div class="form-group">
          <label class="form-label">Product Name <span style="color:var(--status-cancelled)">*</span></label>
          <input type="text" name="name" id="f-name" class="form-control" required placeholder="e.g. Café Latte">
        </div>

        <div class="form-group">
          <label class="form-label">Category <span style="color:var(--status-cancelled)">*</span></label>
          <select name="category_id" id="f-category" class="form-control" required>
            <?php foreach ($catGroups as $group): ?>
              <?php if (!empty($catsByParent[$group['id']])): ?>
                <optgroup label="── <?= e($group['name']) ?>">
                  <?php foreach ($catsByParent[$group['id']] as $sub): ?>
                    <option value="<?= $sub['id'] ?>"><?= e($sub['name']) ?></option>
                  <?php endforeach; ?>
                </optgroup>
              <?php else: ?>
                <option value="<?= $group['id'] ?>"><?= e($group['name']) ?></option>
              <?php endif; ?>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label">Price (₱) <span style="color:var(--status-cancelled)">*</span></label>
          <input type="number" name="price" id="f-price" class="form-control"
            required min="0.5" step="0.50" placeholder="0.00">
        </div>

        <div class="form-group">
          <label class="form-label">Description (optional)</label>
          <textarea name="description" id="f-desc" class="form-control" rows="2"
            placeholder="Brief description…"></textarea>
        </div>

        <!-- Image upload with live preview -->
        <div class="form-group">
          <label class="form-label">Product Image (optional — JPG, PNG, WEBP, max 2MB)</label>

          <!-- Current image preview (shown when editing a product that already has an image) -->
          <div id="current-img-wrap" style="display:none;margin-bottom:10px">
            <div style="font-size:.75rem;color:var(--text-muted);margin-bottom:4px">Current image:</div>
            <img id="current-img" src="" alt="Current"
              style="width:80px;height:80px;object-fit:cover;border-radius:var(--radius-sm);border:1px solid var(--border-color)">
          </div>

          <!-- New image preview (shown after the user picks a file) -->
          <div id="new-img-wrap" style="display:none;margin-bottom:10px">
            <div style="font-size:.75rem;color:var(--text-muted);margin-bottom:4px">New image preview:</div>
            <img id="new-img-preview" src="" alt="Preview"
              style="width:80px;height:80px;object-fit:cover;border-radius:var(--radius-sm);border:1px solid var(--border-color)">
          </div>

          <input type="file" name="product_image" id="f-image"
            class="form-control" accept="image/jpeg,image/png,image/webp"
            onchange="previewImage(this)">
          <div style="font-size:.74rem;color:var(--text-muted);margin-top:5px">
            Leave blank to keep the existing image when editing.
          </div>
        </div>

        <!-- Customisation options -->
        <div class="form-group">
          <label class="form-label">Customisation Options</label>
          <div style="display:flex;flex-direction:column;gap:var(--space-2);margin-top:4px">
            <label style="display:flex;align-items:center;gap:var(--space-3);cursor:pointer;font-size:0.84rem">
              <input type="checkbox" name="has_sizes" id="f-has-sizes" value="1"
                style="width:16px;height:16px;accent-color:var(--primary-color);cursor:pointer">
              <span>
                <strong>Sizes</strong>
                <span style="color:var(--text-muted);font-weight:400"> — Small (−₱10) / Medium / Large (+₱15)</span>
              </span>
            </label>
            <label style="display:flex;align-items:center;gap:var(--space-3);cursor:pointer;font-size:0.84rem">
              <input type="checkbox" name="has_sugar" id="f-has-sugar" value="1"
                style="width:16px;height:16px;accent-color:var(--primary-color);cursor:pointer">
              <span>
                <strong>Sugar level</strong>
                <span style="color:var(--text-muted);font-weight:400"> — Full / Less / 50% / No Sugar</span>
              </span>
            </label>
            <label style="display:flex;align-items:center;gap:var(--space-3);cursor:pointer;font-size:0.84rem">
              <input type="checkbox" name="has_addons" id="f-has-addons" value="1"
                style="width:16px;height:16px;accent-color:var(--primary-color);cursor:pointer">
              <span>
                <strong>Add-ons</strong>
                <span style="color:var(--text-muted);font-weight:400"> — show extra items from the Add-ons category</span>
              </span>
            </label>
          </div>
        </div>

      </div><!-- /modal-body -->

      <div class="modal-footer">
        <button type="button" class="btn btn-danger" id="modal-delete-btn"
          style="display:none;margin-right:auto"
          onclick="submitDelete()">
          <i class="fa-solid fa-trash"></i> Delete
        </button>
        <button type="button" class="btn btn-ghost" onclick="closeModal()">Cancel</button>
        <button type="submit" class="btn btn-primary">
          <i class="fa-solid fa-floppy-disk"></i> Save Product
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ===================== ADD CATEGORY WITH PRODUCTS MODAL ===================== -->
<div class="modal-overlay hidden" id="category-modal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">
        <i class="fa-solid fa-plus"></i> Add Category with Products
      </div>
      <button class="modal-close" onclick="closeCategoryModal()"><i class="fa-solid fa-xmark"></i></button>
    </div>

    <form method="POST" id="category-form" enctype="multipart/form-data">
      <?= csrfField() ?>
      <input type="hidden" name="save_category_with_products" value="1">

      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Category</label>
          <select name="parent_id_select" id="menu-select" class="form-control" onchange="updateMenuSelection()">
            <option value="">-- Create Category --</option>
            <?php foreach ($catGroups as $group): ?>
              <option value="<?= $group['id'] ?>"><?= e($group['name']) ?></option>
            <?php endforeach; ?>
            <option value="custom">+ New Category</option>
          </select>
          <input type="text" name="custom_parent_name" id="custom-parent-input" class="form-control"
            placeholder="Enter new menu category name..." style="display:none;margin-top:8px">
          <input type="hidden" name="parent_id_hidden" id="parent-id-hidden" value="">
        </div>

        <div class="form-group">
          <label class="form-label">Sub Category <span style="color:var(--status-cancelled)">*</span></label>
          <input type="text" name="category_name" class="form-control" placeholder="e.g. Coffee, Breakfast, Pasta">
        </div>

        <hr style="margin:20px 0;border:none;border-top:1px solid var(--border-color)">

        <div style="margin-bottom:15px">
          <strong>Add Products to this Category</strong>
          <button type="button" class="btn btn-sm btn-outline" onclick="addProductField()" style="margin-left:10px">
            <i class="fa-solid fa-plus"></i> Add Product
          </button>
        </div>

        <div id="products-container">
          <!-- Product fields will be added here -->
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeCategoryModal()">Cancel</button>
        <button type="submit" class="btn btn-primary">
          <i class="fa-solid fa-floppy-disk"></i> Save Category & Products
        </button>
      </div>
    </form>
  </div>
</div>

<script>
  const imgBase = '<?= $imgBase ?>';

  function openAddModal() {
    // Reset form for a fresh Add
    document.getElementById('f-product-id').value = '0';
    document.getElementById('f-name').value = '';
    document.getElementById('f-price').value = '';
    document.getElementById('f-desc').value = '';
    document.getElementById('f-image').value = '';
    document.getElementById('current-img-wrap').style.display = 'none';
    document.getElementById('new-img-wrap').style.display = 'none';
    document.getElementById('modal-delete-btn').style.display = 'none';
    document.getElementById('f-has-sizes').checked = false;
    document.getElementById('f-has-sugar').checked = false;
    document.getElementById('f-has-addons').checked = false;
    document.getElementById('modal-title').innerHTML =
      '<i class="fa-solid fa-plus"></i> Add Product';
    document.getElementById('product-modal').classList.remove('hidden');
  }

  function closeModal() {
    document.getElementById('product-modal').classList.add('hidden');
  }

  function editProduct(p) {
    document.getElementById('f-product-id').value = p.id;
    document.getElementById('f-name').value = p.name;
    document.getElementById('f-category').value = p.category_id;
    document.getElementById('f-price').value = p.price;
    document.getElementById('f-desc').value = p.description || '';
    document.getElementById('f-image').value = ''; // clear file input
    document.getElementById('new-img-wrap').style.display = 'none';

    // Show the existing image if there is one
    if (p.image_path) {
      document.getElementById('current-img').src = imgBase + p.image_path;
      document.getElementById('current-img-wrap').style.display = 'block';
    } else {
      document.getElementById('current-img-wrap').style.display = 'none';
    }

    // Wire delete button to this product
    document.getElementById('delete-product-id').value = p.id;
    document.getElementById('modal-delete-btn').style.display = '';
    document.getElementById('modal-delete-btn').dataset.name = p.name;

    // Customisation flags
    document.getElementById('f-has-sizes').checked = !!parseInt(p.has_sizes);
    document.getElementById('f-has-sugar').checked = !!parseInt(p.has_sugar);
    document.getElementById('f-has-addons').checked = !!parseInt(p.has_addons);

    document.getElementById('modal-title').innerHTML =
      '<i class="fa-solid fa-pen"></i> Edit Product';
    document.getElementById('product-modal').classList.remove('hidden');
  }

  function submitDelete() {
    const name = document.getElementById('modal-delete-btn').dataset.name || 'this product';
    if (!confirmDelete('Delete "' + name + '"? This cannot be undone.')) return;
    document.getElementById('delete-form').submit();
  }

  function handleToggle(el) {
    if (!confirm("Change product availability?")) {
      el.checked = !el.checked;
      return;
    }
    el.disabled = true;
    el.form.submit();
  }

  // Show a preview of the newly chosen image before saving
  function previewImage(input) {
    const wrap = document.getElementById('new-img-wrap');
    const preview = document.getElementById('new-img-preview');
    if (input.files && input.files[0]) {
      const reader = new FileReader();
      reader.onload = e => {
        preview.src = e.target.result;
        wrap.style.display = 'block';
      };
      reader.readAsDataURL(input.files[0]);
    } else {
      wrap.style.display = 'none';
    }
  }

  // Close modal when clicking outside it
  document.getElementById('product-modal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
  });
</script>

<script>
  let productIndex = 0;

  function updateMenuSelection() {
    const select = document.getElementById('menu-select');
    const customInput = document.getElementById('custom-parent-input');
    if (select.value === 'custom') {
      customInput.style.display = 'block';
      customInput.focus();
    } else {
      customInput.style.display = 'none';
      customInput.value = '';
    }
  }

  function openCategoryModal() {
    document.getElementById('category-form').reset();
    document.getElementById('products-container').innerHTML = '';
    productIndex = 0;
    document.getElementById('menu-select').value = '';
    document.getElementById('custom-parent-input').style.display = 'none';
    document.getElementById('custom-parent-input').value = '';
    document.getElementById('category-modal').classList.remove('hidden');
  }

  function closeCategoryModal() {
    document.getElementById('category-modal').classList.add('hidden');
  }

  function addProductField() {
    const container = document.getElementById('products-container');
    const div = document.createElement('div');
    div.className = 'product-field';
    div.style.border = '1px solid var(--border-color)';
    div.style.borderRadius = 'var(--radius-sm)';
    div.style.padding = '15px';
    div.style.marginBottom = '15px';
    div.innerHTML = `
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
        <strong>Product ${productIndex + 1}</strong>
        <button type="button" class="btn btn-sm btn-danger" onclick="removeProductField(this)">
          <i class="fa-solid fa-trash"></i> Remove
        </button>
      </div>
      <div class="form-group">
        <label class="form-label">Product Name <span style="color:var(--status-cancelled)">*</span></label>
        <input type="text" name="product_name[]" class="form-control" placeholder="e.g. Café Latte">
      </div>
      <div class="form-group">
        <label class="form-label">Price (₱) <span style="color:var(--status-cancelled)">*</span></label>
        <input type="number" name="product_price[]" class="form-control" min="0.5" step="0.50" placeholder="0.00">
      </div>
      <div class="form-group">
        <label class="form-label">Description (optional)</label>
        <textarea name="product_desc[]" class="form-control" rows="2" placeholder="Brief description…"></textarea>
      </div>
      <div class="form-group">
        <label class="form-label">Product Image (optional — JPG, PNG, WEBP, max 2MB)</label>
        <input type="file" name="product_image[]" class="form-control" accept="image/jpeg,image/png,image/webp">
      </div>
      <div class="form-group">
        <label class="form-label">Customisation Options</label>
        <div style="display:flex;flex-direction:column;gap:var(--space-2);margin-top:4px">
          <label style="display:flex;align-items:center;gap:var(--space-3);cursor:pointer;font-size:0.84rem">
            <input type="checkbox" class="size-checkbox" name="product_has_sizes_${productIndex}" value="1" style="width:16px;height:16px;accent-color:var(--primary-color);cursor:pointer" onchange="toggleCustomizationDetails(this, ${productIndex})">
            <span><strong>Sizes</strong> <span style="color:var(--text-muted);font-weight:400"> — Small (−₱10) / Medium / Large (+₱15)</span></span>
          </label>
          <label style="display:flex;align-items:center;gap:var(--space-3);cursor:pointer;font-size:0.84rem">
            <input type="checkbox" class="sugar-checkbox" name="product_has_sugar_${productIndex}" value="1" style="width:16px;height:16px;accent-color:var(--primary-color);cursor:pointer" onchange="toggleCustomizationDetails(this, ${productIndex})">
            <span><strong>Sugar level</strong> <span style="color:var(--text-muted);font-weight:400"> — Full / Less / 50% / No Sugar</span></span>
          </label>
          <label style="display:flex;align-items:center;gap:var(--space-3);cursor:pointer;font-size:0.84rem">
            <input type="checkbox" class="addon-checkbox" name="product_has_addons_${productIndex}" value="1" style="width:16px;height:16px;accent-color:var(--primary-color);cursor:pointer" onchange="toggleCustomizationDetails(this, ${productIndex})">
            <span><strong>Add-ons</strong> <span style="color:var(--text-muted);font-weight:400"> — show extra items from the Add-ons category</span></span>
          </label>
        </div>
      </div>
    `;
    container.appendChild(div);
    productIndex++;
  }

  function toggleCustomizationDetails(checkbox, index) {
    // Visual feedback - checked items show details, unchecked items hide them
    // The descriptions are shown inline with the checkboxes
    if (checkbox.checked) {
      checkbox.parentElement.style.opacity = '1';
      checkbox.parentElement.style.fontWeight = '500';
    } else {
      checkbox.parentElement.style.opacity = '0.7';
      checkbox.parentElement.style.fontWeight = '400';
    }
  }

  function removeProductField(btn) {
    btn.closest('.product-field').remove();
  }

  // Close category modal when clicking outside
  document.getElementById('category-modal').addEventListener('click', function(e) {
    if (e.target === this) closeCategoryModal();
  });
</script>

<?php layoutFooter(); ?>