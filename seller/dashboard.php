<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
startSession();
requireRole(['seller']);

$user_id  = $_SESSION['user_id'];
$store_id = $_SESSION['store_id'];
$page_title = 'Seller Dashboard';

// Stats
$stats = db_query_one($conn,
    "SELECT
       (SELECT COUNT(*) FROM products WHERE store_id=? AND is_active=1) AS total_products,
       (SELECT COUNT(*) FROM orders WHERE store_id=? AND order_status NOT IN ('cancelled')) AS total_orders,
       (SELECT COUNT(*) FROM rentals WHERE store_id=? AND rental_status NOT IN ('cancelled')) AS total_rentals,
       (SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE store_id=? AND payment_status='paid') AS total_revenue,
       (SELECT COUNT(*) FROM orders WHERE store_id=? AND order_status='pending') AS pending_orders,
       (SELECT COUNT(*) FROM rentals WHERE store_id=? AND rental_status='active') AS active_rentals",
    'iiiiii', [$store_id,$store_id,$store_id,$store_id,$store_id,$store_id]);

$store = db_query_one($conn, "SELECT * FROM stores WHERE store_id=?", 'i', [$store_id]);

// Recent orders
$recent_orders = db_query($conn,
    "SELECT o.*, u.username, COUNT(oi.order_item_id) AS item_count
     FROM orders o JOIN user_accounts u ON u.user_id=o.user_id
     LEFT JOIN order_items oi ON oi.order_id=o.order_id
     WHERE o.store_id=?
     GROUP BY o.order_id ORDER BY o.created_at DESC LIMIT 5", 'i', [$store_id]);

// Recent rentals
$recent_rentals = db_query($conn,
    "SELECT r.*, u.username, MAX(ri.rental_end_date) AS due_date
     FROM rentals r JOIN user_accounts u ON u.user_id=r.user_id
     LEFT JOIN rental_items ri ON ri.rental_id=r.rental_id
     WHERE r.store_id=?
     GROUP BY r.rental_id ORDER BY r.created_at DESC LIMIT 5", 'i', [$store_id]);

// Top products
$top_products = db_query($conn,
    "SELECT p.product_name, p.view_count, p.main_image,
            COUNT(oi.order_item_id) AS sold_count,
            COALESCE(SUM(oi.unit_price*oi.quantity),0) AS revenue
     FROM products p
     LEFT JOIN order_items oi ON oi.product_id=p.product_id
     LEFT JOIN orders o ON o.order_id=oi.order_id AND o.payment_status='paid'
     WHERE p.store_id=?
     GROUP BY p.product_id ORDER BY sold_count DESC LIMIT 5", 'i', [$store_id]);

require_once __DIR__ . '/../includes/header.php';
?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:2rem;flex-wrap:wrap;gap:1rem;">
  <div>
    <h1 class="page-title">📊 Seller Dashboard</h1>
    <p class="page-subtitle">Welcome back, <span style="color:var(--accent-pink);"><?= htmlspecialchars($_SESSION['username']) ?></span>! Here's your store overview.</p>
  </div>
  <div style="display:flex;gap:0.75rem;">
    <a href="/seller/products.php?action=new" class="btn btn-primary"><i class="fas fa-plus"></i> Add Product</a>
    <a href="/store.php?slug=<?= urlencode($store['store_slug'] ?? '') ?>" class="btn btn-outline" target="_blank"><i class="fas fa-eye"></i> View Store</a>
  </div>
</div>

<!-- Stats -->
<div class="grid grid-3" style="margin-bottom:2rem;">
  <?php
  $stat_cards = [
    ['📦','Total Products',  $stats['total_products'],  'purple', null],
    ['🛒','Total Orders',    $stats['total_orders'],    'cyan',   null],
    ['🎪','Total Rentals',   $stats['total_rentals'],   'pink',   null],
    ['💰','Total Revenue',   '₱'.number_format($stats['total_revenue'],2), 'gold', null],
    ['⏳','Pending Orders',  $stats['pending_orders'],  'gold',   '/seller/orders.php?status=pending'],
    ['🔴','Active Rentals',  $stats['active_rentals'],  'green',  '/seller/rentals.php?status=active'],
  ];
  foreach ($stat_cards as [$icon,$label,$val,$color,$link]):
  ?>
  <?php if ($link): ?>
  <a href="<?= $link ?>" style="text-decoration:none;">
  <?php endif; ?>
  <div class="stat-card">
    <div class="stat-icon <?= $color ?>"><?= $icon ?></div>
    <div>
      <div class="stat-value" style="font-size:1.5rem;"><?= $val ?></div>
      <div class="stat-label"><?= $label ?></div>
    </div>
  </div>
  <?php if ($link): ?></a><?php endif; ?>
  <?php endforeach; ?>
</div>

<div class="grid grid-2" style="gap:2rem;align-items:start;">

  <!-- Recent Orders -->
  <div class="card">
    <div class="section-header" style="margin-bottom:1rem;">
      <h3 class="section-title" style="font-size:1rem;">📦 Recent Orders</h3>
      <a href="/seller/orders.php" class="btn btn-outline btn-sm" style="font-size:0.75rem;">View All</a>
    </div>
    <?php if ($recent_orders): ?>
    <div style="display:flex;flex-direction:column;gap:0.75rem;">
      <?php foreach ($recent_orders as $o): ?>
      <div style="display:flex;align-items:center;justify-content:space-between;padding:0.75rem;background:rgba(255,255,255,0.03);border-radius:10px;gap:0.75rem;">
        <div>
          <div style="font-weight:700;font-size:0.88rem;">#<?= $o['order_id'] ?></div>
          <div style="font-size:0.78rem;color:var(--text-muted);">👤 <?= htmlspecialchars($o['username']) ?> · <?= $o['item_count'] ?> items</div>
          <div style="font-size:0.75rem;color:var(--text-muted);"><?= date('M d, Y',strtotime($o['created_at'])) ?></div>
        </div>
        <div style="text-align:right;">
          <div style="font-weight:700;color:var(--accent-gold);font-size:0.9rem;">₱<?= number_format($o['total_amount'],2) ?></div>
          <span class="status <?= match($o['order_status']){'pending'=>'status-pending','confirmed','processing'=>'status-confirmed','shipped'=>'status-shipped','delivered'=>'status-delivered',default=>'status-cancelled'} ?>" style="font-size:0.68rem;"><?= ucfirst($o['order_status']) ?></span>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="empty-state" style="padding:1.5rem;"><span style="font-size:2rem;">📦</span><p>No orders yet</p></div>
    <?php endif; ?>
  </div>

  <!-- Recent Rentals -->
  <div class="card">
    <div class="section-header" style="margin-bottom:1rem;">
      <h3 class="section-title" style="font-size:1rem;">🎪 Active Rentals</h3>
      <a href="/seller/rentals.php" class="btn btn-outline btn-sm" style="font-size:0.75rem;">View All</a>
    </div>
    <?php if ($recent_rentals): ?>
    <div style="display:flex;flex-direction:column;gap:0.75rem;">
      <?php foreach ($recent_rentals as $r):
        $due      = strtotime($r['due_date']);
        $overdue  = $r['rental_status']==='active' && $due < strtotime('today');
      ?>
      <div style="display:flex;align-items:center;justify-content:space-between;padding:0.75rem;background:rgba(255,255,255,0.03);border-radius:10px;gap:0.75rem;<?= $overdue?'border:1px solid rgba(255,82,82,0.3);':'' ?>">
        <div>
          <div style="font-weight:700;font-size:0.88rem;">#<?= $r['rental_id'] ?><?= $overdue?' ⚠️':'' ?></div>
          <div style="font-size:0.78rem;color:var(--text-muted);">👤 <?= htmlspecialchars($r['username']) ?></div>
          <div style="font-size:0.75rem;color:<?= $overdue?'#ff5252':'var(--text-muted)' ?>;">
            Due: <?= $r['due_date'] ? date('M d, Y',strtotime($r['due_date'])) : '—' ?>
          </div>
        </div>
        <div style="text-align:right;">
          <div style="font-weight:700;color:var(--accent-cyan);font-size:0.9rem;">₱<?= number_format($r['total_amount'],2) ?></div>
          <span class="status <?= match($r['rental_status']){'pending'=>'status-pending','confirmed'=>'status-confirmed','active'=>'status-active','returned'=>'status-returned',default=>'status-cancelled'} ?>" style="font-size:0.68rem;"><?= ucfirst($r['rental_status']) ?></span>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="empty-state" style="padding:1.5rem;"><span style="font-size:2rem;">🎪</span><p>No rentals yet</p></div>
    <?php endif; ?>
  </div>

</div>

<!-- Top Products -->
<?php if ($top_products): ?>
<div class="card" style="margin-top:2rem;">
  <div class="section-header" style="margin-bottom:1rem;">
    <h3 class="section-title" style="font-size:1rem;">⭐ Top Selling Products</h3>
    <a href="/seller/products.php" class="btn btn-outline btn-sm" style="font-size:0.75rem;">Manage All</a>
  </div>
  <div class="table-wrapper">
    <table>
      <thead><tr>
        <th>Product</th><th>Views</th><th>Sold</th><th>Revenue</th>
      </tr></thead>
      <tbody>
        <?php foreach ($top_products as $tp): ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:0.6rem;">
              <div style="width:38px;height:46px;border-radius:6px;overflow:hidden;background:var(--bg-mid);flex-shrink:0;">
                <img src="<?= htmlspecialchars($tp['main_image']??'https://picsum.photos/38/46') ?>" style="width:100%;height:100%;object-fit:cover;">
              </div>
              <span style="font-size:0.88rem;font-weight:600;"><?= htmlspecialchars($tp['product_name']) ?></span>
            </div>
          </td>
          <td style="color:var(--text-secondary);"><?= number_format($tp['view_count']) ?></td>
          <td style="color:var(--accent-cyan);"><?= $tp['sold_count'] ?></td>
          <td style="color:var(--accent-gold);font-weight:700;">₱<?= number_format($tp['revenue'],2) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
