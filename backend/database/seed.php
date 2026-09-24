<?php
/**
 * database/seed.php
 * -----------------------------------------------------------
 * Inserts sample pharmacies, medicines and a demo customer so
 * you can try the search / reserve flow immediately.
 *
 * Visit once in the browser:
 *   http://localhost/medicine_checker/database/seed.php
 * then DELETE this file.
 *
 * Safe to re-run: it skips seeding if the demo data already exists.
 *
 * Demo logins (all approved pharmacies are searchable):
 *   Pharmacy : abc@pharmacy.local     / Pharma@123   (approved)
 *   Pharmacy : xyz@pharmacy.local     / Pharma@123   (approved)
 *   Pharmacy : health@pharmacy.local  / Pharma@123   (pending, hidden from search)
 *   Customer : rahul@user.local       / User@123
 */
require_once __DIR__ . '/db.php';

header('Content-Type: text/plain; charset=utf-8');

// ---- Guard: don't double-seed ----
$check = $conn->prepare("SELECT id FROM users WHERE email = 'abc@pharmacy.local' LIMIT 1");
$check->execute();
if ($check->get_result()->fetch_assoc()) {
    exit("Demo data already exists. Nothing to do.\nDelete database/seed.php when you are finished.\n");
}
$check->close();

$pharmaHash = password_hash('Pharma@123', PASSWORD_DEFAULT);
$userHash   = password_hash('User@123', PASSWORD_DEFAULT);

/** Create a pharmacy owner user + pharmacy row, return pharmacy id. */
function make_pharmacy(mysqli $conn, string $owner, string $email, string $phone,
                       string $hash, string $pharmName, string $address,
                       string $license, string $status): int
{
    $stmt = $conn->prepare(
        "INSERT INTO users (name, email, phone, password, role, address, status)
         VALUES (?, ?, ?, ?, 'pharmacy', ?, 'active')"
    );
    $stmt->bind_param('sssss', $owner, $email, $phone, $hash, $address);
    $stmt->execute();
    $uid = $stmt->insert_id;
    $stmt->close();

    $stmt = $conn->prepare(
        "INSERT INTO pharmacies (user_id, pharmacy_name, address, phone, license_number, status)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param('isssss', $uid, $pharmName, $address, $phone, $license, $status);
    $stmt->execute();
    $pid = $stmt->insert_id;
    $stmt->close();
    return $pid;
}

/** Insert a medicine for a pharmacy. */
function make_medicine(mysqli $conn, int $pid, string $name, string $generic,
                       string $category, float $price, int $qty, string $expiry): void
{
    $desc = $name . ' supplied by the pharmacy.';
    $manu = 'Generic Labs';
    $stmt = $conn->prepare(
        "INSERT INTO medicines
            (pharmacy_id, name, generic_name, category, description, manufacturer, price, quantity, expiry_date, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')"
    );
    $stmt->bind_param('issssdiss', $pid, $name, $generic, $category, $desc, $manu, $price, $qty, $expiry);
    $stmt->execute();
    $stmt->close();
}

$conn->begin_transaction();
try {
    // ---- Demo customer ----
    $stmt = $conn->prepare(
        "INSERT INTO users (name, email, phone, password, role, address, status)
         VALUES ('Rahul Sharma', 'rahul@user.local', '9876500000', ?, 'user', '12 MG Road, Bengaluru', 'active')"
    );
    $stmt->bind_param('s', $userHash);
    $stmt->execute();
    $stmt->close();

    // ---- Pharmacies ----
    $abc = make_pharmacy($conn, 'Anil Kumar', 'abc@pharmacy.local', '9876511111',
        $pharmaHash, 'ABC Pharmacy', '45 Church Street, Bengaluru', 'KA-ABC-001', 'approved');

    $xyz = make_pharmacy($conn, 'Sara Ali', 'xyz@pharmacy.local', '9876522222',
        $pharmaHash, 'XYZ Pharmacy', '9 Brigade Road, Bengaluru', 'KA-XYZ-002', 'approved');

    $hp = make_pharmacy($conn, 'Ravi Menon', 'health@pharmacy.local', '9876533333',
        $pharmaHash, 'HealthPlus Pharmacy', '3 Residency Road, Bengaluru', 'KA-HP-003', 'pending');

    $future = date('Y-m-d', strtotime('+2 years'));

    // ---- ABC Pharmacy inventory (approved) ----
    make_medicine($conn, $abc, 'Paracetamol 500mg', 'Paracetamol', 'Pain Relief', 25.00, 50, $future);
    make_medicine($conn, $abc, 'Amoxicillin 250mg', 'Amoxicillin', 'Antibiotic', 80.00, 8,  $future);   // low
    make_medicine($conn, $abc, 'Cetirizine 10mg',   'Cetirizine', 'Allergy',     15.00, 0,  $future);   // out
    make_medicine($conn, $abc, 'Vitamin C 500mg',   'Ascorbic Acid', 'Supplement', 120.00, 100, $future);

    // ---- XYZ Pharmacy inventory (approved) ----
    make_medicine($conn, $xyz, 'Paracetamol 650mg', 'Paracetamol', 'Pain Relief', 30.00, 0,  $future);  // out
    make_medicine($conn, $xyz, 'Ibuprofen 400mg',   'Ibuprofen',   'Pain Relief', 40.00, 35, $future);
    make_medicine($conn, $xyz, 'Azithromycin 500mg','Azithromycin','Antibiotic',  110.00, 5, $future);  // low
    make_medicine($conn, $xyz, 'Omeprazole 20mg',   'Omeprazole',  'Gastro',      55.00, 60, $future);

    // ---- HealthPlus inventory (pending pharmacy -> hidden from public search) ----
    make_medicine($conn, $hp, 'Metformin 500mg', 'Metformin', 'Diabetes', 45.00, 40, $future);

    $conn->commit();

    echo "Demo data inserted successfully.\n\n";
    echo "Pharmacy logins (password Pharma@123):\n";
    echo "  abc@pharmacy.local     ABC Pharmacy       (approved)\n";
    echo "  xyz@pharmacy.local     XYZ Pharmacy       (approved)\n";
    echo "  health@pharmacy.local  HealthPlus Pharmacy(pending)\n\n";
    echo "Customer login: rahul@user.local / User@123\n\n";
    echo "Try searching 'Paracetamol' to see Available and Out-of-Stock rows.\n";
    echo "HealthPlus is pending, so its Metformin will NOT appear until an admin approves it.\n\n";
    echo "IMPORTANT: delete database/seed.php now.\n";
} catch (Throwable $ex) {
    $conn->rollback();
    http_response_code(500);
    echo "Seeding failed: " . $ex->getMessage() . "\n";
}
