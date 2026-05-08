<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = getPDO();
$systemUserId = 1;
$processed = ['warnings' => 0, 'escalations' => 0];

$tickets = open_ticket_query($pdo)->fetchAll();
foreach ($tickets as $ticket) {
    $slaStatus = get_sla_status($ticket);
    $ticketId = (int) $ticket['id'];

    if ($slaStatus === 'At Risk') {
        $pdo->prepare("UPDATE tickets SET current_status = 'At Risk', last_updated_at = :updated_at WHERE id = :id")
            ->execute(['updated_at' => now(), 'id' => $ticketId]);
        if (!empty($ticket['assigned_agent_id'])) {
            create_notification($pdo, (int) $ticket['assigned_agent_id'], $ticketId, 'SLA warning', "{$ticket['ticket_number']} is nearing SLA breach.", 'sla_warning');
        }
        foreach (get_admin_user_ids($pdo) as $adminId) {
            create_notification($pdo, $adminId, $ticketId, 'SLA warning', "{$ticket['ticket_number']} is nearing SLA breach.", 'sla_warning');
        }
        audit_log($pdo, $systemUserId, 'SLA_WARNING', $ticketId, null, 'Ticket nearing breach');
        $processed['warnings']++;
        continue;
    }

    if ($slaStatus !== 'Breached') {
        continue;
    }

    $targetLevelCode = match (true) {
        $ticket['priority_code'] === 'P1' || (float) $ticket['affected_user_percentage'] >= 50 => 'LEVEL3',
        $ticket['priority_code'] === 'P2' => 'LEVEL2',
        default => 'LEVEL2',
    };

    $targetLevel = get_level_by_code($pdo, $targetLevelCode);
    if (!$targetLevel) {
        continue;
    }

    $agent = get_available_agent($pdo, (int) $targetLevel['id'], (int) $ticket['category_id']);
    assign_ticket($pdo, $ticketId, (int) $targetLevel['id'], $agent['id'] ?? null, $systemUserId, 'Automatic SLA escalation');
    $newStatus = $targetLevelCode === 'LEVEL3' ? 'Escalated to Level 3' : 'Escalated to Level 2';
    $pdo->prepare("UPDATE tickets SET current_status = :current_status, last_updated_at = :updated_at WHERE id = :id")
        ->execute(['current_status' => $newStatus, 'updated_at' => now(), 'id' => $ticketId]);

    create_escalation_record($pdo, $ticketId, $ticket['current_level_id'] ? (int) $ticket['current_level_id'] : null, (int) $targetLevel['id'], $ticket['assigned_agent_id'] ? (int) $ticket['assigned_agent_id'] : null, $agent['id'] ?? null, 'Auto escalation due to SLA breach', $systemUserId);
    add_ticket_update($pdo, $ticketId, $systemUserId, 'Auto Escalation', 'Ticket auto-escalated after SLA breach.');
    audit_log($pdo, $systemUserId, 'SLA_BREACHED', $ticketId, $ticket['current_status'], $newStatus);

    create_notification($pdo, (int) $ticket['requester_id'], $ticketId, 'Ticket escalated', "{$ticket['ticket_number']} was escalated after SLA breach.", 'ticket_escalated');
    if (!empty($agent['id'])) {
        create_notification($pdo, (int) $agent['id'], $ticketId, 'Escalated ticket assigned', "{$ticket['ticket_number']} was auto-escalated to you.", 'ticket_escalated');
    }
    foreach (get_admin_user_ids($pdo) as $adminId) {
        create_notification($pdo, $adminId, $ticketId, 'SLA breached', "{$ticket['ticket_number']} breached SLA and was escalated.", 'sla_breached');
    }
    $processed['escalations']++;
}

echo json_encode([
    'ran_at' => now(),
    'warnings_created' => $processed['warnings'],
    'escalations_processed' => $processed['escalations'],
], JSON_PRETTY_PRINT);
