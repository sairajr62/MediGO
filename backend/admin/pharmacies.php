<?php
/**
 * admin/pharmacies.php — approve / reject / suspend / reactivate pharmacies.
 */
require_once __DIR__ . '/../includes/functions.php';
require_login('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id     = (int)($_POST['pharmacy_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $map = [
        'approve' => 'approved',
        'reject'  => 'rejected',
        'suspend' => 'suspended',
        'pending' => 'pending',
    ];
    if (isset($map[$action])) {
        $new = $map[$action];
        $stmt = $conn->prepare('UPDATE pharmacies SET status = ? WHERE id = ?');
        $stmt->bind_param('si', $new, $id);
        $stmt->execute();
        $stmt->close();
        set_flash('success', 'Pharmacy status set to ' . $new . '.');
    } else {
        set_flash('error', 'Unknown action.');
    }
    redirect(base_url('admin/pharmacies.php'));
}

$filter = $_GET['status'] ?? '';
$sql = "SELECT p.*, u.name AS owner, u.email, u.status AS user_status
        FROM pharmacies p JOIN users u ON u.id = p.user_id";
$params = []; $types = '';
if (in_array($filter, ['pending','approved','rejected','suspended'], true)) {
    $sql .= ' WHERE p.status = ?';
    $params[] = $filter; $types = 's';
}
$sql .= ' ORDER BY p.created_at DESC';
$stmt = $conn->prepare($sql);
if ($params) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$badges = ['pending'=>'badge-warning','approved'=>'badge-success','rejected'=>'badge-danger','suspended'=>'badge-muted'];

$pageTitle = 'Manage Pharmacies';
require __DIR__ . '/../includes/header.php';
?>
<h1 class="page-title">Manage Pharmacies</h1>
<form method="get" class="filter-bar">
  <select name="status" onchange="this.form.submit()">
    <option value="">All statuses</option>
    <?php foreach (['pending','approved','rejected','suspended'] as $s): ?>
      <option value="<?= $s ?>" <?= $filter === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
    <?php endforeach; ?>
  </select>
</form>

<div class="table-wrap">
<table class="data-table">
  <thead><tr><th>Pharmacy</th><th>Owner</th><th>License</th><th>Phone</th><th>Status</th><th>Actions</th></tr></thead>
  <tbody>
  <?php if (!$rows): ?>
    <tr><td colspan="6" class="empty">No pharmacies found.</td></tr>
  <?php else: foreach ($rows as $r): ?>
    <tr>
      <td data-label="Pharmacy"><strong><?= e($r['pharmacy_name']) ?></strong><br><small class="muted"><?= e($r['address'] ?: '') ?></small></td>
      <td data-label="Owner"><?= e($r['owner']) ?><br><small class="muted"><?= e($r['email']) ?></small></td>
      <td data-label="License"><?= e($r['license_number'] ?: '—') ?></td>
      <td data-label="Phone"><?= e($r['phone'] ?: '—') ?></td>
      <td data-label="Status"><span class="badge <?= $badges[$r['status']] ?? 'badge-muted' ?>"><?= e(ucfirst($r['status'])) ?></span></td>
      <td data-label="Actions">
        <div class="action-row">
          <?php if ($r['status'] !== 'approved'): ?><?= admin_pharm_form($r['id'],'approve','Approve','btn-success') ?><?php endif; ?>
          <?php if ($r['status'] === 'pending'): ?><?= admin_pharm_form($r['id'],'reject','Reject','btn-danger') ?><?php endif; ?>
          <?php if ($r['status'] === 'approved'): ?><?= admin_pharm_form($r['id'],'suspend','Suspend','btn-outline','Suspend this pharmacy?') ?><?php endif; ?>
          <?php if (in_array($r['status'], ['suspended','rejected'], true)): ?><?= admin_pharm_form($r['id'],'approve','Reactivate','btn-primary') ?><?php endif; ?>
        </div>
      </td>
    </tr>
  <?php endforeach; endif; ?>
  </tbody>
</table>
</div>
<?php
require __DIR__ . '/../includes/footer.php';

function admin_pharm_form(int $id, string $action, string $label, string $cls, ?string $confirm = null): string
{
    $c = $confirm ? ' data-confirm="' . e($confirm) . '"' : '';
    return '<form method="post" class="inline"' . $c . '>'
        . csrf_field()
        . '<input type="hidden" name="pharmacy_id" value="' . (int)$id . '">'
        . '<input type="hidden" name="action" value="' . e($action) . '">'
        . '<button class="btn btn-sm ' . e($cls) . '">' . e($label) . '</button></form>';
}
?>
