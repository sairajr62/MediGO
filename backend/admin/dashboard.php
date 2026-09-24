<?php
/**
 * admin/dashboard.php — system-wide overview.
 */
require_once __DIR__ . '/../includes/functions.php';
require_login('admin');

function scalar(mysqli $conn, string $sql): int
{
    $r = $conn->query($sql);
    return (int)($r->fetch_row()[0] ?? 0);
}

$totalUsers    = scalar($conn, "SELECT COUNT(*) FROM users WHERE role = 'user'");
$totalPharmacy = scalar($conn, "SELECT COUNT(*) FROM pharmacies");
$pendingPharm  = scalar($conn, "SELECT COUNT(*) FROM pharmacies WHERE status = 'pending'");
$totalMeds     = scalar($conn, "SELECT COUNT(*) FROM medicines WHERE status = 'active'");
$totalRes      = scalar($conn, "SELECT COUNT(*) FROM reservations");
$pendingRes    = scalar($conn, "SELECT COUNT(*) FROM reservations WHERE status = 'pending'");

$pageTitle = 'Admin Dashboard';
require __DIR__ . '/../includes/header.php';
?>
<h1 class="page-title">Admin Dashboard</h1>
<div class="card-grid">
  <div class="stat-card"><span class="stat-num"><?= number_format($totalUsers) ?></span><span class="stat-label">Users</span></div>
  <div class="stat-card"><span class="stat-num"><?= number_format($totalPharmacy) ?></span><span class="stat-label">Pharmacies</span></div>
  <div class="stat-card"><span class="stat-num"><?= number_format($totalMeds) ?></span><span class="stat-label">Medicines</span></div>
  <div class="stat-card"><span class="stat-num"><?= number_format($totalRes) ?></span><span class="stat-label">Reservations</span></div>
  <div class="stat-card"><span class="stat-num"><?= number_format($pendingRes) ?></span><span class="stat-label">Pending Reservations</span></div>
  <div class="stat-card"><span class="stat-num"><?= number_format($pendingPharm) ?></span><span class="stat-label">Pharmacies Awaiting Approval</span></div>
</div>

<div class="quick-actions">
  <a class="btn btn-primary" href="<?= e(base_url('admin/pharmacies.php')) ?>">Manage Pharmacies</a>
  <a class="btn btn-outline" href="<?= e(base_url('admin/medicines.php')) ?>">Manage Medicines</a>
  <a class="btn btn-outline" href="<?= e(base_url('admin/reservations.php')) ?>">All Reservations</a>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
