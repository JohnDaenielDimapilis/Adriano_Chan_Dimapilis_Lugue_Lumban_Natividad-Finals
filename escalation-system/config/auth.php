<?php
declare(strict_types=1);

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

const SESSION_TIMEOUT = 3600;

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(419);
        exit('Invalid CSRF token.');
    }
}

function login_user(array $user): void
{
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['last_activity'] = time();
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    if (!empty($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
        logout_user();
        return null;
    }

    $_SESSION['last_activity'] = time();

    $pdo = getPDO();
    $stmt = $pdo->prepare("
        SELECT u.*, r.name AS role_name, r.code AS role_code, ap.support_level_id,
               sl.name AS support_level_name, sl.code AS support_level_code,
               t.name AS team_name
        FROM users u
        INNER JOIN roles r ON r.id = u.role_id
        LEFT JOIN agent_profiles ap ON ap.user_id = u.id
        LEFT JOIN support_levels sl ON sl.id = ap.support_level_id
        LEFT JOIN teams t ON t.id = ap.team_id
        WHERE u.id = :id AND u.is_active = 1
        LIMIT 1
    ");
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        header('Location: /escalation-system/login.php');
        exit;
    }

    return $user;
}

function require_role(array|string $roles): array
{
    $user = require_login();
    $roles = (array) $roles;

    if (!in_array($user['role_code'], $roles, true)) {
        http_response_code(403);
        exit('Unauthorized access.');
    }

    return $user;
}

function dashboard_for_role(string $roleCode): string
{
    return match ($roleCode) {
        'ADMIN' => '/escalation-system/admin/dashboard.php',
        'REQUESTER' => '/escalation-system/requester/dashboard.php',
        default => '/escalation-system/agent/dashboard.php',
    };
}
