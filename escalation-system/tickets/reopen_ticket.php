<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
$user = require_role(['REQUESTER', 'ADMIN']);
$pdo = getPDO();
$ticket = fetch_ticket($pdo, (int) ($_GET['id'] ?? 0));
if (!$ticket || !can_view_ticket($user, $ticket)) {
    http_response_code(404);
    exit('Ticket not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $reason = trim($_POST['reason'] ?? '');
    $pdo->prepare("UPDATE tickets SET current_status = 'Reopened', last_updated_at = :updated_at WHERE id = :id")
        ->execute(['updated_at' => now(), 'id' => $ticket['id']]);
    add_ticket_update($pdo, (int) $ticket['id'], (int) $user['id'], 'Reopened', $reason ?: 'Requester reopened the ticket.');
    audit_log($pdo, (int) $user['id'], 'TICKET_REOPENED', (int) $ticket['id'], 'Resolved', 'Reopened');
    if (!empty($ticket['assigned_agent_id'])) {
        create_notification($pdo, (int) $ticket['assigned_agent_id'], (int) $ticket['id'], 'Ticket reopened', "{$ticket['ticket_number']} has been reopened.", 'ticket_reopened');
    }
    flash('success', 'Ticket reopened.');
    header('Location: /escalation-system/requester/ticket_view.php?id=' . (int) $ticket['id']);
    exit;
}

$pageTitle = 'Reopen Ticket';
$pageSubtitle = 'Request more work if the resolution does not fully address the reported issue.';
include __DIR__ . '/../includes/header.php';
?>
<section class="form-card">
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <label>Reason for Reopening<textarea name="reason" required></textarea></label>
        <button type="submit">Reopen Ticket</button>
    </form>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
