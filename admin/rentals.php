<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
startSession();
requireRole(['admin','super_admin']);

$page_title = 'Manage Rentals';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rid    = (int)($_POST['rental_id'] ?? 0);
    $status = $_POST['new_status'] ?? '';
    if ($rid && $status) {
        db_execute($conn,"UPDATE rentals SET rental_status=? WHERE rental_id=?","si",[$status,$rid]);
        $msg = "Rental #$rid updated to ".ucfirst($status).".";
    }
}

$status_f = $_GET['status'] ?? '';
$page_num = max(1,(int)($_GET['page']??1));
$per_page = 25; $offset = ($page_num-1)*$per_page;

$where=[]; $params=[]; $types='';
if ($status_f) { $where[]="r.rental_status=?"; $params[]=$status_f; $types.='s'; }
$w = $where ? implode(' AND ',$where) : '1=1';

$total = db_query_one($conn,"SELECT COUNT(*) AS c FROM rentals r WHERE $w",$types?:null,$params?:null)['c'];
$pages = ceil($total/$per_page);

$rentals = db_query($conn,
    "SELECT r.*, u.username, s.store_name,
            COUNT(ri.rental_item_id) AS item_count,
            MAX(ri.rental_end_date) AS due_date
     FROM rentals r
     JOIN user_accounts u ON u.user_id=r.user_id
     JOIN stores s ON s.store_id=r.store_id
     LEFT JOIN rental_items ri ON ri.rental_id=r.rental_id
     WHERE $w
     GROUP BY r.rental_id ORDER BY r.created_at DESC LIMIT ? OFFSET ?",
    ($types?:'').'ii', array_merge($params?:[], [$per_page,$offset]));

require_once __DIR__ . '/../includes/header.php';
?>

<h1 class="page-title">🎪 Manage Rentals</h1>
<p class="page-subtitle"><?= number_format($total) ?> rentals total</p>

<?php if ($msg): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($msg) ?></div><?php endif; ?>

<div class="filter-tags" style="margin-bottom:1.5rem;">
  <?php foreach([''  =>'All','pending'=>'⏳ Pending','confirmed'=>'✅ Confirmed','active'=>'🟢 Active','returned'=>'📦 Returned','overdue'=>'⚠️ Overdue','cancelled'=>'❌ Cancelled'] as $v=>$l): ?>
  <a href="?status=<?= $v ?>" class="filter-tag <?= $status_f===$v?'active':'' ?>"><?= $l ?></a>
  <?php endforeach; ?>
</div>

<div class="card p-0">
  <div class="table-wrapper">
    <table>
      <thead><tr><th>Rental</th><th>Customer</th><th>Store</th><th>Items</th><th>Due Date</th><th>Amount</th><th>Status</th><th>Update</th></tr></thead>
      <tbody>
        <?php foreach ($rentals as $r):
          $overdue = $r['rental_status']==='active' && strtotime($r['due_date']) < strtotime('today');
        ?>
        <tr style="<?= $overdue?'background:rgba(255,82,82,0.04);':'' ?>">
          <td>
            <div style="font-weight:700;">#<?= $r['rental_id'] ?><?= $overdue?' ⚠️':'' ?></div>
            <div style="font-size:0.72rem;color:var(--text-muted);"><?= date('M d, Y',strtotime($r['created_at'])) ?></div>
          </td>
          <td style="font-size:0.88rem;"><?= htmlspecialchars($r['username']) ?></td>
          <td style="font-size:0.85rem;"><?= htmlspecialchars($r['store_name']) ?></td>
          <td style="color:var(--text-secondary);"><?= $r['item_count'] ?></td>
          <td style="color:<?= $overdue?'#ff5252':'var(--text-secondary)' ?>;font-size:0.85rem;">
            <?= $r['due_date'] ? date('M d, Y',strtotime($r['due_date'])) : '—' ?>
          </td>
          <td>
            <div style="color:var(--accent-gold);font-weight:700;font-size:0.88rem;">₱<?= number_format($r['total_amount'],2) ?></div>
            <div style="color:var(--accent-pink);font-size:0.75rem;">+₱<?= number_format($r['deposit_amount'],2) ?> dep.</div>
          </td>
          <td>
            <span class="status <?= $overdue?'status-overdue':match($r['rental_status']){'pending'=>'status-pending','confirmed'=>'status-confirmed','active'=>'status-active','returned'=>'status-returned',default=>'status-cancelled'} ?>">
              <?= $overdue ? '⚠️ Overdue' : ucfirst($r['rental_status']) ?>
            </span>
          </td>
          <td>
            <?php if (!in_array($r['rental_status'],['returned','cancelled'])): ?>
            <form method="POST" style="display:flex;gap:0.3rem;">
              <input type="hidden" name="rental_id" value="<?= $r['rental_id'] ?>">
              <select name="new_status" class="form-control" style="padding:0.25rem 0.4rem;font-size:0.75rem;width:auto;">
                <?php foreach(['confirmed','active','returned','overdue','cancelled'] as $s): ?>
                <option value="<?= $s ?>" <?= $r['rental_status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
              </select>
              <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-check"></i></button>
            </form>
            <?php else: ?>—<?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($pages > 1): ?>
<div class="pagination">
  <?php $base='?'.http_build_query(array_merge($_GET,['page'=>'__P__'])); ?>
  <?php if ($page_num>1): ?><a href="<?= str_replace('__P__',$page_num-1,$base) ?>" class="page-btn">‹</a><?php endif; ?>
  <?php for($i=max(1,$page_num-2);$i<=min($pages,$page_num+2);$i++): ?>
  <a href="<?= str_replace('__P__',$i,$base) ?>" class="page-btn <?= $i===$page_num?'active':'' ?>"><?= $i ?></a>
  <?php endfor; ?>
  <?php if ($page_num<$pages): ?><a href="<?= str_replace('__P__',$page_num+1,$base) ?>" class="page-btn">›</a><?php endif; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
