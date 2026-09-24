<?php
/**
 * admin/reservations.php — view all reservations, filter by
 * status / pharmacy / user / medicine / date.
 */
require_once __DIR__ . '/../includes/functions.php';
require_login('admin');

$status     = $_GET['status'] ?? '';
$pharmacyId = (int)($_GET['pharmacy_id'] ?? 0);
$search     = trim($_GET['q'] ?? '');   // matches user name or medicine name
$dateFrom   = trim($_GET['from'] ?? '');
$dateTo     = trim($_GET['to'] ?? '');

$sql = "SELECT r.id, r.quantity, r.status, r.reserved_at,
               u.name AS customer, m.name AS medicine, p.pharmacy_name
        FROM reservations r
        JOIN users u      ON u.id = r.user_id
        JOIN medicines m  ON m.id = r.medicine_id
        JOIN pharmacies p ON p.id = r.pharmacy_id
        WHERE 1=1";
$params = []; $types = '';

if (in_array($status, ['pending','confirmed','collected','cancelled','expired'], true)) {
    $sql .= " AND r.status = ?"; $params[] = $status; $types .= 's';
}
if ($pharmacyId > 0) {
    $sql .= " AND r.pharmacy_id = ?"; $params[] = $pharmacyId; $types .= 'i';
}
if ($search !== '') {
    $sql .= " AND (u.name LIKE ? OR m.name LIKE ?)";
    $like = '%' . $search . '%';
    $params[] = $like; $params[] = $like; $types .= 'ss';
}
if ($dateFrom !== '' && DateTime::createFromFormat('Y-m-d', $dateFrom)) {
    $sql .= " AND r.reserved_at >= ?"; $params[] = $dateFrom . ' 00:00:00'; $types .= 's';
}
if ($dateTo !== '' && DateTime::createFromFormat('Y-m-d', $dateTo)) {
    $sql .= " AND r.reserved_at <= ?"; $params[] = $dateTo . ' 23:59:59'; $types .= 's';
}
$sql .= " ORDER BY r.reserved_at DESC LIMIT 500";

$stmt = $conn->prepare($sql);
if ($params) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$pharmacies = $conn->query("SELECT id, pharmacy_name FROM pharmacies ORDER BY pharmacy_name")->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'All Reservations';
require __DIR__ . '/../includes/header.php';
?>
<h1 class="page-title">All Reservations</h1>
<form method="get" class="filter-bar filter-bar-wrap">
  <input type="text" name="q" value="<?= e($search) ?>" placeholder="User or medicine">
  <select name="status">
    <option value="">All statuses</option>
    <?php foreach (['pending','confirmed','collected','cancelled','expired'] as $s): ?>
      <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="pharmacy_id">
    <option value="0">All pharmacies</option>
    <?php foreach ($pharmacies as $p): ?>
      <option value="<?= (int)$p['id'] ?>" <?= $pharmacyId === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['pharmacy_name']) ?></option>
    <?php endforeach; ?>
  </select>
  <label class="inline-label">From <input type="date" name="from" value="<?= e($dateFrom) ?>"></label>
  <label class="inline-label">To <input type="date" name="to" value="<?= e($dateTo) ?>"></label>
  <button class="btn btn-primary">Filter</button>
</form>

<div class="table-wrap">
<table class="data-table">
  <thead><tr><th>ID</th><th>User</th><th>Pharmacy</th><th>Medicine</th><th>Qty</th><th>Date</th><th>Status</th></tr></thead>
  <tbody>
  <?php if (!$rows): ?>
    <tr><td colspan="7" class="empty">No reservations match these filters.</td></tr>
  <?php else: foreach ($rows as $r): ?>
    <tr>
      <td data-label="ID">#<?= (int)$r['id'] ?></td>
      <td data-label="User"><?= e($r['customer']) ?></td>
      <td data-label="Pharmacy"><?= e($r['pharmacy_name']) ?></td>
      <td data-label="Medicine"><?= e($r['medicine']) ?></td>
      <td data-label="Qty"><?= (int)$r['quantity'] ?></td>
      <td data-label="Date"><?= e(date('d-M-Y', strtotime($r['reserved_at']))) ?></td>
      <td data-label="Status"><span class="badge <?= reservation_badge($r['status']) ?>"><?= e(ucfirst($r['status'])) ?></span></td>
    </tr>
  <?php endforeach; endif; ?>
  </tbody>
</table>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
