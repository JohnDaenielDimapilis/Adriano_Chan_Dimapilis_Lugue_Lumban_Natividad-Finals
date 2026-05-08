<?php
declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';
logout_user();
header('Location: /escalation-system/login.php');
exit;
