<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
startSession();
requireLogin();

$user_id    = $_SESSION['user_id'];
$page_title = 'My Orders';

$orders = db_query($conn,
    "SELECT o.*, s.store_name, s.store_slug,
            COUNT(oi.order_item_id) AS item_count
     FROM orders o
     JOIN stores s ON s.store_id=o.store_id
     LEFT JOIN order_items oi ON oi.order_id=o.order_id
     WHERE o.user_id=?
     GROUP BY o.order_id
     ORDER BY o.created_at DESC", 'i', [$user_id]);

require_once __DIR__ . '/includes/header.php';

$status_map = [
    'pending'    => 'status-pending',
    'confirmed'  => 'status-confirmed',
    'processing' => 'status-confirmed',
    'shipped'    => 'status-shipped',
    'delivered'  => 'status-delivered',
    'cancelled'  => 'status-cancelled',
    'refunded'   => 'status-cancelled',
];
?>

<h1 class="page-title">📦 My Orders</h1>
<p class="page-subtitle">Track your cosplay purchases</p>

<?php if (!empty($_GET['placed'])): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> 🎉 Your order has been placed successfully! Check your email for confirmation.</div>
<?php endif; ?>

<?php if (!$orders): ?>
<div class="empty-state card">
  <span class="empty-icon">📦</span>
  <h3>No Orders Yet</h3>
  <p>Your purchased cosplay costumes will appear here.</p>
  <a href="/shop.php" class="btn btn-primary" style="margin-top:1rem;">Start Shopping</a>
</div>
<?php else: ?>

<div style="display:flex;flex-direction:column;gap:1.25rem;">
  <?php foreach ($orders as $order):
    $sc = $status_map[$order['order_status']] ?? 'status-pending';
    $ps = $order['payment_status'];
  ?>
  <div class="card">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:1rem;padding-bottom:0.75rem;border-bottom:1px solid var(--border-color);">
      <div>
        <span style="font-size:0.75rem;color:var(--text-muted);">ORDER</span>
        <div style="font-family:'Cinzel',serif;font-weight:700;font-size:1rem;">#<?= $order['order_id'] ?></div>
        <div style="font-size:0.8rem;color:var(--text-muted);"><?= date('M d, Y · h:i A',strtotime($order['created_at'])) ?></div>
      </div>
      <div style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:center;">
        <span class="status <?= $sc ?>"><?= ucfirst($order['order_status']) ?></span>
        <span class="status <?= $ps==='paid'?'status-delivered':'status-pending' ?>">
          <?= ucfirst($ps) ?>
        </span>
      </div>
    </div>

    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
      <div style="display:flex;flex-direction:column;gap:0.3rem;">
        <div style="font-size:0.85rem;color:var(--text-secondary);">
          <i class="fas fa-store" style="color:var(--accent-purple);"></i> <?= htmlspecialchars($order['store_name']) ?>
        </div>
        <div style="font-size:0.85rem;color:var(--text-secondary);">
          <i class="fas fa-box" style="color:var(--accent-cyan);"></i> <?= $order['item_count'] ?> item<?= $order['item_count']!==1?'s':'' ?>
        </div>
        <?php if ($order['payment_method']): ?>
        <div style="font-size:0.82rem;color:var(--text-muted);">
          💰 <?= ucwords(str_replace('_',' ',$order['payment_method'])) ?>
        </div>
        <?php endif; ?>
      </div>
      <div style="text-align:right;">
        <div style="font-size:0.78rem;color:var(--text-muted);">Total</div>
        <div style="font-family:'Cinzel',serif;font-size:1.3rem;font-weight:900;color:var(--accent-gold);">
          ₱<?= number_format($order['total_amount'],2) ?>
        </div>
        <button onclick="toggleOrderItems(<?= $order['order_id'] ?>)"
          class="btn btn-outline btn-sm" style="margin-top:0.5rem;font-size:0.78rem;">
          <i class="fas fa-chevron-down" id="chevron-<?= $order['order_id'] ?>"></i> View Items
        </button>
      </div>
    </div>

    <!-- Order Items (collapsed) -->
    <div id="order-items-<?= $order['order_id'] ?>" style="display:none;margin-top:1rem;padding-top:1rem;border-top:1px solid var(--border-color);">
      <?php
      $items = db_query($conn,
          "SELECT oi.*, p.product_name, p.main_image, p.character_name
           FROM order_items oi JOIN products p ON p.product_id=oi.product_id
           WHERE oi.order_id=?", 'i', [$order['order_id']]);
      foreach ($items as $item):
      ?>
      <div style="display:flex;gap:0.75rem;align-items:center;padding:0.5rem 0;">
        <div style="width:50px;height:60px;border-radius:8px;overflow:hidden;background:var(--bg-mid);flex-shrink:0;">
          <img src="<?= htmlspecialchars($item['main_image']??'https://picsum.photos/50/60') ?>"
               style="width:100%;height:100%;object-fit:cover;">
        </div>
        <div style="flex:1;">
          <div style="font-size:0.9rem;font-weight:600;"><?= htmlspecialchars($item['product_name']) ?></div>
          <?php if ($item['character_name']): ?>
          <div style="font-size:0.78rem;color:var(--accent-pink);">as <?= htmlspecialchars($item['character_name']) ?></div>
          <?php endif; ?>
          <div style="font-size:0.8rem;color:var(--text-muted);">Qty: <?= $item['quantity'] ?></div>
        </div>
        <div style="font-weight:700;color:var(--accent-gold);font-size:0.9rem;">
          ₱<?= number_format($item['unit_price']*$item['quantity'],2) ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<script>
function toggleOrderItems(id) {
  const el  = document.getElementById('order-items-'+id);
  const ch  = document.getElementById('chevron-'+id);
  const vis = el.style.display !== 'none';
  el.style.display = vis ? 'none' : 'block';
  ch.className = vis ? 'fas fa-chevron-down' : 'fas fa-chevron-up';
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
