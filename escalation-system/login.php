<?php
declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';

if (current_user()) {
    header('Location: ' . dashboard_for_role(current_user()['role_code']));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = getPDO()->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash']) && (int) $user['is_active'] === 1) {
        login_user($user);
        audit_log(getPDO(), (int) $user['id'], 'USER_LOGIN', null, null, 'User logged in');
        header('Location: ' . dashboard_for_role(current_user()['role_code']));
        exit;
    }

    flash('error', 'Invalid credentials provided.');
    header('Location: /escalation-system/login.php');
    exit;
}

$pageTitle = 'Login';
include __DIR__ . '/includes/header.php';
?>
<div class="login-page">
    <div class="login-card">
        <h2>Multi-Tier Escalation Management System</h2>
        <p class="muted">Sign in with one of the demo accounts to explore requester, agent, and administrator workflows.</p>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <label>
                Email
                <input type="email" name="email" placeholder="admin@example.com" required>
            </label>
            <label>
                Password
                <input type="password" name="password" placeholder="password123" required>
            </label>
            <button type="submit">Login</button>
        </form>
        <div class="panel">
            <h3>Demo Accounts</h3>
            <p class="muted">`admin@example.com`, `level1@example.com`, `level2@example.com`, `level3@example.com`, `user@example.com`</p>
            <p class="muted">Password: `password123`</p>
        </div>
    </div>
</div>
</body>
</html>
