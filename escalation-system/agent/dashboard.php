<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
$user = require_role(['LEVEL1', 'LEVEL2', 'LEVEL3']);
$pdo = getPDO();
$tickets = fetch_tickets($pdo, ['assigned_agent_id' => (int) $user['id']]);
$levelTickets = fetch_tickets($pdo, ['support_level_id' => (int) $user['support_level_id']]);

$pageTitle = 'Agent Dashboard';
$pageSubtitle = 'Monitor assigned queue, SLA exposure, escalations, and completed resolutions.';
include __DIR__ . '/../includes/header.php';
?>
<section class="cards">
    <div class="card"><h3>Assigned Tickets</h3><div class="kpi"><?= count($tickets) ?></div></div>
    <div class="card"><h3>Pending Tickets</h3><div class="kpi"><?= count(array_filter($tickets, fn($t) => in_array($t['current_status'], ['Assigned', 'In Progress', 'Pending User Response'], true))) ?></div></div>
    <div class="card"><h3>At Risk</h3><div class="kpi"><?= count(array_filter($levelTickets, fn($t) => get_sla_status($t) === 'At Risk')) ?></div></div>
    <div class="card"><h3>Resolved</h3><div class="kpi"><?= count(array_filter($tickets, fn($t) => $t['current_status'] === 'Resolved')) ?></div></div>
</section>

<section class="table-wrap">
    <table>
        <thead>
        <tr>
            <th>Ticket</th>
            <th>Requester</th>
            <th>Priority</th>
            <th>Status</th>
            <th>SLA</th>
            <th>Action</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach (array_slice($levelTickets, 0, 10) as $ticket): ?>
            <tr>
                <td><?= e($ticket['ticket_number']) ?><br><span class="muted"><?= e($ticket['title']) ?></span></td>
                <td><?= e($ticket['requester_name']) ?></td>
                <td><span class="priority-badge <?= priority_badge_class($ticket['priority_code']) ?>"><?= e($ticket['priority_name']) ?></span></td>
                <td><span class="badge <?= ticket_badge_class($ticket['current_status']) ?>"><?= e($ticket['current_status']) ?></span></td>
                <td><?= e(get_sla_status($ticket)) ?></td>
                <td><a class="button secondary" href="/escalation-system/agent/ticket_view.php?id=<?= (int) $ticket['id'] ?>">Open</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
