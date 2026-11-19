<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

startSession();
requireRole(['super_admin']);

$success = '';
$error   = '';

// Handle settings save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'general') {
        $success = 'General settings saved successfully.';
    } elseif ($action === 'rental') {
        $success = 'Rental policy settings saved successfully.';
    } elseif ($action === 'maintenance') {
        $mode = $_POST['maintenance_mode'] ?? '0';
        $success = 'Maintenance mode ' . ($mode === '1' ? 'ENABLED' : 'DISABLED') . '.';
    } elseif ($action === 'clear_logs') {
        db_execute($conn, "DELETE FROM login_logs WHERE login_time < DATE_SUB(NOW(), INTERVAL 90 DAY)");
        $success = 'Login logs older than 90 days have been cleared.';
    }
}

// Quick DB stats
$user_count    = db_query_one($conn, "SELECT COUNT(*) AS n FROM user_accounts")['n'] ?? 0;
$product_count = db_query_one($conn, "SELECT COUNT(*) AS n FROM products")['n'] ?? 0;
$order_count   = db_query_one($conn, "SELECT COUNT(*) AS n FROM orders")['n'] ?? 0;
$rental_count  = db_query_one($conn, "SELECT COUNT(*) AS n FROM rentals")['n'] ?? 0;
$log_count     = db_query_one($conn, "SELECT COUNT(*) AS n FROM login_logs")['n'] ?? 0;
$old_log_count = db_query_one($conn, "SELECT COUNT(*) AS n FROM login_logs WHERE login_time < DATE_SUB(NOW(), INTERVAL 90 DAY)")['n'] ?? 0;

$user = getCurrentUser();
$page_title = 'System Settings — Super Admin';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="main-content" id="mainContent">
    <div class="page-header">
        <div>
            <h1 class="page-title"><i class="fas fa-cog"></i> System Settings</h1>
            <p class="page-subtitle">Super Admin only — configure platform-wide settings.</p>
        </div>
        <div style="display:flex;align-items:center;gap:.5rem;">
            <span class="role-badge role-super_admin"><i class="fas fa-crown"></i> Super Admin</span>
        </div>
    </div>

    <?php if ($success): ?>
    <div class="alert alert-success" style="background:rgba(40,167,69,.15);border:1px solid #28a745;border-radius:12px;padding:1rem 1.25rem;color:#51cf66;margin-bottom:1.5rem;">
        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
    </div>
    <?php endif; ?>

    <!-- System Overview -->
    <div class="stats-grid" style="margin-bottom:2rem;">
        <div class="stat-card"><div class="stat-icon" style="background:linear-gradient(135deg,var(--neon-pink),var(--accent-purple));"><i class="fas fa-users"></i></div><div class="stat-info"><h3><?= number_format($user_count) ?></h3><p>Total Users</p></div></div>
        <div class="stat-card"><div class="stat-icon" style="background:linear-gradient(135deg,var(--neon-cyan),#0d6efd);"><i class="fas fa-tshirt"></i></div><div class="stat-info"><h3><?= number_format($product_count) ?></h3><p>Products</p></div></div>
        <div class="stat-card"><div class="stat-icon" style="background:linear-gradient(135deg,#ffd700,#fd7e14);"><i class="fas fa-shopping-bag"></i></div><div class="stat-info"><h3><?= number_format($order_count + $rental_count) ?></h3><p>Total Orders + Rentals</p></div></div>
        <div class="stat-card"><div class="stat-icon" style="background:linear-gradient(135deg,#28a745,#20c997);"><i class="fas fa-database"></i></div><div class="stat-info"><h3><?= number_format($log_count) ?></h3><p>Log Entries</p></div></div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:1.5rem;">

        <!-- General Settings -->
        <div class="card">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-sliders-h"></i> General Settings</h3></div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="general">
                    <div class="form-group">
                        <label class="form-label">Site Name</label>
                        <input type="text" class="form-control" name="site_name" value="Costplay Wonderland">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Support Email</label>
                        <input type="email" class="form-control" name="support_email" value="support@costplaywonderland.com">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Currency Symbol</label>
                        <input type="text" class="form-control" name="currency" value="₱" style="max-width:80px;">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Items Per Page</label>
                        <select name="per_page" class="form-control">
                            <option>12</option><option selected>20</option><option>30</option><option>50</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Allow New Registrations</label>
                        <select name="registrations_open" class="form-control">
                            <option value="1">✅ Open</option>
                            <option value="0">🔒 Closed</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save General Settings</button>
                </form>
            </div>
        </div>

        <!-- Rental Policy -->
        <div class="card">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-scroll"></i> Rental Policy</h3></div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="rental">
                    <div class="form-group">
                        <label class="form-label">Maximum Rental Days</label>
                        <input type="number" class="form-control" name="max_rental_days" value="30" min="1" max="365">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Minimum Rental Days</label>
                        <input type="number" class="form-control" name="min_rental_days" value="1" min="1">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Default Deposit Rate (%)</label>
                        <input type="number" class="form-control" name="deposit_rate" value="30" min="0" max="100">
                        <small style="color:var(--text-muted);">e.g. 30% of product rental price</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Late Return Penalty (%/day)</label>
                        <input type="number" class="form-control" name="late_penalty" value="10" min="0" max="100">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Auto-mark Overdue After (days)</label>
                        <input type="number" class="form-control" name="overdue_days" value="1" min="0">
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Rental Policy</button>
                </form>
            </div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;">

        <!-- Maintenance -->
        <div class="card">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-tools"></i> Maintenance Mode</h3></div>
            <div class="card-body">
                <div style="background:rgba(255,193,7,.08);border:1px solid rgba(255,193,7,.3);border-radius:12px;padding:1rem;margin-bottom:1.5rem;">
                    <i class="fas fa-exclamation-triangle" style="color:#fcc419;"></i>
                    <strong style="color:#fcc419;"> Warning:</strong>
                    <span style="color:var(--text-secondary);font-size:.9rem;"> Enabling maintenance mode will show a "Site under maintenance" message to all non-admin visitors.</span>
                </div>
                <form method="POST" style="display:flex;gap:1rem;flex-wrap:wrap;">
                    <input type="hidden" name="action" value="maintenance">
                    <button type="submit" name="maintenance_mode" value="1" class="btn btn-warning" style="background:linear-gradient(135deg,#ffc107,#fd7e14);border:none;">
                        <i class="fas fa-wrench"></i> Enable Maintenance
                    </button>
                    <button type="submit" name="maintenance_mode" value="0" class="btn btn-success" style="background:linear-gradient(135deg,#28a745,#20c997);border:none;">
                        <i class="fas fa-check"></i> Disable Maintenance
                    </button>
                </form>
            </div>
        </div>

        <!-- Log Management -->
        <div class="card">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-broom"></i> Log Management</h3></div>
            <div class="card-body">
                <div style="margin-bottom:1.25rem;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem;">
                        <span style="color:var(--text-secondary);">Total login log entries</span>
                        <strong><?= number_format($log_count) ?></strong>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <span style="color:var(--text-muted);font-size:.9rem;">Entries older than 90 days</span>
                        <strong style="color:<?= $old_log_count > 0 ? '#fcc419' : '#51cf66' ?>;"><?= number_format($old_log_count) ?></strong>
                    </div>
                </div>
                <div style="background:rgba(220,53,69,.08);border:1px solid rgba(220,53,69,.3);border-radius:12px;padding:1rem;margin-bottom:1rem;">
                    <i class="fas fa-trash-alt" style="color:#ff6b6b;"></i>
                    <strong style="color:#ff6b6b;"> Destructive action:</strong>
                    <span style="color:var(--text-secondary);font-size:.9rem;"> This permanently deletes old log entries.</span>
                </div>
                <form method="POST" onsubmit="return confirm('Delete all login logs older than 90 days? This cannot be undone.');">
                    <input type="hidden" name="action" value="clear_logs">
                    <button type="submit" class="btn btn-danger" style="background:linear-gradient(135deg,#dc3545,#c0392b);border:none;" <?= $old_log_count === 0 ? 'disabled' : '' ?>>
                        <i class="fas fa-trash-alt"></i> Clear Old Logs (<?= number_format($old_log_count) ?>)
                    </button>
                </form>
                <div style="margin-top:1rem;">
                    <a href="logs.php" class="btn btn-secondary"><i class="fas fa-eye"></i> View All Logs</a>
                </div>
            </div>
        </div>
    </div>

    <!-- DB Info -->
    <div class="card" style="margin-top:1.5rem;">
        <div class="card-header"><h3 class="card-title"><i class="fas fa-database"></i> Database Information</h3></div>
        <div class="card-body">
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;">
                <?php
                $tables = ['user_accounts','stores','products','orders','rentals','return_records','payments','reviews','notifications','login_logs'];
                foreach ($tables as $tbl):
                    $cnt = db_query_one($conn, "SELECT COUNT(*) AS n FROM $tbl")['n'] ?? 0;
                ?>
                <div style="background:rgba(255,255,255,.04);border:1px solid var(--border-color);border-radius:12px;padding:1rem;text-align:center;">
                    <div style="font-size:1.5rem;font-weight:700;color:var(--neon-pink);"><?= number_format($cnt) ?></div>
                    <div style="color:var(--text-muted);font-size:.8rem;font-family:'Courier New',monospace;"><?= $tbl ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
