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
    $rootCause = trim($_POST['root_cause'] ?? '');
    $actionTaken = trim($_POST['action_taken'] ?? '');
    $solutionApplied = trim($_POST['solution_applied'] ?? '');
    $resolutionNotes = trim($_POST['resolution_notes'] ?? '');

    if ($rootCause === '' || $actionTaken === '' || $solutionApplied === '' || $resolutionNotes === '') {
        flash('error', 'All resolution log fields are required.');
        header('Location: /escalation-system/tickets/resolve_ticket.php?id=' . (int) $ticket['id']);
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO resolution_logs (ticket_id, root_cause, action_taken, solution_applied, resolution_notes, resolved_at, resolved_by, resolved_level_id)
        VALUES (:ticket_id, :root_cause, :action_taken, :solution_applied, :resolution_notes, :resolved_at, :resolved_by, :resolved_level_id)
        ON DUPLICATE KEY UPDATE
            root_cause = VALUES(root_cause),
            action_taken = VALUES(action_taken),
            solution_applied = VALUES(solution_applied),
            resolution_notes = VALUES(resolution_notes),
            resolved_at = VALUES(resolved_at),
            resolved_by = VALUES(resolved_by),
            resolved_level_id = VALUES(resolved_level_id)
    ");
    $stmt->execute([
        'ticket_id' => $ticket['id'],
        'root_cause' => $rootCause,
        'action_taken' => $actionTaken,
        'solution_applied' => $solutionApplied,
        'resolution_notes' => $resolutionNotes,
        'resolved_at' => now(),
        'resolved_by' => $user['id'],
        'resolved_level_id' => $ticket['current_level_id'],
    ]);

    $pdo->prepare("
        UPDATE tickets
        SET current_status = 'Resolved',
            resolved_at = :resolved_at,
            last_updated_at = :updated_at
        WHERE id = :id
    ")->execute([
        'resolved_at' => now(),
        'updated_at' => now(),
        'id' => $ticket['id'],
    ]);

    add_ticket_update($pdo, (int) $ticket['id'], (int) $user['id'], 'Resolved', $resolutionNotes);
    audit_log($pdo, (int) $user['id'], 'TICKET_RESOLVED', (int) $ticket['id'], $ticket['current_status'], 'Resolved');
    create_notification($pdo, (int) $ticket['requester_id'], (int) $ticket['id'], 'Ticket resolved', "{$ticket['ticket_number']} has been resolved and is waiting for your confirmation.", 'ticket_resolved');

    flash('success', 'Ticket resolved and resolution log saved.');
    header('Location: /escalation-system/agent/ticket_view.php?id=' . (int) $ticket['id']);
    exit;
}

$pageTitle = 'Resolve Ticket';
$pageSubtitle = 'Complete the formal resolution log before moving the ticket into resolved status.';
include __DIR__ . '/../includes/header.php';
?>
<section class="form-card">
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <label>Root Cause<textarea name="root_cause" required></textarea></label>
        <label>Action Taken<textarea name="action_taken" required></textarea></label>
        <label>Solution Applied<textarea name="solution_applied" required></textarea></label>
        <label>Resolution Notes<textarea name="resolution_notes" required></textarea></label>
        <button type="submit">Mark as Resolved</button>
    </form>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
