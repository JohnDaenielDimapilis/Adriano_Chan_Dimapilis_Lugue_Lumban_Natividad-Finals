<?php
declare(strict_types=1);

$roleCode = $authUser['role_code'] ?? '';
?>
<aside class="sidebar">
    <div class="brand">
        <span class="brand-mark">EMS</span>
        <div>
            <strong>Escalation System</strong>
            <small>Multi-Tier Help Desk</small>
        </div>
    </div>
    <nav>
        <?php if ($roleCode === 'ADMIN'): ?>
            <a href="<?= e(url('/admin/dashboard.php')) ?>">Dashboard</a>
            <a href="<?= e(url('/admin/users.php')) ?>">Users</a>
            <a href="<?= e(url('/admin/agents.php')) ?>">Agents</a>
            <a href="<?= e(url('/admin/teams.php')) ?>">Teams</a>
            <a href="<?= e(url('/admin/sla_rules.php')) ?>">SLA Rules</a>
            <a href="<?= e(url('/admin/reports.php')) ?>">Reports</a>
        <?php elseif ($roleCode === 'REQUESTER'): ?>
            <a href="<?= e(url('/requester/dashboard.php')) ?>">Dashboard</a>
            <a href="<?= e(url('/requester/create_ticket.php')) ?>">Create Ticket</a>
            <a href="<?= e(url('/requester/my_tickets.php')) ?>">My Tickets</a>
        <?php else: ?>
            <a href="<?= e(url('/agent/dashboard.php')) ?>">Dashboard</a>
            <a href="<?= e(url('/agent/assigned_tickets.php')) ?>">Assigned Tickets</a>
        <?php endif; ?>
        <a href="<?= e(url('/logout.php')) ?>">Logout</a>
    </nav>
</aside>
