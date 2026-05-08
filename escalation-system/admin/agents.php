<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_role('ADMIN');
$pdo = getPDO();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $stmt = $pdo->prepare("
        INSERT INTO agent_profiles (user_id, support_level_id, team_id, skills, specialization, agent_status, is_active, updated_at)
        VALUES (:user_id, :support_level_id, :team_id, :skills, :specialization, :agent_status, :is_active, :updated_at)
        ON DUPLICATE KEY UPDATE
            support_level_id = VALUES(support_level_id),
            team_id = VALUES(team_id),
            skills = VALUES(skills),
            specialization = VALUES(specialization),
            agent_status = VALUES(agent_status),
            is_active = VALUES(is_active),
            updated_at = VALUES(updated_at)
    ");
    $stmt->execute([
        'user_id' => (int) $_POST['user_id'],
        'support_level_id' => (int) $_POST['support_level_id'],
        'team_id' => (int) $_POST['team_id'],
        'skills' => trim($_POST['skills']),
        'specialization' => trim($_POST['specialization']),
        'agent_status' => $_POST['agent_status'],
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
        'updated_at' => now(),
    ]);
    flash('success', 'Agent profile saved.');
    header('Location: ' . url('/admin/agents.php'));
    exit;
}

$agentUsers = $pdo->query("
    SELECT u.id, u.full_name, r.code
    FROM users u
    INNER JOIN roles r ON r.id = u.role_id
    WHERE r.code IN ('LEVEL1', 'LEVEL2', 'LEVEL3')
    ORDER BY u.full_name
")->fetchAll();
$levels = get_support_levels($pdo);
$teams = $pdo->query("SELECT * FROM teams WHERE is_active = 1 ORDER BY name")->fetchAll();
$agents = $pdo->query("
    SELECT u.full_name, u.email, sl.name AS level_name, t.name AS team_name,
           ap.skills, ap.specialization, ap.agent_status, ap.is_active
    FROM agent_profiles ap
    INNER JOIN users u ON u.id = ap.user_id
    INNER JOIN support_levels sl ON sl.id = ap.support_level_id
    LEFT JOIN teams t ON t.id = ap.team_id
    ORDER BY sl.level_order, u.full_name
")->fetchAll();

$pageTitle = 'Agent Management';
$pageSubtitle = 'Maintain support tiers, agent availability, specialization, and skills coverage.';
include __DIR__ . '/../includes/header.php';
?>
<section class="form-card">
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <div class="grid-3">
            <label>Agent
                <select name="user_id" required>
                    <?php foreach ($agentUsers as $agent): ?>
                        <option value="<?= (int) $agent['id'] ?>"><?= e($agent['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Support Level
                <select name="support_level_id" required>
                    <?php foreach ($levels as $level): ?>
                        <option value="<?= (int) $level['id'] ?>"><?= e($level['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Team
                <select name="team_id" required>
                    <?php foreach ($teams as $team): ?>
                        <option value="<?= (int) $team['id'] ?>"><?= e($team['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <div class="grid-3">
            <label>Specialization<input type="text" name="specialization"></label>
            <label>Agent Status
                <select name="agent_status">
                    <option>Available</option>
                    <option>Busy</option>
                    <option>Inactive</option>
                </select>
            </label>
            <label><span>Active Profile</span><input type="checkbox" name="is_active" checked></label>
        </div>
        <label>Skills<textarea name="skills"></textarea></label>
        <button type="submit">Save Agent Profile</button>
    </form>
</section>
<section class="table-wrap">
    <table>
        <thead><tr><th>Agent</th><th>Level</th><th>Team</th><th>Status</th><th>Skills</th></tr></thead>
        <tbody>
        <?php foreach ($agents as $agent): ?>
            <tr>
                <td><?= e($agent['full_name']) ?><br><span class="muted"><?= e($agent['email']) ?></span></td>
                <td><?= e($agent['level_name']) ?></td>
                <td><?= e($agent['team_name'] ?? 'Unassigned') ?></td>
                <td><?= e($agent['agent_status']) ?></td>
                <td><?= e($agent['skills']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
