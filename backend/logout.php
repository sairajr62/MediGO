<?php
/**
 * logout.php — destroy the session and return to home.
 */
require_once __DIR__ . '/includes/functions.php';

// Clear all session data.
$_SESSION = [];

// Remove the session cookie.
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}

session_destroy();

// Fresh session just to carry a flash message.
session_start();
set_flash('success', 'You have been logged out.');
redirect(base_url('login.php'));
