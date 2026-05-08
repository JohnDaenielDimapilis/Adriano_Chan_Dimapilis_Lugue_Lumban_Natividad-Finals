<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
$user = require_role(['LEVEL1', 'LEVEL2', 'LEVEL3']);
$ticket = fetch_ticket(getPDO(), (int) ($_GET['id'] ?? 0));
if (!$ticket || !can_view_ticket($user, $ticket)) {
    http_response_code(404);
    exit('Ticket not found.');
}

$updates = fetch_ticket_updates(getPDO(), (int) $ticket['id']);
$resolutionLog = fetch_resolution_log(getPDO(), (int) $ticket['id']);
$pageTitle = 'Manage Ticket';
$pageSubtitle = 'Update the timeline, keep SLA healthy, and capture a complete technical resolution.';
include __DIR__ . '/../includes/header.php';
?>
<?php include __DIR__ . '/../tickets/ticket_view.php'; ?>
<section class="panel">
    <div class="stat-strip">
        <a class="button" href="/escalation-system/tickets/update_ticket.php?id=<?= (int) $ticket['id'] ?>">Add Update</a>
        <a class="button secondary" href="/escalation-system/tickets/resolve_ticket.php?id=<?= (int) $ticket['id'] ?>">Resolve</a>
        <a class="button secondary" href="/escalation-system/tickets/escalate_ticket.php?id=<?= (int) $ticket['id'] ?>">Escalate</a>
    </div>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
