<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
startSession();
$page_title = 'Stores';

$search = trim($_GET['q'] ?? '');
$where  = ["s.is_active=1"];
$params = []; $types = '';
if ($search) {
    $where[] = "(s.store_name LIKE ? OR s.description LIKE ?)";
    $like = "%$search%"; $params = [$like,$like]; $types = 'ss';
}
$where_sql = implode(' AND ', $where);

$stores = db_query($conn,
    "SELECT s.*, u.username,
            (SELECT COUNT(*) FROM products p WHERE p.store_id=s.store_id AND p.is_active=1) AS product_count,
            (SELECT COUNT(*) FROM orders o WHERE o.store_id=s.store_id) AS total_orders
     FROM stores s JOIN user_accounts u ON u.user_id=s.user_id
     WHERE $where_sql
     ORDER BY s.rating DESC, s.total_sales DESC", $types ?: null, $params ?: null);

require_once __DIR__ . '/includes/header.php';
?>

<h1 class="page-title">🏪 All Stores</h1>
<p class="page-subtitle">Discover cosplay stores from talented makers across the Philippines</p>

<!-- Search -->
<form method="GET" style="max-width:500px;margin-bottom:2rem;display:flex;gap:0.75rem;">
  <div style="flex:1;position:relative;">
    <i class="fas fa-search" style="position:absolute;left:1rem;top:50%;transform:translateY(-50%);color:var(--text-muted);"></i>
    <input type="text" name="q" class="form-control" style="padding-left:2.8rem;" placeholder="Search stores..."
           value="<?= htmlspecialchars($search) ?>">
  </div>
  <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
</form>

<?php if (!$stores): ?>
<div class="empty-state card">
  <span class="empty-icon">🏪</span>
  <h3>No Stores Found</h3>
  <?php if ($search): ?>
  <p>No stores match "<?= htmlspecialchars($search) ?>". <a href="/stores.php">View all stores</a></p>
  <?php else: ?>
  <p>No stores available yet. <a href="/register.php?role=seller">Open yours!</a></p>
  <?php endif; ?>
</div>
<?php else: ?>
<div class="grid grid-3">
  <?php foreach ($stores as $st): ?>
  <a href="/store.php?slug=<?= urlencode($st['store_slug']) ?>" style="text-decoration:none;">
    <div class="card" style="overflow:hidden;padding:0;transition:all 0.3s;" onmouseover="this.style.borderColor='var(--accent-purple)';this.style.transform='translateY(-5px)'" onmouseout="this.style.borderColor='var(--border-color)';this.style.transform=''">
      <!-- Banner -->
      <div style="height:110px;background:linear-gradient(135deg,rgba(224,64,251,0.4),rgba(124,77,255,0.4));position:relative;overflow:hidden;">
        <?php if ($st['banner_url']): ?>
        <img src="<?= htmlspecialchars($st['banner_url']) ?>" style="width:100%;height:100%;object-fit:cover;opacity:0.5;">
        <?php else: ?>
        <div style="width:100%;height:100%;background:linear-gradient(135deg,<?= ['#1a0a2e','#0a1a2e','#2e0a1a','#0a2e1a','#1a1a0a'][crc32($st['store_name'])%5] ?>,var(--bg-mid));"></div>
        <?php endif; ?>
        <div style="position:absolute;bottom:-20px;left:1rem;width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,#e040fb,#7c4dff);border:3px solid var(--bg-card);display:flex;align-items:center;justify-content:center;font-size:1.1rem;font-weight:900;color:#fff;box-shadow:0 4px 12px rgba(0,0,0,0.4);">
          <?= strtoupper(substr($st['store_name'],0,1)) ?>
        </div>
        <?php if ($st['is_verified']): ?>
        <div style="position:absolute;top:0.5rem;right:0.5rem;background:rgba(0,229,255,0.9);color:#001;padding:2px 8px;border-radius:50px;font-size:0.68rem;font-weight:700;">✓ Verified</div>
        <?php endif; ?>
      </div>

      <div style="padding:1.5rem 1rem 1rem;">
        <h3 style="font-size:1rem;font-weight:700;color:var(--text-primary);margin-bottom:0.3rem;"><?= htmlspecialchars($st['store_name']) ?></h3>
        <div style="display:flex;align-items:center;gap:0.75rem;font-size:0.8rem;color:var(--text-muted);margin-bottom:0.6rem;">
          <span style="color:var(--accent-gold);">⭐ <?= number_format($st['rating'],1) ?></span>
          <span>·</span>
          <span><?= $st['product_count'] ?> items</span>
          <span>·</span>
          <span><?= $st['total_orders'] ?> sales</span>
        </div>
        <p style="font-size:0.82rem;color:var(--text-secondary);display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;line-height:1.6;">
          <?= htmlspecialchars($st['description'] ?? 'Quality cosplay costumes for every fan.') ?>
        </p>
        <div style="margin-top:0.75rem;padding-top:0.75rem;border-top:1px solid var(--border-color);">
          <span class="btn btn-primary btn-sm" style="pointer-events:none;">Visit Store →</span>
        </div>
      </div>
    </div>
  </a>
  <?php endforeach; ?>
</div>

<!-- CTA: Open a store -->
<div style="margin-top:3rem;text-align:center;padding:3rem;background:linear-gradient(135deg,rgba(224,64,251,0.08),rgba(124,77,255,0.1));border:1px dashed rgba(124,77,255,0.4);border-radius:var(--radius-lg);">
  <div style="font-size:3rem;margin-bottom:1rem;">🏪</div>
  <h2 style="font-family:'Cinzel',serif;font-size:1.4rem;margin-bottom:0.75rem;">Want to Sell Your Costumes?</h2>
  <p style="color:var(--text-secondary);max-width:400px;margin:0 auto 1.5rem;font-size:0.9rem;">
    Open your own Costplay Wonderland store and reach thousands of cosplay fans across the Philippines!
  </p>
  <a href="/register.php?role=seller" class="btn btn-primary btn-lg">
    <i class="fas fa-store"></i> Open a Store
  </a>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
