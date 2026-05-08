<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_role('ADMIN');
$pdo = getPDO();

$auditTrails = $pdo->query("
    SELECT at.*, u.full_name
    FROM audit_trails at
    LEFT JOIN users u ON u.id = at.user_id
    ORDER BY at.created_at DESC
    LIMIT 25
")->fetchAll();
$escalations = $pdo->query("
    SELECT eh.*, t.ticket_number, fl.name AS from_level_name, tl.name AS to_level_name
    FROM escalation_history eh
    INNER JOIN tickets t ON t.id = eh.ticket_id
    LEFT JOIN support_levels fl ON fl.id = eh.from_level_id
    INNER JOIN support_levels tl ON tl.id = eh.to_level_id
    ORDER BY eh.created_at DESC
    LIMIT 25
")->fetchAll();

$pageTitle = 'Reports & Audit';
$pageSubtitle = 'Review escalation history, accountability trails, and recent operational events.';
include __DIR__ . '/../includes/header.php';
?>
<section class="panel">
    <h3>Recent Escalations</h3>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Ticket</th><th>From</th><th>To</th><th>Reason</th><th>When</th></tr></thead>
            <tbody>
            <?php foreach ($escalations as $record): ?>
                <tr>
                    <td><?= e($record['ticket_number']) ?></td>
                    <td><?= e($record['from_level_name'] ?? 'Initial') ?></td>
                    <td><?= e($record['to_level_name']) ?></td>
                    <td><?= e($record['escalation_reason']) ?></td>
                    <td><?= e($record['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<section class="panel">
    <h3>Audit Trail</h3>
    <div class="table-wrap">
        <table>
            <thead><tr><th>User</th><th>Action</th><th>Ticket</th><th>Details</th><th>IP / Time</th></tr></thead>
            <tbody>
            <?php foreach ($auditTrails as $record): ?>
                <tr>
                    <td><?= e($record['full_name'] ?? 'System') ?></td>
                    <td><?= e($record['action_type']) ?></td>
                    <td><?= e((string) ($record['ticket_id'] ?? '-')) ?></td>
                    <td><?= e($record['new_value'] ?? '') ?></td>
                    <td><?= e(($record['ip_address'] ?? '-') . ' / ' . $record['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
