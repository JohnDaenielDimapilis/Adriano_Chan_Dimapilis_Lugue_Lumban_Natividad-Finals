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
    $pdo->prepare("UPDATE tickets SET current_status = 'Closed', closed_at = :closed_at, last_updated_at = :updated_at WHERE id = :id")
        ->execute(['closed_at' => now(), 'updated_at' => now(), 'id' => $ticket['id']]);
    add_ticket_update($pdo, (int) $ticket['id'], (int) $user['id'], 'Closed', 'Ticket closed after confirmation.');
    audit_log($pdo, (int) $user['id'], 'TICKET_CLOSED', (int) $ticket['id'], 'Resolved', 'Closed');
    create_notification($pdo, (int) $ticket['requester_id'], (int) $ticket['id'], 'Ticket closed', "{$ticket['ticket_number']} has been formally closed.", 'ticket_closed');
    flash('success', 'Ticket closed.');
    $target = $user['role_code'] === 'REQUESTER' ? '/escalation-system/requester/ticket_view.php?id=' : '/escalation-system/tickets/ticket_view.php?id=';
    header('Location: ' . $target . (int) $ticket['id']);
    exit;
}

$pageTitle = 'Close Ticket';
$pageSubtitle = 'Confirm the resolution and archive the ticket as completed.';
include __DIR__ . '/../includes/header.php';
?>
<section class="form-card">
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <p>Close ticket <strong><?= e($ticket['ticket_number']) ?></strong>?</p>
        <button type="submit">Close Ticket</button>
    </form>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
