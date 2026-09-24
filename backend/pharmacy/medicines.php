<?php
/**
 * pharmacy/medicines.php — inventory list for this pharmacy.
 * Supports a quick quantity update and links to edit/delete.
 */
require_once __DIR__ . '/../includes/functions.php';
require_login('pharmacy');

$pharmacy = current_pharmacy($conn);
if (!$pharmacy) { redirect(base_url('logout.php')); }
$pid = (int)$pharmacy['id'];

// Quick quantity update.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'set_qty') {
    verify_csrf();
    $mid = (int)($_POST['medicine_id'] ?? 0);
    $qty = (int)($_POST['quantity'] ?? -1);
    if ($qty < 0) {
        set_flash('error', 'Quantity cannot be negative.');
    } else {
        $stmt = $conn->prepare('UPDATE medicines SET quantity = ? WHERE id = ? AND pharmacy_id = ?');
        $stmt->bind_param('iii', $qty, $mid, $pid);
        $stmt->execute();
        $stmt->close();
        set_flash('success', 'Quantity updated.');
    }
    redirect(base_url('pharmacy/medicines.php'));
}

$stmt = $conn->prepare(
    "SELECT * FROM medicines WHERE pharmacy_id = ? AND status = 'active' ORDER BY name"
);
$stmt->bind_param('i', $pid);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$pageTitle = 'Manage Inventory';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <h1 class="page-title">Manage Inventory</h1>
  <a class="btn btn-primary" href="<?= e(base_url('pharmacy/add_medicine.php')) ?>">Add Medicine</a>
</div>

<div class="table-wrap">
<table class="data-table">
  <thead><tr><th>Medicine</th><th>Category</th><th>Price</th><th>Expiry</th><th>Quantity</th><th>Status</th><th></th></tr></thead>
  <tbody>
  <?php if (!$rows): ?>
    <tr><td colspan="7" class="empty">No medicines yet. <a href="<?= e(base_url('pharmacy/add_medicine.php')) ?>">Add one</a>.</td></tr>
  <?php else: foreach ($rows as $r): [$label,$badge] = stock_status((int)$r['quantity']); ?>
    <tr>
      <td data-label="Medicine"><strong><?= e($r['name']) ?></strong><br><small class="muted"><?= e($r['generic_name'] ?: '') ?></small></td>
      <td data-label="Category"><?= e($r['category'] ?: '—') ?></td>
      <td data-label="Price">&#8377;<?= e(number_format((float)$r['price'], 2)) ?></td>
      <td data-label="Expiry"><?= e($r['expiry_date'] ?: '—') ?></td>
      <td data-label="Quantity">
        <form method="post" class="inline qty-form">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="set_qty">
          <input type="hidden" name="medicine_id" value="<?= (int)$r['id'] ?>">
          <input type="number" name="quantity" value="<?= (int)$r['quantity'] ?>" min="0" class="qty-input">
          <button class="btn btn-sm btn-outline">Set</button>
        </form>
      </td>
      <td data-label="Status"><span class="badge <?= $badge ?>"><?= e($label) ?></span></td>
      <td data-label="">
        <a class="btn btn-sm btn-outline" href="<?= e(base_url('pharmacy/edit_medicine.php?id=' . (int)$r['id'])) ?>">Edit</a>
        <form method="post" action="<?= e(base_url('pharmacy/delete_medicine.php')) ?>" class="inline" data-confirm="Delete/deactivate this medicine?">
          <?= csrf_field() ?>
          <input type="hidden" name="medicine_id" value="<?= (int)$r['id'] ?>">
          <button class="btn btn-sm btn-danger">Delete</button>
        </form>
      </td>
    </tr>
  <?php endforeach; endif; ?>
  </tbody>
</table>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
