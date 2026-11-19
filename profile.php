<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
startSession();
requireLogin();

$user_id    = $_SESSION['user_id'];
$page_title = 'My Profile';

$user    = db_query_one($conn,"SELECT * FROM user_accounts WHERE user_id=?","i",[$user_id]);
$profile = db_query_one($conn,"SELECT * FROM customer_profiles WHERE user_id=?","i",[$user_id]);
$msg = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $first = trim($_POST['first_name'] ?? '');
        $last  = trim($_POST['last_name']  ?? '');
        $phone = trim($_POST['phone']      ?? '');
        $addr  = trim($_POST['address']    ?? '');

        if ($profile) {
            db_execute($conn,"UPDATE customer_profiles SET first_name=?,last_name=?,phone=?,address=? WHERE user_id=?",
                'ssssi',[$first,$last,$phone,$addr,$user_id]);
        } else {
            db_execute($conn,"INSERT INTO customer_profiles (user_id,first_name,last_name,phone,address) VALUES (?,?,?,?,?)",
                'issss',[$user_id,$first,$last,$phone,$addr]);
        }
        $profile = db_query_one($conn,"SELECT * FROM customer_profiles WHERE user_id=?","i",[$user_id]);
        $msg = 'Profile updated!';

    } elseif ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new_pw  = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!password_verify($current, $user['password_hash'])) {
            $err = 'Current password is incorrect.';
        } elseif (strlen($new_pw) < 6) {
            $err = 'New password must be at least 6 characters.';
        } elseif ($new_pw !== $confirm) {
            $err = 'Passwords do not match.';
        } else {
            $hash = password_hash($new_pw, PASSWORD_BCRYPT);
            db_execute($conn,"UPDATE user_accounts SET password_hash=? WHERE user_id=?","si",[$hash,$user_id]);
            $msg = 'Password changed successfully!';
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width:800px;margin:0 auto;">
  <h1 class="page-title">👤 My Profile</h1>
  <p class="page-subtitle">Manage your Costplay Wonderland account</p>

  <?php if ($msg): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($err) ?></div><?php endif; ?>

  <div class="grid grid-2" style="gap:1.5rem;align-items:start;">

    <!-- Profile Info -->
    <div class="card">
      <!-- Avatar Section -->
      <div style="text-align:center;margin-bottom:1.5rem;padding-bottom:1.5rem;border-bottom:1px solid var(--border-color);">
        <div style="width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,#e040fb,#7c4dff);display:flex;align-items:center;justify-content:center;font-size:2rem;font-weight:900;color:#fff;margin:0 auto 0.75rem;box-shadow:0 0 20px rgba(224,64,251,0.4);">
          <?= strtoupper(substr($user['username'],0,1)) ?>
        </div>
        <div style="font-family:'Cinzel',serif;font-weight:700;font-size:1.1rem;"><?= htmlspecialchars($user['username']) ?></div>
        <div style="color:var(--text-muted);font-size:0.85rem;"><?= htmlspecialchars($user['email']) ?></div>
        <span class="chip <?= match($user['role']){'super_admin','admin'=>'chip-sale','seller'=>'chip-both',default=>'chip-rent'} ?>" style="margin-top:0.5rem;display:inline-flex;">
          <?= ucwords(str_replace('_',' ',$user['role'])) ?>
        </span>
      </div>

      <form method="POST">
        <input type="hidden" name="action" value="update_profile">
        <div class="grid grid-2" style="gap:0.75rem;">
          <div class="form-group">
            <label class="form-label">First Name</label>
            <input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($profile['first_name'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Last Name</label>
            <input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($profile['last_name'] ?? '') ?>">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Phone Number</label>
          <input type="text" name="phone" class="form-control" placeholder="09XXXXXXXXX" value="<?= htmlspecialchars($profile['phone'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Delivery Address</label>
          <textarea name="address" class="form-control" rows="2"><?= htmlspecialchars($profile['address'] ?? '') ?></textarea>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Profile</button>
      </form>
    </div>

    <div>
      <!-- Change Password -->
      <div class="card" style="margin-bottom:1.5rem;">
        <h3 style="font-family:'Cinzel',serif;font-size:0.95rem;margin-bottom:1.25rem;">🔐 Change Password</h3>
        <form method="POST">
          <input type="hidden" name="action" value="change_password">
          <div class="form-group">
            <label class="form-label">Current Password</label>
            <input type="password" name="current_password" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">New Password</label>
            <input type="password" name="new_password" class="form-control" required minlength="6">
          </div>
          <div class="form-group">
            <label class="form-label">Confirm New Password</label>
            <input type="password" name="confirm_password" class="form-control" required>
          </div>
          <button type="submit" class="btn btn-secondary"><i class="fas fa-key"></i> Update Password</button>
        </form>
      </div>

      <!-- Account Stats -->
      <div class="card">
        <h3 style="font-family:'Cinzel',serif;font-size:0.95rem;margin-bottom:1rem;">📊 Account Stats</h3>
        <?php
        $acct_stats = db_query_one($conn,
            "SELECT
               (SELECT COUNT(*) FROM orders WHERE user_id=?) AS total_orders,
               (SELECT COUNT(*) FROM rentals WHERE user_id=?) AS total_rentals,
               (SELECT COUNT(*) FROM wishlists WHERE user_id=?) AS wishlist_count,
               (SELECT COUNT(*) FROM reviews WHERE user_id=?) AS reviews_count",
            'iiii', [$user_id,$user_id,$user_id,$user_id]);
        $acct_items = [
            ['📦','Total Orders',   $acct_stats['total_orders']],
            ['🎪','Total Rentals',  $acct_stats['total_rentals']],
            ['❤️','Wishlist Items', $acct_stats['wishlist_count']],
            ['⭐','Reviews Written',$acct_stats['reviews_count']],
        ];
        ?>
        <div style="display:flex;flex-direction:column;gap:0.5rem;">
          <?php foreach ($acct_items as [$icon,$label,$val]): ?>
          <div style="display:flex;justify-content:space-between;align-items:center;padding:0.5rem 0.75rem;background:rgba(255,255,255,0.03);border-radius:8px;">
            <span style="color:var(--text-secondary);font-size:0.88rem;"><?= $icon ?> <?= $label ?></span>
            <span style="font-weight:700;color:var(--text-primary);"><?= number_format($val) ?></span>
          </div>
          <?php endforeach; ?>
        </div>
        <div style="margin-top:1rem;font-size:0.78rem;color:var(--text-muted);">
          Member since <?= date('F Y', strtotime($user['created_at'])) ?>
        </div>
      </div>
    </div>

  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
