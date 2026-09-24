<?php
/**
 * user/dashboard.php — customer overview.
 */
require_once __DIR__ . '/../includes/functions.php';
require_login('user');

$uid = current_user_id();

// Counts by status in one query.
$counts = ['total' => 0, 'pending' => 0, 'confirmed' => 0, 'collected' => 0, 'cancelled' => 0, 'expired' => 0];
$stmt = $conn->prepare('SELECT status, COUNT(*) c FROM reservations WHERE user_id = ? GROUP BY status');
$stmt->bind_param('i', $uid);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $counts[$row['status']] = (int)$row['c'];
    $counts['total'] += (int)$row['c'];
}
$stmt->close();

$pageTitle = 'My Dashboard';
require __DIR__ . '/../includes/header.php';
?>
<h1 class="page-title">Welcome, <?= e($_SESSION['name'] ?? 'User') ?></h1>

<div class="card-grid">
  <div class="stat-card"><span class="stat-num"><?= $counts['total'] ?></span><span class="stat-label">Total Reservations</span></div>
  <div class="stat-card"><span class="stat-num"><?= $counts['pending'] ?></span><span class="stat-label">Pending</span></div>
  <div class="stat-card"><span class="stat-num"><?= $counts['confirmed'] ?></span><span class="stat-label">Confirmed</span></div>
  <div class="stat-card"><span class="stat-num"><?= $counts['cancelled'] ?></span><span class="stat-label">Cancelled</span></div>
</div>

<div class="quick-actions">
  <a class="btn btn-primary" href="<?= e(base_url('search.php')) ?>">Search Medicine</a>
  <a class="btn btn-outline" href="<?= e(base_url('user/my_reservations.php')) ?>">My Reservations</a>
  <a class="btn btn-outline" href="<?= e(base_url('logout.php')) ?>">Logout</a>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
