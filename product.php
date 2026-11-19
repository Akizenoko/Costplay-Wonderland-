<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
startSession();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /shop.php'); exit; }

$p = db_query_one($conn,
    "SELECT p.*, s.store_name, s.store_slug, s.rating AS store_rating,
            m.title AS franchise, m.media_type,
            c.name AS category_name,
            COALESCE((SELECT AVG(r.rating) FROM reviews r WHERE r.product_id=p.product_id),0) AS avg_rating,
            (SELECT COUNT(*) FROM reviews r WHERE r.product_id=p.product_id) AS review_count
     FROM products p
     JOIN stores s ON s.store_id=p.store_id
     LEFT JOIN media_titles m ON m.media_id=p.media_id
     LEFT JOIN categories c ON c.category_id=p.category_id
     WHERE p.product_id=? AND p.is_active=1", 'i', [$id]);

if (!$p) { header('Location: /shop.php'); exit; }

// Increment view count
$conn->query("UPDATE products SET view_count=view_count+1 WHERE product_id=$id");

$user = getCurrentUser();
$review_success = '';
$review_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_review') {
    if (!$user) {
        $review_error = 'Please log in to submit a review.';
    } else {
        $rating = max(1, min(5, (int)($_POST['rating'] ?? 5)));
        $title  = trim($_POST['title'] ?? '');
        $body   = trim($_POST['body'] ?? '');
        if (empty($body)) {
            $review_error = 'Please enter your review comments.';
        } else {
            $already = db_query_one($conn, "SELECT review_id FROM reviews WHERE product_id=? AND user_id=?", 'ii', [$id, $user['user_id']]);
            if ($already) {
                $review_error = 'You have already reviewed this product.';
            } else {
                $has_purchased = db_query_one($conn,
                    "SELECT oi.order_item_id FROM order_items oi
                     JOIN orders o ON o.order_id=oi.order_id
                     WHERE oi.product_id=? AND o.user_id=? LIMIT 1",
                    'ii', [$id, $user['user_id']]
                );
                $is_verified = $has_purchased ? 1 : 0;

                db_execute($conn,
                    "INSERT INTO reviews (product_id, user_id, rating, title, body, is_verified_purchase)
                     VALUES (?, ?, ?, ?, ?, ?)",
                    'iiissi', [$id, $user['user_id'], $rating, $title, $body, $is_verified]
                );
                $review_success = 'Thank you! Your cosplay review has been submitted.';
            }
        }
    }
}

// Reviews
$reviews = db_query($conn,
    "SELECT r.*, u.username, u.avatar_url FROM reviews r
     JOIN user_accounts u ON u.user_id=r.user_id
     WHERE r.product_id=? ORDER BY r.created_at DESC LIMIT 10", 'i', [$id]);

// Related products
$related = db_query($conn,
    "SELECT p.*, s.store_name, m.title AS franchise,
            COALESCE((SELECT AVG(r.rating) FROM reviews r WHERE r.product_id=p.product_id),0) AS avg_rating
     FROM products p JOIN stores s ON s.store_id=p.store_id
     LEFT JOIN media_titles m ON m.media_id=p.media_id
     WHERE p.store_id=? AND p.product_id!=? AND p.is_active=1 LIMIT 4",
    'ii', [$p['store_id'], $id]);

$page_title = $p['product_name'];
require_once __DIR__ . '/includes/header.php';

$img  = $p['main_image'] ?? "https://picsum.photos/seed/product{$id}/600/750";
$imgs = json_decode($p['images'] ?? '[]', true) ?: [$img];
if (!in_array($img, $imgs)) array_unshift($imgs, $img);
?>

<div style="max-width:1100px;margin:0 auto;">

  <!-- Breadcrumb -->
  <div style="font-size:0.82rem;color:var(--text-muted);margin-bottom:1.5rem;display:flex;align-items:center;gap:0.4rem;flex-wrap:wrap;">
    <a href="/index.php" style="color:var(--text-muted);text-decoration:none;">Home</a> /
    <a href="/shop.php"  style="color:var(--text-muted);text-decoration:none;">Shop</a> /
    <?php if ($p['franchise']): ?>
    <a href="/shop.php?media=<?= $p['media_id'] ?>" style="color:var(--text-muted);text-decoration:none;"><?= htmlspecialchars($p['franchise']) ?></a> /
    <?php endif; ?>
    <span style="color:var(--accent-purple);"><?= htmlspecialchars($p['product_name']) ?></span>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:3rem;margin-bottom:3rem;">

    <!-- ── LEFT: Images ── -->
    <div>
      <div id="main-image-wrap" style="border-radius:var(--radius-lg);overflow:hidden;border:1px solid var(--border-color);aspect-ratio:3/4;background:var(--bg-mid);margin-bottom:0.75rem;">
        <img id="main-img" src="<?= htmlspecialchars($imgs[0]) ?>" alt="<?= htmlspecialchars($p['product_name']) ?>"
             style="width:100%;height:100%;object-fit:cover;transition:opacity 0.3s;">
      </div>
      <!-- Thumbnails -->
      <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
        <?php foreach ($imgs as $i => $imgUrl): ?>
        <div onclick="switchImg('<?= htmlspecialchars($imgUrl) ?>',this)"
             style="width:70px;height:85px;border-radius:8px;overflow:hidden;border:2px solid <?= $i===0?'var(--accent-purple)':'var(--border-color)' ?>;cursor:pointer;transition:border-color 0.2s;">
          <img src="<?= htmlspecialchars($imgUrl) ?>" style="width:100%;height:100%;object-fit:cover;">
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- ── RIGHT: Info ── -->
    <div>
      <?php if ($p['franchise']): ?>
      <div style="display:inline-flex;align-items:center;gap:0.4rem;background:rgba(0,229,255,0.1);border:1px solid rgba(0,229,255,0.3);border-radius:50px;padding:0.3rem 0.9rem;font-size:0.78rem;color:var(--accent-cyan);margin-bottom:0.75rem;">
        🎌 <?= htmlspecialchars($p['franchise']) ?> · <?= ucfirst($p['media_type'] ?? '') ?>
      </div>
      <?php endif; ?>

      <h1 style="font-family:'Cinzel',serif;font-size:1.6rem;font-weight:900;color:var(--text-primary);line-height:1.25;margin-bottom:0.5rem;">
        <?= htmlspecialchars($p['product_name']) ?>
      </h1>

      <?php if ($p['character_name']): ?>
      <div style="color:var(--text-secondary);font-size:0.95rem;margin-bottom:0.75rem;">
        as <b style="color:var(--accent-pink);"><?= htmlspecialchars($p['character_name']) ?></b>
      </div>
      <?php endif; ?>

      <!-- Rating -->
      <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:1.25rem;">
        <div style="display:flex;gap:2px;">
          <?php for ($i=1;$i<=5;$i++): ?>
          <span style="color:<?= $i<=$p['avg_rating']?'#ffd700':'rgba(255,215,0,0.3)' ?>;font-size:1rem;">★</span>
          <?php endfor; ?>
        </div>
        <span style="color:var(--accent-gold);font-weight:700;"><?= number_format($p['avg_rating'],1) ?></span>
        <span style="color:var(--text-muted);font-size:0.85rem;">(<?= $p['review_count'] ?> reviews)</span>
        <span style="color:var(--text-muted);">·</span>
        <span style="font-size:0.85rem;color:var(--text-muted);"><?= number_format($p['view_count']) ?> views</span>
      </div>

      <!-- Pricing -->
      <div style="background:rgba(124,77,255,0.08);border:1px solid rgba(124,77,255,0.2);border-radius:var(--radius-md);padding:1.25rem;margin-bottom:1.5rem;">
        <?php if ($p['listing_type'] !== 'rent'): ?>
        <div style="display:flex;align-items:baseline;gap:0.5rem;margin-bottom:<?= $p['listing_type']==='both'?'0.75rem':'0' ?>;">
          <span style="font-family:'Cinzel',serif;font-size:1.8rem;font-weight:900;color:var(--accent-gold);">
            ₱<?= number_format($p['sale_price'],2) ?>
          </span>
          <span class="chip chip-sale">🛒 For Sale</span>
        </div>
        <?php endif; ?>
        <?php if ($p['listing_type'] !== 'sale'): ?>
        <div style="display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap;">
          <span style="font-family:'Cinzel',serif;font-size:1.3rem;font-weight:800;color:var(--accent-cyan);">
            ₱<?= number_format($p['rental_price_per_day'],2) ?><span style="font-size:0.75rem;font-weight:400;">/day</span>
          </span>
          <span class="chip chip-rent">📅 Rentable</span>
          <span style="font-size:0.82rem;color:var(--text-muted);">Deposit: ₱<?= number_format($p['rental_deposit'],2) ?></span>
        </div>
        <?php endif; ?>
      </div>

      <!-- Details -->
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.6rem;margin-bottom:1.5rem;font-size:0.88rem;">
        <?php $details = [
          ['📏','Size', $p['size'] ?? 'Various'],
          ['⭐','Condition', ucfirst(str_replace('_',' ',$p['condition_rating'] ?? 'new'))],
          ['📦','Stock', ($p['stock_quantity'] ?? 0).' available'],
          ['🎪','Rental Stock', ($p['rental_stock'] ?? 0).' available'],
        ]; ?>
        <?php foreach ($details as [$icon,$label,$val]): ?>
        <div style="background:rgba(255,255,255,0.03);border:1px solid var(--border-color);border-radius:8px;padding:0.6rem 0.8rem;">
          <div style="color:var(--text-muted);font-size:0.72rem;margin-bottom:0.1rem;"><?= $icon ?> <?= $label ?></div>
          <div style="color:var(--text-primary);font-weight:600;"><?= htmlspecialchars($val) ?></div>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Add to Cart -->
      <?php if ($p['listing_type'] !== 'rent'): ?>
      <div style="margin-bottom:0.75rem;">
        <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:0.75rem;">
          <div style="display:flex;align-items:center;gap:0;border:1px solid var(--border-color);border-radius:8px;overflow:hidden;">
            <button onclick="adjustQty(-1)" style="width:36px;height:36px;background:rgba(255,255,255,0.05);border:none;color:var(--text-primary);cursor:pointer;font-size:1.1rem;">−</button>
            <input type="number" id="qty-input" value="1" min="1" max="<?= $p['stock_quantity'] ?>"
                   style="width:50px;text-align:center;background:transparent;border:none;color:var(--text-primary);font-size:0.95rem;outline:none;">
            <button onclick="adjustQty(1)" style="width:36px;height:36px;background:rgba(255,255,255,0.05);border:none;color:var(--text-primary);cursor:pointer;font-size:1.1rem;">+</button>
          </div>
        </div>
        <button class="btn btn-primary btn-block" onclick="addToCartSale()">
          <i class="fas fa-cart-plus"></i> Add to Cart — Buy
        </button>
      </div>
      <?php endif; ?>

      <?php if ($p['listing_type'] !== 'sale'): ?>
      <!-- Rental form -->
      <div style="background:rgba(0,229,255,0.05);border:1px solid rgba(0,229,255,0.2);border-radius:var(--radius-md);padding:1.25rem;margin-bottom:0.75rem;">
        <div style="font-weight:700;font-size:0.9rem;color:var(--accent-cyan);margin-bottom:0.75rem;">📅 Rent This Costume</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;margin-bottom:0.75rem;">
          <div>
            <label class="form-label">Start Date</label>
            <input type="date" id="rent-start" class="form-control"
                   min="<?= date('Y-m-d') ?>" onchange="updateRentalCost()">
          </div>
          <div>
            <label class="form-label">End Date</label>
            <input type="date" id="rent-end" class="form-control"
                   min="<?= date('Y-m-d',strtotime('+1 day')) ?>" onchange="updateRentalCost()">
          </div>
        </div>
        <div id="rental-summary" style="display:none;font-size:0.85rem;color:var(--text-secondary);margin-bottom:0.75rem;padding:0.75rem;background:rgba(0,0,0,0.2);border-radius:8px;line-height:1.9;">
          <div>📅 Duration: <b id="rent-days" style="color:var(--accent-cyan);">0 days</b></div>
          <div>💰 Rental Total: <b id="rent-total" style="color:var(--accent-gold);">₱0.00</b></div>
          <div>🔒 Deposit: <b style="color:var(--accent-pink);">₱<?= number_format($p['rental_deposit'],2) ?></b></div>
        </div>
        <button class="btn btn-block" style="background:linear-gradient(135deg,#00e5ff,#7c4dff);color:#fff;" onclick="addToCartRent()">
          <i class="fas fa-calendar-check"></i> Add to Cart — Rent
        </button>
      </div>
      <?php endif; ?>

      <button class="btn btn-outline btn-block" onclick="toggleWishlist(<?= $id ?>, this)">
        <i class="far fa-heart"></i> Save to Wishlist
      </button>

      <!-- Store Info -->
      <div style="margin-top:1.5rem;padding:1rem;background:rgba(255,255,255,0.03);border:1px solid var(--border-color);border-radius:var(--radius-md);display:flex;align-items:center;gap:0.75rem;">
        <div style="width:42px;height:42px;border-radius:50%;background:var(--gradient-main);display:flex;align-items:center;justify-content:center;font-weight:700;color:#fff;font-size:1rem;flex-shrink:0;">
          <?= strtoupper(substr($p['store_name'],0,1)) ?>
        </div>
        <div style="flex:1;">
          <div style="font-weight:700;font-size:0.9rem;"><?= htmlspecialchars($p['store_name']) ?></div>
          <div style="font-size:0.78rem;color:var(--accent-gold);">⭐ <?= number_format($p['store_rating'],1) ?></div>
        </div>
        <a href="/store.php?slug=<?= urlencode($p['store_slug']) ?>" class="btn btn-outline btn-sm">Visit Store</a>
      </div>
    </div>
  </div><!-- /product grid -->

  <!-- Description -->
  <div class="card" style="margin-bottom:2rem;">
    <h2 class="section-title" style="margin-bottom:1rem;">📖 Description</h2>
    <p style="color:var(--text-secondary);line-height:1.85;font-size:0.95rem;">
      <?= nl2br(htmlspecialchars($p['description'] ?? 'No description available.')) ?>
    </p>
    <?php if ($p['listing_type'] !== 'sale'): ?>
    <div style="margin-top:1.5rem;padding:1rem;background:rgba(0,229,255,0.05);border:1px solid rgba(0,229,255,0.2);border-radius:10px;">
      <div style="font-weight:700;color:var(--accent-cyan);margin-bottom:0.6rem;">🎪 Rental Terms</div>
      <ul style="color:var(--text-secondary);font-size:0.88rem;line-height:2;padding-left:1.2rem;">
        <li>Costume must be returned on or before the agreed return date.</li>
        <li>No alterations, cutting, dyeing, or permanent modifications allowed.</li>
        <li>Minor wear is acceptable; major damage or stains will incur fees.</li>
        <li>Deposit of <b style="color:var(--accent-pink);">₱<?= number_format($p['rental_deposit'],2) ?></b> is required and refunded after inspection.</li>
        <li>Lost items are charged at full replacement cost.</li>
      </ul>
    </div>
    <?php endif; ?>
  </div>

  <!-- Reviews -->
  <div class="card" style="margin-bottom:2rem;">
    <div class="section-header" style="margin-bottom:1.5rem;">
      <h2 class="section-title">⭐ Customer Reviews</h2>
      <span style="color:var(--text-muted);font-size:0.85rem;"><?= count($reviews) ?> reviews</span>
    </div>

    <?php if ($review_success): ?>
    <div class="alert alert-success" style="background:rgba(40,167,69,.15);border:1px solid #28a745;border-radius:12px;padding:1rem;color:#51cf66;margin-bottom:1.5rem;">
      <i class="fas fa-check-circle"></i> <?= htmlspecialchars($review_success) ?>
    </div>
    <?php endif; ?>

    <?php if ($review_error): ?>
    <div class="alert alert-danger" style="background:rgba(220,53,69,.15);border:1px solid #dc3545;border-radius:12px;padding:1rem;color:#ff6b6b;margin-bottom:1.5rem;">
      <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($review_error) ?>
    </div>
    <?php endif; ?>

    <!-- Review Form -->
    <?php if ($user): ?>
    <div style="background:rgba(255,255,255,0.03);border:1px solid var(--border-color);border-radius:var(--radius-md);padding:1.5rem;margin-bottom:2rem;">
      <h3 style="font-size:1.1rem;margin-bottom:1rem;color:#fff;"><i class="fas fa-pen-nib"></i> Write a Review</h3>
      <form method="POST" action="#reviews">
        <input type="hidden" name="action" value="add_review">
        <div style="display:grid;grid-template-columns:140px 1fr;gap:1rem;margin-bottom:1rem;">
          <div class="form-group" style="margin:0;">
            <label class="form-label" style="font-size:0.85rem;">Rating</label>
            <select name="rating" class="form-control" style="font-size:0.9rem;">
              <option value="5">⭐⭐⭐⭐⭐ (5/5)</option>
              <option value="4">⭐⭐⭐⭐ (4/5)</option>
              <option value="3">⭐⭐⭐ (3/5)</option>
              <option value="2">⭐⭐ (2/5)</option>
              <option value="1">⭐ (1/5)</option>
            </select>
          </div>
          <div class="form-group" style="margin:0;">
            <label class="form-label" style="font-size:0.85rem;">Title (optional)</label>
            <input type="text" name="title" class="form-control" placeholder="e.g. Incredible quality for convention!" style="font-size:0.9rem;">
          </div>
        </div>
        <div class="form-group" style="margin-bottom:1rem;">
          <label class="form-label" style="font-size:0.85rem;">Review</label>
          <textarea name="body" class="form-control" rows="3" placeholder="Share your experience with fit, materials, fabric quality, and packaging..." required style="font-size:0.9rem;"></textarea>
        </div>
        <button type="submit" class="btn btn-primary" style="padding:0.6rem 1.5rem;font-size:0.9rem;">
          <i class="fas fa-paper-plane"></i> Submit Review
        </button>
      </form>
    </div>
    <?php else: ?>
    <div style="background:rgba(255,255,255,0.02);border:1px dashed var(--border-color);border-radius:var(--radius-md);padding:1.25rem;text-align:center;margin-bottom:2rem;">
      <span style="color:var(--text-muted);font-size:0.9rem;">Want to share your thoughts on this cosplay? </span>
      <a href="/login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>" style="color:var(--neon-pink);font-weight:600;text-decoration:none;">Log in to write a review</a>
    </div>
    <?php endif; ?>

    <div id="reviews">

    <?php if ($reviews): ?>
    <div style="display:flex;flex-direction:column;gap:1.25rem;">
      <?php foreach ($reviews as $rv): ?>
      <div style="padding-bottom:1.25rem;border-bottom:1px solid var(--border-color);">
        <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:0.5rem;">
          <div style="width:36px;height:36px;border-radius:50%;background:var(--gradient-main);display:flex;align-items:center;justify-content:center;font-size:0.8rem;font-weight:700;color:#fff;">
            <?= strtoupper(substr($rv['username'],0,1)) ?>
          </div>
          <div>
            <div style="font-weight:700;font-size:0.88rem;"><?= htmlspecialchars($rv['username']) ?></div>
            <div style="display:flex;gap:2px;">
              <?php for ($i=1;$i<=5;$i++): ?>
              <span style="color:<?= $i<=$rv['rating']?'#ffd700':'rgba(255,215,0,0.3)' ?>;font-size:0.85rem;">★</span>
              <?php endfor; ?>
            </div>
          </div>
          <?php if ($rv['is_verified_purchase']): ?>
          <span style="font-size:0.72rem;color:#00e676;background:rgba(0,230,118,0.1);border:1px solid rgba(0,230,118,0.3);padding:1px 7px;border-radius:50px;margin-left:auto;">✓ Verified Purchase</span>
          <?php endif; ?>
        </div>
        <?php if ($rv['title']): ?>
        <div style="font-weight:700;margin-bottom:0.3rem;font-size:0.9rem;"><?= htmlspecialchars($rv['title']) ?></div>
        <?php endif; ?>
        <p style="color:var(--text-secondary);font-size:0.88rem;line-height:1.65;"><?= htmlspecialchars($rv['body']) ?></p>
        <div style="font-size:0.75rem;color:var(--text-muted);margin-top:0.4rem;"><?= date('M d, Y',strtotime($rv['created_at'])) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="empty-state" style="padding:2rem;">
      <span class="empty-icon">💬</span>
      <p>No reviews yet. Be the first to review!</p>
    </div>
    <?php endif; ?>
    </div><!-- /#reviews -->
  </div>

  <!-- Related Products -->
  <?php if ($related): ?>
  <div>
    <div class="section-header"><h2 class="section-title">🎭 More from <?= htmlspecialchars($p['store_name']) ?></h2></div>
    <div class="products-grid">
      <?php foreach ($related as $p): ?>
      <?php include __DIR__ . '/includes/product_card.php'; ?>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

</div><!-- /max-width -->

<script>
const PRODUCT_ID = <?= $id ?>;
const SALE_PRICE = <?= (float)($p['sale_price'] ?? 0) ?>;
const RENT_RATE  = <?= (float)($p['rental_price_per_day'] ?? 0) ?>;
const DEPOSIT    = <?= (float)($p['rental_deposit'] ?? 0) ?>;

function switchImg(src, thumb) {
  document.getElementById('main-img').style.opacity = 0;
  setTimeout(() => {
    document.getElementById('main-img').src = src;
    document.getElementById('main-img').style.opacity = 1;
  }, 200);
  document.querySelectorAll('[onclick*="switchImg"]').forEach(t => t.style.borderColor = 'var(--border-color)');
  thumb.style.borderColor = 'var(--accent-purple)';
}

function adjustQty(delta) {
  const inp = document.getElementById('qty-input');
  const max = parseInt(inp.max) || 99;
  inp.value = Math.max(1, Math.min(max, parseInt(inp.value||1) + delta));
}

function addToCartSale() {
  const qty = parseInt(document.getElementById('qty-input')?.value || 1);
  Cart.addItem(PRODUCT_ID, 'purchase', qty);
}

function addToCartRent() {
  const s = document.getElementById('rent-start')?.value;
  const e = document.getElementById('rent-end')?.value;
  if (!s || !e) { Toast.error('Please select rental dates.'); return; }
  if (new Date(e) <= new Date(s)) { Toast.error('End date must be after start date.'); return; }
  Cart.addItem(PRODUCT_ID, 'rental', 1, s, e);
}

function updateRentalCost() {
  const s = document.getElementById('rent-start')?.value;
  const e = document.getElementById('rent-end')?.value;
  const summary = document.getElementById('rental-summary');
  if (!s || !e) { summary.style.display='none'; return; }
  const days = Math.ceil((new Date(e)-new Date(s))/(1000*60*60*24));
  if (days <= 0) { summary.style.display='none'; return; }
  document.getElementById('rent-days').textContent  = days + ' day' + (days>1?'s':'');
  document.getElementById('rent-total').textContent = '₱' + (days * RENT_RATE).toLocaleString('en-PH',{minimumFractionDigits:2});
  summary.style.display = 'block';
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
