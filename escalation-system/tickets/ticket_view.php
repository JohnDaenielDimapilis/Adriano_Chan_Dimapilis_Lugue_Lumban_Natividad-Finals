<?php
declare(strict_types=1);

if (!isset($ticket)) {
    require_once __DIR__ . '/../config/auth.php';
    $user = require_login();
    $ticket = fetch_ticket(getPDO(), (int) ($_GET['id'] ?? 0));
    if (!$ticket || !can_view_ticket($user, $ticket)) {
        http_response_code(404);
        exit('Ticket not found.');
    }
    $updates = fetch_ticket_updates(getPDO(), (int) $ticket['id']);
    $resolutionLog = fetch_resolution_log(getPDO(), (int) $ticket['id']);
    $pageTitle = 'Ticket View';
    $pageSubtitle = 'Complete incident record with SLA targets, activity timeline, and resolution evidence.';
    include __DIR__ . '/../includes/header.php';
}

$escalations = fetch_escalation_history(getPDO(), (int) $ticket['id']);
?>
<section class="cards">
    <div class="card"><h3>Priority Level</h3><div class="kpi"><?= e($ticket['priority_name']) ?></div></div>
    <div class="card"><h3>Problem Intensity</h3><div class="kpi"><?= e($ticket['problem_intensity']) ?></div></div>
    <div class="card"><h3>Affected User %</h3><div class="kpi"><?= e((string) $ticket['affected_user_percentage']) ?>%</div></div>
    <div class="card"><h3>SLA Status</h3><div class="kpi"><?= e(get_sla_status($ticket)) ?></div></div>
</section>

<section class="panel">
    <h3><?= e($ticket['ticket_number']) ?>: <?= e($ticket['title']) ?></h3>
    <div class="grid-3">
        <div class="stat-chip">Status: <span class="badge <?= ticket_badge_class($ticket['current_status']) ?>"><?= e($ticket['current_status']) ?></span></div>
        <div class="stat-chip">Current Support Level: <?= e($ticket['support_level_name']) ?></div>
        <div class="stat-chip">Assigned Agent: <?= e($ticket['assigned_agent_name'] ?? 'Queue') ?></div>
        <div class="stat-chip">Type of Users Affected: <?= e($ticket['affected_user_type']) ?></div>
        <div class="stat-chip">Number of Users Affected: <?= (int) $ticket['number_of_users_affected'] ?></div>
        <div class="stat-chip">Total Number of Users: <?= (int) $ticket['total_number_of_users'] ?></div>
        <div class="stat-chip">Business Impact: <?= e($ticket['business_impact']) ?></div>
        <div class="stat-chip">First Response Deadline: <?= e($ticket['response_due_at']) ?></div>
        <div class="stat-chip">Resolution Deadline: <?= e($ticket['resolution_due_at']) ?></div>
    </div>
    <p><?= nl2br(e($ticket['description'])) ?></p>
</section>

<section class="grid-2">
    <div class="panel">
        <h3>Activity Timeline</h3>
        <div class="timeline">
            <?php foreach ($updates as $update): ?>
                <div class="timeline-item">
                    <strong><?= e($update['update_type']) ?></strong>
                    <p><?= e($update['message']) ?></p>
                    <small><?= e($update['full_name']) ?> | <?= e($update['created_at']) ?></small>
                </div>
            <?php endforeach; ?>
            <?php if (!$updates): ?>
                <p class="empty-state">No updates yet.</p>
            <?php endif; ?>
        </div>
    </div>
    <div class="panel">
        <h3>Escalation History</h3>
        <div class="timeline">
            <?php foreach ($escalations as $escalation): ?>
                <div class="timeline-item">
                    <strong><?= e($escalation['from_level_name'] ?? 'Initial Queue') ?> to <?= e($escalation['to_level_name']) ?></strong>
                    <p><?= e($escalation['escalation_reason']) ?></p>
                    <small><?= e($escalation['escalated_by_name']) ?> | <?= e($escalation['created_at']) ?></small>
                </div>
            <?php endforeach; ?>
            <?php if (!$escalations): ?>
                <p class="empty-state">No escalation history for this ticket.</p>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php if ($resolutionLog): ?>
    <section class="panel">
        <h3>Resolution Log</h3>
        <div class="grid-2">
            <div><strong>Root Cause</strong><p><?= nl2br(e($resolutionLog['root_cause'])) ?></p></div>
            <div><strong>Action Taken</strong><p><?= nl2br(e($resolutionLog['action_taken'])) ?></p></div>
            <div><strong>Solution Applied</strong><p><?= nl2br(e($resolutionLog['solution_applied'])) ?></p></div>
            <div><strong>Resolution Notes</strong><p><?= nl2br(e($resolutionLog['resolution_notes'])) ?></p></div>
        </div>
        <p class="muted">Resolved by <?= e($resolutionLog['resolved_by_name']) ?> at <?= e($resolutionLog['resolved_level_name']) ?> on <?= e($resolutionLog['resolved_at']) ?></p>
    </section>
<?php endif; ?>

<?php if (isset($user)): ?>
    <section class="panel">
        <div class="stat-strip">
            <?php if ($user['role_code'] === 'ADMIN'): ?>
                <a class="button" href="/escalation-system/tickets/assign_ticket.php?id=<?= (int) $ticket['id'] ?>">Assign / Reassign</a>
            <?php endif; ?>
            <?php if (in_array($user['role_code'], ['LEVEL1', 'LEVEL2', 'LEVEL3', 'ADMIN'], true)): ?>
                <a class="button secondary" href="/escalation-system/tickets/update_ticket.php?id=<?= (int) $ticket['id'] ?>">Add Update</a>
                <a class="button secondary" href="/escalation-system/tickets/resolve_ticket.php?id=<?= (int) $ticket['id'] ?>">Resolve</a>
                <a class="button secondary" href="/escalation-system/tickets/escalate_ticket.php?id=<?= (int) $ticket['id'] ?>">Escalate</a>
            <?php endif; ?>
            <?php if ($user['role_code'] === 'REQUESTER' && $ticket['current_status'] === 'Resolved'): ?>
                <a class="button secondary" href="/escalation-system/tickets/close_ticket.php?id=<?= (int) $ticket['id'] ?>">Confirm & Close</a>
                <a class="button danger" href="/escalation-system/tickets/reopen_ticket.php?id=<?= (int) $ticket['id'] ?>">Reopen</a>
            <?php endif; ?>
        </div>
    </section>
    <?php include __DIR__ . '/../includes/footer.php'; ?>
<?php endif; ?>
