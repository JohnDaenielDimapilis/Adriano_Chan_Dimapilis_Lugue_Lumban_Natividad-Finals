<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_role('ADMIN');
$pdo = getPDO();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $stmt = $pdo->prepare("INSERT INTO teams (name, description, is_active, created_at) VALUES (:name, :description, :is_active, :created_at)");
    $stmt->execute([
        'name' => trim($_POST['name']),
        'description' => trim($_POST['description']),
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
        'created_at' => now(),
    ]);
    flash('success', 'Team created.');
    header('Location: /escalation-system/admin/teams.php');
    exit;
}

$teams = $pdo->query("SELECT * FROM teams ORDER BY name")->fetchAll();
$pageTitle = 'Teams';
$pageSubtitle = 'Configure support teams that own assignment queues and specialization areas.';
include __DIR__ . '/../includes/header.php';
?>
<section class="form-card">
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <div class="grid-2">
            <label>Team Name<input type="text" name="name" required></label>
            <label><span>Active</span><input type="checkbox" name="is_active" checked></label>
        </div>
        <label>Description<textarea name="description"></textarea></label>
        <button type="submit">Create Team</button>
    </form>
</section>
<section class="table-wrap">
    <table>
        <thead><tr><th>Name</th><th>Description</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($teams as $team): ?>
            <tr>
                <td><?= e($team['name']) ?></td>
                <td><?= e($team['description']) ?></td>
                <td><?= (int) $team['is_active'] === 1 ? 'Active' : 'Inactive' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
