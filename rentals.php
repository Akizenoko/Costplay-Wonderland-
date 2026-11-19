<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
startSession();
requireLogin();

$user_id    = $_SESSION['user_id'];
$page_title = 'My Rentals';

$rentals = db_query($conn,
    "SELECT r.*, s.store_name,
            COUNT(ri.rental_item_id) AS item_count,
            MIN(ri.rental_start_date) AS earliest_start,
            MAX(ri.rental_end_date)   AS latest_end
     FROM rentals r
     JOIN stores s ON s.store_id=r.store_id
     LEFT JOIN rental_items ri ON ri.rental_id=r.rental_id
     WHERE r.user_id=?
     GROUP BY r.rental_id
     ORDER BY r.created_at DESC", 'i', [$user_id]);

require_once __DIR__ . '/includes/header.php';

$status_map = [
    'pending'   => 'status-pending',
    'confirmed' => 'status-confirmed',
    'active'    => 'status-active',
    'returned'  => 'status-returned',
    'overdue'   => 'status-overdue',
    'cancelled' => 'status-cancelled',
];
?>

<h1 class="page-title">🎪 My Rentals</h1>
<p class="page-subtitle">Track your cosplay costume rentals</p>

<div style="margin-bottom:1.5rem;padding:1rem 1.25rem;background:rgba(0,229,255,0.07);border:1px solid rgba(0,229,255,0.25);border-radius:var(--radius-md);">
  <strong style="color:var(--accent-cyan);">📋 Rental Reminder:</strong>
  <span style="color:var(--text-secondary);font-size:0.9rem;"> Return costumes on time, in original condition. Deposits are refunded after inspection. Damage fees may apply.</span>
</div>

<?php if (!$rentals): ?>
<div class="empty-state card">
  <span class="empty-icon">🎪</span>
  <h3>No Rentals Yet</h3>
  <p>Rent amazing cosplay costumes for your next convention!</p>
  <a href="/shop.php?type=rent" class="btn btn-primary" style="margin-top:1rem;">Browse Rentals</a>
</div>
<?php else: ?>

<div style="display:flex;flex-direction:column;gap:1.25rem;">
  <?php foreach ($rentals as $r):
    $sc  = $status_map[$r['rental_status']] ?? 'status-pending';
    $now = strtotime('today');
    $due = strtotime($r['latest_end']);
    $days_left = (int)(($due - $now) / 86400);
    $overdue   = $r['rental_status'] === 'active' && $days_left < 0;
  ?>
  <div class="card">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:1rem;padding-bottom:0.75rem;border-bottom:1px solid var(--border-color);">
      <div>
        <span style="font-size:0.75rem;color:var(--text-muted);">RENTAL</span>
        <div style="font-family:'Cinzel',serif;font-weight:700;font-size:1rem;">#<?= $r['rental_id'] ?></div>
        <div style="font-size:0.8rem;color:var(--text-muted);"><?= date('M d, Y',strtotime($r['created_at'])) ?></div>
      </div>
      <div style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:center;">
        <?php if ($overdue): ?>
        <span class="status status-overdue">⚠️ Overdue</span>
        <?php else: ?>
        <span class="status <?= $sc ?>"><?= ucfirst($r['rental_status']) ?></span>
        <?php endif; ?>
        <span class="status <?= $r['payment_status']==='fully_paid'?'status-delivered':'status-pending' ?>">
          <?= ucwords(str_replace('_',' ',$r['payment_status'])) ?>
        </span>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;margin-bottom:1rem;font-size:0.85rem;">
      <div>
        <div style="color:var(--text-muted);font-size:0.72rem;">🏪 Store</div>
        <div style="font-weight:600;"><?= htmlspecialchars($r['store_name']) ?></div>
      </div>
      <div>
        <div style="color:var(--text-muted);font-size:0.72rem;">📅 Rental Period</div>
        <div style="font-weight:600;">
          <?= date('M d',strtotime($r['earliest_start'])) ?> → <?= date('M d, Y',strtotime($r['latest_end'])) ?>
        </div>
      </div>
      <div>
        <div style="color:var(--text-muted);font-size:0.72rem;">🔒 Deposit</div>
        <div style="font-weight:600;color:var(--accent-pink);">₱<?= number_format($r['deposit_amount'],2) ?></div>
      </div>
    </div>

    <?php if ($r['rental_status'] === 'active'): ?>
    <div style="margin-bottom:1rem;padding:0.75rem 1rem;border-radius:8px;background:<?= $overdue?'rgba(255,82,82,0.1)':'rgba(0,230,118,0.08)' ?>;border:1px solid <?= $overdue?'rgba(255,82,82,0.3)':'rgba(0,230,118,0.2)' ?>;">
      <?php if ($overdue): ?>
      <span style="color:#ff5252;font-weight:700;">⚠️ OVERDUE by <?= abs($days_left) ?> day<?= abs($days_left)!==1?'s':'' ?>! Please return immediately.</span>
      <?php else: ?>
      <span style="color:#00e676;font-weight:600;">
        ✅ Due in <b><?= $days_left ?></b> day<?= $days_left!==1?'s':'' ?> (<?= date('M d, Y',strtotime($r['latest_end'])) ?>)
      </span>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.75rem;">
      <div style="font-size:0.85rem;color:var(--text-muted);">
        <?= $r['item_count'] ?> item<?= $r['item_count']!==1?'s':'' ?>
        · <?= ucwords(str_replace('_',' ',$r['payment_method'] ?? '')) ?>
      </div>
      <div style="display:flex;align-items:center;gap:0.75rem;">
        <div style="text-align:right;">
          <div style="font-size:0.72rem;color:var(--text-muted);">Total</div>
          <div style="font-family:'Cinzel',serif;font-weight:900;color:var(--accent-gold);">₱<?= number_format($r['total_amount']+$r['deposit_amount'],2) ?></div>
        </div>
        <button onclick="toggleRentalItems(<?= $r['rental_id'] ?>)" class="btn btn-outline btn-sm">
          <i class="fas fa-chevron-down" id="rchevron-<?= $r['rental_id'] ?>"></i> Details
        </button>
      </div>
    </div>

    <!-- Rental Items (collapsed) -->
    <div id="rental-items-<?= $r['rental_id'] ?>" style="display:none;margin-top:1rem;padding-top:1rem;border-top:1px solid var(--border-color);">
      <?php
      $ritems = db_query($conn,
          "SELECT ri.*, p.product_name, p.main_image, p.character_name,
                  rr.condition_status, rr.inspection_notes, rr.damage_fee, rr.deposit_returned
           FROM rental_items ri
           JOIN products p ON p.product_id=ri.product_id
           LEFT JOIN return_records rr ON rr.rental_item_id=ri.rental_item_id
           WHERE ri.rental_id=?", 'i', [$r['rental_id']]);
      foreach ($ritems as $ri):
        $cond_colors = ['perfect'=>'#00e676','minor_damage'=>'#ffd700','major_damage'=>'#ff5252','lost'=>'#ff1744'];
        $cond_color  = $cond_colors[$ri['condition_status'] ?? ''] ?? 'var(--text-muted)';
      ?>
      <div style="display:flex;gap:0.75rem;align-items:flex-start;padding:0.75rem 0;border-bottom:1px solid rgba(255,255,255,0.04);">
        <div style="width:55px;height:68px;border-radius:8px;overflow:hidden;background:var(--bg-mid);flex-shrink:0;">
          <img src="<?= htmlspecialchars($ri['main_image']??'https://picsum.photos/55/68') ?>"
               style="width:100%;height:100%;object-fit:cover;">
        </div>
        <div style="flex:1;">
          <div style="font-weight:600;font-size:0.9rem;"><?= htmlspecialchars($ri['product_name']) ?></div>
          <?php if ($ri['character_name']): ?>
          <div style="font-size:0.78rem;color:var(--accent-pink);">as <?= htmlspecialchars($ri['character_name']) ?></div>
          <?php endif; ?>
          <div style="font-size:0.8rem;color:var(--text-secondary);">
            📅 <?= date('M d',strtotime($ri['rental_start_date'])) ?> → <?= date('M d, Y',strtotime($ri['rental_end_date'])) ?>
            (<?= $ri['days_count'] ?> days × ₱<?= number_format($ri['daily_rate'],2) ?>)
          </div>
          <?php if ($ri['condition_status']): ?>
          <div style="margin-top:0.3rem;font-size:0.8rem;color:<?= $cond_color ?>;font-weight:600;">
            🔍 Returned: <?= ucwords(str_replace('_',' ',$ri['condition_status'])) ?>
          </div>
          <?php if ($ri['inspection_notes']): ?>
          <div style="font-size:0.78rem;color:var(--text-muted);font-style:italic;">"<?= htmlspecialchars($ri['inspection_notes']) ?>"</div>
          <?php endif; ?>
          <?php if ($ri['damage_fee'] > 0): ?>
          <div style="font-size:0.8rem;color:#ff5252;">💸 Damage fee: ₱<?= number_format($ri['damage_fee'],2) ?></div>
          <?php endif; ?>
          <?php if ($ri['deposit_returned'] > 0): ?>
          <div style="font-size:0.8rem;color:#00e676;">✅ Deposit returned: ₱<?= number_format($ri['deposit_returned'],2) ?></div>
          <?php endif; ?>
          <?php endif; ?>
        </div>
        <div style="font-weight:700;color:var(--accent-cyan);font-size:0.9rem;text-align:right;">
          ₱<?= number_format($ri['subtotal'],2) ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<script>
function toggleRentalItems(id) {
  const el = document.getElementById('rental-items-'+id);
  const ch = document.getElementById('rchevron-'+id);
  const vis = el.style.display !== 'none';
  el.style.display = vis ? 'none' : 'block';
  ch.className = vis ? 'fas fa-chevron-down' : 'fas fa-chevron-up';
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
