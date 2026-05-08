<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
$user = require_role(['ADMIN', 'LEVEL1', 'LEVEL2', 'LEVEL3']);
$pdo = getPDO();
$ticket = fetch_ticket($pdo, (int) ($_GET['id'] ?? 0));
if (!$ticket || !can_view_ticket($user, $ticket)) {
    http_response_code(404);
    exit('Ticket not found.');
}

$currentLevelCode = $ticket['support_level_code'];
$nextLevelCode = match ($currentLevelCode) {
    'LEVEL1' => 'LEVEL2',
    default => 'LEVEL3',
};
$nextLevel = get_level_by_code($pdo, $nextLevelCode);
$agent = get_available_agent($pdo, (int) $nextLevel['id'], (int) $ticket['category_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $reason = trim($_POST['reason'] ?? '');
    if ($reason === '') {
        flash('error', 'Escalation reason is required.');
        header('Location: ' . url('/tickets/escalate_ticket.php?id=' . (int) $ticket['id']));
        exit;
    }

    assign_ticket($pdo, (int) $ticket['id'], (int) $nextLevel['id'], $agent['id'] ?? null, (int) $user['id'], $reason);
    $status = $nextLevelCode === 'LEVEL2' ? 'Escalated to Level 2' : 'Escalated to Level 3';
    $pdo->prepare("UPDATE tickets SET current_status = :status, last_updated_at = :updated WHERE id = :id")
        ->execute(['status' => $status, 'updated' => now(), 'id' => $ticket['id']]);
    create_escalation_record($pdo, (int) $ticket['id'], (int) $ticket['current_level_id'], (int) $nextLevel['id'], $ticket['assigned_agent_id'] ? (int) $ticket['assigned_agent_id'] : null, $agent['id'] ?? null, $reason, (int) $user['id']);
    add_ticket_update($pdo, (int) $ticket['id'], (int) $user['id'], 'Escalation', $reason);
    audit_log($pdo, (int) $user['id'], 'TICKET_ESCALATED', (int) $ticket['id'], $currentLevelCode, $nextLevelCode);
    create_notification($pdo, (int) $ticket['requester_id'], (int) $ticket['id'], 'Ticket escalated', "{$ticket['ticket_number']} was escalated to {$nextLevel['name']}.", 'ticket_escalated');
    if (!empty($agent['id'])) {
        create_notification($pdo, (int) $agent['id'], (int) $ticket['id'], 'Escalated ticket assigned', "{$ticket['ticket_number']} has been escalated to you.", 'ticket_escalated');
    }
    foreach (get_admin_user_ids($pdo) as $adminId) {
        create_notification($pdo, $adminId, (int) $ticket['id'], 'Manual escalation', "{$ticket['ticket_number']} was escalated to {$nextLevel['name']}.", 'ticket_escalated');
    }

    flash('success', 'Ticket escalated.');
    header('Location: ' . url('/agent/ticket_view.php?id=' . (int) $ticket['id']));
    exit;
}

$pageTitle = 'Escalate Ticket';
$pageSubtitle = 'Move the ticket to the next support tier with a documented technical reason.';
include __DIR__ . '/../includes/header.php';
?>
<section class="form-card">
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <p>Current Level: <strong><?= e($ticket['support_level_name']) ?></strong></p>
        <p>Next Level: <strong><?= e($nextLevel['name']) ?></strong></p>
        <label>Escalation Reason<textarea name="reason" required></textarea></label>
        <button type="submit">Escalate Ticket</button>
    </form>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
