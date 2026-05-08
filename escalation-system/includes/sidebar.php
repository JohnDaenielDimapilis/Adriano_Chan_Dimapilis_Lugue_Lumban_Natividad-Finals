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
            <a href="/escalation-system/admin/dashboard.php">Dashboard</a>
            <a href="/escalation-system/admin/users.php">Users</a>
            <a href="/escalation-system/admin/agents.php">Agents</a>
            <a href="/escalation-system/admin/teams.php">Teams</a>
            <a href="/escalation-system/admin/sla_rules.php">SLA Rules</a>
            <a href="/escalation-system/admin/reports.php">Reports</a>
        <?php elseif ($roleCode === 'REQUESTER'): ?>
            <a href="/escalation-system/requester/dashboard.php">Dashboard</a>
            <a href="/escalation-system/requester/create_ticket.php">Create Ticket</a>
            <a href="/escalation-system/requester/my_tickets.php">My Tickets</a>
        <?php else: ?>
            <a href="/escalation-system/agent/dashboard.php">Dashboard</a>
            <a href="/escalation-system/agent/assigned_tickets.php">Assigned Tickets</a>
        <?php endif; ?>
        <a href="/escalation-system/logout.php">Logout</a>
    </nav>
</aside>
