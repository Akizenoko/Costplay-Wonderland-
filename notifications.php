<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

startSession();
requireLogin();

$user = getCurrentUser();
$user_id = $user['user_id'];

// Mark a single notification as read via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    $nid = (int)$_POST['mark_read'];
    db_execute($conn, "UPDATE notifications SET is_read = 1 WHERE notif_id = ? AND user_id = ?", 'ii', [$nid, $user_id]);
    echo json_encode(['success' => true]);
    exit;
}

// Mark all as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read'])) {
    db_execute($conn, "UPDATE notifications SET is_read = 1 WHERE user_id = ?", 'i', [$user_id]);
    header('Location: notifications.php?marked=1');
    exit;
}

$filter = $_GET['filter'] ?? 'all'; // all | unread
$page   = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset   = ($page - 1) * $per_page;

$where = ['user_id = ?'];
$params = [$user_id];
$types  = 'i';
if ($filter === 'unread') {
    $where[]  = 'is_read = 0';
}
$where_sql = implode(' AND ', $where);

$total_res = db_query_one($conn, "SELECT COUNT(*) AS n FROM notifications WHERE $where_sql", $types, $params);
$total = (int)($total_res['n'] ?? 0);
$total_pages = max(1, ceil($total / $per_page));

$fetch_params = array_merge($params, [$per_page, $offset]);
$fetch_types  = $types . 'ii';
$notifications = db_query(
    $conn,
    "SELECT * FROM notifications WHERE $where_sql ORDER BY created_at DESC LIMIT ? OFFSET ?",
    $fetch_types,
    $fetch_params
);

$unread_res = db_query_one($conn, "SELECT COUNT(*) AS n FROM notifications WHERE user_id = ? AND is_read = 0", 'i', [$user_id]);
$unread_count = (int)($unread_res['n'] ?? 0);

$page_title = 'Notifications — Costplay Wonderland';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';

// Icon map for notification types
function notif_icon(string $type): string {
    return match($type) {
        'order_placed'    => '<i class="fas fa-shopping-bag" style="color:#51cf66;"></i>',
        'order_shipped'   => '<i class="fas fa-truck" style="color:var(--neon-cyan);"></i>',
        'order_delivered' => '<i class="fas fa-check-circle" style="color:#51cf66;"></i>',
        'order_cancelled' => '<i class="fas fa-times-circle" style="color:#ff6b6b;"></i>',
        'rental_started'  => '<i class="fas fa-calendar-check" style="color:var(--neon-pink);"></i>',
        'rental_returned' => '<i class="fas fa-undo-alt" style="color:#fcc419;"></i>',
        'rental_overdue'  => '<i class="fas fa-exclamation-triangle" style="color:#ff6b6b;"></i>',
        'review_received' => '<i class="fas fa-star" style="color:#fcc419;"></i>',
        'store_verified'  => '<i class="fas fa-shield-alt" style="color:var(--neon-cyan);"></i>',
        'payment_received'=> '<i class="fas fa-coins" style="color:#ffd700;"></i>',
        default           => '<i class="fas fa-bell" style="color:var(--accent-purple);"></i>',
    };
}
?>
<div class="main-content" id="mainContent">
    <div class="page-header" style="flex-wrap:wrap;gap:1rem;">
        <div>
            <h1 class="page-title"><i class="fas fa-bell"></i> Notifications
                <?php if ($unread_count > 0): ?>
                <span class="badge" style="background:linear-gradient(135deg,var(--neon-pink),var(--accent-purple));color:#fff;font-size:.6em;padding:4px 10px;border-radius:50px;vertical-align:middle;margin-left:.5rem;"><?= $unread_count ?> new</span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">Stay up to date with your orders, rentals, and store activity.</p>
        </div>
        <?php if ($unread_count > 0): ?>
        <form method="POST">
            <button type="submit" name="mark_all_read" value="1" class="btn btn-secondary">
                <i class="fas fa-check-double"></i> Mark All as Read
            </button>
        </form>
        <?php endif; ?>
    </div>

    <?php if (isset($_GET['marked'])): ?>
    <div class="alert alert-success" style="background:rgba(40,167,69,.15);border:1px solid #28a745;border-radius:12px;padding:1rem 1.25rem;color:#51cf66;margin-bottom:1rem;">
        <i class="fas fa-check-circle"></i> All notifications marked as read.
    </div>
    <?php endif; ?>

    <!-- Filter Tabs -->
    <div style="display:flex;gap:.5rem;margin-bottom:1.5rem;border-bottom:1px solid var(--border-color);padding-bottom:0;">
        <?php foreach (['all' => 'All Notifications', 'unread' => 'Unread Only'] as $key => $label): ?>
        <a href="?filter=<?= $key ?>"
           style="padding:.6rem 1.25rem;border-radius:10px 10px 0 0;font-size:.9rem;font-weight:600;text-decoration:none;transition:.2s;
                  <?= $filter === $key
                      ? 'background:linear-gradient(135deg,var(--neon-pink),var(--accent-purple));color:#fff;'
                      : 'color:var(--text-muted);background:rgba(255,255,255,.04);' ?>">
            <?= $label ?>
            <?php if ($key === 'unread' && $unread_count > 0): ?>
            <span style="background:#ff6b6b;color:#fff;border-radius:50px;padding:1px 7px;font-size:.75em;margin-left:.25rem;"><?= $unread_count ?></span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Notification List -->
    <?php if (empty($notifications)): ?>
    <div class="card">
        <div class="empty-state" style="padding:4rem;">
            <i class="fas fa-bell-slash"></i>
            <h3><?= $filter === 'unread' ? 'No unread notifications' : 'No notifications yet' ?></h3>
            <p><?= $filter === 'unread' ? 'You\'re all caught up! 🎉' : 'Your notifications will appear here as you use Costplay Wonderland.' ?></p>
            <?php if ($filter === 'unread'): ?>
            <a href="?filter=all" class="btn btn-primary" style="margin-top:1rem;"><i class="fas fa-list"></i> View All</a>
            <?php endif; ?>
        </div>
    </div>
    <?php else: ?>
    <div class="notifications-list" style="display:flex;flex-direction:column;gap:.75rem;" id="notifList">
        <?php foreach ($notifications as $n): ?>
        <div class="notif-item" id="notif-<?= $n['notif_id'] ?>"
             style="display:flex;align-items:flex-start;gap:1rem;padding:1.25rem 1.5rem;
                    background:<?= $n['is_read'] ? 'rgba(255,255,255,.03)' : 'rgba(255,20,147,.06)' ?>;
                    border:1px solid <?= $n['is_read'] ? 'var(--border-color)' : 'rgba(255,20,147,.25)' ?>;
                    border-radius:14px;transition:.3s;">
            <!-- Icon -->
            <div style="width:44px;height:44px;min-width:44px;border-radius:50%;background:rgba(255,255,255,.07);
                        display:flex;align-items:center;justify-content:center;font-size:1.1rem;">
                <?= notif_icon($n['type'] ?? '') ?>
            </div>
            <!-- Content -->
            <div style="flex:1;">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:.5rem;">
                    <div>
                        <div style="font-weight:700;font-size:0.95rem;color:#fff;margin-bottom:0.25rem;">
                            <?= htmlspecialchars($n['title']) ?>
                        </div>
                        <p style="margin:0;font-size:.92rem;color:var(--text-secondary);line-height:1.5;
                                  font-weight:<?= $n['is_read'] ? '400' : '500' ?>;">
                            <?= htmlspecialchars($n['message']) ?>
                        </p>
                    </div>
                    <?php if (!$n['is_read']): ?>
                    <button onclick="markRead(<?= $n['notif_id'] ?>)" class="btn btn-sm btn-secondary"
                            style="white-space:nowrap;font-size:.75rem;padding:.3rem .75rem;flex-shrink:0;">
                        <i class="fas fa-check"></i> Mark Read
                    </button>
                    <?php endif; ?>
                </div>
                <div style="display:flex;align-items:center;gap:1rem;margin-top:.4rem;">
                    <span style="font-size:.78rem;color:var(--text-muted);">
                        <i class="fas fa-clock"></i>
                        <?= date('M j, Y g:i A', strtotime($n['created_at'])) ?>
                        (<?= timeAgo($n['created_at']) ?>)
                    </span>
                    <?php if (!$n['is_read']): ?>
                    <span style="display:inline-block;width:8px;height:8px;background:var(--neon-pink);border-radius:50%;box-shadow:0 0 6px var(--neon-pink);"></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div style="display:flex;justify-content:center;margin-top:2rem;">
        <div class="pagination">
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
        <a href="?filter=<?= $filter ?>&page=<?= $i ?>" class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<script>
function markRead(id) {
    const item = document.getElementById('notif-' + id);
    const btn  = item?.querySelector('button');
    if (btn) btn.disabled = true;

    fetch('notifications.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'mark_read=' + id
    })
    .then(r => r.json())
    .then(data => {
        if (data.success && item) {
            item.style.background = 'rgba(255,255,255,.03)';
            item.style.borderColor = 'var(--border-color)';
            if (btn) btn.remove();
            const dot = item.querySelector('[style*="border-radius:50%"][style*="neon-pink"]');
            if (dot) dot.remove();
            // Update badge in header
            const badge = document.querySelector('.notif-badge');
            if (badge) {
                const count = parseInt(badge.textContent) - 1;
                if (count <= 0) badge.remove();
                else badge.textContent = count;
            }
        }
    });
}
</script>

<?php
function timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)      return 'just now';
    if ($diff < 3600)    return floor($diff/60) . 'm ago';
    if ($diff < 86400)   return floor($diff/3600) . 'h ago';
    if ($diff < 604800)  return floor($diff/86400) . 'd ago';
    return floor($diff/604800) . 'w ago';
}
include __DIR__ . '/includes/footer.php';
?>
