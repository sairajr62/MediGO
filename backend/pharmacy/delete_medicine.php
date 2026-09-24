<?php
/**
 * pharmacy/delete_medicine.php — soft-delete (deactivate) a medicine.
 * We never physically delete: reservations reference the row, so we
 * set status = 'inactive' to preserve history. POST + CSRF only.
 */
require_once __DIR__ . '/../includes/functions.php';
require_login('pharmacy');

$pharmacy = current_pharmacy($conn);
if (!$pharmacy) { redirect(base_url('logout.php')); }
$pid = (int)$pharmacy['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(base_url('pharmacy/medicines.php'));
}
verify_csrf();

$mid = (int)($_POST['medicine_id'] ?? 0);
$stmt = $conn->prepare("UPDATE medicines SET status = 'inactive' WHERE id = ? AND pharmacy_id = ?");
$stmt->bind_param('ii', $mid, $pid);
$stmt->execute();
$affected = $stmt->affected_rows;
$stmt->close();

set_flash($affected ? 'success' : 'error',
    $affected ? 'Medicine removed from your active inventory.' : 'Medicine not found.');
redirect(base_url('pharmacy/medicines.php'));
