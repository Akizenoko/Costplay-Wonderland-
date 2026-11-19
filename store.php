<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
startSession();

$slug = trim($_GET['slug'] ?? '');
if (!$slug) { header('Location: /stores.php'); exit; }

$store = db_query_one($conn,
    "SELECT s.*, u.username, u.email
     FROM stores s JOIN user_accounts u ON u.user_id=s.user_id
     WHERE s.store_slug=? AND s.is_active=1", 's', [$slug]);
if (!$store) { header('Location: /stores.php'); exit; }

$page_title = $store['store_name'];

// Products
$type     = $_GET['type']     ?? '';
$category = $_GET['category'] ?? '';
$sort     = $_GET['sort']     ?? 'newest';

$where  = ["p.store_id=? AND p.is_active=1"];
$params = [$store['store_id']]; $types = 'i';

if ($type === 'rent') { $where[] = "p.listing_type IN ('rent','both')"; }
elseif ($type === 'sale') { $where[] = "p.listing_type IN ('sale','both')"; }

$order_sql = match($sort) {
    'price_asc'  => 'p.sale_price ASC',
    'price_desc' => 'p.sale_price DESC',
    'popular'    => 'p.view_count DESC',
    default      => 'p.created_at DESC',
};

$products = db_query($conn,
    "SELECT p.*, s.store_name, m.title AS franchise,
            COALESCE((SELECT AVG(r.rating) FROM reviews r WHERE r.product_id=p.product_id),0) AS avg_rating
     FROM products p
     JOIN stores s ON s.store_id=p.store_id
     LEFT JOIN media_titles m ON m.media_id=p.media_id
     WHERE ".implode(' AND ',$where)."
     ORDER BY $order_sql",
    $types, $params);

$stats = db_query_one($conn,
    "SELECT COUNT(*) AS total,
            SUM(CASE WHEN listing_type IN ('sale','both') THEN 1 ELSE 0 END) AS for_sale,
            SUM(CASE WHEN listing_type IN ('rent','both') THEN 1 ELSE 0 END) AS for_rent
     FROM products WHERE store_id=? AND is_active=1", 'i', [$store['store_id']]);

require_once __DIR__ . '/includes/header.php';
?>

<!-- Store Banner -->
<div style="margin:-2rem -2rem 2rem;position:relative;height:220px;overflow:hidden;">
  <?php if ($store['banner_url']): ?>
  <img src="<?= htmlspecialchars($store['banner_url']) ?>" style="width:100%;height:100%;object-fit:cover;opacity:0.6;">
  <?php else: ?>
  <div style="width:100%;height:100%;background:linear-gradient(135deg,#1a0a2e,#0d1a3a,#1a0a2e);"></div>
  <?php endif; ?>
  <div style="position:absolute;inset:0;background:linear-gradient(to top,rgba(13,13,26,1) 0%,rgba(13,13,26,0.4) 60%,transparent 100%);"></div>

  <!-- Store Info -->
  <div style="position:absolute;bottom:1.5rem;left:2rem;display:flex;align-items:flex-end;gap:1.25rem;">
    <div style="width:72px;height:72px;border-radius:16px;background:linear-gradient(135deg,#e040fb,#7c4dff);border:3px solid rgba(255,255,255,0.15);display:flex;align-items:center;justify-content:center;font-size:1.8rem;font-weight:900;color:#fff;box-shadow:0 8px 24px rgba(0,0,0,0.5);flex-shrink:0;">
      <?= strtoupper(substr($store['store_name'],0,1)) ?>
    </div>
    <div>
      <div style="display:flex;align-items:center;gap:0.6rem;flex-wrap:wrap;">
        <h1 style="font-family:'Cinzel',serif;font-size:1.6rem;font-weight:900;color:#fff;text-shadow:0 2px 8px rgba(0,0,0,0.6);"><?= htmlspecialchars($store['store_name']) ?></h1>
        <?php if ($store['is_verified']): ?>
        <span style="background:rgba(0,229,255,0.9);color:#001;padding:2px 8px;border-radius:50px;font-size:0.72rem;font-weight:700;">✓ Verified</span>
        <?php endif; ?>
      </div>
      <div style="display:flex;align-items:center;gap:1rem;font-size:0.85rem;color:rgba(255,255,255,0.75);flex-wrap:wrap;">
        <span style="color:var(--accent-gold);">⭐ <?= number_format($store['rating'],1) ?></span>
        <span>·</span>
        <span><?= $stats['total'] ?> products</span>
        <span>·</span>
        <span>🛒 <?= $stats['for_sale'] ?> for sale</span>
        <span>·</span>
        <span>📅 <?= $stats['for_rent'] ?> for rent</span>
      </div>
    </div>
  </div>
</div>

<div style="max-width:1200px;margin:0 auto;">

  <!-- Store Description -->
  <?php if ($store['description']): ?>
  <div class="card" style="margin-bottom:2rem;">
    <p style="color:var(--text-secondary);font-size:0.95rem;line-height:1.75;"><?= htmlspecialchars($store['description']) ?></p>
    <?php if ($store['contact_phone']): ?>
    <div style="margin-top:0.75rem;font-size:0.85rem;color:var(--text-muted);">
      📞 <?= htmlspecialchars($store['contact_phone']) ?>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- Filters Toolbar -->
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem;">
    <div class="filter-tags">
      <a href="?slug=<?= $slug ?>" class="filter-tag <?= !$type?'active':'' ?>">All</a>
      <a href="?slug=<?= $slug ?>&type=sale" class="filter-tag <?= $type==='sale'?'active':'' ?>">🛒 For Sale</a>
      <a href="?slug=<?= $slug ?>&type=rent" class="filter-tag <?= $type==='rent'?'active':'' ?>">📅 For Rent</a>
    </div>
    <select class="form-control" style="width:auto;" onchange="window.location='?slug=<?= $slug ?>&type=<?= $type ?>&sort='+this.value">
      <option value="newest"     <?= $sort==='newest'    ?'selected':'' ?>>Newest</option>
      <option value="popular"    <?= $sort==='popular'   ?'selected':'' ?>>Most Popular</option>
      <option value="price_asc"  <?= $sort==='price_asc' ?'selected':'' ?>>Price: Low → High</option>
      <option value="price_desc" <?= $sort==='price_desc'?'selected':'' ?>>Price: High → Low</option>
    </select>
  </div>

  <!-- Products -->
  <?php if ($products): ?>
  <div class="products-grid">
    <?php foreach ($products as $p): ?>
    <?php include __DIR__ . '/includes/product_card.php'; ?>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <div class="empty-state card">
    <span class="empty-icon">🎭</span>
    <h3>No Products Found</h3>
    <p>This store hasn't listed any costumes yet.</p>
  </div>
  <?php endif; ?>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
