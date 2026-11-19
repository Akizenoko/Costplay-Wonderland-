<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
startSession();

$page_title = 'Login';

// Redirect if already logged in
if (isLoggedIn()) {
    $role = $_SESSION['role'];
    if ($role === 'seller')     header('Location: /seller/dashboard.php');
    elseif (in_array($role,['admin','super_admin'])) header('Location: /admin/dashboard.php');
    else header('Location: /index.php');
    exit;
}

$error   = '';
$success = '';
$redirect = htmlspecialchars($_GET['redirect'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password']       ?? '';

    if (!$email || !$password) {
        $error = 'Please fill in all fields.';
    } else {
        $result = login($conn, $email, $password);
        if ($result['success']) {
            $role = $_SESSION['role'];
            $dest = $redirect ?: match($role) {
                'seller'      => '/seller/dashboard.php',
                'admin','super_admin' => '/admin/dashboard.php',
                default       => '/index.php'
            };
            header("Location: {$dest}");
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
  <title>Login — Costplay Wonderland</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700;900&family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="/assets/css/main.css">
  <style>
    body { display: flex; min-height: 100vh; align-items: center; justify-content: center; padding: 1rem; }
    body::before {
      content: '';
      position: fixed; inset: 0;
      background: radial-gradient(ellipse at top left, rgba(224,64,251,0.15), transparent 60%),
                  radial-gradient(ellipse at bottom right, rgba(0,229,255,0.1), transparent 60%);
      pointer-events: none;
    }

    .auth-card {
      width: 100%; max-width: 440px;
      background: rgba(22,33,62,0.9);
      backdrop-filter: blur(20px);
      border: 1px solid var(--border-color);
      border-radius: var(--radius-lg);
      padding: 2.5rem;
      position: relative;
      box-shadow: 0 20px 60px rgba(0,0,0,0.5), 0 0 0 1px rgba(124,77,255,0.2);
    }

    .auth-card::before {
      content: '';
      position: absolute; inset: -1px;
      border-radius: inherit;
      background: linear-gradient(135deg, rgba(224,64,251,0.4), rgba(124,77,255,0.1), rgba(0,229,255,0.2));
      z-index: -1;
      border-radius: calc(var(--radius-lg) + 1px);
    }

    .password-wrapper { position: relative; }
    .password-toggle {
      position: absolute; right: 1rem; top: 50%; transform: translateY(-50%);
      background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 1rem;
      padding: 0.2rem;
    }
    .password-toggle:hover { color: var(--accent-purple); }
  </style>
</head>
<body>

<!-- Stars BG -->
<div class="stars-bg" id="stars-bg"></div>

<div class="auth-card">
  <!-- Logo -->
  <div style="text-align:center;margin-bottom:2rem;">
    <div style="width:60px;height:60px;border-radius:16px;background:linear-gradient(135deg,#e040fb,#7c4dff);display:flex;align-items:center;justify-content:center;font-size:1.8rem;margin:0 auto 1rem;box-shadow:0 0 25px rgba(224,64,251,0.4);">🎭</div>
    <h1 style="font-family:'Cinzel',serif;font-size:1.5rem;font-weight:900;background:linear-gradient(135deg,#e040fb,#7c4dff);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;">Welcome Back</h1>
    <p style="color:var(--text-secondary);font-size:0.88rem;margin-top:0.3rem;">Sign in to Costplay Wonderland ✨</p>
  </div>

  <?php if ($error): ?>
  <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <?php if (isset($_GET['registered'])): ?>
  <div class="alert alert-success"><i class="fas fa-check-circle"></i> Account created! Please login.</div>
  <?php endif; ?>

  <form method="POST" id="login-form" novalidate>
    <?php if ($redirect): ?>
    <input type="hidden" name="redirect" value="<?= $redirect ?>">
    <?php endif; ?>

    <div class="form-group">
      <label class="form-label"><i class="fas fa-envelope" style="color:var(--accent-purple);margin-right:0.4rem;"></i>Email Address</label>
      <input type="email" name="email" class="form-control" placeholder="your@email.com"
             value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
    </div>

    <div class="form-group">
      <label class="form-label"><i class="fas fa-lock" style="color:var(--accent-purple);margin-right:0.4rem;"></i>Password</label>
      <div class="password-wrapper">
        <input type="password" name="password" id="password-input" class="form-control"
               placeholder="Enter your password" required style="padding-right:3rem;">
        <button type="button" class="password-toggle" onclick="togglePw()">
          <i class="far fa-eye" id="pw-icon"></i>
        </button>
      </div>
    </div>

    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;">
      <label style="display:flex;align-items:center;gap:0.4rem;cursor:pointer;font-size:0.85rem;color:var(--text-secondary);">
        <input type="checkbox" name="remember" style="accent-color:var(--accent-purple);"> Remember me
      </label>
      <a href="#" style="font-size:0.85rem;color:var(--accent-cyan);">Forgot password?</a>
    </div>

    <button type="submit" class="btn btn-primary btn-block btn-lg">
      <i class="fas fa-sign-in-alt"></i> Login
    </button>
  </form>

  <div style="text-align:center;margin-top:1.5rem;color:var(--text-secondary);font-size:0.88rem;">
    Don't have an account?
    <a href="/register.php" style="color:var(--accent-pink);font-weight:700;">Sign Up Free</a>
  </div>

  <!-- Demo credentials -->
  <details style="margin-top:1.5rem;background:rgba(124,77,255,0.08);border:1px solid rgba(124,77,255,0.2);border-radius:10px;padding:0.75rem 1rem;">
    <summary style="cursor:pointer;font-size:0.82rem;color:var(--accent-purple);font-weight:700;">🔑 Demo Accounts</summary>
    <div style="margin-top:0.75rem;font-size:0.8rem;color:var(--text-secondary);line-height:2;">
      <div><b style="color:var(--accent-gold);">Super Admin:</b> superadmin@costplay.com</div>
      <div><b style="color:var(--accent-pink);">Admin:</b> admin@costplay.com</div>
      <div><b style="color:var(--accent-cyan);">Seller:</b> otakustore@example.com</div>
      <div><b style="color:var(--text-primary);">Customer:</b> sakura_chan@example.com</div>
      <div style="margin-top:0.3rem;padding:0.4rem 0.75rem;background:rgba(0,0,0,0.3);border-radius:6px;font-family:monospace;">
        Password: <b style="color:var(--accent-gold);">password</b>
      </div>
    </div>
  </details>

</div>

<script src="/assets/js/main.js"></script>
<script>
createStarsBg('stars-bg', 100);

function togglePw() {
  const input = document.getElementById('password-input');
  const icon  = document.getElementById('pw-icon');
  if (input.type === 'password') {
    input.type = 'text';
    icon.className = 'far fa-eye-slash';
  } else {
    input.type = 'password';
    icon.className = 'far fa-eye';
  }
}
</script>
</body>
</html>
