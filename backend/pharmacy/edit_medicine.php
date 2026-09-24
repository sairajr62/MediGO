<?php
/**
 * pharmacy/edit_medicine.php — update a medicine owned by this pharmacy.
 */
require_once __DIR__ . '/../includes/functions.php';
require_login('pharmacy');

$pharmacy = current_pharmacy($conn);
if (!$pharmacy) { redirect(base_url('logout.php')); }
$pid = (int)$pharmacy['id'];

$mid = (int)($_GET['id'] ?? $_POST['medicine_id'] ?? 0);

/** Fetch a medicine that belongs to this pharmacy. */
function fetch_owned(mysqli $conn, int $mid, int $pid): ?array
{
    $stmt = $conn->prepare('SELECT * FROM medicines WHERE id = ? AND pharmacy_id = ? LIMIT 1');
    $stmt->bind_param('ii', $mid, $pid);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

$medicine = fetch_owned($conn, $mid, $pid);
if (!$medicine) {
    set_flash('error', 'Medicine not found or not owned by your pharmacy.');
    redirect(base_url('pharmacy/medicines.php'));
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name        = trim($_POST['name'] ?? '');
    $generic     = trim($_POST['generic_name'] ?? '');
    $category    = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $manufacturer= trim($_POST['manufacturer'] ?? '');
    $price       = trim($_POST['price'] ?? '');
    $quantity    = trim($_POST['quantity'] ?? '');
    $expiry      = trim($_POST['expiry_date'] ?? '');

    if ($name === '') { $errors[] = 'Medicine name is required.'; }
    if (!is_numeric($price) || (float)$price < 0) { $errors[] = 'Price must be a non-negative number.'; }
    if ($quantity === '' || !ctype_digit($quantity)) { $errors[] = 'Quantity must be 0 or more.'; }
    if ($expiry !== '') {
        $d = DateTime::createFromFormat('Y-m-d', $expiry);
        if (!$d || $d->format('Y-m-d') !== $expiry) { $errors[] = 'Expiry date is invalid.'; }
    }

    $newImage = $medicine['image'];
    if (!$errors) {
        try {
            $uploaded = handle_image_upload('image');
            if ($uploaded !== '') { $newImage = $uploaded; }
        } catch (RuntimeException $ex) {
            $errors[] = $ex->getMessage();
        }
    }

    if (!$errors) {
        $priceF = (float)$price;
        $qtyI   = (int)$quantity;
        $expiryVal = $expiry !== '' ? $expiry : null;
        $stmt = $conn->prepare(
            "UPDATE medicines SET name=?, generic_name=?, category=?, description=?,
                    manufacturer=?, price=?, quantity=?, expiry_date=?, image=?
             WHERE id=? AND pharmacy_id=?"
        );
        $stmt->bind_param(
            'sssssdissii',
            $name, $generic, $category, $description, $manufacturer,
            $priceF, $qtyI, $expiryVal, $newImage, $mid, $pid
        );
        $stmt->execute();
        $stmt->close();
        set_flash('success', 'Medicine updated.');
        redirect(base_url('pharmacy/medicines.php'));
    }
    // Reflect submitted values back into the form on error.
    $medicine = array_merge($medicine, [
        'name' => $name, 'generic_name' => $generic, 'category' => $category,
        'description' => $description, 'manufacturer' => $manufacturer,
        'price' => $price, 'quantity' => $quantity, 'expiry_date' => $expiry,
    ]);
}

$pageTitle = 'Edit Medicine';
require __DIR__ . '/../includes/header.php';
?>
<h1 class="page-title">Edit Medicine</h1>
<?php if ($errors): ?>
  <div class="alert alert-error"><ul class="plain"><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="form-card">
  <?= csrf_field() ?>
  <input type="hidden" name="medicine_id" value="<?= (int)$medicine['id'] ?>">
  <div class="form-two-col">
    <label>Medicine Name <input type="text" name="name" value="<?= e($medicine['name']) ?>" required></label>
    <label>Generic Name <input type="text" name="generic_name" value="<?= e($medicine['generic_name']) ?>"></label>
    <label>Category <input type="text" name="category" value="<?= e($medicine['category']) ?>"></label>
    <label>Manufacturer <input type="text" name="manufacturer" value="<?= e($medicine['manufacturer']) ?>"></label>
    <label>Price (&#8377;) <input type="number" step="0.01" min="0" name="price" value="<?= e($medicine['price']) ?>" required></label>
    <label>Quantity <input type="number" min="0" name="quantity" value="<?= e($medicine['quantity']) ?>" required></label>
    <label>Expiry Date <input type="date" name="expiry_date" value="<?= e($medicine['expiry_date']) ?>"></label>
    <label>Replace Image <input type="file" name="image" accept="image/*"></label>
  </div>
  <label>Description <textarea name="description" rows="3"><?= e($medicine['description']) ?></textarea></label>
  <?php if (!empty($medicine['image'])): ?>
    <p class="muted">Current image: <?= e($medicine['image']) ?></p>
  <?php endif; ?>
  <button type="submit" class="btn btn-primary">Save Changes</button>
  <a class="btn btn-outline" href="<?= e(base_url('pharmacy/medicines.php')) ?>">Cancel</a>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
