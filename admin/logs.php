<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

startSession();
requireRole(['admin', 'super_admin']);

$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 30;
$offset   = ($page - 1) * $per_page;

$search   = trim($_GET['search'] ?? '');
$status_f = $_GET['status'] ?? '';
$date_f   = $_GET['date'] ?? '';

$where  = ['1=1'];
$params = [];
$types  = '';

if ($search !== '') {
    $where[]  = '(u.username LIKE ? OR u.email LIKE ? OR ll.ip_address LIKE ?)';
    $like     = "%$search%";
    $params[] = $like; $params[] = $like; $params[] = $like;
    $types   .= 'sss';
}
if ($status_f !== '') {
    $where[]  = 'll.status = ?';
    $params[] = $status_f;
    $types   .= 's';
}
if ($date_f !== '') {
    $where[]  = 'DATE(ll.login_time) = ?';
    $params[] = $date_f;
    $types   .= 's';
}

$where_sql = implode(' AND ', $where);

$count_sql = "SELECT COUNT(*) as total FROM login_logs ll LEFT JOIN user_accounts u ON ll.user_id = u.user_id WHERE $where_sql";
$count_result = $params ? db_query_one($conn, $count_sql, $types, $params) : db_query_one($conn, $count_sql);
$total       = (int)($count_result['total'] ?? 0);
$total_pages = max(1, ceil($total / $per_page));

$sql = "
    SELECT ll.*, u.username, u.email, u.role
    FROM login_logs ll
    LEFT JOIN user_accounts u ON ll.user_id = u.user_id
    WHERE $where_sql
    ORDER BY ll.login_time DESC
    LIMIT ? OFFSET ?
";
$fetch_params = array_merge($params, [$per_page, $offset]);
$fetch_types  = $types . 'ii';
$logs = db_query($conn, $sql, $fetch_types, $fetch_params);

// Quick stats
$stats = db_query_one($conn, "
    SELECT
        COUNT(*) AS total_logins,
        SUM(status = 'success') AS successes,
        SUM(status = 'failed') AS failures,
        COUNT(DISTINCT ip_address) AS unique_ips
    FROM login_logs
") ?: ['total_logins' => 0, 'successes' => 0, 'failures' => 0, 'unique_ips' => 0];

$user = getCurrentUser();
$page_title = 'Login Logs — Admin';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="main-content" id="mainContent">
    <div class="page-header">
        <div>
            <h1 class="page-title"><i class="fas fa-shield-alt"></i> Login Activity Logs</h1>
            <p class="page-subtitle">Monitor all authentication attempts across the platform.</p>
        </div>
        <div style="display:flex;gap:.5rem;">
            <button class="btn btn-secondary" onclick="exportLogs()"><i class="fas fa-download"></i> Export CSV</button>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid" style="margin-bottom:2rem;">
        <div class="stat-card">
            <div class="stat-icon" style="background:linear-gradient(135deg,var(--accent-purple),var(--neon-pink));">
                <i class="fas fa-sign-in-alt"></i>
            </div>
            <div class="stat-info"><h3><?= number_format($stats['total_logins'] ?? 0) ?></h3><p>Total Attempts</p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:linear-gradient(135deg,#28a745,#20c997);">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-info"><h3><?= number_format($stats['successes'] ?? 0) ?></h3><p>Successful Logins</p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:linear-gradient(135deg,#dc3545,#c0392b);">
                <i class="fas fa-times-circle"></i>
            </div>
            <div class="stat-info"><h3><?= number_format($stats['failures'] ?? 0) ?></h3><p>Failed Attempts</p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:linear-gradient(135deg,var(--neon-cyan),#0d6efd);">
                <i class="fas fa-network-wired"></i>
            </div>
            <div class="stat-info"><h3><?= number_format($stats['unique_ips'] ?? 0) ?></h3><p>Unique IPs</p></div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card" style="margin-bottom:1.5rem;">
        <div class="card-body">
            <form method="GET" style="display:flex;flex-wrap:wrap;gap:1rem;align-items:flex-end;">
                <div class="form-group" style="flex:1;min-width:200px;margin:0;">
                    <label class="form-label">Search</label>
                    <div style="position:relative;">
                        <i class="fas fa-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);"></i>
                        <input type="text" name="search" class="form-control" placeholder="Username, email or IP…"
                               value="<?= htmlspecialchars($search) ?>" style="padding-left:36px;">
                    </div>
                </div>
                <div class="form-group" style="min-width:150px;margin:0;">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control">
                        <option value="">All</option>
                        <option value="success" <?= $status_f === 'success' ? 'selected' : '' ?>>✅ Success</option>
                        <option value="failed"  <?= $status_f === 'failed'  ? 'selected' : '' ?>>❌ Failed</option>
                    </select>
                </div>
                <div class="form-group" style="min-width:160px;margin:0;">
                    <label class="form-label">Date</label>
                    <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($date_f) ?>">
                </div>
                <div style="display:flex;gap:.5rem;">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
                    <a href="logs.php" class="btn btn-secondary"><i class="fas fa-times"></i> Clear</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Table -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-list"></i> Log Entries (<?= number_format($total) ?>)</h3>
        </div>
        <div class="card-body" style="padding:0;overflow-x:auto;">
            <?php if (empty($logs)): ?>
            <div class="empty-state" style="padding:3rem;">
                <i class="fas fa-shield-alt"></i>
                <h3>No log entries found</h3>
                <p>No login activity matches your filters.</p>
            </div>
            <?php else: ?>
            <table class="data-table" id="logsTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>User</th>
                        <th>Role</th>
                        <th>IP Address</th>
                        <th>User Agent</th>
                        <th>Status</th>
                        <th>Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td><span style="color:var(--text-muted);font-size:.8rem;"><?= $log['log_id'] ?></span></td>
                    <td>
                        <?php if ($log['username']): ?>
                        <div style="font-weight:600;font-size:.85rem;"><?= htmlspecialchars($log['username']) ?></div>
                        <div style="color:var(--text-muted);font-size:.75rem;"><?= htmlspecialchars($log['email'] ?? '') ?></div>
                        <?php else: ?>
                        <span style="color:var(--text-muted);font-size:.85rem;"><i class="fas fa-user-slash"></i> User #<?= $log['user_id'] ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($log['role']): ?>
                        <span class="role-badge role-<?= $log['role'] ?>"><?= ucfirst(str_replace('_', ' ', $log['role'])) ?></span>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td><code style="font-size:.8rem;background:rgba(255,255,255,.05);padding:2px 6px;border-radius:4px;"><?= htmlspecialchars($log['ip_address'] ?? '—') ?></code></td>
                    <td>
                        <div style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:.75rem;color:var(--text-muted);"
                             title="<?= htmlspecialchars($log['user_agent'] ?? '') ?>">
                            <?= htmlspecialchars(substr($log['user_agent'] ?? '—', 0, 60)) ?>
                        </div>
                    </td>
                    <td>
                        <?php if ($log['status'] === 'success'): ?>
                        <span class="status-badge status-active">✅ Success</span>
                        <?php else: ?>
                        <span class="status-badge status-cancelled">❌ Failed</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:.8rem;white-space:nowrap;"><?= date('M j, Y g:i:s A', strtotime($log['login_time'])) ?></td>
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
            <?php endfor;
            echo '</div>';
            ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function exportLogs() {
    const rows = [['ID','Username','Email','Role','IP','Status','Timestamp']];
    document.querySelectorAll('#logsTable tbody tr').forEach(tr => {
        const cells = tr.querySelectorAll('td');
        rows.push([
            cells[0]?.innerText.trim(),
            cells[1]?.innerText.trim().split('\n')[0],
            cells[1]?.innerText.trim().split('\n')[1] || '',
            cells[2]?.innerText.trim(),
            cells[3]?.innerText.trim(),
            cells[5]?.innerText.trim(),
            cells[6]?.innerText.trim()
        ]);
    });
    const csv = rows.map(r => r.map(c => `"${(c||'').replace(/"/g,'""')}"`).join(',')).join('\n');
    const a = document.createElement('a');
    a.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv);
    a.download = 'login_logs_<?= date('Y-m-d') ?>.csv';
    a.click();
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
