<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_role('ADMIN');
$pdo = getPDO();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $stmt = $pdo->prepare("
        UPDATE sla_rules
        SET first_response_minutes = :first_response_minutes,
            resolution_minutes = :resolution_minutes,
            escalation_target_level_id = :escalation_target_level_id,
            warning_before_minutes = :warning_before_minutes,
            is_active = :is_active
        WHERE id = :id
    ");
    $stmt->execute([
        'first_response_minutes' => (int) $_POST['first_response_minutes'],
        'resolution_minutes' => (int) $_POST['resolution_minutes'],
        'escalation_target_level_id' => (int) $_POST['escalation_target_level_id'],
        'warning_before_minutes' => (int) $_POST['warning_before_minutes'],
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
        'id' => (int) $_POST['id'],
    ]);
    flash('success', 'SLA rule updated.');
    header('Location: /escalation-system/admin/sla_rules.php');
    exit;
}

$rules = $pdo->query("
    SELECT sr.*, p.name AS priority_name, p.code AS priority_code, sl.name AS level_name
    FROM sla_rules sr
    INNER JOIN priorities p ON p.id = sr.priority_id
    INNER JOIN support_levels sl ON sl.id = sr.escalation_target_level_id
    ORDER BY p.sort_order
")->fetchAll();
$levels = get_support_levels($pdo);
$pageTitle = 'SLA Rules';
$pageSubtitle = 'Control first-response targets, resolution windows, warnings, and escalation destinations.';
include __DIR__ . '/../includes/header.php';
?>
<?php foreach ($rules as $rule): ?>
    <section class="form-card">
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= (int) $rule['id'] ?>">
            <h3><?= e($rule['priority_name']) ?></h3>
            <div class="grid-3">
                <label>First Response Minutes<input type="number" name="first_response_minutes" value="<?= (int) $rule['first_response_minutes'] ?>" required></label>
                <label>Resolution Minutes<input type="number" name="resolution_minutes" value="<?= (int) $rule['resolution_minutes'] ?>" required></label>
                <label>Warning Before Minutes<input type="number" name="warning_before_minutes" value="<?= (int) $rule['warning_before_minutes'] ?>" required></label>
            </div>
            <div class="grid-2">
                <label>Escalation Target Level
                    <select name="escalation_target_level_id">
                        <?php foreach ($levels as $level): ?>
                            <option value="<?= (int) $level['id'] ?>" <?= (int) $level['id'] === (int) $rule['escalation_target_level_id'] ? 'selected' : '' ?>><?= e($level['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label><span>Active</span><input type="checkbox" name="is_active" <?= (int) $rule['is_active'] === 1 ? 'checked' : '' ?>></label>
            </div>
            <button type="submit">Save Rule</button>
        </form>
    </section>
<?php endforeach; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
