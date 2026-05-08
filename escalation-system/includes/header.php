<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
$authUser = current_user();
$notificationItems = $authUser ? fetch_notifications(getPDO(), (int) $authUser['id']) : [];
$notificationCount = $authUser ? unread_notification_count(getPDO(), (int) $authUser['id']) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Multi-Tier Escalation Management System') ?></title>
    <link rel="stylesheet" href="/escalation-system/assets/css/style.css">
</head>
<body>
<div class="app-shell">
    <?php if ($authUser): ?>
        <?php include __DIR__ . '/sidebar.php'; ?>
    <?php endif; ?>
    <div class="main-content">
        <header class="topbar">
            <div>
                <h1><?= e($pageTitle ?? 'Dashboard') ?></h1>
                <p class="subtitle"><?= e($pageSubtitle ?? 'Professional help desk and escalation management workspace.') ?></p>
            </div>
            <?php if ($authUser): ?>
                <div class="topbar-actions">
                    <div class="notification-menu">
                        <button type="button" class="icon-button" data-notification-toggle>
                            Notifications
                            <?php if ($notificationCount > 0): ?><span class="counter"><?= $notificationCount ?></span><?php endif; ?>
                        </button>
                        <div class="notification-panel" data-notification-panel>
                            <?php foreach ($notificationItems as $notification): ?>
                                <div class="notification-item <?= $notification['is_read'] ? 'read' : 'unread' ?>">
                                    <strong><?= e($notification['title']) ?></strong>
                                    <p><?= e($notification['message']) ?></p>
                                    <small><?= e($notification['created_at']) ?></small>
                                </div>
                            <?php endforeach; ?>
                            <?php if (!$notificationItems): ?>
                                <p class="empty-state">No notifications yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="profile-chip">
                        <span><?= e($authUser['full_name']) ?></span>
                        <small><?= e($authUser['role_name']) ?></small>
                    </div>
                </div>
            <?php endif; ?>
        </header>
        <main class="page-content">
            <?php if ($success = flash('success')): ?>
                <div class="alert alert-success"><?= e($success) ?></div>
            <?php endif; ?>
            <?php if ($error = flash('error')): ?>
                <div class="alert alert-danger"><?= e($error) ?></div>
            <?php endif; ?>
