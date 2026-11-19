<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
startSession();
requireRole(['admin','super_admin']);

$page_title = 'Manage Stores';
$msg = '';

// Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action   = $_POST['action']   ?? '';
    $store_id = (int)($_POST['store_id'] ?? 0);
    if ($action === 'toggle_verified' && $store_id) {
        $conn->query("UPDATE stores SET is_verified=1-is_verified WHERE store_id=$store_id");
        $msg = 'Store verification status updated.';
    } elseif ($action === 'toggle_active' && $store_id) {
        $conn->query("UPDATE stores SET is_active=1-is_active WHERE store_id=$store_id");
        $msg = 'Store status updated.';
    }
}

$search   = trim($_GET['q'] ?? '');
$verified = $_GET['verified'] ?? '';
$where    = ["1=1"]; $params=[]; $types='';
if ($search)   { $where[]="(s.store_name LIKE ? OR u.username LIKE ?)"; $like="%$search%"; $params=array_merge($params,[$like,$like]); $types.='ss'; }
if ($verified === '0') $where[] = "s.is_verified=0";
if ($verified === '1') $where[] = "s.is_verified=1";
$w = implode(' AND ', $where);

$stores = db_query($conn,
    "SELECT s.*, u.username, u.email,
            COUNT(DISTINCT p.product_id) AS product_count,
            COUNT(DISTINCT o.order_id)   AS order_count
     FROM stores s
     JOIN user_accounts u ON u.user_id=s.user_id
     LEFT JOIN products p ON p.store_id=s.store_id AND p.is_active=1
     LEFT JOIN orders o ON o.store_id=s.store_id
     WHERE $w
     GROUP BY s.store_id ORDER BY s.created_at DESC",
    $types?:null, $params?:null);

require_once __DIR__ . '/../includes/header.php';
?>

<h1 class="page-title">🏪 Manage Stores</h1>
<p class="page-subtitle"><?= count($stores) ?> stores found</p>

<?php if ($msg): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($msg) ?></div><?php endif; ?>

<form method="GET" style="display:flex;gap:0.75rem;flex-wrap:wrap;margin-bottom:1.5rem;align-items:flex-end;">
  <div style="flex:1;min-width:200px;">
    <input type="text" name="q" class="form-control" placeholder="Search store name or seller…" value="<?= htmlspecialchars($search) ?>">
  </div>
  <select name="verified" class="form-control" style="width:auto;">
    <option value="">All</option>
    <option value="1" <?= $verified==='1'?'selected':'' ?>>✓ Verified</option>
    <option value="0" <?= $verified==='0'?'selected':'' ?>>⏳ Unverified</option>
  </select>
  <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
  <a href="/admin/stores.php" class="btn btn-outline">Reset</a>
</form>

<div class="card p-0">
  <div class="table-wrapper">
    <table>
      <thead><tr><th>Store</th><th>Seller</th><th>Products</th><th>Orders</th><th>Rating</th><th>Verified</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($stores as $st): ?>
        <tr>
          <td>
            <div style="font-weight:700;font-size:0.9rem;"><?= htmlspecialchars($st['store_name']) ?></div>
            <a href="/store.php?slug=<?= urlencode($st['store_slug']) ?>" target="_blank" style="font-size:0.75rem;color:var(--accent-cyan);"><?= htmlspecialchars($st['store_slug']) ?></a>
          </td>
          <td>
            <div style="font-size:0.88rem;"><?= htmlspecialchars($st['username']) ?></div>
            <div style="font-size:0.75rem;color:var(--text-muted);"><?= htmlspecialchars($st['email']) ?></div>
          </td>
          <td style="color:var(--text-secondary);"><?= $st['product_count'] ?></td>
          <td style="color:var(--text-secondary);"><?= $st['order_count'] ?></td>
          <td style="color:var(--accent-gold);">⭐ <?= number_format($st['rating'],1) ?></td>
          <td>
            <span class="status <?= $st['is_verified']?'status-delivered':'status-pending' ?>">
              <?= $st['is_verified'] ? '✓ Verified' : 'Unverified' ?>
            </span>
          </td>
          <td>
            <span class="status <?= $st['is_active']?'status-active':'status-cancelled' ?>">
              <?= $st['is_active'] ? 'Active' : 'Inactive' ?>
            </span>
          </td>
          <td>
            <div style="display:flex;gap:0.4rem;flex-wrap:wrap;">
              <form method="POST" style="display:inline;">
                <input type="hidden" name="action"   value="toggle_verified">
                <input type="hidden" name="store_id" value="<?= $st['store_id'] ?>">
                <button type="submit" class="btn btn-sm <?= $st['is_verified']?'btn-outline':'btn-primary' ?>" style="font-size:0.75rem;">
                  <?= $st['is_verified'] ? '✗ Unverify' : '✓ Verify' ?>
                </button>
              </form>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="action"   value="toggle_active">
                <input type="hidden" name="store_id" value="<?= $st['store_id'] ?>">
                <button type="submit" class="btn btn-sm <?= $st['is_active']?'btn-danger':'btn-success' ?>" style="font-size:0.75rem;">
                  <?= $st['is_active'] ? '🚫 Disable' : '✅ Enable' ?>
                </button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
