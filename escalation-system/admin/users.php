<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_role('ADMIN');
$pdo = getPDO();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $stmt = $pdo->prepare("
        INSERT INTO users (role_id, full_name, email, password_hash, is_active, created_at)
        VALUES (:role_id, :full_name, :email, :password_hash, :is_active, :created_at)
    ");
    $stmt->execute([
        'role_id' => (int) $_POST['role_id'],
        'full_name' => trim($_POST['full_name']),
        'email' => trim($_POST['email']),
        'password_hash' => password_hash($_POST['password'], PASSWORD_DEFAULT),
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
        'created_at' => now(),
    ]);
    flash('success', 'User added successfully.');
    header('Location: ' . url('/admin/users.php'));
    exit;
}

$roles = $pdo->query("SELECT * FROM roles ORDER BY id")->fetchAll();
$users = $pdo->query("
    SELECT u.*, r.name AS role_name
    FROM users u INNER JOIN roles r ON r.id = u.role_id
    ORDER BY u.created_at DESC
")->fetchAll();

$pageTitle = 'User Management';
$pageSubtitle = 'Manage requester and support accounts with secure, role-based access.';
include __DIR__ . '/../includes/header.php';
?>
<section class="form-card">
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <div class="grid-2">
            <label>Full Name<input type="text" name="full_name" required></label>
            <label>Email<input type="email" name="email" required></label>
        </div>
        <div class="grid-3">
            <label>Role
                <select name="role_id" required>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= (int) $role['id'] ?>"><?= e($role['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Password<input type="text" name="password" value="password123" required></label>
            <label><span>Active</span><input type="checkbox" name="is_active" checked></label>
        </div>
        <button type="submit">Create User</button>
    </form>
</section>
<section class="table-wrap">
    <table>
        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($users as $record): ?>
            <tr>
                <td><?= e($record['full_name']) ?></td>
                <td><?= e($record['email']) ?></td>
                <td><?= e($record['role_name']) ?></td>
                <td><?= (int) $record['is_active'] === 1 ? 'Active' : 'Inactive' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
