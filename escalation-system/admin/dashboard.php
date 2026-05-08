<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_role('ADMIN');
$pdo = getPDO();
$counts = dashboard_counts($pdo);
$metrics = average_metrics($pdo);
$tickets = fetch_tickets($pdo);

$pageTitle = 'Admin Dashboard';
$pageSubtitle = 'Oversee workload, support tiers, SLA performance, and escalation activity across the service desk.';
include __DIR__ . '/../includes/header.php';
?>
<section class="cards">
    <div class="card"><h3>Total Tickets</h3><div class="kpi"><?= (int) ($counts['total_tickets'] ?? 0) ?></div></div>
    <div class="card"><h3>Open Tickets</h3><div class="kpi"><?= (int) ($counts['open_tickets'] ?? 0) ?></div></div>
    <div class="card"><h3>Escalated</h3><div class="kpi"><?= (int) ($counts['escalated_tickets'] ?? 0) ?></div></div>
    <div class="card"><h3>SLA Breached</h3><div class="kpi"><?= (int) ($counts['breached_tickets'] ?? 0) ?></div></div>
    <div class="card"><h3>Resolved</h3><div class="kpi"><?= (int) ($counts['resolved_tickets'] ?? 0) ?></div></div>
    <div class="card"><h3>Closed</h3><div class="kpi"><?= (int) ($counts['closed_tickets'] ?? 0) ?></div></div>
</section>

<section class="cards">
    <div class="card"><h3>Average Response</h3><div class="kpi"><?= (int) round((float) ($metrics['avg_response_minutes'] ?? 0)) ?>m</div></div>
    <div class="card"><h3>Average Resolution</h3><div class="kpi"><?= (int) round((float) ($metrics['avg_resolution_minutes'] ?? 0)) ?>m</div></div>
    <div class="card"><h3>Tickets by Priority</h3><p class="muted">
        P1: <?= count(array_filter($tickets, fn($t) => $t['priority_code'] === 'P1')) ?><br>
        P2: <?= count(array_filter($tickets, fn($t) => $t['priority_code'] === 'P2')) ?><br>
        P3: <?= count(array_filter($tickets, fn($t) => $t['priority_code'] === 'P3')) ?>
    </p></div>
    <div class="card"><h3>Tickets by Level</h3><p class="muted">
        Level 1: <?= count(array_filter($tickets, fn($t) => $t['support_level_name'] === 'Level 1 Support')) ?><br>
        Level 2: <?= count(array_filter($tickets, fn($t) => $t['support_level_name'] === 'Level 2 Support')) ?><br>
        Level 3: <?= count(array_filter($tickets, fn($t) => $t['support_level_name'] === 'Level 3 Support')) ?>
    </p></div>
</section>

<section class="table-wrap">
    <table>
        <thead>
        <tr>
            <th>Ticket</th>
            <th>Requester</th>
            <th>Priority</th>
            <th>Status</th>
            <th>Level</th>
            <th>Action</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach (array_slice($tickets, 0, 10) as $ticket): ?>
            <tr>
                <td><?= e($ticket['ticket_number']) ?><br><span class="muted"><?= e($ticket['title']) ?></span></td>
                <td><?= e($ticket['requester_name']) ?></td>
                <td><span class="priority-badge <?= priority_badge_class($ticket['priority_code']) ?>"><?= e($ticket['priority_name']) ?></span></td>
                <td><span class="badge <?= ticket_badge_class($ticket['current_status']) ?>"><?= e($ticket['current_status']) ?></span></td>
                <td><?= e($ticket['support_level_name']) ?></td>
                <td><a class="button secondary" href="<?= e(url('/tickets/ticket_view.php?id=' . (int) $ticket['id'])) ?>">View</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
