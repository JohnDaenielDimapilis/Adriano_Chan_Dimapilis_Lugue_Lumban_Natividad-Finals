<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
$user = require_role(['LEVEL1', 'LEVEL2', 'LEVEL3']);
$tickets = fetch_tickets(getPDO(), ['support_level_id' => (int) $user['support_level_id']]);

$pageTitle = 'Assigned Tickets';
$pageSubtitle = 'Work your current support queue, update progress, resolve, or escalate as needed.';
include __DIR__ . '/../includes/header.php';
?>
<section class="table-wrap">
    <table>
        <thead>
        <tr>
            <th>Ticket</th>
            <th>Requester</th>
            <th>Priority</th>
            <th>Status</th>
            <th>Assigned</th>
            <th>SLA</th>
            <th>Action</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($tickets as $ticket): ?>
            <tr>
                <td><?= e($ticket['ticket_number']) ?><br><span class="muted"><?= e($ticket['title']) ?></span></td>
                <td><?= e($ticket['requester_name']) ?></td>
                <td><span class="priority-badge <?= priority_badge_class($ticket['priority_code']) ?>"><?= e($ticket['priority_name']) ?></span></td>
                <td><span class="badge <?= ticket_badge_class($ticket['current_status']) ?>"><?= e($ticket['current_status']) ?></span></td>
                <td><?= e($ticket['assigned_agent_name'] ?? 'Queue') ?></td>
                <td><?= e(get_sla_status($ticket)) ?></td>
                <td><a class="button secondary" href="<?= e(url('/agent/ticket_view.php?id=' . (int) $ticket['id'])) ?>">Manage</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$tickets): ?>
            <tr><td colspan="7" class="empty-state">No tickets in the current level queue.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
