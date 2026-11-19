<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
startSession();

$page_title = 'Home';

// Fetch featured products
$featured = db_query($conn,
    "SELECT p.*, s.store_name, m.title AS franchise,
            COALESCE((SELECT AVG(r.rating) FROM reviews r WHERE r.product_id=p.product_id),0) AS avg_rating
     FROM products p
     JOIN stores s ON s.store_id = p.store_id
     LEFT JOIN media_titles m ON m.media_id = p.media_id
     WHERE p.is_featured=1 AND p.is_active=1
     ORDER BY p.view_count DESC LIMIT 8"
);

// Fetch categories
$categories = db_query($conn, "SELECT * FROM categories LIMIT 6");

// Fetch featured stores
$stores = db_query($conn,
    "SELECT s.*, u.username,
            (SELECT COUNT(*) FROM products p WHERE p.store_id=s.store_id AND p.is_active=1) AS product_count
     FROM stores s JOIN user_accounts u ON u.user_id=s.user_id
     WHERE s.is_active=1 ORDER BY s.rating DESC LIMIT 4"
);

// Stats
$stats = db_query_one($conn,
    "SELECT
        (SELECT COUNT(*) FROM products WHERE is_active=1) AS total_products,
        (SELECT COUNT(*) FROM stores WHERE is_active=1) AS total_stores,
        (SELECT COUNT(*) FROM user_accounts WHERE role='customer') AS total_customers,
        (SELECT COUNT(*) FROM rentals WHERE rental_status='returned') AS total_rentals"
);

// Latest products
$latest = db_query($conn,
    "SELECT p.*, s.store_name, m.title AS franchise
     FROM products p
     JOIN stores s ON s.store_id = p.store_id
     LEFT JOIN media_titles m ON m.media_id = p.media_id
     WHERE p.is_active=1 ORDER BY p.created_at DESC LIMIT 10"
);

require_once __DIR__ . '/includes/header.php';
?>

<!-- Stars Background -->
<div class="stars-bg" id="stars-bg"></div>

<!-- ═══ HERO SECTION ════════════════════════════════════════════ -->
<section style="
  position: relative;
  min-height: 85vh;
  display: flex;
  align-items: center;
  overflow: hidden;
  margin: -2rem -2rem 3rem;
  padding: 4rem 3rem;
  background: linear-gradient(135deg, rgba(13,13,26,0.95) 0%, rgba(26,26,46,0.9) 50%, rgba(22,33,62,0.95) 100%);
">
  <!-- BG Orbs -->
  <div style="position:absolute;top:-100px;right:-100px;width:500px;height:500px;background:radial-gradient(circle,rgba(224,64,251,0.15),transparent 70%);border-radius:50%;pointer-events:none;"></div>
  <div style="position:absolute;bottom:-80px;left:-80px;width:400px;height:400px;background:radial-gradient(circle,rgba(0,229,255,0.1),transparent 70%);border-radius:50%;pointer-events:none;"></div>

  <div style="max-width:700px; position:relative; z-index:1;">
    <div style="display:inline-flex;align-items:center;gap:0.5rem;background:rgba(224,64,251,0.1);border:1px solid rgba(224,64,251,0.3);border-radius:50px;padding:0.4rem 1rem;font-size:0.8rem;color:var(--accent-pink);margin-bottom:1.5rem;">
      🎌 Philippines' #1 Cosplay Marketplace
    </div>
    <h1 style="font-family:'Cinzel',serif;font-size:clamp(2.2rem,5vw,3.8rem);font-weight:900;line-height:1.1;margin-bottom:1.2rem;">
      <span style="background:linear-gradient(135deg,#e040fb,#7c4dff);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;" class="glow-text">Costplay</span><br>
      <span style="color:var(--text-primary);">Wonderland</span>
    </h1>
    <p style="font-size:1.1rem;color:var(--text-secondary);max-width:520px;line-height:1.75;margin-bottom:2rem;">
      Discover, buy, and <span style="color:var(--accent-cyan);font-weight:700;">rent</span> premium cosplay costumes from your favorite anime, games, and movies. Transform into your favorite character today! ✨
    </p>
    <div style="display:flex;gap:1rem;flex-wrap:wrap;">
      <a href="/shop.php" class="btn btn-primary btn-lg">
        <i class="fas fa-shopping-bag"></i> Shop Now
      </a>
      <a href="/shop.php?type=rent" class="btn btn-secondary btn-lg">
        <i class="fas fa-calendar-alt"></i> Rent a Costume
      </a>
    </div>

    <!-- Mini Stats -->
    <div style="display:flex;gap:2rem;margin-top:3rem;flex-wrap:wrap;">
      <?php
      $hero_stats = [
        ['🎭', $stats['total_products'] ?? 0, 'Costumes'],
        ['🏪', $stats['total_stores']   ?? 0, 'Stores'],
        ['😊', $stats['total_customers'] ?? 0, 'Cosplayers'],
        ['🎪', $stats['total_rentals']  ?? 0, 'Rentals Done'],
      ];
      foreach ($hero_stats as [$icon, $val, $lbl]):
      ?>
      <div>
        <div style="font-family:'Cinzel',serif;font-size:1.6rem;font-weight:900;color:var(--text-primary);">
          <?= $icon ?> <span data-animate-num="<?= $val ?>"><?= number_format($val) ?></span>+
        </div>
        <div style="font-size:0.78rem;color:var(--text-muted);"><?= $lbl ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Hero Image Grid -->
  <div style="position:absolute;right:2rem;top:50%;transform:translateY(-50%);display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;width:380px;opacity:0.85;" class="hero-grid">
    <?php foreach(array_slice($featured, 0, 4) as $i => $p): ?>
    <div style="border-radius:14px;overflow:hidden;aspect-ratio:3/4;border:1px solid var(--border-color);transform:<?= $i%2===0?'rotate(-2deg)':'rotate(2deg)' ?>;">
      <img src="<?= htmlspecialchars($p['main_image'] ?? 'https://picsum.photos/seed/'.$p['product_id'].'/300/400') ?>"
           alt="<?= htmlspecialchars($p['product_name']) ?>"
           style="width:100%;height:100%;object-fit:cover;">
    </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- ═══ CATEGORIES ════════════════════════════════════════════ -->
<section style="margin-bottom:4rem;">
  <div class="section-header">
    <h2 class="section-title">🗂️ Browse Categories</h2>
    <a href="/shop.php" class="btn btn-outline btn-sm">View All</a>
  </div>
  <div class="grid" style="grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:1rem;">
    <?php foreach ($categories as $cat): ?>
    <a href="/shop.php?category=<?= urlencode($cat['slug']) ?>" style="
      display:flex;flex-direction:column;align-items:center;justify-content:center;
      gap:0.6rem;padding:1.5rem 1rem;
      background:var(--bg-card);border:1px solid var(--border-color);
      border-radius:var(--radius-md);text-decoration:none;
      transition:all 0.3s;text-align:center;
    "
    onmouseover="this.style.borderColor='var(--accent-purple)';this.style.background='rgba(124,77,255,0.1)';this.style.transform='translateY(-4px)'"
    onmouseout="this.style.borderColor='var(--border-color)';this.style.background='var(--bg-card)';this.style.transform=''">
      <span style="font-size:2rem;"><?= $cat['icon'] ?></span>
      <span style="font-size:0.85rem;font-weight:700;color:var(--text-primary);"><?= htmlspecialchars($cat['name']) ?></span>
    </a>
    <?php endforeach; ?>
    <a href="/shop.php" style="
      display:flex;flex-direction:column;align-items:center;justify-content:center;
      gap:0.6rem;padding:1.5rem 1rem;
      background:linear-gradient(135deg,rgba(224,64,251,0.1),rgba(124,77,255,0.1));
      border:1px dashed var(--accent-purple);
      border-radius:var(--radius-md);text-decoration:none;transition:all 0.3s;
    "
    onmouseover="this.style.transform='translateY(-4px)'"
    onmouseout="this.style.transform=''">
      <span style="font-size:2rem;">✨</span>
      <span style="font-size:0.85rem;font-weight:700;color:var(--accent-purple);">View All</span>
    </a>
  </div>
</section>

<!-- ═══ FEATURED PRODUCTS ════════════════════════════════════ -->
<section style="margin-bottom:4rem;">
  <div class="section-header">
    <h2 class="section-title">⭐ Featured Costumes</h2>
    <a href="/shop.php?featured=1" class="btn btn-outline btn-sm">See All Featured</a>
  </div>
  <div class="products-grid">
    <?php foreach ($featured as $p): ?>
    <?php include __DIR__ . '/includes/product_card.php'; ?>
    <?php endforeach; ?>
  </div>
</section>

<!-- ═══ HOW IT WORKS ══════════════════════════════════════════ -->
<section style="margin-bottom:4rem;padding:3rem;background:var(--bg-card);border:1px solid var(--border-color);border-radius:var(--radius-lg);">
  <h2 class="section-title" style="margin-bottom:2.5rem;justify-content:center;text-align:center;">🎪 How It Works</h2>
  <div class="grid grid-3" style="gap:2rem;">
    <?php
    $hiw = [
      ['🛍️','Buy Costumes','Browse thousands of cosplay costumes. Add to cart, checkout, and receive your costume ready for your next convention!', 'var(--accent-gold)'],
      ['📅','Rent & Enjoy','Pick your dates, pay a small deposit, and rent amazing costumes for your event. Return on time and get your deposit back!', 'var(--accent-cyan)'],
      ['🏪','Open a Store','Are you a cosplay maker? Open your own store on Costplay Wonderland, list your costumes, and earn!', 'var(--accent-pink)'],
    ];
    foreach ($hiw as $i => [$icon,$title,$desc,$color]):
    ?>
    <div style="text-align:center;padding:2rem;">
      <div style="width:70px;height:70px;border-radius:50%;background:linear-gradient(135deg,rgba(224,64,251,0.15),rgba(124,77,255,0.15));border:2px solid <?= $color ?>;display:flex;align-items:center;justify-content:center;font-size:1.8rem;margin:0 auto 1.2rem;">
        <?= $icon ?>
      </div>
      <div style="position:absolute;font-size:0.7rem;font-weight:900;color:<?= $color ?>;font-family:'Cinzel',serif;">STEP <?= $i+1 ?></div>
      <h3 style="font-family:'Cinzel',serif;font-size:1rem;margin-bottom:0.75rem;color:var(--text-primary);"><?= $title ?></h3>
      <p style="color:var(--text-secondary);font-size:0.88rem;line-height:1.7;"><?= $desc ?></p>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- ═══ LATEST ARRIVALS ════════════════════════════════════════ -->
<section style="margin-bottom:4rem;">
  <div class="section-header">
    <h2 class="section-title">🆕 Latest Arrivals</h2>
    <a href="/shop.php?sort=newest" class="btn btn-outline btn-sm">View All New</a>
  </div>
  <div class="products-grid">
    <?php foreach ($latest as $p): ?>
    <?php include __DIR__ . '/includes/product_card.php'; ?>
    <?php endforeach; ?>
  </div>
</section>

<!-- ═══ FEATURED STORES ════════════════════════════════════════ -->
<section style="margin-bottom:4rem;">
  <div class="section-header">
    <h2 class="section-title">🏪 Top Stores</h2>
    <a href="/stores.php" class="btn btn-outline btn-sm">All Stores</a>
  </div>
  <div class="grid grid-4">
    <?php foreach ($stores as $st): ?>
    <a href="/store.php?slug=<?= urlencode($st['store_slug']) ?>" style="
      display:block;background:var(--bg-card);border:1px solid var(--border-color);
      border-radius:var(--radius-md);overflow:hidden;text-decoration:none;transition:all 0.3s;
    "
    onmouseover="this.style.borderColor='var(--accent-purple)';this.style.transform='translateY(-4px)'"
    onmouseout="this.style.borderColor='var(--border-color)';this.style.transform=''">
      <!-- Banner -->
      <div style="height:90px;background:linear-gradient(135deg,rgba(224,64,251,0.3),rgba(124,77,255,0.3));position:relative;">
        <?php if ($st['banner_url']): ?>
        <img src="<?= htmlspecialchars($st['banner_url']) ?>" style="width:100%;height:100%;object-fit:cover;opacity:0.6;" alt="">
        <?php endif; ?>
        <!-- Logo -->
        <div style="position:absolute;bottom:-22px;left:1rem;width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,#e040fb,#7c4dff);border:3px solid var(--bg-card);display:flex;align-items:center;justify-content:center;font-size:1.1rem;font-weight:700;color:#fff;">
          <?= strtoupper(substr($st['store_name'],0,1)) ?>
        </div>
        <?php if ($st['is_verified']): ?>
        <div style="position:absolute;top:0.5rem;right:0.5rem;background:rgba(0,229,255,0.9);color:#001;padding:2px 7px;border-radius:50px;font-size:0.68rem;font-weight:700;">✓ Verified</div>
        <?php endif; ?>
      </div>
      <div style="padding:1.5rem 1rem 1rem;">
        <h3 style="font-size:0.95rem;font-weight:700;color:var(--text-primary);margin-bottom:0.3rem;"><?= htmlspecialchars($st['store_name']) ?></h3>
        <div style="display:flex;align-items:center;gap:0.4rem;font-size:0.78rem;color:var(--text-muted);margin-bottom:0.5rem;">
          <span style="color:var(--accent-gold);">⭐ <?= number_format($st['rating'],1) ?></span>
          <span>·</span>
          <span><?= $st['product_count'] ?> items</span>
        </div>
        <p style="font-size:0.8rem;color:var(--text-secondary);display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">
          <?= htmlspecialchars($st['description'] ?? 'Quality cosplay costumes') ?>
        </p>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
</section>

<!-- ═══ RENTAL CTA BANNER ════════════════════════════════════ -->
<section style="margin-bottom:4rem;padding:3rem;background:linear-gradient(135deg,rgba(0,229,255,0.1),rgba(124,77,255,0.15));border:1px solid rgba(0,229,255,0.3);border-radius:var(--radius-lg);text-align:center;position:relative;overflow:hidden;">
  <div style="position:absolute;inset:0;background:url('https://picsum.photos/seed/convention/1200/400') center/cover no-repeat;opacity:0.05;"></div>
  <div style="position:relative;z-index:1;">
    <div style="font-size:3rem;margin-bottom:1rem;">🎪</div>
    <h2 style="font-family:'Cinzel',serif;font-size:1.8rem;font-weight:900;margin-bottom:0.75rem;color:var(--text-primary);">
      Planning for <span style="color:var(--accent-cyan);">AniFest?</span>
    </h2>
    <p style="color:var(--text-secondary);max-width:500px;margin:0 auto 2rem;font-size:0.95rem;">
      Rent amazing cosplay costumes for your convention. Pay a small deposit, enjoy the event, return in good condition, and get your deposit back!
    </p>
    <div style="display:flex;gap:1rem;justify-content:center;flex-wrap:wrap;">
      <a href="/shop.php?type=rent" class="btn btn-primary btn-lg">
        <i class="fas fa-calendar-check"></i> Browse Rentals
      </a>
      <a href="/rentals.php" class="btn btn-secondary btn-lg">
        <i class="fas fa-info-circle"></i> Rental Guide
      </a>
    </div>
  </div>
</section>

<script>
// Create star background
createStarsBg('stars-bg', 150);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
