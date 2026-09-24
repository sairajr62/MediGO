<?php
/**
 * admin/medicines.php — view all medicines, search, filter by pharmacy,
 * activate / deactivate.
 */
require_once __DIR__ . '/../includes/functions.php';
require_login('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id     = (int)($_POST['medicine_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if (in_array($action, ['activate', 'deactivate'], true)) {
        $new = $action === 'activate' ? 'active' : 'inactive';
        $stmt = $conn->prepare('UPDATE medicines SET status = ? WHERE id = ?');
        $stmt->bind_param('si', $new, $id);
        $stmt->execute();
        $stmt->close();
        set_flash('success', 'Medicine set to ' . $new . '.');
    }
    redirect(base_url('admin/medicines.php'));
}

$q          = trim($_GET['q'] ?? '');
$pharmacyId = (int)($_GET['pharmacy_id'] ?? 0);

$sql = "SELECT m.*, p.pharmacy_name FROM medicines m JOIN pharmacies p ON p.id = m.pharmacy_id WHERE 1=1";
$params = []; $types = '';
if ($q !== '') {
    $sql .= " AND (m.name LIKE ? OR m.generic_name LIKE ?)";
    $like = '%' . $q . '%';
    $params[] = $like; $params[] = $like; $types .= 'ss';
}
if ($pharmacyId > 0) {
    $sql .= " AND m.pharmacy_id = ?";
    $params[] = $pharmacyId; $types .= 'i';
}
$sql .= " ORDER BY m.created_at DESC LIMIT 300";
$stmt = $conn->prepare($sql);
if ($params) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Pharmacy list for the filter.
$pharmacies = $conn->query("SELECT id, pharmacy_name FROM pharmacies ORDER BY pharmacy_name")->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Manage Medicines';
require __DIR__ . '/../includes/header.php';
?>
<h1 class="page-title">Manage Medicines</h1>
<form method="get" class="filter-bar">
  <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search name / generic">
  <select name="pharmacy_id">
    <option value="0">All pharmacies</option>
    <?php foreach ($pharmacies as $p): ?>
      <option value="<?= (int)$p['id'] ?>" <?= $pharmacyId === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['pharmacy_name']) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn btn-primary">Filter</button>
</form>

<div class="table-wrap">
<table class="data-table">
  <thead><tr><th>Medicine</th><th>Pharmacy</th><th>Price</th><th>Stock</th><th>Med. Status</th><th>Actions</th></tr></thead>
  <tbody>
  <?php if (!$rows): ?>
    <tr><td colspan="6" class="empty">No medicines found.</td></tr>
  <?php else: foreach ($rows as $r): [$label,$badge] = stock_status((int)$r['quantity']); ?>
    <tr>
      <td data-label="Medicine"><strong><?= e($r['name']) ?></strong><br><small class="muted"><?= e($r['generic_name'] ?: '') ?></small></td>
      <td data-label="Pharmacy"><?= e($r['pharmacy_name']) ?></td>
      <td data-label="Price">&#8377;<?= e(number_format((float)$r['price'], 2)) ?></td>
      <td data-label="Stock"><?= (int)$r['quantity'] ?> <span class="badge <?= $badge ?>"><?= e($label) ?></span></td>
      <td data-label="Med. Status"><span class="badge <?= $r['status'] === 'active' ? 'badge-success' : 'badge-muted' ?>"><?= e(ucfirst($r['status'])) ?></span></td>
      <td data-label="Actions">
        <form method="post" class="inline" data-confirm="Change this medicine's status?">
          <?= csrf_field() ?>
          <input type="hidden" name="medicine_id" value="<?= (int)$r['id'] ?>">
          <?php if ($r['status'] === 'active'): ?>
            <input type="hidden" name="action" value="deactivate">
            <button class="btn btn-sm btn-danger">Deactivate</button>
          <?php else: ?>
            <input type="hidden" name="action" value="activate">
            <button class="btn btn-sm btn-success">Activate</button>
          <?php endif; ?>
        </form>
      </td>
    </tr>
  <?php endforeach; endif; ?>
  </tbody>
</table>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
