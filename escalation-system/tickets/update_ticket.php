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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $message = trim($_POST['message'] ?? '');
    $status = $_POST['current_status'] ?? $ticket['current_status'];
    if ($message === '') {
        flash('error', 'Update notes are required.');
        header('Location: /escalation-system/tickets/update_ticket.php?id=' . (int) $ticket['id']);
        exit;
    }

    $stmt = $pdo->prepare("
        UPDATE tickets
        SET current_status = :current_status,
            last_updated_at = :last_updated_at,
            first_response_at = COALESCE(first_response_at, :first_response_at)
        WHERE id = :id
    ");
    $stmt->execute([
        'current_status' => $status,
        'last_updated_at' => now(),
        'first_response_at' => now(),
        'id' => $ticket['id'],
    ]);

    add_ticket_update($pdo, (int) $ticket['id'], (int) $user['id'], 'Status Update', $message);
    audit_log($pdo, (int) $user['id'], 'TICKET_UPDATED', (int) $ticket['id'], $ticket['current_status'], $status);
    create_notification($pdo, (int) $ticket['requester_id'], (int) $ticket['id'], 'Ticket updated', "{$ticket['ticket_number']} has a new progress update.", 'ticket_updated');

    flash('success', 'Ticket updated.');
    header('Location: /escalation-system/agent/ticket_view.php?id=' . (int) $ticket['id']);
    exit;
}

$pageTitle = 'Update Ticket';
$pageSubtitle = 'Capture progress notes and keep first-response and resolution clocks accurate.';
include __DIR__ . '/../includes/header.php';
?>
<section class="form-card">
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <label>Update Message<textarea name="message" required></textarea></label>
        <label>Status
            <select name="current_status">
                <?php foreach (['Assigned', 'In Progress', 'Pending User Response', 'At Risk', 'Resolved'] as $status): ?>
                    <option value="<?= e($status) ?>" <?= $ticket['current_status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit">Save Update</button>
    </form>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
