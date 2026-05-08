<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
$user = require_role('REQUESTER');
$pdo = getPDO();
$tickets = fetch_tickets($pdo, ['requester_id' => (int) $user['id']]);

$pageTitle = 'Requester Dashboard';
$pageSubtitle = 'Track submitted incidents, check SLA status, and reopen unresolved concerns when needed.';
include __DIR__ . '/../includes/header.php';
?>
<section class="cards">
    <div class="card"><h3>My Tickets</h3><div class="kpi"><?= count($tickets) ?></div></div>
    <div class="card"><h3>Open</h3><div class="kpi"><?= count(array_filter($tickets, fn($t) => in_array($t['current_status'], ['Open', 'Assigned', 'In Progress', 'At Risk', 'Reopened'], true))) ?></div></div>
    <div class="card"><h3>Resolved</h3><div class="kpi"><?= count(array_filter($tickets, fn($t) => $t['current_status'] === 'Resolved')) ?></div></div>
    <div class="card"><h3>Closed</h3><div class="kpi"><?= count(array_filter($tickets, fn($t) => $t['current_status'] === 'Closed')) ?></div></div>
</section>

<section class="panel">
    <div class="stat-strip">
        <a class="button" href="/escalation-system/requester/create_ticket.php">Submit New Ticket</a>
        <a class="button secondary" href="/escalation-system/requester/my_tickets.php">View Ticket List</a>
    </div>
</section>

<section class="table-wrap">
    <table>
        <thead>
        <tr>
            <th>Ticket</th>
            <th>Priority</th>
            <th>Status</th>
            <th>SLA</th>
            <th>Assigned Agent</th>
            <th>Action</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach (array_slice($tickets, 0, 8) as $ticket): ?>
            <tr>
                <td><strong><?= e($ticket['ticket_number']) ?></strong><br><span class="muted"><?= e($ticket['title']) ?></span></td>
                <td><span class="priority-badge <?= priority_badge_class($ticket['priority_code']) ?>"><?= e($ticket['priority_name']) ?></span></td>
                <td><span class="badge <?= ticket_badge_class($ticket['current_status']) ?>"><?= e($ticket['current_status']) ?></span></td>
                <td><?= e(get_sla_status($ticket)) ?></td>
                <td><?= e($ticket['assigned_agent_name'] ?? 'Queue') ?></td>
                <td><a class="button secondary" href="/escalation-system/requester/ticket_view.php?id=<?= (int) $ticket['id'] ?>">Open</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$tickets): ?>
            <tr><td colspan="6" class="empty-state">No tickets submitted yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
