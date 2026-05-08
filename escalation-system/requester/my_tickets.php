<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
$user = require_role('REQUESTER');
$tickets = fetch_tickets(getPDO(), ['requester_id' => (int) $user['id']]);

$pageTitle = 'My Tickets';
$pageSubtitle = 'Review all submitted tickets, current handling level, and resolution progress.';
include __DIR__ . '/../includes/header.php';
?>
<section class="table-wrap">
    <table>
        <thead>
        <tr>
            <th>Ticket Number</th>
            <th>Title</th>
            <th>Priority</th>
            <th>Status</th>
            <th>Level</th>
            <th>Assigned Agent</th>
            <th>Action</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($tickets as $ticket): ?>
            <tr>
                <td><?= e($ticket['ticket_number']) ?></td>
                <td><?= e($ticket['title']) ?></td>
                <td><span class="priority-badge <?= priority_badge_class($ticket['priority_code']) ?>"><?= e($ticket['priority_name']) ?></span></td>
                <td><span class="badge <?= ticket_badge_class($ticket['current_status']) ?>"><?= e($ticket['current_status']) ?></span></td>
                <td><?= e($ticket['support_level_name']) ?></td>
                <td><?= e($ticket['assigned_agent_name'] ?? 'Queue') ?></td>
                <td><a class="button secondary" href="/escalation-system/requester/ticket_view.php?id=<?= (int) $ticket['id'] ?>">View</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$tickets): ?>
            <tr><td colspan="7" class="empty-state">No tickets found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
