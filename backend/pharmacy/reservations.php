<?php
/**
 * pharmacy/reservations.php — view and manage reservations for this pharmacy.
 * Status transitions: confirm, collect, cancel, expire.
 * Cancel/expire return the reserved units to stock.
 */
require_once __DIR__ . '/../includes/functions.php';
require_login('pharmacy');

$pharmacy = current_pharmacy($conn);
if (!$pharmacy) { redirect(base_url('logout.php')); }
$pid = (int)$pharmacy['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $rid    = (int)($_POST['reservation_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    // Map action -> [new status, restores stock?]
    $map = [
        'confirm' => ['confirmed', false, ['pending']],
        'collect' => ['collected', false, ['pending', 'confirmed']],
        'cancel'  => ['cancelled', true,  ['pending', 'confirmed']],
        'expire'  => ['expired',   true,  ['pending', 'confirmed']],
    ];

    if (!isset($map[$action])) {
        set_flash('error', 'Unknown action.');
        redirect(base_url('pharmacy/reservations.php'));
    }
    [$newStatus, $restore, $allowedFrom] = $map[$action];

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare(
            'SELECT id, medicine_id, quantity, status FROM reservations
             WHERE id = ? AND pharmacy_id = ? FOR UPDATE'
        );
        $stmt->bind_param('ii', $rid, $pid);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$r) {
            throw new RuntimeException('Reservation not found.');
        }
        if (!in_array($r['status'], $allowedFrom, true)) {
            throw new RuntimeException('This reservation cannot move to ' . $newStatus . '.');
        }

        if ($restore) {
            $upd = $conn->prepare('UPDATE medicines SET quantity = quantity + ? WHERE id = ?');
            $upd->bind_param('ii', $r['quantity'], $r['medicine_id']);
            $upd->execute();
            $upd->close();
        }

        $upd = $conn->prepare('UPDATE reservations SET status = ? WHERE id = ?');
        $upd->bind_param('si', $newStatus, $rid);
        $upd->execute();
        $upd->close();

        $conn->commit();
        set_flash('success', 'Reservation updated to ' . $newStatus . '.');
    } catch (Throwable $ex) {
        $conn->rollback();
        set_flash('error', $ex->getMessage());
    }
    redirect(base_url('pharmacy/reservations.php'));
}

// Optional status filter.
$filter = $_GET['status'] ?? '';
$sql = "SELECT r.id, r.quantity, r.status, r.reserved_at,
               m.name AS medicine, u.name AS customer, u.phone AS customer_phone
        FROM reservations r
        JOIN medicines m ON m.id = r.medicine_id
        JOIN users u     ON u.id = r.user_id
        WHERE r.pharmacy_id = ?";
$types = 'i';
$params = [$pid];
if (in_array($filter, ['pending','confirmed','collected','cancelled','expired'], true)) {
    $sql .= ' AND r.status = ?';
    $types .= 's';
    $params[] = $filter;
}
$sql .= ' ORDER BY r.reserved_at DESC';
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$pageTitle = 'Reservations';
require __DIR__ . '/../includes/header.php';
?>
<h1 class="page-title">Reservations</h1>

<form method="get" class="filter-bar">
  <select name="status" onchange="this.form.submit()">
    <option value="">All statuses</option>
    <?php foreach (['pending','confirmed','collected','cancelled','expired'] as $s): ?>
      <option value="<?= $s ?>" <?= $filter === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
    <?php endforeach; ?>
  </select>
</form>

<div class="table-wrap">
<table class="data-table">
  <thead><tr><th>ID</th><th>Customer</th><th>Medicine</th><th>Qty</th><th>Date</th><th>Status</th><th>Actions</th></tr></thead>
  <tbody>
  <?php if (!$rows): ?>
    <tr><td colspan="7" class="empty">No reservations found.</td></tr>
  <?php else: foreach ($rows as $r): ?>
    <tr>
      <td data-label="ID">#<?= (int)$r['id'] ?></td>
      <td data-label="Customer"><?= e($r['customer']) ?><br><small class="muted"><?= e($r['customer_phone'] ?: '') ?></small></td>
      <td data-label="Medicine"><?= e($r['medicine']) ?></td>
      <td data-label="Qty"><?= (int)$r['quantity'] ?></td>
      <td data-label="Date"><?= e(date('d-M-Y', strtotime($r['reserved_at']))) ?></td>
      <td data-label="Status"><span class="badge <?= reservation_badge($r['status']) ?>"><?= e(ucfirst($r['status'])) ?></span></td>
      <td data-label="Actions">
        <?php if (in_array($r['status'], ['pending','confirmed'], true)): ?>
          <div class="action-row">
            <?php if ($r['status'] === 'pending'): ?>
              <?= status_form($r['id'], 'confirm', 'Confirm', 'btn-primary') ?>
            <?php endif; ?>
            <?= status_form($r['id'], 'collect', 'Collected', 'btn-success') ?>
            <?= status_form($r['id'], 'cancel', 'Cancel', 'btn-danger', 'Cancel this reservation and release stock?') ?>
            <?= status_form($r['id'], 'expire', 'Expire', 'btn-outline', 'Mark expired and release stock?') ?>
          </div>
        <?php else: ?>—<?php endif; ?>
      </td>
    </tr>
  <?php endforeach; endif; ?>
  </tbody>
</table>
</div>
<?php
require __DIR__ . '/../includes/footer.php';

/** Small helper to render an inline status-change form. */
function status_form(int $rid, string $action, string $label, string $cls, ?string $confirm = null): string
{
    $c = $confirm ? ' data-confirm="' . e($confirm) . '"' : '';
    return '<form method="post" class="inline"' . $c . '>'
        . csrf_field()
        . '<input type="hidden" name="reservation_id" value="' . (int)$rid . '">'
        . '<input type="hidden" name="action" value="' . e($action) . '">'
        . '<button class="btn btn-sm ' . e($cls) . '">' . e($label) . '</button>'
        . '</form>';
}
?>
