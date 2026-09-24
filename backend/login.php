<?php
/**
 * login.php — authenticate and redirect by role.
 */
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    redirect(role_home(current_role()));
}

$error = '';
$emailOld = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $emailOld = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($emailOld === '' || $password === '') {
        $error = 'Please enter both email and password.';
    } else {
        $stmt = $conn->prepare('SELECT id, name, password, role, status FROM users WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $emailOld);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // Same generic message whether the email or the password is wrong.
        if (!$user || !password_verify($password, $user['password'])) {
            $error = 'Invalid email or password.';
        } elseif ($user['status'] !== 'active') {
            $error = 'Your account is inactive. Please contact the administrator.';
        } else {
            // Prevent session fixation.
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['role']    = $user['role'];
            $_SESSION['name']    = $user['name'];

            // Rehash if PHP's default cost/algorithm has changed.
            if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $up = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
                $up->bind_param('si', $newHash, $user['id']);
                $up->execute();
                $up->close();
            }

            set_flash('success', 'Welcome back, ' . $user['name'] . '!');
            redirect(role_home($user['role']));
        }
    }
}

$pageTitle = 'Login';
require __DIR__ . '/includes/header.php';
?>
<div class="auth-wrapper">
  <div class="auth-card">
    <h1>Log in</h1>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" novalidate>
      <?= csrf_field() ?>
      <label>Email
        <input type="email" name="email" value="<?= e($emailOld) ?>" required autofocus>
      </label>
      <label>Password
        <span class="password-wrap">
          <input type="password" name="password" id="loginPassword" required>
          <button type="button" class="toggle-password" data-target="loginPassword">Show</button>
        </span>
      </label>
      <button type="submit" class="btn btn-primary btn-block">Log in</button>
    </form>
    <p class="auth-alt">No account yet? <a href="<?= e(base_url('register.php')) ?>">Register</a>.</p>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
