<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
$user = require_role('REQUESTER');
$ticket = fetch_ticket(getPDO(), (int) ($_GET['id'] ?? 0));
if (!$ticket || !can_view_ticket($user, $ticket)) {
    http_response_code(404);
    exit('Ticket not found.');
}

$updates = fetch_ticket_updates(getPDO(), (int) $ticket['id']);
$resolutionLog = fetch_resolution_log(getPDO(), (int) $ticket['id']);
$pageTitle = 'Ticket Details';
$pageSubtitle = 'Track the full history, escalation path, and final resolution details.';
include __DIR__ . '/../includes/header.php';
?>
<?php include __DIR__ . '/../tickets/ticket_view.php'; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
