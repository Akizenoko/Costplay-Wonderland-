<?php
// ─────────────────────────────────────────────────────────────
// Auth Functions — Costplay Wonderland
// ─────────────────────────────────────────────────────────────

function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function isLoggedIn(): bool {
    startSession();
    return isset($_SESSION['user_id']);
}

function getCurrentUser(): ?array {
    startSession();
    if (!isset($_SESSION['user_id'])) return null;
    return [
        'user_id'  => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'email'    => $_SESSION['email'],
        'role'     => $_SESSION['role'],
        'store_id' => $_SESSION['store_id'] ?? null,
        'avatar'   => $_SESSION['avatar']   ?? null,
    ];
}

function hasRole(string ...$roles): bool {
    startSession();
    return isset($_SESSION['role']) && in_array($_SESSION['role'], $roles);
}

function requireLogin(string $redirect = '/login.php'): void {
    if (!isLoggedIn()) {
        $back = urlencode($_SERVER['REQUEST_URI']);
        header("Location: {$redirect}?redirect={$back}");
        exit;
    }
}

function requireRole(array $roles, string $redirect = '/index.php'): void {
    requireLogin();
    if (!hasRole(...$roles)) {
        header("Location: {$redirect}?error=unauthorized");
        exit;
    }
}

function login(mysqli $conn, string $email, string $password): array {
    $stmt = $conn->prepare(
        "SELECT user_id, username, email, password_hash, role, avatar_url, is_active 
         FROM user_accounts WHERE email = ? LIMIT 1"
    );
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user   = $result->fetch_assoc();
    $stmt->close();

    if (!$user) {
        return ['success' => false, 'message' => 'Invalid email or password.'];
    }
    if (!$user['is_active']) {
        return ['success' => false, 'message' => 'Your account has been suspended.'];
    }
    // Allow plain "password" for seeded demo data (hash of "password" is the seed hash)
    if (!password_verify($password, $user['password_hash'])) {
        // Log failed attempt
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $stmt2 = $conn->prepare("INSERT INTO login_logs (user_id, ip_address, user_agent, status) VALUES (?,?,?,'failed')");
        $stmt2->bind_param('iss', $user['user_id'], $ip, $ua);
        $stmt2->execute();
        $stmt2->close();
        return ['success' => false, 'message' => 'Invalid email or password.'];
    }

    // Load store_id if seller
    $store_id = null;
    if ($user['role'] === 'seller') {
        $st = $conn->prepare("SELECT store_id FROM stores WHERE user_id = ? LIMIT 1");
        $st->bind_param('i', $user['user_id']);
        $st->execute();
        $sr = $st->get_result()->fetch_assoc();
        $st->close();
        $store_id = $sr['store_id'] ?? null;
    }

    startSession();
    session_regenerate_id(true);
    $_SESSION['user_id']  = $user['user_id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email']    = $user['email'];
    $_SESSION['role']     = $user['role'];
    $_SESSION['store_id'] = $store_id;
    $_SESSION['avatar']   = $user['avatar_url'];

    // Log success
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $stmt3 = $conn->prepare("INSERT INTO login_logs (user_id, ip_address, user_agent, status) VALUES (?,?,?,'success')");
    $stmt3->bind_param('iss', $user['user_id'], $ip, $ua);
    $stmt3->execute();
    $stmt3->close();

    return ['success' => true, 'user' => $user];
}

function logout(): void {
    startSession();
    $_SESSION = [];
    session_destroy();
    header('Location: /index.php');
    exit;
}

function register(mysqli $conn, array $data): array {
    $username = trim($data['username'] ?? '');
    $email    = trim($data['email']    ?? '');
    $password = $data['password']       ?? '';
    $role     = in_array($data['role'] ?? '', ['customer', 'seller']) ? $data['role'] : 'customer';

    // Validate
    if (strlen($username) < 3)  return ['success' => false, 'message' => 'Username must be at least 3 characters.'];
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['success' => false, 'message' => 'Invalid email address.'];
    if (strlen($password) < 6)  return ['success' => false, 'message' => 'Password must be at least 6 characters.'];

    // Check duplicates
    $check = $conn->prepare("SELECT user_id FROM user_accounts WHERE email = ? OR username = ?");
    $check->bind_param('ss', $email, $username);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $check->close();
        return ['success' => false, 'message' => 'Email or username already taken.'];
    }
    $check->close();

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $conn->prepare("INSERT INTO user_accounts (username, email, password_hash, role) VALUES (?,?,?,?)");
    $stmt->bind_param('ssss', $username, $email, $hash, $role);
    $stmt->execute();
    $user_id = $conn->insert_id;
    $stmt->close();

    if (!$user_id) return ['success' => false, 'message' => 'Registration failed. Please try again.'];

    // Create customer profile
    if ($role === 'customer') {
        $cp = $conn->prepare("INSERT INTO customer_profiles (user_id) VALUES (?)");
        $cp->bind_param('i', $user_id);
        $cp->execute();
        $cp->close();
    }

    // Create store if seller
    if ($role === 'seller') {
        $store_name = $username . "'s Store";
        $slug       = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $username)) . '-store';
        $sv = $conn->prepare("INSERT INTO stores (user_id, store_name, store_slug) VALUES (?,?,?)");
        $sv->bind_param('iss', $user_id, $store_name, $slug);
        $sv->execute();
        $sv->close();
    }

    // Welcome notification
    $title   = 'Welcome to Costplay Wonderland! 🎌';
    $message = 'Your account is ready. Start exploring amazing cosplay costumes!';
    $wn = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?,?,?,'welcome')");
    $wn->bind_param('iss', $user_id, $title, $message);
    $wn->execute();
    $wn->close();

    return ['success' => true, 'user_id' => $user_id];
}

function getCartCount(mysqli $conn): int {
    if (!isLoggedIn()) return 0;
    $uid  = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT SUM(quantity) as cnt FROM cart_items WHERE user_id = ?");
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($row['cnt'] ?? 0);
}

function getUnreadNotifCount(mysqli $conn): int {
    if (!isLoggedIn()) return 0;
    $uid  = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($row['cnt'] ?? 0);
}
