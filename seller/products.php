<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
startSession();
requireRole(['seller']);

$user_id  = $_SESSION['user_id'];
$store_id = $_SESSION['store_id'];
$page_title = 'My Products';

// Handle actions
$msg = $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $pid         = (int)($_POST['product_id'] ?? 0);
        $name        = trim($_POST['product_name'] ?? '');
        $character   = trim($_POST['character_name'] ?? '');
        $desc        = trim($_POST['description'] ?? '');
        $ltype       = $_POST['listing_type'] ?? 'both';
        $sale_price  = $_POST['sale_price']  ? (float)$_POST['sale_price']  : null;
        $rent_price  = $_POST['rental_price'] ? (float)$_POST['rental_price'] : null;
        $deposit     = $_POST['deposit']      ? (float)$_POST['deposit']      : null;
        $size        = trim($_POST['size'] ?? '');
        $stock       = (int)($_POST['stock_quantity'] ?? 1);
        $rent_stock  = (int)($_POST['rental_stock']   ?? 1);
        $condition   = $_POST['condition_rating'] ?? 'new';
        $media_id    = ($_POST['media_id'] ?? '') !== '' ? (int)$_POST['media_id'] : null;
        $category_id = ($_POST['category_id'] ?? '') !== '' ? (int)$_POST['category_id'] : null;
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;
        $main_img    = trim($_POST['main_image'] ?? '');

        if (!$name) { $err = 'Product name is required.'; }
        else {
            if ($pid) {
                db_execute($conn,
                    "UPDATE products SET product_name=?,character_name=?,description=?,listing_type=?,
                     sale_price=?,rental_price_per_day=?,rental_deposit=?,size=?,stock_quantity=?,
                     rental_stock=?,condition_rating=?,media_id=?,category_id=?,is_featured=?,main_image=?
                     WHERE product_id=? AND store_id=?",
                    'ssssdddsiisssiiii',
                    [$name,$character,$desc,$ltype,$sale_price,$rent_price,$deposit,$size,
                     $stock,$rent_stock,$condition,$media_id,$category_id,$is_featured,$main_img,$pid,$store_id]);
                $msg = 'Product updated successfully!';
            } else {
                $new_id = db_execute($conn,
                    "INSERT INTO products (store_id,product_name,character_name,description,listing_type,
                     sale_price,rental_price_per_day,rental_deposit,size,stock_quantity,rental_stock,
                     condition_rating,media_id,category_id,is_featured,main_image)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                    'issssdddsiissiii',
                    [$store_id,$name,$character,$desc,$ltype,$sale_price,$rent_price,$deposit,$size,
                     $stock,$rent_stock,$condition,$media_id,$category_id,$is_featured,$main_img]);
                $msg = "Product #$new_id added successfully!";
            }
        }
    } elseif ($action === 'toggle_active') {
        $pid = (int)($_POST['product_id'] ?? 0);
        $conn->query("UPDATE products SET is_active = 1-is_active WHERE product_id=$pid AND store_id=$store_id");
        $msg = 'Product status updated.';
    } elseif ($action === 'delete') {
        $pid = (int)($_POST['product_id'] ?? 0);
        $conn->query("UPDATE products SET is_active=0 WHERE product_id=$pid AND store_id=$store_id");
        $msg = 'Product removed.';
    }
}

$edit_product = null;
if (!empty($_GET['edit'])) {
    $edit_product = db_query_one($conn,
        "SELECT * FROM products WHERE product_id=? AND store_id=?",
        'ii', [(int)$_GET['edit'], $store_id]);
}
$show_form = !empty($_GET['action']) && $_GET['action']==='new' || $edit_product;

// Products list
$products = db_query($conn,
    "SELECT p.*, m.title AS franchise, c.name AS cat_name
     FROM products p
     LEFT JOIN media_titles m ON m.media_id=p.media_id
     LEFT JOIN categories c ON c.category_id=p.category_id
     WHERE p.store_id=?
     ORDER BY p.created_at DESC", 'i', [$store_id]);

$media_list    = db_query($conn, "SELECT * FROM media_titles ORDER BY title");
$category_list = db_query($conn, "SELECT * FROM categories ORDER BY name");

require_once __DIR__ . '/../includes/header.php';
?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:2rem;flex-wrap:wrap;gap:1rem;">
  <div>
    <h1 class="page-title">🎭 My Products</h1>
    <p class="page-subtitle"><?= count($products) ?> products in your store</p>
  </div>
  <a href="?action=new" class="btn btn-primary"><i class="fas fa-plus"></i> Add New Product</a>
</div>

<?php if ($msg): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($err) ?></div><?php endif; ?>

<!-- ═══ ADD / EDIT FORM ════════════════════════════════════════ -->
<?php if ($show_form): ?>
<div class="card" style="margin-bottom:2rem;border-color:var(--accent-purple);">
  <h3 style="font-family:'Cinzel',serif;font-size:1rem;margin-bottom:1.5rem;">
    <?= $edit_product ? '✏️ Edit Product' : '➕ Add New Product' ?>
  </h3>
  <form method="POST">
    <input type="hidden" name="action" value="save">
    <?php if ($edit_product): ?>
    <input type="hidden" name="product_id" value="<?= $edit_product['product_id'] ?>">
    <?php endif; ?>

    <div class="grid grid-2" style="gap:1rem;">
      <div class="form-group">
        <label class="form-label">Product Name *</label>
        <input type="text" name="product_name" class="form-control" required
               value="<?= htmlspecialchars($edit_product['product_name'] ?? '') ?>" placeholder="e.g. Tanjiro Full Set">
      </div>
      <div class="form-group">
        <label class="form-label">Character Name</label>
        <input type="text" name="character_name" class="form-control"
               value="<?= htmlspecialchars($edit_product['character_name'] ?? '') ?>" placeholder="e.g. Tanjiro Kamado">
      </div>
      <div class="form-group">
        <label class="form-label">Franchise / Series</label>
        <select name="media_id" class="form-control">
          <option value="">— Select Franchise —</option>
          <?php foreach ($media_list as $m): ?>
          <option value="<?= $m['media_id'] ?>" <?= ($edit_product['media_id']??'')==$m['media_id']?'selected':'' ?>>
            <?= htmlspecialchars($m['title']) ?> (<?= $m['media_type'] ?>)
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Category</label>
        <select name="category_id" class="form-control">
          <option value="">— Select Category —</option>
          <?php foreach ($category_list as $c): ?>
          <option value="<?= $c['category_id'] ?>" <?= ($edit_product['category_id']??'')==$c['category_id']?'selected':'' ?>>
            <?= htmlspecialchars($c['icon'].' '.$c['name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="form-group">
      <label class="form-label">Description</label>
      <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($edit_product['description'] ?? '') ?></textarea>
    </div>

    <div class="form-group">
      <label class="form-label">Listing Type *</label>
      <div style="display:flex;gap:1rem;flex-wrap:wrap;">
        <?php foreach (['both'=>'💫 Both (Sale & Rent)','sale'=>'🛒 For Sale Only','rent'=>'📅 For Rent Only'] as $val=>$lbl): ?>
        <label style="display:flex;align-items:center;gap:0.4rem;cursor:pointer;font-size:0.9rem;color:var(--text-secondary);">
          <input type="radio" name="listing_type" value="<?= $val ?>" style="accent-color:var(--accent-purple);"
                 <?= (($edit_product['listing_type']??'both')===$val)?'checked':'' ?> onchange="togglePriceFields()">
          <?= $lbl ?>
        </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="grid grid-3" style="gap:1rem;">
      <div class="form-group" id="sale-price-field">
        <label class="form-label">💰 Sale Price (₱)</label>
        <input type="number" name="sale_price" class="form-control" min="0" step="0.01"
               value="<?= $edit_product['sale_price'] ?? '' ?>" placeholder="0.00">
      </div>
      <div class="form-group" id="rent-price-field">
        <label class="form-label">📅 Rental Price/Day (₱)</label>
        <input type="number" name="rental_price" class="form-control" min="0" step="0.01"
               value="<?= $edit_product['rental_price_per_day'] ?? '' ?>" placeholder="0.00">
      </div>
      <div class="form-group" id="deposit-field">
        <label class="form-label">🔒 Rental Deposit (₱)</label>
        <input type="number" name="deposit" class="form-control" min="0" step="0.01"
               value="<?= $edit_product['rental_deposit'] ?? '' ?>" placeholder="0.00">
      </div>
    </div>

    <div class="grid grid-3" style="gap:1rem;">
      <div class="form-group">
        <label class="form-label">Size</label>
        <select name="size" class="form-control">
          <option value="">Select Size</option>
          <?php foreach (['XS','S','M','L','XL','XXL','One Size','Custom'] as $s): ?>
          <option value="<?= $s ?>" <?= ($edit_product['size']??'')===$s?'selected':'' ?>><?= $s ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Stock Qty (Sale)</label>
        <input type="number" name="stock_quantity" class="form-control" min="0"
               value="<?= $edit_product['stock_quantity'] ?? 1 ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Rental Stock</label>
        <input type="number" name="rental_stock" class="form-control" min="0"
               value="<?= $edit_product['rental_stock'] ?? 1 ?>">
      </div>
    </div>

    <div class="grid grid-2" style="gap:1rem;">
      <div class="form-group">
        <label class="form-label">Condition</label>
        <select name="condition_rating" class="form-control">
          <?php foreach (['new'=>'✨ New','like_new'=>'🌟 Like New','good'=>'👍 Good','fair'=>'👌 Fair'] as $val=>$lbl): ?>
          <option value="<?= $val ?>" <?= ($edit_product['condition_rating']??'new')===$val?'selected':'' ?>><?= $lbl ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Main Image URL</label>
        <input type="url" name="main_image" class="form-control" placeholder="https://..."
               value="<?= htmlspecialchars($edit_product['main_image'] ?? '') ?>">
        <div class="form-hint">Paste a direct image URL (JPG/PNG)</div>
      </div>
    </div>

    <div class="form-group">
      <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;">
        <input type="checkbox" name="is_featured" style="accent-color:var(--accent-gold);"
               <?= !empty($edit_product['is_featured'])?'checked':'' ?>>
        <span style="font-size:0.9rem;color:var(--text-secondary);">⭐ Mark as Featured Product</span>
      </label>
    </div>

    <div style="display:flex;gap:0.75rem;flex-wrap:wrap;">
      <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?= $edit_product ? 'Update Product' : 'Add Product' ?></button>
      <a href="/seller/products.php" class="btn btn-outline">Cancel</a>
    </div>
  </form>
</div>
<?php endif; ?>

<!-- ═══ PRODUCTS TABLE ═════════════════════════════════════════ -->
<?php if ($products): ?>
<div class="card p-0">
  <div class="table-wrapper">
    <table>
      <thead>
        <tr>
          <th>Product</th><th>Type</th><th>Price</th>
          <th>Stock</th><th>Status</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($products as $p): ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:0.75rem;">
              <div style="width:44px;height:54px;border-radius:8px;overflow:hidden;background:var(--bg-mid);flex-shrink:0;">
                <img src="<?= htmlspecialchars($p['main_image']??'https://picsum.photos/44/54') ?>"
                     style="width:100%;height:100%;object-fit:cover;">
              </div>
              <div>
                <div style="font-weight:700;font-size:0.9rem;"><?= htmlspecialchars($p['product_name']) ?></div>
                <?php if ($p['character_name']): ?>
                <div style="font-size:0.75rem;color:var(--accent-pink);">as <?= htmlspecialchars($p['character_name']) ?></div>
                <?php endif; ?>
                <div style="font-size:0.72rem;color:var(--text-muted);"><?= htmlspecialchars($p['franchise']??$p['cat_name']??'') ?></div>
              </div>
            </div>
          </td>
          <td><?= match($p['listing_type']){'sale'=>'<span class="chip chip-sale">🛒 Sale</span>','rent'=>'<span class="chip chip-rent">📅 Rent</span>',default=>'<span class="chip chip-both">💫 Both</span>'} ?></td>
          <td>
            <?php if ($p['sale_price']): ?><div style="color:var(--accent-gold);font-weight:700;font-size:0.88rem;">₱<?= number_format($p['sale_price'],2) ?></div><?php endif; ?>
            <?php if ($p['rental_price_per_day']): ?><div style="color:var(--accent-cyan);font-size:0.82rem;">₱<?= number_format($p['rental_price_per_day'],2) ?>/day</div><?php endif; ?>
          </td>
          <td>
            <div style="font-size:0.85rem;">🛒 <?= $p['stock_quantity'] ?></div>
            <div style="font-size:0.82rem;color:var(--text-muted);">📅 <?= $p['rental_stock'] ?></div>
          </td>
          <td>
            <?php if ($p['is_active']): ?>
            <span class="status status-active">Active</span>
            <?php else: ?>
            <span class="status status-cancelled">Inactive</span>
            <?php endif; ?>
            <?php if ($p['is_featured']): ?>
            <div><span class="chip chip-featured" style="font-size:0.68rem;margin-top:3px;">⭐ Featured</span></div>
            <?php endif; ?>
          </td>
          <td>
            <div style="display:flex;gap:0.4rem;flex-wrap:wrap;">
              <a href="/product.php?id=<?= $p['product_id'] ?>" class="btn btn-outline btn-sm" title="View" target="_blank"><i class="fas fa-eye"></i></a>
              <a href="?edit=<?= $p['product_id'] ?>" class="btn btn-secondary btn-sm" title="Edit"><i class="fas fa-edit"></i></a>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="action"     value="toggle_active">
                <input type="hidden" name="product_id" value="<?= $p['product_id'] ?>">
                <button type="submit" class="btn btn-outline btn-sm" title="<?= $p['is_active']?'Deactivate':'Activate' ?>">
                  <i class="fas fa-<?= $p['is_active']?'toggle-on':'toggle-off' ?>"></i>
                </button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php else: ?>
<div class="empty-state card">
  <span class="empty-icon">🎭</span>
  <h3>No Products Yet</h3>
  <p>Add your first cosplay costume to your store!</p>
  <a href="?action=new" class="btn btn-primary" style="margin-top:1rem;"><i class="fas fa-plus"></i> Add Product</a>
</div>
<?php endif; ?>

<script>
function togglePriceFields() {
  const type = document.querySelector('[name="listing_type"]:checked')?.value;
  document.getElementById('sale-price-field').style.opacity = type==='rent'  ? '0.4' : '1';
  document.getElementById('rent-price-field').style.opacity = type==='sale'  ? '0.4' : '1';
  document.getElementById('deposit-field').style.opacity    = type==='sale'  ? '0.4' : '1';
}
document.addEventListener('DOMContentLoaded', togglePriceFields);
document.querySelectorAll('[name="listing_type"]').forEach(r => r.addEventListener('change', togglePriceFields));
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
