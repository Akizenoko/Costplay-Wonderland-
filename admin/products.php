<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
startSession();
requireRole(['admin','super_admin']);

$page_title = 'Manage Products';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pid    = (int)($_POST['product_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($pid && $action === 'toggle_active')   { $conn->query("UPDATE products SET is_active=1-is_active WHERE product_id=$pid");   $msg='Product status updated.'; }
    if ($pid && $action === 'toggle_featured') { $conn->query("UPDATE products SET is_featured=1-is_featured WHERE product_id=$pid"); $msg='Featured status updated.'; }
}

$search   = trim($_GET['q'] ?? '');
$type     = $_GET['type']     ?? '';
$page_num = max(1,(int)($_GET['page']??1));
$per_page = 25;
$offset   = ($page_num-1)*$per_page;

$where  = ["1=1"]; $params=[]; $types='';
if ($search) { $where[]="(p.product_name LIKE ? OR p.character_name LIKE ?)"; $like="%$search%"; $params=array_merge($params,[$like,$like]); $types.='ss'; }
if ($type)   { $where[]="p.listing_type=?"; $params[]=$type; $types.='s'; }
$w = implode(' AND ',$where);

$total = db_query_one($conn,"SELECT COUNT(*) AS c FROM products p WHERE $w",$types?:null,$params?:null)['c'];
$pages = ceil($total/$per_page);

$products = db_query($conn,
    "SELECT p.*, s.store_name, m.title AS franchise
     FROM products p
     JOIN stores s ON s.store_id=p.store_id
     LEFT JOIN media_titles m ON m.media_id=p.media_id
     WHERE $w ORDER BY p.created_at DESC LIMIT ? OFFSET ?",
    ($types?:'').'ii', array_merge($params?:[], [$per_page,$offset]));

require_once __DIR__ . '/../includes/header.php';
?>

<h1 class="page-title">🎭 Manage Products</h1>
<p class="page-subtitle"><?= number_format($total) ?> products total</p>

<?php if ($msg): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($msg) ?></div><?php endif; ?>

<form method="GET" style="display:flex;gap:0.75rem;flex-wrap:wrap;margin-bottom:1.5rem;align-items:flex-end;">
  <div style="flex:1;min-width:200px;">
    <input type="text" name="q" class="form-control" placeholder="Search product or character…" value="<?= htmlspecialchars($search) ?>">
  </div>
  <select name="type" class="form-control" style="width:auto;">
    <option value="">All Types</option>
    <option value="sale" <?= $type==='sale'?'selected':'' ?>>🛒 Sale</option>
    <option value="rent" <?= $type==='rent'?'selected':'' ?>>📅 Rent</option>
    <option value="both" <?= $type==='both'?'selected':'' ?>>💫 Both</option>
  </select>
  <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
  <a href="/admin/products.php" class="btn btn-outline">Reset</a>
</form>

<div class="card p-0">
  <div class="table-wrapper">
    <table>
      <thead><tr><th>Product</th><th>Store</th><th>Type</th><th>Price</th><th>Stock</th><th>Featured</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($products as $p): ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:0.6rem;">
              <div style="width:38px;height:46px;border-radius:6px;overflow:hidden;background:var(--bg-mid);flex-shrink:0;">
                <img src="<?= htmlspecialchars($p['main_image']??'https://picsum.photos/38/46') ?>" style="width:100%;height:100%;object-fit:cover;">
              </div>
              <div>
                <div style="font-weight:700;font-size:0.88rem;"><?= htmlspecialchars($p['product_name']) ?></div>
                <?php if ($p['character_name']): ?><div style="font-size:0.72rem;color:var(--accent-pink);"><?= htmlspecialchars($p['character_name']) ?></div><?php endif; ?>
                <?php if ($p['franchise']): ?><div style="font-size:0.7rem;color:var(--text-muted);"><?= htmlspecialchars($p['franchise']) ?></div><?php endif; ?>
              </div>
            </div>
          </td>
          <td style="font-size:0.85rem;"><?= htmlspecialchars($p['store_name']) ?></td>
          <td><?= match($p['listing_type']){'sale'=>'<span class="chip chip-sale" style="font-size:0.7rem;">🛒 Sale</span>','rent'=>'<span class="chip chip-rent" style="font-size:0.7rem;">📅 Rent</span>',default=>'<span class="chip chip-both" style="font-size:0.7rem;">💫 Both</span>'} ?></td>
          <td>
            <?php if ($p['sale_price']): ?><div style="color:var(--accent-gold);font-size:0.82rem;">₱<?= number_format($p['sale_price'],2) ?></div><?php endif; ?>
            <?php if ($p['rental_price_per_day']): ?><div style="color:var(--accent-cyan);font-size:0.78rem;">₱<?= number_format($p['rental_price_per_day'],2) ?>/day</div><?php endif; ?>
          </td>
          <td style="font-size:0.82rem;color:var(--text-secondary);">
            🛒 <?= $p['stock_quantity'] ?><br>📅 <?= $p['rental_stock'] ?>
          </td>
          <td>
            <form method="POST" style="display:inline;">
              <input type="hidden" name="action"     value="toggle_featured">
              <input type="hidden" name="product_id" value="<?= $p['product_id'] ?>">
              <button type="submit" class="btn btn-sm" style="font-size:0.72rem;background:<?= $p['is_featured']?'rgba(255,215,0,0.15)':'rgba(255,255,255,0.05)' ?>;border:1px solid <?= $p['is_featured']?'rgba(255,215,0,0.4)':'var(--border-color)' ?>;color:<?= $p['is_featured']?'#ffd700':'var(--text-muted)' ?>;">
                <?= $p['is_featured'] ? '⭐ Featured' : '☆ Feature' ?>
              </button>
            </form>
          </td>
          <td><span class="status <?= $p['is_active']?'status-active':'status-cancelled' ?>"><?= $p['is_active']?'Active':'Hidden' ?></span></td>
          <td>
            <div style="display:flex;gap:0.3rem;">
              <a href="/product.php?id=<?= $p['product_id'] ?>" class="btn btn-outline btn-sm" target="_blank"><i class="fas fa-eye"></i></a>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="action"     value="toggle_active">
                <input type="hidden" name="product_id" value="<?= $p['product_id'] ?>">
                <button type="submit" class="btn btn-sm <?= $p['is_active']?'btn-danger':'btn-success' ?>" style="font-size:0.72rem;">
                  <?= $p['is_active'] ? '🚫' : '✅' ?>
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

<!-- Pagination -->
<?php if ($pages > 1): ?>
<div class="pagination">
  <?php $base='?'.http_build_query(array_merge($_GET,['page'=>'__P__'])); ?>
  <?php if ($page_num>1): ?><a href="<?= str_replace('__P__',$page_num-1,$base) ?>" class="page-btn">‹</a><?php endif; ?>
  <?php for($i=max(1,$page_num-2);$i<=min($pages,$page_num+2);$i++): ?>
  <a href="<?= str_replace('__P__',$i,$base) ?>" class="page-btn <?= $i===$page_num?'active':'' ?>"><?= $i ?></a>
  <?php endfor; ?>
  <?php if ($page_num<$pages): ?><a href="<?= str_replace('__P__',$page_num+1,$base) ?>" class="page-btn">›</a><?php endif; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
