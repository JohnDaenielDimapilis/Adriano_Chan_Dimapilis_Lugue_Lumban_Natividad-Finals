<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
$user = require_role('ADMIN');
$pdo = getPDO();
$ticket = fetch_ticket($pdo, (int) ($_GET['id'] ?? 0));
if (!$ticket) {
    http_response_code(404);
    exit('Ticket not found.');
}

$levels = get_support_levels($pdo);
$agents = $pdo->query("
    SELECT u.id, u.full_name, sl.id AS support_level_id, sl.name AS level_name
    FROM agent_profiles ap
    INNER JOIN users u ON u.id = ap.user_id
    INNER JOIN support_levels sl ON sl.id = ap.support_level_id
    WHERE ap.is_active = 1 AND u.is_active = 1
    ORDER BY sl.level_order, u.full_name
")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $levelId = (int) $_POST['support_level_id'];
    $agentId = (int) $_POST['assigned_agent_id'];
    $reason = trim($_POST['assignment_reason'] ?? 'Manual assignment');
    assign_ticket($pdo, (int) $ticket['id'], $levelId, $agentId ?: null, (int) $user['id'], $reason);
    add_ticket_update($pdo, (int) $ticket['id'], (int) $user['id'], 'Assignment', $reason);
    audit_log($pdo, (int) $user['id'], 'TICKET_ASSIGNED', (int) $ticket['id'], null, $reason);
    if ($agentId) {
        create_notification($pdo, $agentId, (int) $ticket['id'], 'Ticket assigned', "{$ticket['ticket_number']} has been assigned to you.", 'ticket_assigned');
    }
    flash('success', 'Ticket assignment updated.');
    header('Location: /escalation-system/tickets/ticket_view.php?id=' . (int) $ticket['id']);
    exit;
}

$pageTitle = 'Assign Ticket';
$pageSubtitle = 'Manually assign or reassign a ticket to the right support queue or named agent.';
include __DIR__ . '/../includes/header.php';
?>
<section class="form-card">
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <div class="grid-2">
            <label>Support Level
                <select name="support_level_id" required>
                    <?php foreach ($levels as $level): ?>
                        <option value="<?= (int) $level['id'] ?>" <?= (int) $ticket['current_level_id'] === (int) $level['id'] ? 'selected' : '' ?>><?= e($level['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Assigned Agent
                <select name="assigned_agent_id">
                    <option value="0">Queue only</option>
                    <?php foreach ($agents as $agent): ?>
                        <option value="<?= (int) $agent['id'] ?>" <?= (int) $ticket['assigned_agent_id'] === (int) $agent['id'] ? 'selected' : '' ?>><?= e($agent['full_name'] . ' - ' . $agent['level_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <label>Reason<textarea name="assignment_reason" required></textarea></label>
        <button type="submit">Save Assignment</button>
    </form>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
