<?php
/**
 * database/create_admin.php
 * -----------------------------------------------------------
 * One-time bootstrap script to create the first administrator.
 * Visit this page in the browser, submit the form, then DELETE
 * this file. It refuses to run if an admin already exists.
 */
require_once __DIR__ . '/db.php';

$done  = false;
$error = '';

// Block the script once at least one admin exists.
$res = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role = 'admin'");
$adminCount = (int)($res->fetch_assoc()['c'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $adminCount === 0) {
    $name  = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    if ($name === '' || $email === '' || strlen($pass) < 8) {
        $error = 'Name, a valid email, and a password of at least 8 characters are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $stmt = $conn->prepare(
            "INSERT INTO users (name, email, phone, password, role, address, status)
             VALUES (?, ?, '', ?, 'admin', 'Head Office', 'active')"
        );
        $stmt->bind_param('sss', $name, $email, $hash);
        $stmt->execute();
        $stmt->close();
        $done = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create Administrator</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="auth-body">
    <div class="auth-card">
        <h1>Create Administrator</h1>
        <?php if ($adminCount > 0): ?>
            <div class="alert alert-error">An administrator already exists. For security, delete this file (database/create_admin.php).</div>
        <?php elseif ($done): ?>
            <div class="alert alert-success">Administrator created. Please <strong>delete database/create_admin.php</strong> now, then <a href="../login.php">log in</a>.</div>
        <?php else: ?>
            <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <form method="post" autocomplete="off">
                <label>Full Name
                    <input type="text" name="name" required>
                </label>
                <label>Email
                    <input type="email" name="email" required>
                </label>
                <label>Password (min 8 characters)
                    <input type="password" name="password" minlength="8" required>
                </label>
                <button type="submit" class="btn btn-primary btn-block">Create Admin</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
