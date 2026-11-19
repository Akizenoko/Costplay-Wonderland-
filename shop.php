<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
startSession();
$page_title = 'Shop';

// Filters
$q        = trim($_GET['q']        ?? '');
$category = trim($_GET['category'] ?? '');
$type     = trim($_GET['type']     ?? '');
$media    = trim($_GET['media']    ?? '');
$sort     = trim($_GET['sort']     ?? 'newest');
$min_p    = (float)($_GET['min_price'] ?? 0);
$max_p    = (float)($_GET['max_price'] ?? 99999);
$page_num = max(1,(int)($_GET['page'] ?? 1));
$per_page = 20;
$offset   = ($page_num - 1) * $per_page;

// Build WHERE
$where  = ["p.is_active = 1"];
$params = [];
$types  = '';

if ($q) {
    $where[] = "(p.product_name LIKE ? OR p.character_name LIKE ? OR m.title LIKE ?)";
    $like = "%$q%";
    $params = array_merge($params, [$like, $like, $like]);
    $types .= 'sss';
}
if ($category) {
    $where[] = "c.slug = ?";
    $params[] = $category; $types .= 's';
}
if ($type === 'rent') {
    $where[] = "p.listing_type IN ('rent','both')";
} elseif ($type === 'sale') {
    $where[] = "p.listing_type IN ('sale','both')";
}
if ($media) {
    $where[] = "m.media_id = ?";
    $params[] = (int)$media; $types .= 'i';
}
if ($min_p > 0) {
    $where[] = "(p.sale_price >= ? OR p.rental_price_per_day >= ?)";
    $params[] = $min_p; $params[] = $min_p; $types .= 'dd';
}
if ($max_p < 99999) {
    $where[] = "(p.sale_price <= ? OR p.rental_price_per_day <= ?)";
    $params[] = $max_p; $params[] = $max_p; $types .= 'dd';
}
if (!empty($_GET['featured'])) {
    $where[] = "p.is_featured = 1";
}

$where_sql = implode(' AND ', $where);

$order_sql = match($sort) {
    'price_asc'  => 'COALESCE(p.sale_price, p.rental_price_per_day) ASC',
    'price_desc' => 'COALESCE(p.sale_price, p.rental_price_per_day) DESC',
    'popular'    => 'p.view_count DESC',
    'rating'     => 'avg_rating DESC',
    default      => 'p.created_at DESC',
};

// Count
$count_sql = "SELECT COUNT(*) as total
              FROM products p
              JOIN stores s ON s.store_id = p.store_id
              LEFT JOIN media_titles m ON m.media_id = p.media_id
              LEFT JOIN categories c ON c.category_id = p.category_id
              WHERE $where_sql";
$count_stmt = $conn->prepare($count_sql);
if ($types) $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_products = $count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();
$total_pages = ceil($total_products / $per_page);

// Products
$sql = "SELECT p.*, s.store_name, m.title AS franchise, c.name AS category_name,
               COALESCE((SELECT AVG(r.rating) FROM reviews r WHERE r.product_id=p.product_id),0) AS avg_rating
        FROM products p
        JOIN stores s ON s.store_id = p.store_id
        LEFT JOIN media_titles m ON m.media_id = p.media_id
        LEFT JOIN categories c ON c.category_id = p.category_id
        WHERE $where_sql
        ORDER BY $order_sql
        LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$all_params = array_merge($params, [$per_page, $offset]);
$all_types  = $types . 'ii';
$stmt->bind_param($all_types, ...$all_params);
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Sidebar filters data
$categories_all = db_query($conn, "SELECT * FROM categories ORDER BY name");
$media_all      = db_query($conn, "SELECT * FROM media_titles ORDER BY title LIMIT 30");

require_once __DIR__ . '/includes/header.php';
?>

<div style="display:grid;grid-template-columns:240px 1fr;gap:2rem;align-items:start;">

<!-- ═══ FILTERS SIDEBAR ════════════════════════════════════════ -->
<aside>
  <form method="GET" id="filter-form">
    <?php if ($q): ?><input type="hidden" name="q" value="<?= htmlspecialchars($q) ?>"><?php endif; ?>

    <div class="card" style="position:sticky;top:calc(var(--header-h) + 1rem);">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.2rem;">
        <h3 style="font-family:'Cinzel',serif;font-size:0.95rem;font-weight:700;">🔍 Filters</h3>
        <a href="/shop.php" class="btn btn-outline btn-sm" style="font-size:0.75rem;">Reset</a>
      </div>

      <!-- Type -->
      <div style="margin-bottom:1.5rem;">
        <div class="form-label">Listing Type</div>
        <div style="display:flex;flex-direction:column;gap:0.4rem;">
          <?php foreach ([''=>'All','sale'=>'🛒 For Sale','rent'=>'📅 For Rent','both'=>'💫 Buy & Rent'] as $val=>$lbl): ?>
          <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;font-size:0.88rem;color:var(--text-secondary);padding:0.3rem 0.5rem;border-radius:6px;" onmouseover="this.style.background='rgba(124,77,255,0.08)'" onmouseout="this.style.background=''">
            <input type="radio" name="type" value="<?= $val ?>" <?= ($type===$val)?'checked':'' ?> onchange="document.getElementById('filter-form').submit()" style="accent-color:var(--accent-purple);">
            <?= $lbl ?>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Category -->
      <div style="margin-bottom:1.5rem;">
        <div class="form-label">Category</div>
        <select name="category" class="form-control" onchange="this.form.submit()">
          <option value="">All Categories</option>
          <?php foreach ($categories_all as $cat): ?>
          <option value="<?= htmlspecialchars($cat['slug']) ?>" <?= ($category===$cat['slug'])?'selected':'' ?>>
            <?= htmlspecialchars($cat['icon'].' '.$cat['name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Franchise -->
      <div style="margin-bottom:1.5rem;">
        <div class="form-label">Franchise / Series</div>
        <select name="media" class="form-control" onchange="this.form.submit()">
          <option value="">All Franchises</option>
          <?php foreach ($media_all as $med): ?>
          <option value="<?= $med['media_id'] ?>" <?= ((int)$media===$med['media_id'])?'selected':'' ?>>
            <?= htmlspecialchars($med['title']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Price -->
      <div style="margin-bottom:1.5rem;">
        <div class="form-label">Price Range (₱)</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;">
          <input type="number" name="min_price" class="form-control" placeholder="Min" value="<?= $min_p ?: '' ?>" min="0">
          <input type="number" name="max_price" class="form-control" placeholder="Max" value="<?= ($max_p < 99999) ? $max_p : '' ?>" min="0">
        </div>
      </div>

      <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-filter"></i> Apply Filters</button>
    </div>
  </form>
</aside>

<!-- ═══ MAIN PRODUCTS AREA ══════════════════════════════════════ -->
<div>
  <!-- Toolbar -->
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem;">
    <div>
      <h1 class="page-title" style="font-size:1.5rem;margin-bottom:0.1rem;">
        <?= $q ? "Results for \"".htmlspecialchars($q)."\"" : ($category ? ucwords(str_replace('-',' ',$category)) : '🛍️ All Costumes') ?>
      </h1>
      <p style="color:var(--text-muted);font-size:0.85rem;"><?= number_format($total_products) ?> costumes found</p>
    </div>
    <div style="display:flex;align-items:center;gap:0.75rem;">
      <label style="font-size:0.85rem;color:var(--text-secondary);">Sort by:</label>
      <select class="form-control" style="width:auto;padding:0.4rem 0.75rem;" onchange="window.location='?<?= http_build_query(array_merge($_GET,['sort'=>'__SORT__','page'=>1])) ?>'.replace('__SORT__',this.value)">
        <?php foreach(['newest'=>'Newest','popular'=>'Most Popular','rating'=>'Top Rated','price_asc'=>'Price: Low→High','price_desc'=>'Price: High→Low'] as $val=>$lbl): ?>
        <option value="<?= $val ?>" <?= ($sort===$val)?'selected':'' ?>><?= $lbl ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <!-- Active filter tags -->
  <?php $active = array_filter(['q'=>$q,'category'=>$category,'type'=>$type,'media'=>$media]); ?>
  <?php if ($active): ?>
  <div class="filter-tags" style="margin-bottom:1.5rem;">
    <?php foreach ($active as $k=>$v): ?>
    <a href="?<?= http_build_query(array_diff_key($_GET,[$k=>1])) ?>" class="filter-tag active">
      <?= htmlspecialchars($v) ?> ✕
    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Products Grid -->
  <?php if ($products): ?>
  <div class="products-grid">
    <?php foreach ($products as $p): ?>
    <?php include __DIR__ . '/includes/product_card.php'; ?>
    <?php endforeach; ?>
  </div>

  <!-- Pagination -->
  <?php if ($total_pages > 1): ?>
  <div class="pagination">
    <?php
    $base = '?' . http_build_query(array_merge($_GET, ['page' => '__P__']));
    if ($page_num > 1): ?>
    <a href="<?= str_replace('__P__', $page_num-1, $base) ?>" class="page-btn">‹</a>
    <?php endif;
    for ($i = max(1,$page_num-2); $i <= min($total_pages,$page_num+2); $i++): ?>
    <a href="<?= str_replace('__P__', $i, $base) ?>" class="page-btn <?= $i===$page_num?'active':'' ?>"><?= $i ?></a>
    <?php endfor;
    if ($page_num < $total_pages): ?>
    <a href="<?= str_replace('__P__', $page_num+1, $base) ?>" class="page-btn">›</a>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <?php else: ?>
  <div class="empty-state">
    <span class="empty-icon">🎭</span>
    <h3>No Costumes Found</h3>
    <p>Try adjusting your filters or search term.</p>
    <a href="/shop.php" class="btn btn-primary" style="margin-top:1rem;">Browse All</a>
  </div>
  <?php endif; ?>
</div>
</div><!-- /grid -->

<?php require_once __DIR__ . '/includes/footer.php'; ?>
