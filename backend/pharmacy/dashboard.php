<?php
/**
 * pharmacy/dashboard.php — inventory + reservation overview.
 */
require_once __DIR__ . '/../includes/functions.php';
require_login('pharmacy');

$pharmacy = current_pharmacy($conn);
if (!$pharmacy) {
    set_flash('error', 'No pharmacy profile is linked to this account. Please contact the administrator.');
    redirect(base_url('logout.php'));
}
$pid = (int)$pharmacy['id'];

// Medicine stats.
$med = $conn->prepare(
    "SELECT
        COUNT(*) AS total,
        SUM(quantity > 0)  AS available,
        SUM(quantity = 0)  AS out_stock
     FROM medicines WHERE pharmacy_id = ? AND status = 'active'"
);
$med->bind_param('i', $pid);
$med->execute();
$m = $med->get_result()->fetch_assoc();
$med->close();

// Pending reservations.
$res = $conn->prepare("SELECT COUNT(*) c FROM reservations WHERE pharmacy_id = ? AND status = 'pending'");
$res->bind_param('i', $pid);
$res->execute();
$pending = (int)$res->get_result()->fetch_assoc()['c'];
$res->close();

$pageTitle = 'Pharmacy Dashboard';
require __DIR__ . '/../includes/header.php';
?>
<h1 class="page-title"><?= e($pharmacy['pharmacy_name']) ?></h1>

<?php if ($pharmacy['status'] !== 'approved'): ?>
  <div class="alert alert-warning">
    Your pharmacy status is <strong><?= e(ucfirst($pharmacy['status'])) ?></strong>.
    Your medicines will not appear in public search until an administrator approves your pharmacy.
  </div>
<?php endif; ?>

<div class="card-grid">
  <div class="stat-card"><span class="stat-num"><?= (int)($m['total'] ?? 0) ?></span><span class="stat-label">Total Medicines</span></div>
  <div class="stat-card"><span class="stat-num"><?= (int)($m['available'] ?? 0) ?></span><span class="stat-label">Available</span></div>
  <div class="stat-card"><span class="stat-num"><?= (int)($m['out_stock'] ?? 0) ?></span><span class="stat-label">Out of Stock</span></div>
  <div class="stat-card"><span class="stat-num"><?= $pending ?></span><span class="stat-label">Pending Reservations</span></div>
</div>

<div class="quick-actions">
  <a class="btn btn-primary" href="<?= e(base_url('pharmacy/add_medicine.php')) ?>">Add Medicine</a>
  <a class="btn btn-outline" href="<?= e(base_url('pharmacy/medicines.php')) ?>">Manage Inventory</a>
  <a class="btn btn-outline" href="<?= e(base_url('pharmacy/reservations.php')) ?>">Reservations</a>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
