<?php
declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';
$user = current_user();

if (!$user) {
    header('Location: /escalation-system/login.php');
    exit;
}

header('Location: ' . dashboard_for_role($user['role_code']));
exit;
