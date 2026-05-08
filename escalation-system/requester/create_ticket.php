<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
$user = require_role('REQUESTER');
$pdo = getPDO();
$categories = get_categories($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $categoryId = (int) ($_POST['category_id'] ?? 0);
    $problemIntensity = $_POST['problem_intensity'] ?? 'Low';
    $affectedUserType = trim($_POST['affected_user_type'] ?? '');
    $affectedUsers = max(1, (int) ($_POST['number_of_users_affected'] ?? 1));
    $totalUsers = max(1, (int) ($_POST['total_number_of_users'] ?? 1));
    $businessImpact = $_POST['business_impact'] ?? 'Minor inconvenience';
    $manualPriority = $_POST['manual_priority'] ?? '';
    $overrideReason = trim($_POST['priority_override_reason'] ?? '');

    $percentage = calculate_affected_percentage($affectedUsers, $totalUsers);
    $priorityCode = determine_priority_code($problemIntensity, $businessImpact, $percentage);
    $priorityOverrideUserId = null;

    if (in_array($manualPriority, ['P1', 'P2', 'P3'], true) && $manualPriority !== $priorityCode) {
        $priorityCode = $manualPriority;
        $priorityOverrideUserId = (int) $user['id'];
        if ($overrideReason === '') {
            flash('error', 'Override reason is required when manually changing priority.');
            header('Location: ' . url('/requester/create_ticket.php'));
            exit;
        }
    }

    if ($title === '' || $description === '' || !$categoryId || $affectedUserType === '') {
        flash('error', 'Please complete all required ticket fields.');
        header('Location: ' . url('/requester/create_ticket.php'));
        exit;
    }

    $priority = get_priority_by_code($pdo, $priorityCode);
    $slaRule = get_sla_rule($pdo, (int) $priority['id']);
    $level = get_level_by_code($pdo, initial_level_for_priority($priorityCode));
    $agent = get_available_agent($pdo, (int) $level['id'], $categoryId);
    $ticketNumber = generate_ticket_number($pdo);
    $createdAt = now();

    $stmt = $pdo->prepare("
        INSERT INTO tickets (
            ticket_number, requester_id, title, description, category_id, priority_id,
            problem_intensity, affected_user_type, number_of_users_affected, total_number_of_users,
            affected_user_percentage, business_impact, attachment_name, current_status, current_level_id,
            assigned_agent_id, response_due_at, resolution_due_at, last_updated_at,
            priority_overridden_by, priority_override_reason, created_at
        ) VALUES (
            :ticket_number, :requester_id, :title, :description, :category_id, :priority_id,
            :problem_intensity, :affected_user_type, :number_of_users_affected, :total_number_of_users,
            :affected_user_percentage, :business_impact, :attachment_name, :current_status, :current_level_id,
            :assigned_agent_id, :response_due_at, :resolution_due_at, :last_updated_at,
            :priority_overridden_by, :priority_override_reason, :created_at
        )
    ");
    $stmt->execute([
        'ticket_number' => $ticketNumber,
        'requester_id' => $user['id'],
        'title' => $title,
        'description' => $description,
        'category_id' => $categoryId,
        'priority_id' => $priority['id'],
        'problem_intensity' => $problemIntensity,
        'affected_user_type' => $affectedUserType,
        'number_of_users_affected' => $affectedUsers,
        'total_number_of_users' => $totalUsers,
        'affected_user_percentage' => $percentage,
        'business_impact' => $businessImpact,
        'attachment_name' => $_FILES['attachment']['name'] ?? null,
        'current_status' => $agent ? 'Assigned' : 'Open',
        'current_level_id' => $level['id'],
        'assigned_agent_id' => $agent['id'] ?? null,
        'response_due_at' => add_minutes($createdAt, (int) $slaRule['first_response_minutes']),
        'resolution_due_at' => add_minutes($createdAt, (int) $slaRule['resolution_minutes']),
        'last_updated_at' => $createdAt,
        'priority_overridden_by' => $priorityOverrideUserId,
        'priority_override_reason' => $overrideReason ?: null,
        'created_at' => $createdAt,
    ]);

    $ticketId = (int) $pdo->lastInsertId();
    assign_ticket($pdo, $ticketId, (int) $level['id'], $agent['id'] ?? null, (int) $user['id'], 'Initial routing based on SLA rule engine');
    add_ticket_update($pdo, $ticketId, (int) $user['id'], 'Ticket Created', 'Requester submitted the ticket.');
    audit_log($pdo, (int) $user['id'], 'TICKET_CREATED', $ticketId, null, json_encode(['priority' => $priorityCode, 'level' => $level['code']]));

    create_notification($pdo, (int) $user['id'], $ticketId, 'Ticket submitted', "Your ticket {$ticketNumber} has been submitted.", 'ticket_created');
    if (!empty($agent['id'])) {
        create_notification($pdo, (int) $agent['id'], $ticketId, 'New ticket assigned', "{$ticketNumber} has been assigned to you.", 'ticket_assigned');
    }
    foreach (get_admin_user_ids($pdo) as $adminId) {
        create_notification($pdo, $adminId, $ticketId, 'New ticket created', "{$ticketNumber} was created and routed to {$level['name']}.", 'ticket_created');
    }

    flash('success', 'Ticket created successfully.');
    header('Location: ' . url('/requester/ticket_view.php?id=' . $ticketId));
    exit;
}

$pageTitle = 'Create Ticket';
$pageSubtitle = 'Capture incident severity, affected population, and business impact to drive SLA-aware routing.';
include __DIR__ . '/../includes/header.php';
?>
<section class="form-card">
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <div class="grid-2">
            <label>Ticket Title<input type="text" name="title" required></label>
            <label>Category
                <select name="category_id" required>
                    <option value="">Select category</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= (int) $category['id'] ?>"><?= e($category['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <label>Description<textarea name="description" required></textarea></label>
        <div class="grid-3">
            <label>Problem Intensity
                <select name="problem_intensity" required>
                    <option>Critical</option>
                    <option>High</option>
                    <option>Medium</option>
                    <option selected>Low</option>
                </select>
            </label>
            <label>Type of Users Affected
                <select name="affected_user_type" required>
                    <option value="">Select</option>
                    <option>Regular Users</option>
                    <option>Employees</option>
                    <option>Support Staff</option>
                    <option>Department Users</option>
                    <option>Administrators</option>
                    <option>External Clients</option>
                    <option>All Users</option>
                    <option>Other</option>
                </select>
            </label>
            <label>Business Impact
                <select name="business_impact" required>
                    <option>No major impact</option>
                    <option selected>Minor inconvenience</option>
                    <option>Department-level disruption</option>
                    <option>Major operation affected</option>
                    <option>System-wide outage</option>
                    <option>Security or data risk</option>
                </select>
            </label>
        </div>
        <div class="grid-3">
            <label>Number of Users Affected<input type="number" name="number_of_users_affected" min="1" value="1" required></label>
            <label>Total Number of Users in Affected Group<input type="number" name="total_number_of_users" min="1" value="1" required></label>
            <label>Auto-computed Affected User Percentage<input type="number" name="affected_user_percentage" step="0.01" readonly></label>
        </div>
        <div class="grid-3">
            <label>Optional Attachment<input type="file" name="attachment"></label>
            <label>Manual Priority Override
                <select name="manual_priority">
                    <option value="">Use system-calculated priority</option>
                    <option value="P1">P1 Critical</option>
                    <option value="P2">P2 Medium</option>
                    <option value="P3">P3 Low</option>
                </select>
            </label>
            <label>Override Reason<input type="text" name="priority_override_reason" placeholder="Required only if overriding"></label>
        </div>
        <button type="submit">Submit Ticket</button>
    </form>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
