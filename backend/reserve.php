<?php
/**
 * reserve.php — a logged-in user reserves an available medicine.
 * Stock decrement + reservation insert happen in one transaction
 * with row locking so two users cannot oversell the same stock.
 */
require_once __DIR__ . '/includes/functions.php';
require_login('user');

$medicineId = (int)($_GET['medicine_id'] ?? $_POST['medicine_id'] ?? 0);
if ($medicineId <= 0) {
    set_flash('error', 'No medicine selected.');
    redirect(base_url('search.php'));
}

/** Load medicine + pharmacy for display / validation. */
function load_medicine(mysqli $conn, int $id): ?array
{
    $stmt = $conn->prepare(
        "SELECT m.*, p.pharmacy_name, p.status AS pharmacy_status, p.id AS pharmacy_id
         FROM medicines m JOIN pharmacies p ON p.id = m.pharmacy_id
         WHERE m.id = ? LIMIT 1"
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

$medicine = load_medicine($conn, $medicineId);
if (!$medicine) {
    set_flash('error', 'That medicine no longer exists.');
    redirect(base_url('search.php'));
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $qty = (int)($_POST['quantity'] ?? 0);

    if ($qty < 1) {
        $errors[] = 'Please enter a quantity of at least 1.';
    }

    if (!$errors) {
        $conn->begin_transaction();
        try {
            // Lock the medicine row for the duration of the transaction.
            $stmt = $conn->prepare(
                "SELECT m.status, m.quantity, m.expiry_date, m.pharmacy_id, p.status AS pharmacy_status
                 FROM medicines m JOIN pharmacies p ON p.id = m.pharmacy_id
                 WHERE m.id = ? FOR UPDATE"
            );
            $stmt->bind_param('i', $medicineId);
            $stmt->execute();
            $locked = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$locked) {
                throw new RuntimeException('Medicine not found.');
            }
            if ($locked['status'] !== 'active') {
                throw new RuntimeException('This medicine is not available for reservation.');
            }
            if ($locked['pharmacy_status'] !== 'approved') {
                throw new RuntimeException('This pharmacy is not currently approved.');
            }
            if ($locked['expiry_date'] !== null && $locked['expiry_date'] < date('Y-m-d')) {
                throw new RuntimeException('This medicine has expired and cannot be reserved.');
            }
            if ($qty > (int)$locked['quantity']) {
                throw new RuntimeException('Only ' . (int)$locked['quantity'] . ' unit(s) are available.');
            }

            // Decrement stock.
            $upd = $conn->prepare('UPDATE medicines SET quantity = quantity - ? WHERE id = ?');
            $upd->bind_param('ii', $qty, $medicineId);
            $upd->execute();
            $upd->close();

            // Create the reservation.
            $uid = current_user_id();
            $pid = (int)$locked['pharmacy_id'];
            $ins = $conn->prepare(
                "INSERT INTO reservations (user_id, medicine_id, pharmacy_id, quantity, status)
                 VALUES (?, ?, ?, ?, 'pending')"
            );
            $ins->bind_param('iiii', $uid, $medicineId, $pid, $qty);
            $ins->execute();
            $reservationId = $ins->insert_id;
            $ins->close();

            $conn->commit();
            set_flash('success', "Reservation #$reservationId created and is now pending. The pharmacy will confirm it.");
            redirect(base_url('user/my_reservations.php'));
        } catch (Throwable $ex) {
            $conn->rollback();
            $errors[] = $ex->getMessage();
            $medicine = load_medicine($conn, $medicineId); // refresh quantity
        }
    }
}

[$label, $badge] = stock_status((int)$medicine['quantity']);
$expired = $medicine['expiry_date'] !== null && $medicine['expiry_date'] < date('Y-m-d');
$reservable = $medicine['status'] === 'active'
    && $medicine['pharmacy_status'] === 'approved'
    && !$expired
    && (int)$medicine['quantity'] > 0;

$pageTitle = 'Reserve Medicine';
require __DIR__ . '/includes/header.php';
?>
<h1 class="page-title">Reserve Medicine</h1>

<?php if ($errors): ?>
  <div class="alert alert-error">
    <ul class="plain"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
  </div>
<?php endif; ?>

<div class="reserve-card">
  <div class="reserve-media">
    <?php if (!empty($medicine['image'])): ?>
      <img src="<?= e(base_url('uploads/medicine/' . $medicine['image'])) ?>" alt="<?= e($medicine['name']) ?>">
    <?php else: ?>
      <div class="reserve-media-placeholder">Rx</div>
    <?php endif; ?>
  </div>
  <div class="reserve-info">
    <h2><?= e($medicine['name']) ?></h2>
    <p class="muted"><?= e($medicine['generic_name'] ?: '') ?> <?= $medicine['category'] ? '· ' . e($medicine['category']) : '' ?></p>
    <table class="kv">
      <tr><th>Pharmacy</th><td><?= e($medicine['pharmacy_name']) ?></td></tr>
      <tr><th>Price</th><td>&#8377;<?= e(number_format((float)$medicine['price'], 2)) ?></td></tr>
      <tr><th>Available</th><td><?= (int)$medicine['quantity'] ?></td></tr>
      <tr><th>Expiry</th><td><?= e($medicine['expiry_date'] ?: '—') ?></td></tr>
      <tr><th>Status</th><td><span class="badge <?= $badge ?>"><?= e($label) ?></span></td></tr>
    </table>

    <?php if ($reservable): ?>
      <form method="post" id="reserveForm" data-max="<?= (int)$medicine['quantity'] ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="medicine_id" value="<?= (int)$medicine['id'] ?>">
        <label>Quantity
          <input type="number" name="quantity" id="reserveQty" min="1" max="<?= (int)$medicine['quantity'] ?>" value="1" required>
        </label>
        <button type="submit" class="btn btn-primary">Reserve</button>
        <a class="btn btn-outline" href="<?= e(base_url('search.php')) ?>">Back to search</a>
      </form>
    <?php else: ?>
      <div class="alert alert-error">This medicine cannot be reserved right now
        <?= $expired ? '(expired)' : ((int)$medicine['quantity'] === 0 ? '(out of stock)' : '') ?>.
      </div>
      <a class="btn btn-outline" href="<?= e(base_url('search.php')) ?>">Back to search</a>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
