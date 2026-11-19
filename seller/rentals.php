<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
startSession();
requireRole(['seller']);

$store_id   = $_SESSION['store_id'];
$page_title = 'Store Rentals';

$status_filter = $_GET['status'] ?? '';
$where  = ["r.store_id=?"];
$params = [$store_id]; $types = 'i';
if ($status_filter) { $where[] = "r.rental_status=?"; $params[] = $status_filter; $types .= 's'; }

$msg = '';
// Update rental status
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action    = $_POST['action'] ?? '';
    $rental_id = (int)($_POST['rental_id'] ?? 0);

    if ($action === 'update_status') {
        $new_status = $_POST['new_status'] ?? '';
        $allowed    = ['confirmed','active','cancelled'];
        if (in_array($new_status, $allowed)) {
            db_execute($conn, "UPDATE rentals SET rental_status=? WHERE rental_id=? AND store_id=?",
                'sii', [$new_status, $rental_id, $store_id]);
            $msg = "Rental #$rental_id status updated.";
        }
    } elseif ($action === 'process_return') {
        // Mark rental as returned and create return records
        $item_ids   = $_POST['rental_item_id']  ?? [];
        $conditions = $_POST['condition_status'] ?? [];
        $notes      = $_POST['inspection_notes'] ?? [];
        $damage_fees= $_POST['damage_fee']       ?? [];

        foreach ($item_ids as $i => $item_id) {
            $item_id    = (int)$item_id;
            $condition  = $conditions[$i]  ?? 'perfect';
            $note       = $notes[$i]       ?? '';
            $damage_fee = (float)($damage_fees[$i] ?? 0);

            // Get deposit for this item
            $ri = db_query_one($conn, "SELECT ri.*, p.rental_deposit FROM rental_items ri
                                       JOIN products p ON p.product_id=ri.product_id
                                       WHERE ri.rental_item_id=? AND ri.rental_id=?",
                               'ii', [$item_id, $rental_id]);
            if (!$ri) continue;

            $deposit_returned = max(0, ($ri['rental_deposit'] ?? 0) - $damage_fee);

            db_execute($conn,
                "INSERT INTO return_records
                 (rental_id,rental_item_id,return_date,condition_status,inspection_notes,damage_fee,deposit_returned,inspected_by)
                 VALUES (?,?,NOW(),?,?,?,?,?)",
                'iissddii',
                [$rental_id, $item_id, $condition, $note, $damage_fee, $deposit_returned, $_SESSION['user_id']]);

            // Restore rental stock
            db_execute($conn, "UPDATE products SET rental_stock=rental_stock+1 WHERE product_id=?",
                'i', [$ri['product_id']]);
        }

        db_execute($conn, "UPDATE rentals SET rental_status='returned' WHERE rental_id=? AND store_id=?",
            'ii', [$rental_id, $store_id]);

        // Notify customer
        $rental_row = db_query_one($conn, "SELECT user_id FROM rentals WHERE rental_id=?", 'i', [$rental_id]);
        if ($rental_row) {
            db_execute($conn, "INSERT INTO notifications(user_id,title,message,type,link) VALUES(?,?,?,?,?)",
                'issss', [$rental_row['user_id'],
                    "Rental #$rental_id Returned ✅",
                    "Your rental has been inspected and marked as returned. Check your rental page for deposit details.",
                    'rental', '/rentals.php']);
        }
        $msg = "Rental #$rental_id marked as returned.";
    }
}

$rentals = db_query($conn,
    "SELECT r.*, u.username, u.email,
            COUNT(ri.rental_item_id) AS item_count,
            MIN(ri.rental_start_date) AS start_date,
            MAX(ri.rental_end_date)   AS due_date
     FROM rentals r
     JOIN user_accounts u ON u.user_id=r.user_id
     LEFT JOIN rental_items ri ON ri.rental_id=r.rental_id
     WHERE " . implode(' AND ', $where) . "
     GROUP BY r.rental_id
     ORDER BY r.created_at DESC",
    $types, $params);

require_once __DIR__ . '/../includes/header.php';
?>

<h1 class="page-title">🎪 Store Rentals</h1>
<p class="page-subtitle">Manage costume rentals and returns</p>

<?php if ($msg): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($msg) ?></div><?php endif; ?>

<!-- Filter Tabs -->
<div class="filter-tags" style="margin-bottom:1.5rem;">
  <?php foreach (['' => 'All', 'pending' => '⏳ Pending', 'confirmed' => '✅ Confirmed',
                  'active' => '🟢 Active', 'returned' => '📦 Returned', 'overdue' => '⚠️ Overdue', 'cancelled' => '❌ Cancelled'] as $val => $lbl): ?>
  <a href="?status=<?= $val ?>" class="filter-tag <?= $status_filter === $val ? 'active' : '' ?>"><?= $lbl ?></a>
  <?php endforeach; ?>
</div>

<?php if (!$rentals): ?>
<div class="empty-state card"><span class="empty-icon">🎪</span><h3>No Rentals Found</h3></div>
<?php else: ?>

<div style="display:flex;flex-direction:column;gap:1.25rem;">
  <?php foreach ($rentals as $r):
    $now      = strtotime('today');
    $due      = strtotime($r['due_date'] ?? 'today');
    $overdue  = $r['rental_status'] === 'active' && $due < $now;
    $days_late= $overdue ? (int)(($now - $due) / 86400) : 0;
  ?>
  <div class="card <?= $overdue ? 'border-danger' : '' ?>" style="<?= $overdue ? 'border-color:rgba(255,82,82,0.4);' : '' ?>">
    <!-- Header -->
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:1rem;padding-bottom:0.75rem;border-bottom:1px solid var(--border-color);">
      <div style="display:flex;align-items:center;gap:1rem;">
        <div>
          <div style="font-family:'Cinzel',serif;font-weight:700;">Rental #<?= $r['rental_id'] ?></div>
          <div style="font-size:0.78rem;color:var(--text-muted);"><?= date('M d, Y', strtotime($r['created_at'])) ?></div>
        </div>
        <div>
          <div style="font-weight:600;font-size:0.9rem;">👤 <?= htmlspecialchars($r['username']) ?></div>
          <div style="font-size:0.75rem;color:var(--text-muted);"><?= htmlspecialchars($r['email']) ?></div>
        </div>
      </div>
      <div style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;">
        <?php if ($overdue): ?>
        <span class="status status-overdue">⚠️ OVERDUE <?= $days_late ?>d</span>
        <?php else: ?>
        <span class="status <?= match($r['rental_status']){'pending'=>'status-pending','confirmed'=>'status-confirmed','active'=>'status-active','returned'=>'status-returned',default=>'status-cancelled'} ?>">
          <?= ucfirst($r['rental_status']) ?>
        </span>
        <?php endif; ?>
        <span style="font-family:'Cinzel',serif;font-weight:700;color:var(--accent-gold);">₱<?= number_format($r['total_amount'] + $r['deposit_amount'], 2) ?></span>
      </div>
    </div>

    <!-- Info Grid -->
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;font-size:0.85rem;margin-bottom:1rem;">
      <div><div style="color:var(--text-muted);font-size:0.72rem;">📅 Period</div>
        <div style="font-weight:600;"><?= date('M d', strtotime($r['start_date'])) ?> → <?= date('M d, Y', strtotime($r['due_date'])) ?></div></div>
      <div><div style="color:var(--text-muted);font-size:0.72rem;">🎭 Items</div>
        <div style="font-weight:600;"><?= $r['item_count'] ?> costume<?= $r['item_count'] != 1 ? 's' : '' ?></div></div>
      <div><div style="color:var(--text-muted);font-size:0.72rem;">🔒 Deposit</div>
        <div style="font-weight:600;color:var(--accent-pink);">₱<?= number_format($r['deposit_amount'], 2) ?></div></div>
      <div><div style="color:var(--text-muted);font-size:0.72rem;">💰 Payment</div>
        <div style="font-weight:600;"><?= ucwords(str_replace('_', ' ', $r['payment_method'] ?? '—')) ?></div></div>
    </div>

    <!-- Actions -->
    <div style="display:flex;gap:0.75rem;flex-wrap:wrap;align-items:center;">
      <?php if (!in_array($r['rental_status'], ['returned', 'cancelled'])): ?>

        <?php if ($r['rental_status'] !== 'active'): ?>
        <form method="POST" style="display:flex;gap:0.4rem;">
          <input type="hidden" name="action"    value="update_status">
          <input type="hidden" name="rental_id" value="<?= $r['rental_id'] ?>">
          <select name="new_status" class="form-control" style="padding:0.3rem 0.6rem;font-size:0.82rem;width:auto;">
            <?php foreach (['confirmed' => 'Confirmed', 'active' => 'Active (Handed Out)', 'cancelled' => 'Cancelled'] as $sv => $sl): ?>
            <option value="<?= $sv ?>" <?= $r['rental_status'] === $sv ? 'selected' : '' ?>><?= $sl ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-check"></i> Update</button>
        </form>
        <?php endif; ?>

        <?php if ($r['rental_status'] === 'active'): ?>
        <button class="btn btn-success btn-sm" onclick="openReturnModal(<?= $r['rental_id'] ?>)">
          <i class="fas fa-undo"></i> Process Return
        </button>
        <?php endif; ?>

      <?php else: ?>
        <span style="color:var(--text-muted);font-size:0.85rem;">
          <?= $r['rental_status'] === 'returned' ? '✅ Returned & Inspected' : '❌ Cancelled' ?>
        </span>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ═══ RETURN MODAL ════════════════════════════════════════════ -->
<div id="return-modal" class="modal-overlay">
  <div class="modal" style="max-width:680px;">
    <div class="modal-header">
      <span class="modal-title">🔍 Process Return & Inspection</span>
      <button class="modal-close" onclick="closeModal('return-modal')">✕</button>
    </div>
    <div class="modal-body" id="return-modal-body">
      <div class="spinner"></div>
    </div>
  </div>
</div>

<script>
async function openReturnModal(rentalId) {
  openModal('return-modal');
  const body = document.getElementById('return-modal-body');
  body.innerHTML = '<div class="spinner"></div>';

  const res  = await fetch(`/api/rentals.php?action=get_items&rental_id=${rentalId}`);
  const data = await res.json();

  if (!data.success || !data.items.length) {
    body.innerHTML = '<p style="color:var(--text-muted);text-align:center;">No items found.</p>';
    return;
  }

  let html = `<form method="POST" id="return-form">
    <input type="hidden" name="action" value="process_return">
    <input type="hidden" name="rental_id" value="${rentalId}">
    <div style="margin-bottom:1rem;padding:0.75rem;background:rgba(0,229,255,0.07);border:1px solid rgba(0,229,255,0.2);border-radius:8px;font-size:0.82rem;color:var(--text-secondary);">
      ℹ️ Inspect each costume carefully. Set condition and any damage fees. Deposit will be refunded minus damage fees.
    </div>`;

  data.items.forEach((item, i) => {
    html += `
    <div style="padding:1rem;background:rgba(255,255,255,0.03);border-radius:10px;margin-bottom:0.75rem;border:1px solid var(--border-color);">
      <input type="hidden" name="rental_item_id[]" value="${item.rental_item_id}">
      <div style="font-weight:700;margin-bottom:0.75rem;">${item.product_name}</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;">
        <div>
          <label class="form-label">Condition</label>
          <select name="condition_status[]" class="form-control" onchange="calcDeposit(this,${item.deposit_per_item})">
            <option value="perfect">✅ Perfect</option>
            <option value="minor_damage">⚠️ Minor Damage</option>
            <option value="major_damage">🔴 Major Damage</option>
            <option value="lost">❌ Lost</option>
          </select>
        </div>
        <div>
          <label class="form-label">Damage Fee (₱)</label>
          <input type="number" name="damage_fee[]" class="form-control damage-fee-${i}" value="0" min="0" step="0.01"
                 max="${item.deposit_per_item}">
        </div>
      </div>
      <div class="form-group" style="margin-top:0.6rem;margin-bottom:0;">
        <label class="form-label">Inspection Notes</label>
        <textarea name="inspection_notes[]" class="form-control" rows="2" placeholder="Describe any issues..."></textarea>
      </div>
      <div style="margin-top:0.5rem;font-size:0.8rem;color:var(--text-muted);">
        Deposit: ₱${parseFloat(item.deposit_per_item).toFixed(2)}
      </div>
    </div>`;
  });

  html += `<div style="display:flex;gap:0.75rem;justify-content:flex-end;">
    <button type="button" class="btn btn-outline" onclick="closeModal('return-modal')">Cancel</button>
    <button type="submit" class="btn btn-success"><i class="fas fa-check-circle"></i> Confirm Return</button>
  </div></form>`;

  body.innerHTML = html;
  document.getElementById('return-form').addEventListener('submit', function(e) {
    e.preventDefault();
    this.closest('form') || this;
    // Submit via regular form
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '';
    new FormData(this).forEach((v, k) => {
      const inp = document.createElement('input');
      inp.type = 'hidden'; inp.name = k; inp.value = v;
      form.appendChild(inp);
    });
    document.body.appendChild(form);
    form.submit();
  });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
