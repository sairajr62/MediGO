<?php
/**
 * includes/header.php
 * Shared top-of-page markup + navigation.
 * A page may set $pageTitle before including this file.
 */
if (!isset($pageTitle)) {
    $pageTitle = 'MediCheck';
}
$role = current_role();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> &middot; MediCheck</title>
    <link rel="stylesheet" href="<?= e(base_url('css/style.css')) ?>">
</head>
<body>
<nav class="navbar">
    <div class="navbar-inner">
        <a class="navbar-brand" href="<?= e(base_url('index.php')) ?>">
            <span class="brand-mark">Rx</span> MediCheck
        </a>
        <button class="navbar-toggle" id="navToggle" aria-label="Toggle menu">&#9776;</button>
        <div class="navbar-links" id="navLinks">
            <a href="<?= e(base_url('index.php')) ?>">Home</a>
            <a href="<?= e(base_url('search.php')) ?>">Search Medicines</a>
            <?php if (is_logged_in()): ?>
                <a href="<?= e(role_home($role)) ?>">Dashboard</a>
                <?php if ($role === 'user'): ?>
                    <a href="<?= e(base_url('user/my_reservations.php')) ?>">My Reservations</a>
                <?php endif; ?>
                <a class="btn btn-outline btn-sm" href="<?= e(base_url('logout.php')) ?>">Logout</a>
            <?php else: ?>
                <a href="<?= e(base_url('login.php')) ?>">Login</a>
                <a class="btn btn-primary btn-sm" href="<?= e(base_url('register.php')) ?>">Register</a>
            <?php endif; ?>
        </div>
    </div>
</nav>
<main class="container">
<?php foreach (get_flashes() as $flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?>" data-dismiss>
        <?= e($flash['message']) ?>
        <span class="alert-close">&times;</span>
    </div>
<?php endforeach; ?>
