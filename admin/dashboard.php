<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
startSession();
requireRole(['admin','super_admin']);

$page_title = 'Admin Dashboard';

$stats = db_query_one($conn,
    "SELECT
       (SELECT COUNT(*) FROM user_accounts) AS total_users,
       (SELECT COUNT(*) FROM user_accounts WHERE role='customer') AS total_customers,
       (SELECT COUNT(*) FROM user_accounts WHERE role='seller') AS total_sellers,
       (SELECT COUNT(*) FROM stores WHERE is_active=1) AS total_stores,
       (SELECT COUNT(*) FROM products WHERE is_active=1) AS total_products,
       (SELECT COUNT(*) FROM orders WHERE order_status NOT IN ('cancelled')) AS total_orders,
       (SELECT COUNT(*) FROM rentals WHERE rental_status NOT IN ('cancelled')) AS total_rentals,
       (SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE payment_status='paid') AS total_revenue,
       (SELECT COUNT(*) FROM orders WHERE order_status='pending') AS pending_orders,
       (SELECT COUNT(*) FROM rentals WHERE rental_status='active') AS active_rentals,
       (SELECT COUNT(*) FROM rentals WHERE rental_status='overdue') AS overdue_rentals,
       (SELECT COUNT(*) FROM stores WHERE is_verified=0 AND is_active=1) AS unverified_stores");

$recent_users = db_query($conn,
    "SELECT * FROM user_accounts ORDER BY created_at DESC LIMIT 6");

$recent_orders = db_query($conn,
    "SELECT o.*, u.username, s.store_name
     FROM orders o JOIN user_accounts u ON u.user_id=o.user_id
     JOIN stores s ON s.store_id=o.store_id
     ORDER BY o.created_at DESC LIMIT 6");

$top_stores = db_query($conn,
    "SELECT s.*,
            COUNT(DISTINCT p.product_id) AS product_count,
            COUNT(DISTINCT o.order_id) AS order_count,
            COALESCE(SUM(o.total_amount),0) AS revenue
     FROM stores s
     LEFT JOIN products p ON p.store_id=s.store_id AND p.is_active=1
     LEFT JOIN orders o ON o.store_id=s.store_id AND o.payment_status='paid'
     WHERE s.is_active=1
     GROUP BY s.store_id ORDER BY revenue DESC LIMIT 5");

require_once __DIR__ . '/../includes/header.php';
?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:2rem;flex-wrap:wrap;gap:1rem;">
  <div>
    <h1 class="page-title">📊 Admin Dashboard</h1>
    <p class="page-subtitle">Costplay Wonderland system overview</p>
  </div>
  <div style="display:flex;gap:0.6rem;flex-wrap:wrap;">
    <a href="/admin/users.php"    class="btn btn-outline btn-sm"><i class="fas fa-users"></i> Users</a>
    <a href="/admin/stores.php"   class="btn btn-outline btn-sm"><i class="fas fa-store"></i> Stores</a>
    <a href="/admin/products.php" class="btn btn-outline btn-sm"><i class="fas fa-tshirt"></i> Products</a>
    <a href="/admin/orders.php"   class="btn btn-outline btn-sm"><i class="fas fa-box"></i> Orders</a>
  </div>
</div>

<!-- Key Metrics -->
<div class="grid" style="grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:1rem;margin-bottom:2rem;">
  <?php
  $metrics = [
    ['👥','Total Users',      $stats['total_users'],     'purple', '/admin/users.php'],
    ['🏪','Active Stores',    $stats['total_stores'],    'cyan',   '/admin/stores.php'],
    ['🎭','Products',         $stats['total_products'],  'pink',   '/admin/products.php'],
    ['📦','Total Orders',     $stats['total_orders'],    'gold',   '/admin/orders.php'],
    ['🎪','Total Rentals',    $stats['total_rentals'],   'green',  '/admin/rentals.php'],
    ['💰','Revenue',          '₱'.number_format($stats['total_revenue'],0), 'gold', null],
    ['⏳','Pending Orders',   $stats['pending_orders'],  'gold',   '/admin/orders.php?status=pending'],
    ['🔴','Active Rentals',   $stats['active_rentals'],  'green',  '/admin/rentals.php?status=active'],
    ['⚠️','Overdue Rentals',  $stats['overdue_rentals'], 'pink',   '/admin/rentals.php?status=overdue'],
    ['🔍','Unverified Stores',$stats['unverified_stores'],'cyan',  '/admin/stores.php?verified=0'],
  ];
  foreach ($metrics as [$icon,$label,$val,$color,$link]):
  ?>
  <?php if ($link): ?><a href="<?= $link ?>" style="text-decoration:none;"><?php endif; ?>
  <div class="stat-card">
    <div class="stat-icon <?= $color ?>"><?= $icon ?></div>
    <div>
      <div class="stat-value" style="font-size:1.4rem;" data-animate-num="<?= is_numeric($val) ? $val : 0 ?>">
        <?= is_numeric($val) ? number_format($val) : $val ?>
      </div>
      <div class="stat-label"><?= $label ?></div>
    </div>
  </div>
  <?php if ($link): ?></a><?php endif; ?>
  <?php endforeach; ?>
</div>

<div class="grid grid-2" style="gap:2rem;align-items:start;margin-bottom:2rem;">

  <!-- Recent Orders -->
  <div class="card">
    <div class="section-header" style="margin-bottom:1rem;">
      <h3 class="section-title" style="font-size:1rem;">📦 Recent Orders</h3>
      <a href="/admin/orders.php" class="btn btn-outline btn-sm" style="font-size:0.75rem;">View All</a>
    </div>
    <div style="display:flex;flex-direction:column;gap:0.6rem;">
      <?php foreach ($recent_orders as $o): ?>
      <div style="display:flex;align-items:center;justify-content:space-between;padding:0.65rem 0.75rem;background:rgba(255,255,255,0.03);border-radius:8px;">
        <div>
          <span style="font-weight:700;font-size:0.88rem;">#<?= $o['order_id'] ?></span>
          <span style="color:var(--text-muted);font-size:0.8rem;margin-left:0.5rem;">— <?= htmlspecialchars($o['username']) ?></span>
          <div style="font-size:0.72rem;color:var(--text-muted);"><?= htmlspecialchars($o['store_name']) ?> · <?= date('M d',strtotime($o['created_at'])) ?></div>
        </div>
        <div style="text-align:right;">
          <div style="font-weight:700;color:var(--accent-gold);font-size:0.88rem;">₱<?= number_format($o['total_amount'],2) ?></div>
          <span class="status <?= match($o['order_status']){'pending'=>'status-pending','confirmed','processing'=>'status-confirmed','shipped'=>'status-shipped','delivered'=>'status-delivered',default=>'status-cancelled'} ?>" style="font-size:0.65rem;"><?= ucfirst($o['order_status']) ?></span>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- New Users -->
  <div class="card">
    <div class="section-header" style="margin-bottom:1rem;">
      <h3 class="section-title" style="font-size:1rem;">👥 New Users</h3>
      <a href="/admin/users.php" class="btn btn-outline btn-sm" style="font-size:0.75rem;">View All</a>
    </div>
    <div style="display:flex;flex-direction:column;gap:0.6rem;">
      <?php foreach ($recent_users as $u): ?>
      <div style="display:flex;align-items:center;gap:0.75rem;padding:0.5rem 0.75rem;background:rgba(255,255,255,0.03);border-radius:8px;">
        <div style="width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,#e040fb,#7c4dff);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:0.85rem;color:#fff;flex-shrink:0;">
          <?= strtoupper(substr($u['username'],0,1)) ?>
        </div>
        <div style="flex:1;min-width:0;">
          <div style="font-weight:600;font-size:0.88rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($u['username']) ?></div>
          <div style="font-size:0.72rem;color:var(--text-muted);"><?= htmlspecialchars($u['email']) ?></div>
        </div>
        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:0.2rem;">
          <span class="chip <?= match($u['role']){'super_admin','admin'=>'chip-sale','seller'=>'chip-both',default=>'chip-rent'} ?>" style="font-size:0.65rem;">
            <?= ucwords(str_replace('_',' ',$u['role'])) ?>
          </span>
          <span style="font-size:0.7rem;color:var(--text-muted);"><?= date('M d',strtotime($u['created_at'])) ?></span>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

</div>

<!-- Top Stores -->
<div class="card">
  <div class="section-header" style="margin-bottom:1rem;">
    <h3 class="section-title" style="font-size:1rem;">🏆 Top Stores by Revenue</h3>
    <a href="/admin/stores.php" class="btn btn-outline btn-sm" style="font-size:0.75rem;">Manage All</a>
  </div>
  <div class="table-wrapper">
    <table>
      <thead><tr><th>#</th><th>Store</th><th>Products</th><th>Orders</th><th>Revenue</th><th>Rating</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($top_stores as $i => $st): ?>
        <tr>
          <td style="color:var(--accent-gold);font-weight:700;"><?= $i+1 ?></td>
          <td>
            <div style="font-weight:700;font-size:0.9rem;"><?= htmlspecialchars($st['store_name']) ?></div>
            <?php if ($st['is_verified']): ?><span style="font-size:0.7rem;color:#00e5ff;">✓ Verified</span><?php endif; ?>
          </td>
          <td style="color:var(--text-secondary);"><?= $st['product_count'] ?></td>
          <td style="color:var(--text-secondary);"><?= $st['order_count'] ?></td>
          <td style="color:var(--accent-gold);font-weight:700;">₱<?= number_format($st['revenue'],2) ?></td>
          <td style="color:var(--accent-gold);">⭐ <?= number_format($st['rating'],1) ?></td>
          <td><span class="status status-active">Active</span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
