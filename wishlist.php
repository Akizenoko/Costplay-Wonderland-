<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
startSession();
requireLogin();

$user_id    = $_SESSION['user_id'];
$page_title = 'My Wishlist';

$items = db_query($conn,
    "SELECT p.*, s.store_name, m.title AS franchise,
            COALESCE((SELECT AVG(r.rating) FROM reviews r WHERE r.product_id=p.product_id),0) AS avg_rating,
            w.wishlist_id
     FROM wishlists w
     JOIN products p ON p.product_id=w.product_id
     JOIN stores s ON s.store_id=p.store_id
     LEFT JOIN media_titles m ON m.media_id=p.media_id
     WHERE w.user_id=? AND p.is_active=1
     ORDER BY w.added_at DESC", 'i', [$user_id]);

require_once __DIR__ . '/includes/header.php';
?>

<h1 class="page-title">❤️ My Wishlist</h1>
<p class="page-subtitle"><?= count($items) ?> saved costume<?= count($items)!==1?'s':'' ?></p>

<?php if (!$items): ?>
<div class="empty-state card">
  <span class="empty-icon">❤️</span>
  <h3>Your Wishlist is Empty</h3>
  <p>Save your favorite costumes to come back to them later!</p>
  <a href="/shop.php" class="btn btn-primary" style="margin-top:1rem;">Browse Costumes</a>
</div>
<?php else: ?>
<div class="products-grid">
  <?php foreach ($items as $p): ?>
  <?php include __DIR__ . '/includes/product_card.php'; ?>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
