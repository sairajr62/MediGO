<?php
/**
 * user/my_reservations.php — history + cancel action.
 * Cancelling a pending/confirmed reservation returns the stock.
 */
require_once __DIR__ . '/../includes/functions.php';
require_login('user');

$uid = current_user_id();

// ---- Handle cancel ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    verify_csrf();
    $rid = (int)($_POST['reservation_id'] ?? 0);

    $conn->begin_transaction();
    try {
        // Lock the reservation and ensure it belongs to this user.
        $stmt = $conn->prepare(
            'SELECT id, medicine_id, quantity, status FROM reservations
             WHERE id = ? AND user_id = ? FOR UPDATE'
        );
        $stmt->bind_param('ii', $rid, $uid);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$r) {
            throw new RuntimeException('Reservation not found.');
        }
        if (!in_array($r['status'], ['pending', 'confirmed'], true)) {
            throw new RuntimeException('Only pending or confirmed reservations can be cancelled.');
        }

        // Restore stock.
        $upd = $conn->prepare('UPDATE medicines SET quantity = quantity + ? WHERE id = ?');
        $upd->bind_param('ii', $r['quantity'], $r['medicine_id']);
        $upd->execute();
        $upd->close();

        $upd = $conn->prepare("UPDATE reservations SET status = 'cancelled' WHERE id = ?");
        $upd->bind_param('i', $rid);
        $upd->execute();
        $upd->close();

        $conn->commit();
        set_flash('success', 'Reservation cancelled and stock released.');
    } catch (Throwable $ex) {
        $conn->rollback();
        set_flash('error', $ex->getMessage());
    }
    redirect(base_url('user/my_reservations.php'));
}

// ---- List reservations ----
$stmt = $conn->prepare(
    "SELECT r.id, r.quantity, r.status, r.reserved_at,
            m.name AS medicine, p.pharmacy_name
     FROM reservations r
     JOIN medicines m  ON m.id = r.medicine_id
     JOIN pharmacies p ON p.id = r.pharmacy_id
     WHERE r.user_id = ?
     ORDER BY r.reserved_at DESC"
);
$stmt->bind_param('i', $uid);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$pageTitle = 'My Reservations';
require __DIR__ . '/../includes/header.php';
?>
<h1 class="page-title">My Reservations</h1>
<div class="table-wrap">
<table class="data-table">
  <thead>
    <tr><th>ID</th><th>Medicine</th><th>Pharmacy</th><th>Qty</th><th>Date</th><th>Status</th><th></th></tr>
  </thead>
  <tbody>
  <?php if (!$rows): ?>
    <tr><td colspan="7" class="empty">You have no reservations yet. <a href="<?= e(base_url('search.php')) ?>">Search medicines</a>.</td></tr>
  <?php else: foreach ($rows as $r): ?>
    <tr>
      <td data-label="ID">#<?= (int)$r['id'] ?></td>
      <td data-label="Medicine"><?= e($r['medicine']) ?></td>
      <td data-label="Pharmacy"><?= e($r['pharmacy_name']) ?></td>
      <td data-label="Qty"><?= (int)$r['quantity'] ?></td>
      <td data-label="Date"><?= e(date('d-M-Y', strtotime($r['reserved_at']))) ?></td>
      <td data-label="Status"><span class="badge <?= reservation_badge($r['status']) ?>"><?= e(ucfirst($r['status'])) ?></span></td>
      <td data-label="">
        <?php if (in_array($r['status'], ['pending', 'confirmed'], true)): ?>
          <form method="post" class="inline" data-confirm="Cancel this reservation?">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="cancel">
            <input type="hidden" name="reservation_id" value="<?= (int)$r['id'] ?>">
            <button type="submit" class="btn btn-sm btn-danger">Cancel</button>
          </form>
        <?php else: ?>—<?php endif; ?>
      </td>
    </tr>
  <?php endforeach; endif; ?>
  </tbody>
</table>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
