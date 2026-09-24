<?php
/**
 * search.php — public medicine search across approved pharmacies.
 */
require_once __DIR__ . '/includes/functions.php';

$q          = trim($_GET['q'] ?? '');
$category   = trim($_GET['category'] ?? '');
$location   = trim($_GET['location'] ?? '');
$availability = $_GET['availability'] ?? '';   // '', 'in', 'out'

// Build a parameterised query dynamically.
$sql = "SELECT m.id, m.name, m.generic_name, m.category, m.price, m.quantity,
               m.expiry_date, m.image, p.pharmacy_name, p.address AS pharmacy_address, p.id AS pharmacy_id
        FROM medicines m
        JOIN pharmacies p ON p.id = m.pharmacy_id
        WHERE m.status = 'active'
          AND p.status = 'approved'
          AND (m.expiry_date IS NULL OR m.expiry_date >= CURDATE())";

$params = [];
$types  = '';

if ($q !== '') {
    $sql .= " AND (m.name LIKE ? OR m.generic_name LIKE ?)";
    $like = '%' . $q . '%';
    $params[] = $like; $params[] = $like; $types .= 'ss';
}
if ($category !== '') {
    $sql .= " AND m.category = ?";
    $params[] = $category; $types .= 's';
}
if ($location !== '') {
    $sql .= " AND (p.pharmacy_name LIKE ? OR p.address LIKE ?)";
    $loc = '%' . $location . '%';
    $params[] = $loc; $params[] = $loc; $types .= 'ss';
}
if ($availability === 'in') {
    $sql .= " AND m.quantity > 0";
} elseif ($availability === 'out') {
    $sql .= " AND m.quantity = 0";
}

$sql .= " ORDER BY (m.quantity > 0) DESC, m.name ASC LIMIT 200";

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$results = $stmt->get_result();
$rows = $results->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Distinct categories for the filter dropdown.
$cats = [];
$cres = $conn->query("SELECT DISTINCT category FROM medicines
                      WHERE category IS NOT NULL AND category <> '' AND status='active'
                      ORDER BY category");
while ($c = $cres->fetch_assoc()) {
    $cats[] = $c['category'];
}

$pageTitle = 'Search Medicines';
require __DIR__ . '/includes/header.php';
?>
<h1 class="page-title">Search Medicines</h1>

<form class="search-panel" method="get" action="<?= e(base_url('search.php')) ?>">
  <div class="search-grid">
    <input type="text" name="q" value="<?= e($q) ?>" placeholder="Medicine or generic name">
    <select name="category">
      <option value="">All categories</option>
      <?php foreach ($cats as $c): ?>
        <option value="<?= e($c) ?>" <?= $c === $category ? 'selected' : '' ?>><?= e($c) ?></option>
      <?php endforeach; ?>
    </select>
    <input type="text" name="location" value="<?= e($location) ?>" placeholder="Pharmacy or area">
    <select name="availability">
      <option value=""    <?= $availability === ''    ? 'selected' : '' ?>>Any availability</option>
      <option value="in"  <?= $availability === 'in'   ? 'selected' : '' ?>>In stock</option>
      <option value="out" <?= $availability === 'out'  ? 'selected' : '' ?>>Out of stock</option>
    </select>
    <button type="submit" class="btn btn-primary">Search</button>
  </div>
</form>

<p class="muted"><?= count($rows) ?> result<?= count($rows) === 1 ? '' : 's' ?> found.</p>

<div class="table-wrap">
<table class="data-table">
  <thead>
    <tr>
      <th>Medicine</th><th>Generic</th><th>Category</th><th>Pharmacy</th>
      <th>Price</th><th>Available</th><th>Status</th><th></th>
    </tr>
  </thead>
  <tbody>
  <?php if (!$rows): ?>
    <tr><td colspan="8" class="empty">No medicines matched your search.</td></tr>
  <?php else: foreach ($rows as $r):
      [$label, $badge] = stock_status((int)$r['quantity']); ?>
    <tr>
      <td data-label="Medicine"><strong><?= e($r['name']) ?></strong></td>
      <td data-label="Generic"><?= e($r['generic_name'] ?: '—') ?></td>
      <td data-label="Category"><?= e($r['category'] ?: '—') ?></td>
      <td data-label="Pharmacy"><?= e($r['pharmacy_name']) ?><br><small class="muted"><?= e($r['pharmacy_address'] ?: '') ?></small></td>
      <td data-label="Price">&#8377;<?= e(number_format((float)$r['price'], 2)) ?></td>
      <td data-label="Available"><?= (int)$r['quantity'] ?></td>
      <td data-label="Status"><span class="badge <?= $badge ?>"><?= e($label) ?></span></td>
      <td data-label="">
        <?php if ((int)$r['quantity'] > 0): ?>
          <a class="btn btn-sm btn-primary" href="<?= e(base_url('reserve.php?medicine_id=' . (int)$r['id'])) ?>">Reserve</a>
        <?php else: ?>
          <button class="btn btn-sm" disabled>Unavailable</button>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; endif; ?>
  </tbody>
</table>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
