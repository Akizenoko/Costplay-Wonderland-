<?php
// ─────────────────────────────────────────────────────────────
// Sidebar Component — Costplay Wonderland
// Role-based navigation items
// ─────────────────────────────────────────────────────────────
if (!function_exists('startSession')) {
    require_once __DIR__ . '/auth.php';
}
startSession();

$user     = getCurrentUser();
$role     = $user['role'] ?? 'guest';
$cur_path = $_SERVER['PHP_SELF'];

function nav_active(string $file): string {
    global $cur_path;
    return str_ends_with($cur_path, $file) ? 'active' : '';
}

function nav_active_dir(string $dir): string {
    global $cur_path;
    return str_contains($cur_path, $dir) ? 'active' : '';
}
?>
<div id="sidebar-overlay" class="sidebar-overlay"></div>

<aside id="sidebar" class="sidebar">
  <!-- Logo -->
  <div class="sidebar-logo">
    <div class="sidebar-logo-icon">🎭</div>
    <div class="sidebar-logo-text">
      <span class="logo-title">Costplay</span>
      <span class="logo-sub">Wonderland ✨</span>
    </div>
    <button class="burger-btn" id="burger-btn" title="Toggle Sidebar">☰</button>
  </div>

  <!-- Navigation -->
  <nav class="sidebar-nav">

    <!-- Main -->
    <div class="nav-section-label">Main</div>

    <a href="/index.php" class="nav-item <?= nav_active('index.php') ?>">
      <span class="nav-icon">🏠</span>
      <span class="nav-label">Home</span>
    </a>

    <a href="/shop.php" class="nav-item <?= nav_active('shop.php') ?>">
      <span class="nav-icon">🛍️</span>
      <span class="nav-label">Shop</span>
    </a>

    <a href="/stores.php" class="nav-item <?= nav_active('stores.php') ?>">
      <span class="nav-icon">🏪</span>
      <span class="nav-label">Stores</span>
    </a>

    <?php if ($role === 'guest'): ?>
    <!-- Guest -->
    <div class="nav-section-label">Account</div>

    <a href="/login.php" class="nav-item <?= nav_active('login.php') ?>">
      <span class="nav-icon">🔐</span>
      <span class="nav-label">Login</span>
    </a>
    <a href="/register.php" class="nav-item <?= nav_active('register.php') ?>">
      <span class="nav-icon">📝</span>
      <span class="nav-label">Register</span>
    </a>

    <?php elseif ($role === 'customer'): ?>
    <!-- Customer -->
    <div class="nav-section-label">Shopping</div>

    <a href="/cart.php" class="nav-item <?= nav_active('cart.php') ?>">
      <span class="nav-icon">🛒</span>
      <span class="nav-label">My Cart</span>
      <?php
        global $conn;
        if (isset($conn)) {
            $cnt = getCartCount($conn);
            if ($cnt > 0) echo "<span class='nav-badge'>{$cnt}</span>";
        }
      ?>
    </a>

    <a href="/orders.php" class="nav-item <?= nav_active('orders.php') ?>">
      <span class="nav-icon">📦</span>
      <span class="nav-label">My Orders</span>
    </a>

    <a href="/rentals.php" class="nav-item <?= nav_active('rentals.php') ?>">
      <span class="nav-icon">🎪</span>
      <span class="nav-label">My Rentals</span>
    </a>

    <a href="/wishlist.php" class="nav-item <?= nav_active('wishlist.php') ?>">
      <span class="nav-icon">❤️</span>
      <span class="nav-label">Wishlist</span>
    </a>

    <div class="nav-section-label">Account</div>
    <a href="/profile.php" class="nav-item <?= nav_active('profile.php') ?>">
      <span class="nav-icon">👤</span>
      <span class="nav-label">Profile</span>
    </a>

    <?php elseif ($role === 'seller'): ?>
    <!-- Seller -->
    <div class="nav-section-label">My Store</div>

    <a href="/seller/dashboard.php" class="nav-item <?= nav_active('dashboard.php') ?>">
      <span class="nav-icon">📊</span>
      <span class="nav-label">Dashboard</span>
    </a>
    <a href="/seller/products.php" class="nav-item <?= nav_active_dir('/seller/products') ?>">
      <span class="nav-icon">🎭</span>
      <span class="nav-label">My Products</span>
    </a>
    <a href="/seller/orders.php" class="nav-item <?= nav_active_dir('/seller/orders') ?>">
      <span class="nav-icon">📦</span>
      <span class="nav-label">Store Orders</span>
    </a>
    <a href="/seller/rentals.php" class="nav-item <?= nav_active_dir('/seller/rentals') ?>">
      <span class="nav-icon">🎪</span>
      <span class="nav-label">Store Rentals</span>
    </a>
    <a href="/seller/store-settings.php" class="nav-item <?= nav_active('store-settings.php') ?>">
      <span class="nav-icon">⚙️</span>
      <span class="nav-label">Store Settings</span>
    </a>

    <div class="nav-section-label">Browsing</div>
    <a href="/cart.php" class="nav-item <?= nav_active('cart.php') ?>">
      <span class="nav-icon">🛒</span>
      <span class="nav-label">My Cart</span>
    </a>
    <a href="/orders.php" class="nav-item">
      <span class="nav-icon">🧾</span>
      <span class="nav-label">My Purchases</span>
    </a>

    <div class="nav-section-label">Account</div>
    <a href="/profile.php" class="nav-item <?= nav_active('profile.php') ?>">
      <span class="nav-icon">👤</span>
      <span class="nav-label">Profile</span>
    </a>

    <?php elseif ($role === 'admin' || $role === 'super_admin'): ?>
    <!-- Admin / Super Admin -->
    <div class="nav-section-label">Administration</div>

    <a href="/admin/dashboard.php" class="nav-item <?= nav_active('dashboard.php') ?>">
      <span class="nav-icon">📊</span>
      <span class="nav-label">Dashboard</span>
    </a>
    <a href="/admin/users.php" class="nav-item <?= nav_active('users.php') ?>">
      <span class="nav-icon">👥</span>
      <span class="nav-label">Users</span>
    </a>
    <a href="/admin/stores.php" class="nav-item <?= nav_active_dir('/admin/stores') ?>">
      <span class="nav-icon">🏪</span>
      <span class="nav-label">Stores</span>
    </a>
    <a href="/admin/products.php" class="nav-item <?= nav_active_dir('/admin/products') ?>">
      <span class="nav-icon">🎭</span>
      <span class="nav-label">Products</span>
    </a>
    <a href="/admin/orders.php" class="nav-item <?= nav_active_dir('/admin/orders') ?>">
      <span class="nav-icon">📦</span>
      <span class="nav-label">Orders</span>
    </a>
    <a href="/admin/rentals.php" class="nav-item <?= nav_active_dir('/admin/rentals') ?>">
      <span class="nav-icon">🎪</span>
      <span class="nav-label">Rentals</span>
    </a>
    <a href="/admin/returns.php" class="nav-item <?= nav_active_dir('/admin/returns') ?>">
      <span class="nav-icon">🔄</span>
      <span class="nav-label">Return Records</span>
    </a>

    <?php if ($role === 'super_admin'): ?>
    <div class="nav-section-label">System</div>
    <a href="/admin/settings.php" class="nav-item <?= nav_active('settings.php') ?>">
      <span class="nav-icon">⚙️</span>
      <span class="nav-label">Settings</span>
    </a>
    <a href="/admin/logs.php" class="nav-item <?= nav_active('logs.php') ?>">
      <span class="nav-icon">📋</span>
      <span class="nav-label">Activity Logs</span>
    </a>
    <?php endif; ?>

    <?php endif; ?>

  </nav><!-- /sidebar-nav -->

  <!-- Sidebar Footer -->
  <div class="sidebar-footer">
    <?php if ($user): ?>
    <div class="nav-item" style="cursor:default; margin-bottom: 0.4rem;">
      <span class="nav-icon">
        <div style="width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,#e040fb,#7c4dff);display:flex;align-items:center;justify-content:center;font-size:0.75rem;font-weight:700;color:#fff;">
          <?= strtoupper(substr($user['username'], 0, 1)) ?>
        </div>
      </span>
      <div class="nav-label" style="line-height:1.2; overflow:hidden;">
        <div style="font-weight:700;font-size:0.85rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($user['username']) ?></div>
        <div style="font-size:0.68rem;color:var(--text-muted);text-transform:capitalize;"><?= str_replace('_', ' ', $user['role']) ?></div>
      </div>
    </div>
    <a href="/logout.php" class="nav-item" style="color:var(--text-muted);">
      <span class="nav-icon">🚪</span>
      <span class="nav-label">Logout</span>
    </a>
    <?php else: ?>
    <a href="/login.php" class="nav-item">
      <span class="nav-icon">🔐</span>
      <span class="nav-label">Login</span>
    </a>
    <?php endif; ?>
  </div>

</aside>
