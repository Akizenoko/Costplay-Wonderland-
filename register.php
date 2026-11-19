<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
startSession();

$page_title = 'Register';
if (isLoggedIn()) { header('Location: /index.php'); exit; }

$error   = '';
$success = '';
$default_role = $_GET['role'] ?? 'customer';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['password'] !== $_POST['confirm_password']) {
        $error = 'Passwords do not match.';
    } else {
        $result = register($conn, [
            'username' => $_POST['username'] ?? '',
            'email'    => $_POST['email']    ?? '',
            'password' => $_POST['password'] ?? '',
            'role'     => $_POST['role']     ?? 'customer',
        ]);
        if ($result['success']) {
            header('Location: /login.php?registered=1');
            exit;
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register — Costplay Wonderland</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700;900&family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="/assets/css/main.css">
  <style>
    body { display:flex; min-height:100vh; align-items:center; justify-content:center; padding:1rem; }
    body::before {
      content:''; position:fixed; inset:0;
      background: radial-gradient(ellipse at top right,rgba(0,229,255,0.12),transparent 60%),
                  radial-gradient(ellipse at bottom left,rgba(224,64,251,0.12),transparent 60%);
      pointer-events:none;
    }
    .auth-card { width:100%; max-width:500px; background:rgba(22,33,62,0.92); backdrop-filter:blur(20px); border:1px solid var(--border-color); border-radius:var(--radius-lg); padding:2.5rem; position:relative; box-shadow:0 20px 60px rgba(0,0,0,0.5); }
    .role-selector { display:grid; grid-template-columns:1fr 1fr; gap:0.75rem; margin-bottom:1.5rem; }
    .role-option { display:none; }
    .role-label {
      display:flex; flex-direction:column; align-items:center; gap:0.5rem;
      padding:1.2rem 1rem; border-radius:var(--radius-sm);
      background:rgba(255,255,255,0.03); border:2px solid var(--border-color);
      cursor:pointer; transition:all 0.3s; text-align:center;
    }
    .role-option:checked + .role-label {
      border-color:var(--accent-purple);
      background:rgba(124,77,255,0.15);
      box-shadow:0 0 15px rgba(124,77,255,0.2);
    }
    .role-label .role-icon { font-size:1.8rem; }
    .role-label .role-name { font-size:0.85rem; font-weight:700; color:var(--text-primary); }
    .role-label .role-desc { font-size:0.72rem; color:var(--text-muted); }
  </style>
</head>
<body>
<div class="stars-bg" id="stars-bg"></div>

<div class="auth-card">
  <div style="text-align:center;margin-bottom:2rem;">
    <div style="width:60px;height:60px;border-radius:16px;background:linear-gradient(135deg,#e040fb,#7c4dff);display:flex;align-items:center;justify-content:center;font-size:1.8rem;margin:0 auto 1rem;box-shadow:0 0 25px rgba(224,64,251,0.4);">✨</div>
    <h1 style="font-family:'Cinzel',serif;font-size:1.5rem;font-weight:900;background:linear-gradient(135deg,#e040fb,#7c4dff);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;">Join the Wonderland</h1>
    <p style="color:var(--text-secondary);font-size:0.88rem;margin-top:0.3rem;">Create your Costplay Wonderland account 🎌</p>
  </div>

  <?php if ($error): ?>
  <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST" id="register-form" novalidate>

    <!-- Role Selection -->
    <div class="form-group">
      <label class="form-label">I want to…</label>
      <div class="role-selector">
        <div>
          <input type="radio" name="role" id="role-customer" value="customer" class="role-option"
                 <?= ($default_role === 'customer') ? 'checked' : '' ?>>
          <label for="role-customer" class="role-label">
            <span class="role-icon">🛒</span>
            <span class="role-name">Shop & Rent</span>
            <span class="role-desc">Buy or rent cosplay costumes</span>
          </label>
        </div>
        <div>
          <input type="radio" name="role" id="role-seller" value="seller" class="role-option"
                 <?= ($default_role === 'seller') ? 'checked' : '' ?>>
          <label for="role-seller" class="role-label">
            <span class="role-icon">🏪</span>
            <span class="role-name">Open a Store</span>
            <span class="role-desc">Sell & rent my costumes</span>
          </label>
        </div>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
      <div class="form-group" style="margin-bottom:0;">
        <label class="form-label"><i class="fas fa-user" style="color:var(--accent-purple);margin-right:0.4rem;"></i>Username</label>
        <input type="text" name="username" class="form-control" placeholder="coolcosplayer"
               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required minlength="3">
      </div>
      <div class="form-group" style="margin-bottom:0;">
        <label class="form-label"><i class="fas fa-envelope" style="color:var(--accent-purple);margin-right:0.4rem;"></i>Email</label>
        <input type="email" name="email" class="form-control" placeholder="you@email.com"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-top:1rem;">
      <div class="form-group" style="margin-bottom:0;">
        <label class="form-label"><i class="fas fa-lock" style="color:var(--accent-purple);margin-right:0.4rem;"></i>Password</label>
        <input type="password" name="password" class="form-control" placeholder="Min 6 characters" required minlength="6">
      </div>
      <div class="form-group" style="margin-bottom:0;">
        <label class="form-label"><i class="fas fa-lock" style="color:var(--accent-purple);margin-right:0.4rem;"></i>Confirm Password</label>
        <input type="password" name="confirm_password" class="form-control" placeholder="Repeat password" required>
      </div>
    </div>

    <div style="margin:1.5rem 0;padding:1rem;background:rgba(0,229,255,0.05);border:1px solid rgba(0,229,255,0.2);border-radius:10px;font-size:0.82rem;color:var(--text-secondary);">
      <i class="fas fa-info-circle" style="color:var(--accent-cyan);"></i>
      By registering, you agree to our <a href="#" style="color:var(--accent-cyan);">Terms of Service</a> and <a href="#" style="color:var(--accent-cyan);">Rental Policy</a>.
    </div>

    <button type="submit" class="btn btn-primary btn-block btn-lg">
      <i class="fas fa-user-plus"></i> Create Account
    </button>
  </form>

  <div style="text-align:center;margin-top:1.5rem;color:var(--text-secondary);font-size:0.88rem;">
    Already have an account?
    <a href="/login.php" style="color:var(--accent-pink);font-weight:700;">Login Here</a>
  </div>
</div>

<script src="/assets/js/main.js"></script>
<script>
createStarsBg('stars-bg', 100);

// Form validation
document.getElementById('register-form').addEventListener('submit', function(e) {
  const pw  = this.querySelector('[name="password"]').value;
  const cpw = this.querySelector('[name="confirm_password"]').value;
  if (pw !== cpw) {
    e.preventDefault();
    Toast.error('Passwords do not match!');
  }
});
</script>
</body>
</html>
