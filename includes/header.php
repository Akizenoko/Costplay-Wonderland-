<?php
// ─────────────────────────────────────────────────────────────
// Header Component — Costplay Wonderland
// ─────────────────────────────────────────────────────────────
if (!isset($conn)) {
    require_once __DIR__ . '/db.php';
}
if (!function_exists('startSession')) {
    require_once __DIR__ . '/auth.php';
}
startSession();

$user         = getCurrentUser();
$cart_count   = isset($conn) ? getCartCount($conn) : 0;
$notif_count  = isset($conn) ? getUnreadNotifCount($conn) : 0;
$page_title   = $page_title ?? 'Costplay Wonderland';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($page_title) ?> — Costplay Wonderland</title>
  <meta name="description" content="Costplay Wonderland — The ultimate cosplay e-commerce & rental platform.">

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;700;900&family=Nunito:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

  <!-- Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

  <!-- Styles -->
  <link rel="stylesheet" href="/assets/css/main.css">
  <?php if (isset($extra_css)): ?>
    <?php foreach ((array)$extra_css as $css): ?>
    <link rel="stylesheet" href="<?= $css ?>">
    <?php endforeach; ?>
  <?php endif; ?>
</head>
<body>

<?php require_once __DIR__ . '/sidebar.php'; ?>

<!-- Top Header -->
<header id="top-header" class="top-header">
  <!-- Mobile burger -->
  <button id="mobile-menu-btn" class="burger-btn" style="display:none; margin-right:0.5rem;" title="Menu">
    <i class="fas fa-bars"></i>
  </button>

  <!-- Search -->
  <div class="header-search">
    <i class="fas fa-search search-icon"></i>
    <form action="/shop.php" method="GET">
      <input type="text" name="q" placeholder="Search costumes, characters, franchises..."
             value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
    </form>
  </div>

  <!-- Actions -->
  <div class="header-actions">

    <?php if ($user): ?>
    <!-- Notifications -->
    <div style="position:relative;">
      <button class="header-icon-btn" data-dropdown="notif-dropdown" title="Notifications">
        <i class="fas fa-bell"></i>
        <?php if ($notif_count > 0): ?>
        <span class="badge"><?= $notif_count ?></span>
        <?php endif; ?>
      </button>
      <div id="notif-dropdown" class="dropdown-menu" style="
        position:absolute; top:calc(100%+0.5rem); right:0;
        background:var(--bg-elevated); border:1px solid var(--border-color);
        border-radius:var(--radius-md); width:300px; z-index:300;
        box-shadow:var(--shadow-card); display:none; overflow:hidden;">
        <div style="padding:0.75rem 1rem; border-bottom:1px solid var(--border-color); font-weight:700; font-size:0.85rem;">
          🔔 Notifications
        </div>
        <div style="max-height:300px; overflow-y:auto; padding:0.5rem 0;">
          <a href="/notifications.php" style="display:block;padding:0.5rem 1rem;font-size:0.82rem;color:var(--accent-cyan);text-align:center;">
            View all notifications
          </a>
        </div>
      </div>
    </div>

    <!-- Cart -->
    <a href="/cart.php" class="header-icon-btn" title="Shopping Cart" style="text-decoration:none; color:var(--text-secondary);">
      <i class="fas fa-shopping-cart"></i>
      <span class="badge cart-count-badge" style="<?= $cart_count > 0 ? '' : 'display:none' ?>"><?= $cart_count ?></span>
    </a>

    <!-- User Menu -->
    <div style="position:relative;">
      <button class="user-avatar-btn" data-dropdown="user-dropdown">
        <div class="user-avatar">
          <?php if ($user['avatar']): ?>
            <img src="<?= htmlspecialchars($user['avatar']) ?>" alt="avatar">
          <?php else: ?>
            <?= strtoupper(substr($user['username'], 0, 1)) ?>
          <?php endif; ?>
        </div>
        <span style="font-size:0.85rem; font-weight:700; max-width:100px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
          <?= htmlspecialchars($user['username']) ?>
        </span>
        <i class="fas fa-chevron-down" style="font-size:0.7rem; color:var(--text-muted);"></i>
      </button>
      <div id="user-dropdown" class="dropdown-menu" style="
        position:absolute; top:calc(100%+0.5rem); right:0;
        background:var(--bg-elevated); border:1px solid var(--border-color);
        border-radius:var(--radius-md); width:200px; z-index:300;
        box-shadow:var(--shadow-card); display:none; overflow:hidden; padding:0.4rem;">
        <div style="padding:0.6rem 0.8rem; border-bottom:1px solid var(--border-color); margin-bottom:0.4rem;">
          <div style="font-weight:700; font-size:0.85rem;"><?= htmlspecialchars($user['username']) ?></div>
          <div style="font-size:0.72rem; color:var(--text-muted); text-transform:capitalize;"><?= str_replace('_',' ',$user['role']) ?></div>
        </div>
        <a href="/profile.php" style="display:flex;align-items:center;gap:0.6rem;padding:0.5rem 0.8rem;border-radius:8px;color:var(--text-secondary);font-size:0.85rem;text-decoration:none;" onmouseover="this.style.background='rgba(124,77,255,0.1)'" onmouseout="this.style.background=''">
          <i class="fas fa-user" style="width:16px;"></i> My Profile
        </a>
        <?php if (in_array($user['role'], ['seller'])): ?>
        <a href="/seller/dashboard.php" style="display:flex;align-items:center;gap:0.6rem;padding:0.5rem 0.8rem;border-radius:8px;color:var(--text-secondary);font-size:0.85rem;text-decoration:none;" onmouseover="this.style.background='rgba(124,77,255,0.1)'" onmouseout="this.style.background=''">
          <i class="fas fa-store" style="width:16px;"></i> My Store
        </a>
        <?php endif; ?>
        <?php if (in_array($user['role'], ['admin','super_admin'])): ?>
        <a href="/admin/dashboard.php" style="display:flex;align-items:center;gap:0.6rem;padding:0.5rem 0.8rem;border-radius:8px;color:var(--text-secondary);font-size:0.85rem;text-decoration:none;" onmouseover="this.style.background='rgba(124,77,255,0.1)'" onmouseout="this.style.background=''">
          <i class="fas fa-cog" style="width:16px;"></i> Admin Panel
        </a>
        <?php endif; ?>
        <div style="border-top:1px solid var(--border-color); margin:0.4rem 0;"></div>
        <a href="/logout.php" style="display:flex;align-items:center;gap:0.6rem;padding:0.5rem 0.8rem;border-radius:8px;color:#ff5252;font-size:0.85rem;text-decoration:none;" onmouseover="this.style.background='rgba(255,82,82,0.1)'" onmouseout="this.style.background=''">
          <i class="fas fa-sign-out-alt" style="width:16px;"></i> Logout
        </a>
      </div>
    </div>

    <?php else: ?>
    <!-- Guest -->
    <a href="/login.php" class="btn btn-secondary btn-sm">Login</a>
    <a href="/register.php" class="btn btn-primary btn-sm">Sign Up</a>
    <?php endif; ?>

  </div>
</header>

<!-- Dropdown CSS patch -->
<style>
.dropdown-menu { display: none !important; }
.dropdown-menu.open { display: block !important; }
@media (max-width:768px) { #mobile-menu-btn { display: flex !important; } }
</style>

<!-- Main Content wrapper opened here, closed in footer.php -->
<div id="main-content" class="main-content">
