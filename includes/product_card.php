<?php
// includes/product_card.php — reusable product card partial
// Expects $p array with product data
$type_badge = match($p['listing_type'] ?? 'both') {
    'sale' => '<span class="chip chip-sale">🛒 For Sale</span>',
    'rent' => '<span class="chip chip-rent">📅 For Rent</span>',
    default => '<span class="chip chip-both">💫 Buy / Rent</span>',
};
$avg = round($p['avg_rating'] ?? 0, 1);
$img = $p['main_image'] ?? 'https://picsum.photos/seed/costume' . $p['product_id'] . '/400/500';
?>
<div class="product-card" onclick="window.location='/product.php?id=<?= $p['product_id'] ?>'">
  <div class="product-card-image">
    <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($p['product_name']) ?>" loading="lazy">

    <div class="product-card-badges">
      <?= $type_badge ?>
      <?php if (!empty($p['is_featured'])): ?>
      <span class="chip chip-featured">⭐ Featured</span>
      <?php endif; ?>
    </div>

    <div class="product-card-actions">
      <button class="product-action-btn" title="Add to Wishlist"
              onclick="event.stopPropagation(); toggleWishlist(<?= $p['product_id'] ?>, this)">
        <i class="far fa-heart"></i>
      </button>
      <button class="product-action-btn" title="Quick View"
              onclick="event.stopPropagation(); window.location='/product.php?id=<?= $p['product_id'] ?>'">
        <i class="fas fa-eye"></i>
      </button>
      <button class="product-action-btn" title="Add to Cart"
              onclick="event.stopPropagation(); Cart.addItem(<?= $p['product_id'] ?>, 'purchase')">
        <i class="fas fa-cart-plus"></i>
      </button>
    </div>
  </div>

  <div class="product-card-info">
    <?php if (!empty($p['franchise'])): ?>
    <div class="product-card-franchise"><?= htmlspecialchars($p['franchise']) ?></div>
    <?php endif; ?>

    <div class="product-card-name"><?= htmlspecialchars($p['product_name']) ?></div>

    <?php if (!empty($p['character_name'])): ?>
    <div class="product-card-character">as <?= htmlspecialchars($p['character_name']) ?></div>
    <?php endif; ?>

    <div class="product-card-pricing">
      <?php if ($p['sale_price']): ?>
      <span class="price-sale">₱<?= number_format($p['sale_price'], 2) ?></span>
      <?php endif; ?>
      <?php if ($p['rental_price_per_day']): ?>
      <span class="price-rent">📅 ₱<?= number_format($p['rental_price_per_day'], 2) ?>/day</span>
      <?php endif; ?>
    </div>

    <div class="product-card-footer">
      <span class="product-card-store">
        <i class="fas fa-store"></i>
        <?= htmlspecialchars($p['store_name'] ?? '') ?>
      </span>
      <?php if ($avg > 0): ?>
      <span class="product-card-rating">
        ⭐ <?= $avg ?>
      </span>
      <?php endif; ?>
    </div>
  </div>
</div>
