<?php
/**
 * pharmacy/add_medicine.php — add a medicine to this pharmacy.
 */
require_once __DIR__ . '/../includes/functions.php';
require_login('pharmacy');

$pharmacy = current_pharmacy($conn);
if (!$pharmacy) {
    set_flash('error', 'No pharmacy profile linked to this account.');
    redirect(base_url('logout.php'));
}
$pid = (int)$pharmacy['id'];

$errors = [];
$old = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $old = [
        'name'         => trim($_POST['name'] ?? ''),
        'generic_name' => trim($_POST['generic_name'] ?? ''),
        'category'     => trim($_POST['category'] ?? ''),
        'description'  => trim($_POST['description'] ?? ''),
        'manufacturer' => trim($_POST['manufacturer'] ?? ''),
        'price'        => trim($_POST['price'] ?? ''),
        'quantity'     => trim($_POST['quantity'] ?? ''),
        'expiry_date'  => trim($_POST['expiry_date'] ?? ''),
    ];

    if ($old['name'] === '') {
        $errors[] = 'Medicine name is required.';
    }
    if (!is_numeric($old['price']) || (float)$old['price'] < 0) {
        $errors[] = 'Price must be a non-negative number.';
    }
    if ($old['quantity'] === '' || !ctype_digit($old['quantity'])) {
        $errors[] = 'Quantity must be a whole number of 0 or more.';
    }
    if ($old['expiry_date'] !== '') {
        $d = DateTime::createFromFormat('Y-m-d', $old['expiry_date']);
        if (!$d || $d->format('Y-m-d') !== $old['expiry_date']) {
            $errors[] = 'Expiry date is invalid.';
        } elseif ($old['expiry_date'] < date('Y-m-d')) {
            $errors[] = 'Expiry date cannot be in the past.';
        }
    }

    $image = '';
    if (!$errors) {
        try {
            $image = handle_image_upload('image');
        } catch (RuntimeException $ex) {
            $errors[] = $ex->getMessage();
        }
    }

    if (!$errors) {
        $price = (float)$old['price'];
        $qty   = (int)$old['quantity'];
        $expiry = $old['expiry_date'] !== '' ? $old['expiry_date'] : null;
        $stmt = $conn->prepare(
            "INSERT INTO medicines
                (pharmacy_id, name, generic_name, category, description, manufacturer, price, quantity, expiry_date, image, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')"
        );
        $stmt->bind_param(
            'isssssdiss',
            $pid, $old['name'], $old['generic_name'], $old['category'], $old['description'],
            $old['manufacturer'], $price, $qty, $expiry, $image
        );
        $stmt->execute();
        $stmt->close();
        set_flash('success', 'Medicine added successfully.');
        redirect(base_url('pharmacy/medicines.php'));
    }
}

$pageTitle = 'Add Medicine';
require __DIR__ . '/../includes/header.php';
?>
<h1 class="page-title">Add Medicine</h1>
<?php if ($errors): ?>
  <div class="alert alert-error"><ul class="plain"><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="form-card">
  <?= csrf_field() ?>
  <div class="form-two-col">
    <label>Medicine Name <input type="text" name="name" value="<?= e($old['name'] ?? '') ?>" required></label>
    <label>Generic Name <input type="text" name="generic_name" value="<?= e($old['generic_name'] ?? '') ?>"></label>
    <label>Category <input type="text" name="category" value="<?= e($old['category'] ?? '') ?>"></label>
    <label>Manufacturer <input type="text" name="manufacturer" value="<?= e($old['manufacturer'] ?? '') ?>"></label>
    <label>Price (&#8377;) <input type="number" step="0.01" min="0" name="price" value="<?= e($old['price'] ?? '') ?>" required></label>
    <label>Quantity <input type="number" min="0" name="quantity" value="<?= e($old['quantity'] ?? '') ?>" required></label>
    <label>Expiry Date <input type="date" name="expiry_date" value="<?= e($old['expiry_date'] ?? '') ?>"></label>
    <label>Image <input type="file" name="image" accept="image/*"></label>
  </div>
  <label>Description <textarea name="description" rows="3"><?= e($old['description'] ?? '') ?></textarea></label>
  <button type="submit" class="btn btn-primary">Add Medicine</button>
  <a class="btn btn-outline" href="<?= e(base_url('pharmacy/medicines.php')) ?>">Cancel</a>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
