<?php
declare(strict_types=1);

date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'Asia/Shanghai');

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function app_base_path(): string
{
    $configured = rtrim((string) getenv('APP_BASE_PATH'), '/');
    if ($configured !== '') {
        return $configured;
    }

    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    return str_contains($scriptName, '/escalation-system/') ? '/escalation-system' : '';
}

function url(string $path = ''): string
{
    $path = '/' . ltrim($path, '/');
    return app_base_path() . ($path === '/' ? '' : $path);
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }

    $value = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $value;
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? 'CLI';
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function calculate_affected_percentage(int $affected, int $total): float
{
    $total = max($total, 1);
    return round(($affected / $total) * 100, 2);
}

function determine_priority_code(string $intensity, string $impact, float $percentage): string
{
    if (
        $percentage >= 50 ||
        in_array($impact, ['System-wide outage', 'Security or data risk', 'Major operation affected'], true) ||
        $intensity === 'Critical'
    ) {
        return 'P1';
    }

    if (
        ($percentage >= 10 && $percentage < 50) ||
        $impact === 'Department-level disruption' ||
        in_array($intensity, ['High', 'Medium'], true)
    ) {
        return 'P2';
    }

    return 'P3';
}

function get_priorities(PDO $pdo): array
{
    return $pdo->query("SELECT * FROM priorities ORDER BY sort_order")->fetchAll();
}

function get_categories(PDO $pdo): array
{
    return $pdo->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY name")->fetchAll();
}

function get_support_levels(PDO $pdo): array
{
    return $pdo->query("SELECT * FROM support_levels ORDER BY level_order")->fetchAll();
}

function get_priority_by_code(PDO $pdo, string $code): array
{
    $stmt = $pdo->prepare("SELECT * FROM priorities WHERE code = :code LIMIT 1");
    $stmt->execute(['code' => $code]);
    return $stmt->fetch() ?: [];
}

function get_level_by_code(PDO $pdo, string $code): array
{
    $stmt = $pdo->prepare("SELECT * FROM support_levels WHERE code = :code LIMIT 1");
    $stmt->execute(['code' => $code]);
    return $stmt->fetch() ?: [];
}

function get_sla_rule(PDO $pdo, int $priorityId): array
{
    $stmt = $pdo->prepare("SELECT * FROM sla_rules WHERE priority_id = :priority_id AND is_active = 1 LIMIT 1");
    $stmt->execute(['priority_id' => $priorityId]);
    return $stmt->fetch() ?: [];
}

function add_minutes(string $datetime, int $minutes): string
{
    $dt = new DateTime($datetime);
    $dt->modify(sprintf('+%d minutes', $minutes));
    return $dt->format('Y-m-d H:i:s');
}

function generate_ticket_number(PDO $pdo): string
{
    do {
        $ticketNumber = 'EMS-' . date('Ymd') . '-' . random_int(1000, 9999);
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE ticket_number = :ticket_number");
        $stmt->execute(['ticket_number' => $ticketNumber]);
    } while ((int) $stmt->fetchColumn() > 0);

    return $ticketNumber;
}

function create_notification(PDO $pdo, int $recipientId, ?int $ticketId, string $title, string $message, string $type): void
{
    $stmt = $pdo->prepare("
        INSERT INTO notifications (recipient_user_id, ticket_id, title, message, notification_type, is_read, created_at)
        VALUES (:recipient_user_id, :ticket_id, :title, :message, :notification_type, 0, :created_at)
    ");
    $stmt->execute([
        'recipient_user_id' => $recipientId,
        'ticket_id' => $ticketId,
        'title' => $title,
        'message' => $message,
        'notification_type' => $type,
        'created_at' => now(),
    ]);
}

function audit_log(PDO $pdo, ?int $userId, string $actionType, ?int $ticketId, ?string $oldValue, ?string $newValue): void
{
    $stmt = $pdo->prepare("
        INSERT INTO audit_trails (user_id, action_type, ticket_id, old_value, new_value, ip_address, created_at)
        VALUES (:user_id, :action_type, :ticket_id, :old_value, :new_value, :ip_address, :created_at)
    ");
    $stmt->execute([
        'user_id' => $userId,
        'action_type' => $actionType,
        'ticket_id' => $ticketId,
        'old_value' => $oldValue,
        'new_value' => $newValue,
        'ip_address' => client_ip(),
        'created_at' => now(),
    ]);
}

function add_ticket_update(PDO $pdo, int $ticketId, int $userId, string $updateType, string $message): void
{
    $stmt = $pdo->prepare("
        INSERT INTO ticket_updates (ticket_id, user_id, update_type, message, created_at)
        VALUES (:ticket_id, :user_id, :update_type, :message, :created_at)
    ");
    $stmt->execute([
        'ticket_id' => $ticketId,
        'user_id' => $userId,
        'update_type' => $updateType,
        'message' => $message,
        'created_at' => now(),
    ]);
}

function get_available_agent(PDO $pdo, int $supportLevelId, ?int $categoryId = null): ?array
{
    $sql = "
        SELECT u.id, u.full_name, ap.skills, ap.agent_status
        FROM agent_profiles ap
        INNER JOIN users u ON u.id = ap.user_id
        WHERE ap.support_level_id = :support_level_id
          AND u.is_active = 1
          AND ap.is_active = 1
          AND ap.agent_status IN ('Available', 'Busy')
        ORDER BY (ap.agent_status = 'Available') DESC, ap.updated_at ASC, u.full_name ASC
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['support_level_id' => $supportLevelId]);
    $agent = $stmt->fetch();

    return $agent ?: null;
}

function assign_ticket(PDO $pdo, int $ticketId, int $levelId, ?int $agentId, int $assignedBy, string $reason = 'Automatic assignment'): void
{
    $stmt = $pdo->prepare("
        UPDATE tickets
        SET current_level_id = :current_level_id,
            assigned_agent_id = :assigned_agent_id,
            current_status = :current_status,
            last_updated_at = :last_updated_at
        WHERE id = :id
    ");
    $stmt->execute([
        'current_level_id' => $levelId,
        'assigned_agent_id' => $agentId,
        'current_status' => $agentId ? 'Assigned' : 'Open',
        'last_updated_at' => now(),
        'id' => $ticketId,
    ]);

    $stmt = $pdo->prepare("
        INSERT INTO ticket_assignments (ticket_id, support_level_id, assigned_agent_id, assigned_by, assignment_reason, created_at)
        VALUES (:ticket_id, :support_level_id, :assigned_agent_id, :assigned_by, :assignment_reason, :created_at)
    ");
    $stmt->execute([
        'ticket_id' => $ticketId,
        'support_level_id' => $levelId,
        'assigned_agent_id' => $agentId,
        'assigned_by' => $assignedBy,
        'assignment_reason' => $reason,
        'created_at' => now(),
    ]);
}

function get_admin_user_ids(PDO $pdo): array
{
    $stmt = $pdo->query("
        SELECT u.id
        FROM users u
        INNER JOIN roles r ON r.id = u.role_id
        WHERE r.code = 'ADMIN' AND u.is_active = 1
    ");
    return array_map('intval', array_column($stmt->fetchAll(), 'id'));
}

function initial_level_for_priority(string $priorityCode): string
{
    return match ($priorityCode) {
        'P1' => 'LEVEL3',
        'P2' => 'LEVEL2',
        default => 'LEVEL1',
    };
}

function get_sla_status(array $ticket): string
{
    if (in_array($ticket['current_status'], ['Resolved', 'Closed'], true)) {
        return 'Resolved';
    }

    $now = time();
    $responseDue = strtotime((string) ($ticket['response_due_at'] ?? ''));
    $resolutionDue = strtotime((string) ($ticket['resolution_due_at'] ?? ''));

    if (($resolutionDue && $now > $resolutionDue) || ($responseDue && $now > $responseDue && empty($ticket['first_response_at']))) {
        return 'Breached';
    }

    $riskWindow = 1800;
    if (($resolutionDue && ($resolutionDue - $now) <= $riskWindow) || ($responseDue && ($responseDue - $now) <= $riskWindow && empty($ticket['first_response_at']))) {
        return 'At Risk';
    }

    return 'On Time';
}

function can_view_ticket(array $user, array $ticket): bool
{
    if ($user['role_code'] === 'ADMIN') {
        return true;
    }

    if ($user['role_code'] === 'REQUESTER') {
        return (int) $ticket['requester_id'] === (int) $user['id'];
    }

    return (int) $ticket['assigned_agent_id'] === (int) $user['id'] || (int) $ticket['current_level_id'] === (int) ($user['support_level_id'] ?? 0);
}

function fetch_ticket(PDO $pdo, int $ticketId): ?array
{
    $stmt = $pdo->prepare("
        SELECT t.*, c.name AS category_name, p.name AS priority_name, p.code AS priority_code,
               sl.name AS support_level_name, sl.code AS support_level_code,
               r.full_name AS requester_name, a.full_name AS assigned_agent_name
        FROM tickets t
        INNER JOIN categories c ON c.id = t.category_id
        INNER JOIN priorities p ON p.id = t.priority_id
        INNER JOIN support_levels sl ON sl.id = t.current_level_id
        INNER JOIN users r ON r.id = t.requester_id
        LEFT JOIN users a ON a.id = t.assigned_agent_id
        WHERE t.id = :id
        LIMIT 1
    ");
    $stmt->execute(['id' => $ticketId]);
    $ticket = $stmt->fetch();

    return $ticket ?: null;
}

function ticket_badge_class(string $status): string
{
    return match ($status) {
        'Resolved' => 'badge-success',
        'Closed' => 'badge-secondary',
        'Escalated to Level 2', 'Escalated to Level 3' => 'badge-danger',
        'At Risk' => 'badge-warning',
        'SLA Breached' => 'badge-danger',
        'In Progress' => 'badge-primary',
        default => 'badge-info',
    };
}

function priority_badge_class(string $priorityCode): string
{
    return match ($priorityCode) {
        'P1' => 'priority-p1',
        'P2' => 'priority-p2',
        default => 'priority-p3',
    };
}

function create_escalation_record(PDO $pdo, int $ticketId, ?int $fromLevelId, int $toLevelId, ?int $fromAgentId, ?int $toAgentId, string $reason, int $performedBy): void
{
    $stmt = $pdo->prepare("
        INSERT INTO escalation_history (
            ticket_id, from_level_id, to_level_id, from_agent_id, to_agent_id,
            escalation_reason, escalated_by, created_at
        ) VALUES (
            :ticket_id, :from_level_id, :to_level_id, :from_agent_id, :to_agent_id,
            :escalation_reason, :escalated_by, :created_at
        )
    ");
    $stmt->execute([
        'ticket_id' => $ticketId,
        'from_level_id' => $fromLevelId,
        'to_level_id' => $toLevelId,
        'from_agent_id' => $fromAgentId,
        'to_agent_id' => $toAgentId,
        'escalation_reason' => $reason,
        'escalated_by' => $performedBy,
        'created_at' => now(),
    ]);
}

function fetch_notifications(PDO $pdo, int $userId, int $limit = 8): array
{
    $stmt = $pdo->prepare("
        SELECT * FROM notifications
        WHERE recipient_user_id = :recipient_user_id
        ORDER BY created_at DESC
        LIMIT {$limit}
    ");
    $stmt->execute(['recipient_user_id' => $userId]);
    return $stmt->fetchAll();
}

function unread_notification_count(PDO $pdo, int $userId): int
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE recipient_user_id = :id AND is_read = 0");
    $stmt->execute(['id' => $userId]);
    return (int) $stmt->fetchColumn();
}

function fetch_ticket_updates(PDO $pdo, int $ticketId): array
{
    $stmt = $pdo->prepare("
        SELECT tu.*, u.full_name
        FROM ticket_updates tu
        INNER JOIN users u ON u.id = tu.user_id
        WHERE tu.ticket_id = :ticket_id
        ORDER BY tu.created_at DESC
    ");
    $stmt->execute(['ticket_id' => $ticketId]);
    return $stmt->fetchAll();
}

function fetch_resolution_log(PDO $pdo, int $ticketId): ?array
{
    $stmt = $pdo->prepare("
        SELECT rl.*, u.full_name AS resolved_by_name, sl.name AS resolved_level_name
        FROM resolution_logs rl
        INNER JOIN users u ON u.id = rl.resolved_by
        INNER JOIN support_levels sl ON sl.id = rl.resolved_level_id
        WHERE rl.ticket_id = :ticket_id
        LIMIT 1
    ");
    $stmt->execute(['ticket_id' => $ticketId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function fetch_escalation_history(PDO $pdo, int $ticketId): array
{
    $stmt = $pdo->prepare("
        SELECT eh.*, fl.name AS from_level_name, tl.name AS to_level_name,
               fu.full_name AS from_agent_name, tu.full_name AS to_agent_name,
               eu.full_name AS escalated_by_name
        FROM escalation_history eh
        LEFT JOIN support_levels fl ON fl.id = eh.from_level_id
        INNER JOIN support_levels tl ON tl.id = eh.to_level_id
        LEFT JOIN users fu ON fu.id = eh.from_agent_id
        LEFT JOIN users tu ON tu.id = eh.to_agent_id
        INNER JOIN users eu ON eu.id = eh.escalated_by
        WHERE eh.ticket_id = :ticket_id
        ORDER BY eh.created_at DESC
    ");
    $stmt->execute(['ticket_id' => $ticketId]);
    return $stmt->fetchAll();
}

function fetch_tickets(PDO $pdo, array $filters = []): array
{
    $where = ['1=1'];
    $params = [];

    if (!empty($filters['requester_id'])) {
        $where[] = 't.requester_id = :requester_id';
        $params['requester_id'] = $filters['requester_id'];
    }

    if (!empty($filters['assigned_agent_id'])) {
        $where[] = 't.assigned_agent_id = :assigned_agent_id';
        $params['assigned_agent_id'] = $filters['assigned_agent_id'];
    }

    if (!empty($filters['support_level_id'])) {
        $where[] = 't.current_level_id = :support_level_id';
        $params['support_level_id'] = $filters['support_level_id'];
    }

    if (!empty($filters['statuses']) && is_array($filters['statuses'])) {
        $placeholders = [];
        foreach ($filters['statuses'] as $index => $status) {
            $key = 'status_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $status;
        }
        $where[] = 't.current_status IN (' . implode(',', $placeholders) . ')';
    }

    $sql = "
        SELECT t.*, p.code AS priority_code, p.name AS priority_name, c.name AS category_name,
               sl.name AS support_level_name, r.full_name AS requester_name, a.full_name AS assigned_agent_name
        FROM tickets t
        INNER JOIN priorities p ON p.id = t.priority_id
        INNER JOIN categories c ON c.id = t.category_id
        INNER JOIN support_levels sl ON sl.id = t.current_level_id
        INNER JOIN users r ON r.id = t.requester_id
        LEFT JOIN users a ON a.id = t.assigned_agent_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY t.created_at DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function dashboard_counts(PDO $pdo): array
{
    $stmt = $pdo->query("
        SELECT
            COUNT(*) AS total_tickets,
            SUM(current_status = 'Open') AS open_tickets,
            SUM(current_status = 'In Progress') AS in_progress_tickets,
            SUM(current_status IN ('Escalated to Level 2', 'Escalated to Level 3')) AS escalated_tickets,
            SUM(current_status = 'SLA Breached') AS breached_tickets,
            SUM(current_status = 'Resolved') AS resolved_tickets,
            SUM(current_status = 'Closed') AS closed_tickets
        FROM tickets
    ");
    return $stmt->fetch() ?: [];
}

function average_metrics(PDO $pdo): array
{
    $stmt = $pdo->query("
        SELECT
            AVG(TIMESTAMPDIFF(MINUTE, created_at, first_response_at)) AS avg_response_minutes,
            AVG(TIMESTAMPDIFF(MINUTE, created_at, resolved_at)) AS avg_resolution_minutes
        FROM tickets
        WHERE first_response_at IS NOT NULL OR resolved_at IS NOT NULL
    ");
    return $stmt->fetch() ?: [];
}

function open_ticket_query(PDO $pdo): PDOStatement
{
    return $pdo->query("
        SELECT t.*, p.code AS priority_code, s.warning_before_minutes
        FROM tickets t
        INNER JOIN priorities p ON p.id = t.priority_id
        INNER JOIN sla_rules s ON s.priority_id = t.priority_id AND s.is_active = 1
        WHERE t.current_status IN ('Open', 'Assigned', 'In Progress', 'Pending User Response', 'At Risk', 'SLA Breached', 'Reopened', 'Escalated to Level 2', 'Escalated to Level 3')
        ORDER BY t.created_at ASC
    ");
}
