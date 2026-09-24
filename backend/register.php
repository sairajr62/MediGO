<?php
/**
 * register.php — create a User or Pharmacy account.
 */
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    redirect(role_home(current_role()));
}

$errors = [];
$old    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $old = [
        'name'          => trim($_POST['name'] ?? ''),
        'email'         => trim($_POST['email'] ?? ''),
        'phone'         => trim($_POST['phone'] ?? ''),
        'address'       => trim($_POST['address'] ?? ''),
        'role'          => $_POST['role'] ?? 'user',
        'pharmacy_name' => trim($_POST['pharmacy_name'] ?? ''),
        'license_number'=> trim($_POST['license_number'] ?? ''),
    ];
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    // ---- Server-side validation ----
    if ($old['name'] === '') {
        $errors[] = 'Full name is required.';
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email address is required.';
    }
    if ($old['phone'] !== '' && !preg_match('/^[0-9+\-\s]{7,20}$/', $old['phone'])) {
        $errors[] = 'Phone number format looks invalid.';
    }
    if (!in_array($old['role'], ['user', 'pharmacy'], true)) {
        $errors[] = 'Please choose a valid account type.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain at least one letter and one number.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Password and confirmation do not match.';
    }
    if ($old['role'] === 'pharmacy' && $old['pharmacy_name'] === '') {
        $errors[] = 'Pharmacy name is required for pharmacy accounts.';
    }

    // ---- Unique email check ----
    if (!$errors) {
        $stmt = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $old['email']);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()) {
            $errors[] = 'An account with that email already exists.';
        }
        $stmt->close();
    }

    // ---- Create account (transaction so pharmacy row stays consistent) ----
    if (!$errors) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare(
                'INSERT INTO users (name, email, phone, password, role, address, status)
                 VALUES (?, ?, ?, ?, ?, ?, "active")'
            );
            $stmt->bind_param(
                'ssssss',
                $old['name'], $old['email'], $old['phone'], $hash, $old['role'], $old['address']
            );
            $stmt->execute();
            $newUserId = $stmt->insert_id;
            $stmt->close();

            if ($old['role'] === 'pharmacy') {
                // New pharmacies start as "pending" until an admin approves them.
                $stmt = $conn->prepare(
                    'INSERT INTO pharmacies (user_id, pharmacy_name, address, phone, license_number, status)
                     VALUES (?, ?, ?, ?, ?, "pending")'
                );
                $stmt->bind_param(
                    'issss',
                    $newUserId, $old['pharmacy_name'], $old['address'], $old['phone'], $old['license_number']
                );
                $stmt->execute();
                $stmt->close();
            }

            $conn->commit();

            if ($old['role'] === 'pharmacy') {
                set_flash('success', 'Pharmacy account created. An administrator must approve it before you can list medicines. You can log in now.');
            } else {
                set_flash('success', 'Account created successfully. You can now log in.');
            }
            redirect(base_url('login.php'));
        } catch (mysqli_sql_exception $ex) {
            $conn->rollback();
            $errors[] = 'Could not create the account. Please try again.';
            error_log('Registration error: ' . $ex->getMessage());
        }
    }
}

$pageTitle = 'Register';
require __DIR__ . '/includes/header.php';
?>
<div class="auth-wrapper">
  <div class="auth-card">
    <h1>Create your account</h1>
    <p class="muted">Register as a customer to reserve medicines, or as a pharmacy to list your inventory.</p>

    <?php if ($errors): ?>
      <div class="alert alert-error">
        <ul class="plain"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
      </div>
    <?php endif; ?>

    <form method="post" id="registerForm" novalidate>
      <?= csrf_field() ?>
      <label>Account Type
        <select name="role" id="roleSelect" required>
          <option value="user"     <?= ($old['role'] ?? 'user') === 'user' ? 'selected' : '' ?>>Customer (User)</option>
          <option value="pharmacy" <?= ($old['role'] ?? '') === 'pharmacy' ? 'selected' : '' ?>>Pharmacy</option>
        </select>
      </label>

      <label>Full Name
        <input type="text" name="name" value="<?= e($old['name'] ?? '') ?>" required>
      </label>

      <div class="pharmacy-fields" id="pharmacyFields" style="<?= ($old['role'] ?? '') === 'pharmacy' ? '' : 'display:none' ?>">
        <label>Pharmacy Name
          <input type="text" name="pharmacy_name" value="<?= e($old['pharmacy_name'] ?? '') ?>">
        </label>
        <label>License Number
          <input type="text" name="license_number" value="<?= e($old['license_number'] ?? '') ?>">
        </label>
      </div>

      <label>Email
        <input type="email" name="email" value="<?= e($old['email'] ?? '') ?>" required>
      </label>
      <label>Phone Number
        <input type="text" name="phone" value="<?= e($old['phone'] ?? '') ?>">
      </label>
      <label>Address
        <input type="text" name="address" value="<?= e($old['address'] ?? '') ?>">
      </label>

      <label>Password
        <span class="password-wrap">
          <input type="password" name="password" id="password" minlength="8" required>
          <button type="button" class="toggle-password" data-target="password">Show</button>
        </span>
        <small class="muted">At least 8 characters, including a letter and a number.</small>
      </label>
      <label>Confirm Password
        <input type="password" name="confirm_password" id="confirm_password" minlength="8" required>
      </label>

      <button type="submit" class="btn btn-primary btn-block">Register</button>
    </form>
    <p class="auth-alt">Already have an account? <a href="<?= e(base_url('login.php')) ?>">Log in</a>.</p>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
