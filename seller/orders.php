<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
startSession();
requireRole(['seller']);

$store_id   = $_SESSION['store_id'];
$page_title = 'Store Orders';

$status_filter = $_GET['status'] ?? '';
$where = ["o.store_id=?"];
$params = [$store_id]; $types = 'i';
if ($status_filter) { $where[] = "o.order_status=?"; $params[]=$status_filter; $types.='s'; }

// Handle status update
$msg = '';
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['order_id'])) {
    $oid    = (int)$_POST['order_id'];
    $status = $_POST['new_status'] ?? '';
    $allowed = ['confirmed','processing','shipped','delivered','cancelled'];
    if (in_array($status,$allowed)) {
        db_execute($conn,"UPDATE orders SET order_status=? WHERE order_id=? AND store_id=?","sii",[$status,$oid,$store_id]);
        // Notify customer
        $order = db_query_one($conn,"SELECT user_id FROM orders WHERE order_id=?","i",[$oid]);
        if ($order) {
            db_execute($conn,"INSERT INTO notifications(user_id,title,message,type,link) VALUES(?,?,?,?,?)",
                "issss",[$order['user_id'],"Order #{$oid} Updated","Your order status is now: ".ucfirst($status),"order","/orders.php"]);
        }
        $msg = "Order #$oid updated to ".ucfirst($status).".";
    }
}

$orders = db_query($conn,
    "SELECT o.*, u.username, u.email, COUNT(oi.order_item_id) AS item_count
     FROM orders o JOIN user_accounts u ON u.user_id=o.user_id
     LEFT JOIN order_items oi ON oi.order_id=o.order_id
     WHERE ".implode(' AND ',$where)."
     GROUP BY o.order_id ORDER BY o.created_at DESC",
    $types, $params);

require_once __DIR__ . '/../includes/header.php';
?>

<h1 class="page-title">📦 Store Orders</h1>
<p class="page-subtitle">Manage orders from your store</p>

<?php if ($msg): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($msg) ?></div><?php endif; ?>

<!-- Filter tabs -->
<div class="filter-tags" style="margin-bottom:1.5rem;">
  <?php foreach ([''  =>'All',
                  'pending'   =>'⏳ Pending',
                  'confirmed' =>'✅ Confirmed',
                  'shipped'   =>'🚚 Shipped',
                  'delivered' =>'📬 Delivered',
                  'cancelled' =>'❌ Cancelled'] as $val=>$lbl): ?>
  <a href="?status=<?= $val ?>" class="filter-tag <?= $status_filter===$val?'active':'' ?>"><?= $lbl ?></a>
  <?php endforeach; ?>
</div>

<?php if (!$orders): ?>
<div class="empty-state card"><span class="empty-icon">📦</span><h3>No Orders Found</h3></div>
<?php else: ?>
<div class="card p-0">
  <div class="table-wrapper">
    <table>
      <thead><tr><th>Order</th><th>Customer</th><th>Items</th><th>Amount</th><th>Payment</th><th>Status</th><th>Update</th></tr></thead>
      <tbody>
        <?php foreach ($orders as $o): ?>
        <tr>
          <td>
            <div style="font-weight:700;">#<?= $o['order_id'] ?></div>
            <div style="font-size:0.75rem;color:var(--text-muted);"><?= date('M d, Y',strtotime($o['created_at'])) ?></div>
          </td>
          <td>
            <div style="font-size:0.88rem;font-weight:600;"><?= htmlspecialchars($o['username']) ?></div>
            <div style="font-size:0.75rem;color:var(--text-muted);"><?= htmlspecialchars($o['email']) ?></div>
          </td>
          <td style="color:var(--text-secondary);"><?= $o['item_count'] ?> item<?= $o['item_count']!==1?'s':'' ?></td>
          <td style="color:var(--accent-gold);font-weight:700;">₱<?= number_format($o['total_amount'],2) ?></td>
          <td>
            <span class="status <?= $o['payment_status']==='paid'?'status-delivered':'status-pending' ?>">
              <?= ucfirst($o['payment_status']) ?>
            </span>
          </td>
          <td>
            <span class="status <?= match($o['order_status']){'pending'=>'status-pending','confirmed','processing'=>'status-confirmed','shipped'=>'status-shipped','delivered'=>'status-delivered',default=>'status-cancelled'} ?>">
              <?= ucfirst($o['order_status']) ?>
            </span>
          </td>
          <td>
            <?php if (!in_array($o['order_status'],['delivered','cancelled'])): ?>
            <form method="POST" style="display:flex;gap:0.4rem;">
              <input type="hidden" name="order_id" value="<?= $o['order_id'] ?>">
              <select name="new_status" class="form-control" style="padding:0.3rem 0.5rem;font-size:0.8rem;width:auto;">
                <?php foreach (['confirmed','processing','shipped','delivered','cancelled'] as $st): ?>
                <option value="<?= $st ?>" <?= $o['order_status']===$st?'selected':'' ?>><?= ucfirst($st) ?></option>
                <?php endforeach; ?>
              </select>
              <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-check"></i></button>
            </form>
            <?php else: ?>
            <span style="color:var(--text-muted);font-size:0.8rem;">—</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
