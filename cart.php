<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
startSession();
requireLogin();

$user_id    = $_SESSION['user_id'];
$page_title = 'My Cart';

// Fetch cart items with product info
$cart_items = db_query($conn,
    "SELECT ci.*, p.product_name, p.character_name, p.main_image, p.sale_price,
            p.rental_price_per_day, p.rental_deposit, p.stock_quantity, p.rental_stock,
            p.listing_type, s.store_name, s.store_id,
            m.title AS franchise
     FROM cart_items ci
     JOIN products p ON p.product_id = ci.product_id
     JOIN stores s ON s.store_id = p.store_id
     LEFT JOIN media_titles m ON m.media_id = p.media_id
     WHERE ci.user_id = ?
     ORDER BY s.store_id, ci.added_at DESC", 'i', [$user_id]);

// Group by store
$grouped = [];
$grand_total = 0;
foreach ($cart_items as $item) {
    $key = $item['store_id'];
    if (!isset($grouped[$key])) {
        $grouped[$key] = ['store_name' => $item['store_name'], 'items' => []];
    }
    if ($item['item_type'] === 'purchase') {
        $sub = ($item['sale_price'] ?? 0) * $item['quantity'];
    } else {
        $days = max(1, (int)round((strtotime($item['rental_end_date']) - strtotime($item['rental_start_date'])) / 86400));
        $sub  = ($item['rental_price_per_day'] ?? 0) * $days * $item['quantity']
              + ($item['rental_deposit'] ?? 0) * $item['quantity'];
    }
    $item['subtotal'] = $sub;
    $grand_total += $sub;
    $grouped[$key]['items'][] = $item;
}

require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width:1000px;margin:0 auto;">
  <h1 class="page-title">🛒 My Cart</h1>
  <p class="page-subtitle"><?= count($cart_items) ?> item<?= count($cart_items)!==1?'s':'' ?> in your cart</p>

  <?php if (!$cart_items): ?>
  <div class="empty-state card">
    <span class="empty-icon">🛒</span>
    <h3>Your Cart is Empty</h3>
    <p>Add some amazing cosplay costumes to get started!</p>
    <a href="/shop.php" class="btn btn-primary" style="margin-top:1rem;">Browse Costumes</a>
  </div>
  <?php else: ?>

  <div style="display:grid;grid-template-columns:1fr 320px;gap:2rem;align-items:start;">

    <!-- Cart Items -->
    <div>
      <?php foreach ($grouped as $store_id => $group): ?>
      <div class="card" style="margin-bottom:1.5rem;">
        <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:1rem;padding-bottom:0.75rem;border-bottom:1px solid var(--border-color);">
          <span style="font-size:1rem;">🏪</span>
          <span style="font-weight:700;font-size:0.95rem;"><?= htmlspecialchars($group['store_name']) ?></span>
        </div>

        <?php foreach ($group['items'] as $item): ?>
        <div id="cart-row-<?= $item['cart_item_id'] ?>" style="display:flex;gap:1rem;padding:1rem 0;border-bottom:1px solid rgba(255,255,255,0.04);align-items:center;">
          <!-- Image -->
          <a href="/product.php?id=<?= $item['product_id'] ?>" style="flex-shrink:0;">
            <div style="width:80px;height:100px;border-radius:10px;overflow:hidden;background:var(--bg-mid);">
              <img src="<?= htmlspecialchars($item['main_image'] ?? 'https://picsum.photos/seed/cp'.$item['product_id'].'/80/100') ?>"
                   style="width:100%;height:100%;object-fit:cover;" alt="">
            </div>
          </a>

          <!-- Info -->
          <div style="flex:1;min-width:0;">
            <div style="font-weight:700;font-size:0.95rem;margin-bottom:0.2rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
              <?= htmlspecialchars($item['product_name']) ?>
            </div>
            <?php if ($item['character_name']): ?>
            <div style="font-size:0.8rem;color:var(--accent-pink);margin-bottom:0.3rem;">as <?= htmlspecialchars($item['character_name']) ?></div>
            <?php endif; ?>

            <?php if ($item['item_type'] === 'rental'): ?>
            <div style="font-size:0.8rem;color:var(--accent-cyan);display:flex;align-items:center;gap:0.4rem;margin-bottom:0.3rem;">
              📅 <?= date('M d',strtotime($item['rental_start_date'])) ?> → <?= date('M d, Y',strtotime($item['rental_end_date'])) ?>
            </div>
            <div style="font-size:0.78rem;color:var(--text-muted);">
              ₱<?= number_format($item['rental_price_per_day'],2) ?>/day + ₱<?= number_format($item['rental_deposit'],2) ?> deposit
            </div>
            <span class="chip chip-rent" style="margin-top:0.3rem;">📅 Rental</span>
            <?php else: ?>
            <div style="font-size:0.88rem;color:var(--accent-gold);">₱<?= number_format($item['sale_price'],2) ?> each</div>
            <span class="chip chip-sale" style="margin-top:0.3rem;">🛒 Purchase</span>
            <?php endif; ?>
          </div>

          <!-- Qty + Subtotal + Remove -->
          <div style="display:flex;flex-direction:column;align-items:flex-end;gap:0.5rem;flex-shrink:0;">
            <?php if ($item['item_type'] === 'purchase'): ?>
            <div style="display:flex;align-items:center;gap:0;border:1px solid var(--border-color);border-radius:8px;overflow:hidden;">
              <button onclick="updateQty(<?= $item['cart_item_id'] ?>, -1)"
                style="width:30px;height:30px;background:rgba(255,255,255,0.05);border:none;color:var(--text-primary);cursor:pointer;">−</button>
              <span style="padding:0 0.6rem;font-size:0.9rem;min-width:28px;text-align:center;"><?= $item['quantity'] ?></span>
              <button onclick="updateQty(<?= $item['cart_item_id'] ?>, 1)"
                style="width:30px;height:30px;background:rgba(255,255,255,0.05);border:none;color:var(--text-primary);cursor:pointer;">+</button>
            </div>
            <?php endif; ?>
            <div style="font-family:'Cinzel',serif;font-weight:700;color:var(--accent-gold);">
              ₱<?= number_format($item['subtotal'],2) ?>
            </div>
            <button onclick="removeItem(<?= $item['cart_item_id'] ?>)"
              style="background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:0.8rem;display:flex;align-items:center;gap:0.3rem;"
              onmouseover="this.style.color='#ff5252'" onmouseout="this.style.color='var(--text-muted)'">
              <i class="fas fa-trash-alt"></i> Remove
            </button>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endforeach; ?>

      <a href="/shop.php" style="display:inline-flex;align-items:center;gap:0.5rem;color:var(--text-secondary);font-size:0.88rem;text-decoration:none;" onmouseover="this.style.color='var(--accent-cyan)'" onmouseout="this.style.color='var(--text-secondary)'">
        ← Continue Shopping
      </a>
    </div>

    <!-- Order Summary -->
    <div class="card" style="position:sticky;top:calc(var(--header-h) + 1rem);">
      <h3 style="font-family:'Cinzel',serif;font-size:1rem;font-weight:700;margin-bottom:1.25rem;">📋 Order Summary</h3>

      <?php
      $purchase_total = 0; $rental_total = 0; $deposit_total = 0;
      foreach ($cart_items as $item) {
          if ($item['item_type'] === 'purchase') {
              $purchase_total += ($item['sale_price'] ?? 0) * $item['quantity'];
          } else {
              $days = max(1,(int)round((strtotime($item['rental_end_date'])-strtotime($item['rental_start_date']))/86400));
              $rental_total  += ($item['rental_price_per_day'] ?? 0) * $days;
              $deposit_total += ($item['rental_deposit'] ?? 0);
          }
      }
      ?>

      <div style="font-size:0.9rem;line-height:2.2;">
        <?php if ($purchase_total > 0): ?>
        <div style="display:flex;justify-content:space-between;color:var(--text-secondary);">
          <span>🛒 Purchases</span><span>₱<?= number_format($purchase_total,2) ?></span>
        </div>
        <?php endif; ?>
        <?php if ($rental_total > 0): ?>
        <div style="display:flex;justify-content:space-between;color:var(--text-secondary);">
          <span>📅 Rental Fees</span><span>₱<?= number_format($rental_total,2) ?></span>
        </div>
        <div style="display:flex;justify-content:space-between;color:var(--text-secondary);">
          <span>🔒 Deposits</span><span>₱<?= number_format($deposit_total,2) ?></span>
        </div>
        <?php endif; ?>
        <div style="border-top:1px solid var(--border-color);margin:0.75rem 0;"></div>
        <div style="display:flex;justify-content:space-between;font-size:1rem;font-weight:800;color:var(--accent-gold);">
          <span>Grand Total</span><span>₱<?= number_format($grand_total,2) ?></span>
        </div>
      </div>

      <?php if ($deposit_total > 0): ?>
      <div style="margin-top:0.75rem;padding:0.6rem 0.75rem;background:rgba(0,229,255,0.07);border:1px solid rgba(0,229,255,0.2);border-radius:8px;font-size:0.78rem;color:var(--text-secondary);">
        🔒 ₱<?= number_format($deposit_total,2) ?> deposit is refundable after rental return & inspection.
      </div>
      <?php endif; ?>

      <div style="margin-top:1.25rem;display:flex;flex-direction:column;gap:0.6rem;">
        <a href="/checkout.php" class="btn btn-primary btn-block btn-lg">
          <i class="fas fa-credit-card"></i> Proceed to Checkout
        </a>
        <button onclick="clearCart()" class="btn btn-outline btn-block">
          <i class="fas fa-trash"></i> Clear Cart
        </button>
      </div>
    </div>

  </div>
  <?php endif; ?>
</div>

<script>
async function updateQty(cartItemId, delta) {
  const body = new FormData();
  body.append('action', 'update');
  body.append('cart_item_id', cartItemId);
  body.append('delta', delta);
  const res  = await fetch('/api/cart.php', {method:'POST', body});
  const data = await res.json();
  if (data.success) { location.reload(); }
  else Toast.error(data.message || 'Could not update quantity.');
}

async function removeItem(cartItemId) {
  const body = new FormData();
  body.append('action', 'remove');
  body.append('cart_item_id', cartItemId);
  const res  = await fetch('/api/cart.php', {method:'POST', body});
  const data = await res.json();
  if (data.success) {
    document.getElementById('cart-row-' + cartItemId)?.remove();
    Cart.updateBadge(data.cart_count);
    Toast.success('Item removed.');
    setTimeout(() => location.reload(), 600);
  }
}

async function clearCart() {
  if (!confirm('Remove all items from your cart?')) return;
  const body = new FormData();
  body.append('action', 'clear');
  const res  = await fetch('/api/cart.php', {method:'POST', body});
  const data = await res.json();
  if (data.success) { location.reload(); }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
