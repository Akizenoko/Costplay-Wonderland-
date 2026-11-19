<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
startSession();
requireRole(['admin','super_admin']);

$page_title = 'Manage Users';
$msg = $err = '';

// Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action']  ?? '';
    $user_id = (int)($_POST['user_id'] ?? 0);

    if ($action === 'toggle_active' && $user_id) {
        $conn->query("UPDATE user_accounts SET is_active=1-is_active WHERE user_id=$user_id AND role NOT IN ('super_admin')");
        $msg = 'User status updated.';
    } elseif ($action === 'change_role' && $user_id) {
        $role    = $_POST['new_role'] ?? '';
        $allowed = ['customer','seller','admin'];
        if ($_SESSION['role'] === 'super_admin') $allowed[] = 'super_admin';
        if (in_array($role, $allowed)) {
            db_execute($conn,"UPDATE user_accounts SET role=? WHERE user_id=?","si",[$role,$user_id]);
            $msg = 'User role updated.';
        }
    } elseif ($action === 'verify_store') {
        $store_id = (int)($_POST['store_id'] ?? 0);
        if ($store_id) {
            db_execute($conn,"UPDATE stores SET is_verified=1 WHERE store_id=?","i",[$store_id]);
            $msg = 'Store verified successfully.';
        }
    }
}

// Filters
$role_filter   = $_GET['role'] ?? '';
$search        = trim($_GET['q'] ?? '');
$status_filter = $_GET['status'] ?? '';
$page_num      = max(1,(int)($_GET['page']??1));
$per_page      = 20;
$offset        = ($page_num-1)*$per_page;

$where  = ["1=1"]; $params = []; $types = '';
if ($role_filter) { $where[] = "u.role=?"; $params[]=$role_filter; $types.='s'; }
if ($search) {
    $where[] = "(u.username LIKE ? OR u.email LIKE ?)";
    $like    = "%$search%"; $params=array_merge($params,[$like,$like]); $types.='ss';
}
if ($status_filter === 'active')   $where[] = "u.is_active=1";
if ($status_filter === 'inactive') $where[] = "u.is_active=0";

$where_sql = implode(' AND ', $where);

$total = db_query_one($conn,"SELECT COUNT(*) AS c FROM user_accounts u WHERE $where_sql",$types?:null,$params?:null)['c'];
$pages = ceil($total/$per_page);

$users = db_query($conn,
    "SELECT u.*, s.store_name, s.store_id, s.is_verified AS store_verified
     FROM user_accounts u
     LEFT JOIN stores s ON s.user_id=u.user_id
     WHERE $where_sql
     ORDER BY u.created_at DESC LIMIT ? OFFSET ?",
    ($types?:'').'ii', array_merge($params?:[], [$per_page,$offset]));

require_once __DIR__ . '/../includes/header.php';
?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:2rem;flex-wrap:wrap;gap:1rem;">
  <div>
    <h1 class="page-title">👥 Manage Users</h1>
    <p class="page-subtitle"><?= number_format($total) ?> total users</p>
  </div>
</div>

<?php if ($msg): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($msg) ?></div><?php endif; ?>

<!-- Filters -->
<form method="GET" style="display:flex;gap:0.75rem;flex-wrap:wrap;margin-bottom:1.5rem;align-items:flex-end;">
  <div style="flex:1;min-width:200px;">
    <input type="text" name="q" class="form-control" placeholder="Search username or email…" value="<?= htmlspecialchars($search) ?>">
  </div>
  <select name="role" class="form-control" style="width:auto;">
    <option value="">All Roles</option>
    <?php foreach(['customer','seller','admin','super_admin'] as $r): ?>
    <option value="<?= $r ?>" <?= $role_filter===$r?'selected':'' ?>><?= ucwords(str_replace('_',' ',$r)) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="status" class="form-control" style="width:auto;">
    <option value="">All Status</option>
    <option value="active"   <?= $status_filter==='active'  ?'selected':'' ?>>Active</option>
    <option value="inactive" <?= $status_filter==='inactive'?'selected':'' ?>>Inactive</option>
  </select>
  <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
  <a href="/admin/users.php" class="btn btn-outline">Reset</a>
</form>

<div class="card p-0">
  <div class="table-wrapper">
    <table>
      <thead>
        <tr><th>User</th><th>Role</th><th>Store</th><th>Status</th><th>Joined</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u): ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:0.6rem;">
              <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#e040fb,#7c4dff);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:0.85rem;color:#fff;flex-shrink:0;">
                <?= strtoupper(substr($u['username'],0,1)) ?>
              </div>
              <div>
                <div style="font-weight:700;font-size:0.9rem;"><?= htmlspecialchars($u['username']) ?></div>
                <div style="font-size:0.75rem;color:var(--text-muted);"><?= htmlspecialchars($u['email']) ?></div>
              </div>
            </div>
          </td>
          <td>
            <span class="chip <?= match($u['role']){'super_admin','admin'=>'chip-sale','seller'=>'chip-both',default=>'chip-rent'} ?>" style="font-size:0.72rem;">
              <?= ucwords(str_replace('_',' ',$u['role'])) ?>
            </span>
          </td>
          <td>
            <?php if ($u['store_name']): ?>
            <a href="/store.php?slug=<?= urlencode($u['store_name']) ?>" style="font-size:0.85rem;color:var(--accent-cyan);"><?= htmlspecialchars($u['store_name']) ?></a>
            <?php if (!$u['store_verified']): ?>
            <form method="POST" style="display:inline;margin-left:0.4rem;">
              <input type="hidden" name="action"   value="verify_store">
              <input type="hidden" name="store_id" value="<?= $u['store_id'] ?>">
              <button type="submit" class="btn btn-sm" style="font-size:0.68rem;padding:1px 6px;background:rgba(0,229,255,0.15);border:1px solid rgba(0,229,255,0.4);color:#00e5ff;border-radius:50px;">✓ Verify</button>
            </form>
            <?php else: ?>
            <span style="font-size:0.72rem;color:#00e676;margin-left:0.3rem;">✓</span>
            <?php endif; ?>
            <?php else: ?>
            <span style="color:var(--text-muted);font-size:0.82rem;">—</span>
            <?php endif; ?>
          </td>
          <td>
            <span class="status <?= $u['is_active'] ? 'status-active' : 'status-cancelled' ?>">
              <?= $u['is_active'] ? 'Active' : 'Suspended' ?>
            </span>
          </td>
          <td style="color:var(--text-muted);font-size:0.82rem;"><?= date('M d, Y',strtotime($u['created_at'])) ?></td>
          <td>
            <div style="display:flex;gap:0.4rem;flex-wrap:wrap;">
              <?php if ($u['role'] !== 'super_admin' || $_SESSION['role']==='super_admin'): ?>
              <!-- Change role -->
              <form method="POST" style="display:flex;gap:0.3rem;">
                <input type="hidden" name="action"  value="change_role">
                <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                <select name="new_role" class="form-control" style="padding:0.2rem 0.4rem;font-size:0.75rem;width:auto;">
                  <?php foreach(array_filter(['customer','seller','admin', $_SESSION['role']==='super_admin'?'super_admin':null]) as $r): ?>
                  <option value="<?= $r ?>" <?= $u['role']===$r?'selected':'' ?>><?= ucwords(str_replace('_',' ',$r)) ?></option>
                  <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-secondary btn-sm" title="Change role"><i class="fas fa-user-tag"></i></button>
              </form>
              <!-- Toggle active -->
              <form method="POST" style="display:inline;">
                <input type="hidden" name="action"  value="toggle_active">
                <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                <button type="submit" class="btn btn-sm <?= $u['is_active']?'btn-danger':'btn-success' ?>" style="font-size:0.75rem;">
                  <?= $u['is_active'] ? '🚫 Suspend' : '✅ Activate' ?>
                </button>
              </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Pagination -->
<?php if ($pages > 1): ?>
<div class="pagination">
  <?php $base = '?'.http_build_query(array_merge($_GET,['page'=>'__P__'])); ?>
  <?php if ($page_num>1): ?><a href="<?= str_replace('__P__',$page_num-1,$base) ?>" class="page-btn">‹</a><?php endif; ?>
  <?php for($i=max(1,$page_num-2);$i<=min($pages,$page_num+2);$i++): ?>
  <a href="<?= str_replace('__P__',$i,$base) ?>" class="page-btn <?= $i===$page_num?'active':'' ?>"><?= $i ?></a>
  <?php endfor; ?>
  <?php if ($page_num<$pages): ?><a href="<?= str_replace('__P__',$page_num+1,$base) ?>" class="page-btn">›</a><?php endif; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
