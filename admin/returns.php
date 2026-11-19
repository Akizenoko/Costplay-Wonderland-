<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

startSession();
requireRole(['admin', 'super_admin']);

$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

$search      = trim($_GET['search'] ?? '');
$condition_f = $_GET['condition'] ?? '';
$date_from   = $_GET['date_from'] ?? '';
$date_to     = $_GET['date_to'] ?? '';

// Build WHERE
$where = ['1=1'];
$params = [];
$types  = '';

if ($search !== '') {
    $where[]  = '(u.username LIKE ? OR u.email LIKE ? OR p.product_name LIKE ?)';
    $like     = "%$search%";
    $params[] = $like; $params[] = $like; $params[] = $like;
    $types   .= 'sss';
}
if ($condition_f !== '') {
    $where[]  = 'rr.condition_status = ?';
    $params[] = $condition_f;
    $types   .= 's';
}
if ($date_from !== '') {
    $where[]  = 'rr.return_date >= ?';
    $params[] = $date_from . ' 00:00:00';
    $types   .= 's';
}
if ($date_to !== '') {
    $where[]  = 'rr.return_date <= ?';
    $params[] = $date_to . ' 23:59:59';
    $types   .= 's';
}

$where_sql = implode(' AND ', $where);

// Count
$count_sql = "
    SELECT COUNT(*) as total
    FROM return_records rr
    JOIN rental_items ri ON rr.rental_item_id = ri.rental_item_id
    JOIN rentals r       ON ri.rental_id = r.rental_id
    JOIN products p      ON ri.product_id = p.product_id
    JOIN user_accounts u ON r.user_id = u.user_id
    WHERE $where_sql
";
$count_result = $params ? db_query_one($conn, $count_sql, $types, $params) : db_query_one($conn, $count_sql);
$total = (int)($count_result['total'] ?? 0);
$total_pages = max(1, ceil($total / $per_page));

// Fetch
$sql = "
    SELECT rr.*,
           ri.days_count, ri.daily_rate, ri.deposit_per_item, ri.rental_start_date, ri.rental_end_date,
           p.product_name, p.main_image,
           r.rental_id,
           u.username, u.email,
           s.store_name
    FROM return_records rr
    JOIN rental_items ri ON rr.rental_item_id = ri.rental_item_id
    JOIN rentals r       ON ri.rental_id = r.rental_id
    JOIN products p      ON ri.product_id = p.product_id
    JOIN user_accounts u ON r.user_id = u.user_id
    JOIN stores s        ON r.store_id = s.store_id
    WHERE $where_sql
    ORDER BY rr.return_date DESC
    LIMIT ? OFFSET ?
";
$fetch_params = array_merge($params, [$per_page, $offset]);
$fetch_types  = $types . 'ii';
$returns = db_query($conn, $sql, $fetch_types, $fetch_params);

// Summary stats
$stats_sql = "
    SELECT
        COUNT(*) AS total_returns,
        SUM(CASE WHEN condition_status = 'perfect' THEN 1 ELSE 0 END) AS perfect_count,
        SUM(CASE WHEN condition_status = 'minor_damage' THEN 1 ELSE 0 END) AS minor_count,
        SUM(CASE WHEN condition_status = 'major_damage' THEN 1 ELSE 0 END) AS major_count,
        SUM(CASE WHEN condition_status = 'lost' THEN 1 ELSE 0 END) AS lost_count,
        COALESCE(SUM(damage_fee), 0) AS total_damage_fees,
        COALESCE(SUM(deposit_returned), 0) AS total_deposits_returned
    FROM return_records
";
$stats = db_query_one($conn, $stats_sql) ?: [
    'total_returns' => 0, 'perfect_count' => 0, 'minor_count' => 0,
    'major_count' => 0, 'lost_count' => 0, 'total_damage_fees' => 0, 'total_deposits_returned' => 0
];

$user = getCurrentUser();
$page_title = 'Return Records — Admin';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="main-content" id="mainContent">
    <div class="page-header">
        <div>
            <h1 class="page-title"><i class="fas fa-undo-alt"></i> Return Records</h1>
            <p class="page-subtitle">Inspect and track all rental returns across all stores.</p>
        </div>
        <a href="rentals.php" class="btn btn-secondary"><i class="fas fa-box-open"></i> View Rentals</a>
    </div>

    <!-- Summary Cards -->
    <div class="stats-grid" style="margin-bottom:2rem;">
        <div class="stat-card">
            <div class="stat-icon" style="background:linear-gradient(135deg,var(--neon-pink),var(--accent-purple));">
                <i class="fas fa-undo-alt"></i>
            </div>
            <div class="stat-info">
                <h3><?= number_format($stats['total_returns'] ?? 0) ?></h3>
                <p>Total Returns</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:linear-gradient(135deg,#28a745,#20c997);">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-info">
                <h3><?= number_format($stats['perfect_count'] ?? 0) ?></h3>
                <p>Perfect Condition</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:linear-gradient(135deg,#ffc107,#fd7e14);">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="stat-info">
                <h3><?= number_format(($stats['minor_count'] ?? 0) + ($stats['major_count'] ?? 0)) ?></h3>
                <p>Damaged Returns</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:linear-gradient(135deg,var(--neon-cyan),#0d6efd);">
                <i class="fas fa-coins"></i>
            </div>
            <div class="stat-info">
                <h3>₱<?= number_format($stats['total_damage_fees'] ?? 0, 2) ?></h3>
                <p>Total Damage Fees</p>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card" style="margin-bottom:1.5rem;">
        <div class="card-body">
            <form method="GET" class="filter-form" style="display:flex;flex-wrap:wrap;gap:1rem;align-items:flex-end;">
                <div class="form-group" style="flex:1;min-width:220px;margin:0;">
                    <label class="form-label">Search</label>
                    <div style="position:relative;">
                        <i class="fas fa-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);"></i>
                        <input type="text" name="search" class="form-control" placeholder="Username, email or product…"
                               value="<?= htmlspecialchars($search) ?>" style="padding-left:36px;">
                    </div>
                </div>
                <div class="form-group" style="min-width:160px;margin:0;">
                    <label class="form-label">Condition</label>
                    <select name="condition" class="form-control">
                        <option value="">All Conditions</option>
                        <?php foreach (['perfect'=>'Perfect','minor_damage'=>'Minor Damage','major_damage'=>'Major Damage','lost'=>'Lost'] as $v=>$l): ?>
                        <option value="<?= $v ?>" <?= $condition_f === $v ? 'selected' : '' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="min-width:140px;margin:0;">
                    <label class="form-label">From</label>
                    <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($date_from) ?>">
                </div>
                <div class="form-group" style="min-width:140px;margin:0;">
                    <label class="form-label">To</label>
                    <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($date_to) ?>">
                </div>
                <div style="display:flex;gap:.5rem;">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
                    <a href="returns.php" class="btn btn-secondary"><i class="fas fa-times"></i> Clear</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Table -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-list"></i> Returns (<?= number_format($total) ?>)</h3>
        </div>
        <div class="card-body" style="padding:0;overflow-x:auto;">
            <?php if (empty($returns)): ?>
            <div class="empty-state" style="padding:3rem;">
                <i class="fas fa-box-open"></i>
                <h3>No return records found</h3>
                <p>Try adjusting your filters.</p>
            </div>
            <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Product</th>
                        <th>Store</th>
                        <th>Customer</th>
                        <th>Rental Period</th>
                        <th>Returned At</th>
                        <th>Condition</th>
                        <th>Damage Fee</th>
                        <th>Deposit Returned</th>
                        <th>Inspection Notes</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($returns as $rr): ?>
                <?php
                    $cond = $rr['condition_status'];
                    $cond_class = match($cond) {
                        'perfect'      => 'status-active',
                        'minor_damage' => 'status-pending',
                        'major_damage' => 'status-cancelled',
                        'lost'         => 'status-cancelled',
                        default        => 'status-pending'
                    };
                    $cond_label = match($cond) {
                        'perfect'      => '✅ Perfect',
                        'minor_damage' => '⚠️ Minor Damage',
                        'major_damage' => '🔴 Major Damage',
                        'lost'         => '❌ Lost',
                        default        => ucwords(str_replace('_', ' ', (string)$cond))
                    };
                ?>
                <tr>
                    <td><span class="badge" style="background:rgba(255,20,147,.15);color:var(--neon-pink);">#<?= $rr['return_id'] ?></span></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:.75rem;">
                            <img src="<?= htmlspecialchars($rr['main_image'] ?: 'https://picsum.photos/40/40?random=' . $rr['return_id']) ?>"
                                 alt="" style="width:40px;height:40px;object-fit:cover;border-radius:8px;border:1px solid var(--border-color);">
                            <div>
                                <div style="font-weight:600;font-size:.85rem;"><?= htmlspecialchars($rr['product_name']) ?></div>
                                <div style="color:var(--text-muted);font-size:.75rem;">₱<?= number_format($rr['daily_rate'], 2) ?>/day</div>
                            </div>
                        </div>
                    </td>
                    <td><?= htmlspecialchars($rr['store_name']) ?></td>
                    <td>
                        <div style="font-weight:600;font-size:.85rem;"><?= htmlspecialchars($rr['username']) ?></div>
                        <div style="color:var(--text-muted);font-size:.75rem;"><?= htmlspecialchars($rr['email']) ?></div>
                    </td>
                    <td>
                        <div style="font-size:.8rem;"><?= date('M j', strtotime($rr['rental_start_date'])) ?> – <?= date('M j, Y', strtotime($rr['rental_end_date'])) ?></div>
                    </td>
                    <td>
                        <div style="font-size:.8rem;"><?= date('M j, Y g:i A', strtotime($rr['return_date'])) ?></div>
                    </td>
                    <td><span class="status-badge <?= $cond_class ?>"><?= $cond_label ?></span></td>
                    <td>
                        <?php if ($rr['damage_fee'] > 0): ?>
                        <span style="color:#ff6b6b;font-weight:700;">₱<?= number_format($rr['damage_fee'], 2) ?></span>
                        <?php else: ?>
                        <span style="color:var(--text-muted);">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span style="color:#51cf66;font-weight:700;">₱<?= number_format($rr['deposit_returned'], 2) ?></span>
                    </td>
                    <td style="max-width:180px;font-size:.8rem;color:var(--text-muted);">
                        <?= htmlspecialchars($rr['inspection_notes'] ?? '—') ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <?php if ($total_pages > 1): ?>
        <div class="card-footer" style="display:flex;justify-content:center;padding:1rem;">
            <?php
            $q = $_GET;
            echo '<div class="pagination">';
            for ($i = 1; $i <= $total_pages; $i++):
                $q['page'] = $i;
                $active = $i === $page ? 'active' : '';
            ?>
            <a href="?<?= http_build_query($q) ?>" class="page-btn <?= $active ?>"><?= $i ?></a>
            <?php endfor; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
