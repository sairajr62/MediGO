<?php
/**
 * includes/functions.php
 * -----------------------------------------------------------
 * Shared bootstrap: session, CSRF, auth guards, helpers.
 * Include this at the very top of every PHP page:
 *     require_once __DIR__ . '/../includes/functions.php';
 */

// ---- Session -------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once __DIR__ . '/../database/db.php';

/**
 * Base URL/path helper so links work regardless of the folder
 * the app is installed under. Adjust APP_BASE if needed.
 */
if (!defined('APP_BASE')) {
    // Directory of the front controller relative to web root.
    $script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    // Strip trailing /admin, /pharmacy, /user, /database so base points at project root.
    $script = preg_replace('#/(admin|pharmacy|user|database)$#', '', $script);
    define('APP_BASE', rtrim($script, '/'));
}

function base_url(string $path = ''): string
{
    return APP_BASE . '/' . ltrim($path, '/');
}

// ---- Output escaping ----------------------------------------
function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// ---- CSRF ----------------------------------------------------
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $sent = $_POST['csrf_token'] ?? '';
    if (!is_string($sent) || !hash_equals($_SESSION['csrf_token'] ?? '', $sent)) {
        http_response_code(419);
        die('Invalid or expired form token. Please go back and try again.');
    }
}

// ---- Flash messages -----------------------------------------
function set_flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

// ---- Auth helpers -------------------------------------------
function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

function current_role(): ?string
{
    return $_SESSION['role'] ?? null;
}

function current_user_id(): ?int
{
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

/** Redirect helper. */
function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

/** Require a logged-in session; optionally restrict to one or more roles. */
function require_login($roles = null): void
{
    if (!is_logged_in()) {
        set_flash('error', 'Please log in to continue.');
        redirect(base_url('login.php'));
    }
    if ($roles !== null) {
        $roles = (array)$roles;
        if (!in_array(current_role(), $roles, true)) {
            http_response_code(403);
            die('Access denied: you do not have permission to view this page.');
        }
    }
}

/**
 * For a logged-in pharmacy user, return their pharmacy row (or null).
 * Cached on first call per request.
 */
function current_pharmacy(mysqli $conn): ?array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache ?: null;
    }
    $uid = current_user_id();
    if (!$uid) {
        return null;
    }
    $stmt = $conn->prepare('SELECT * FROM pharmacies WHERE user_id = ? LIMIT 1');
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $cache = $stmt->get_result()->fetch_assoc() ?: false;
    $stmt->close();
    return $cache ?: null;
}

/** Map a medicine quantity to a stock status label + css class. */
function stock_status(int $quantity, int $lowThreshold = 10): array
{
    if ($quantity <= 0) {
        return ['Out of Stock', 'badge-danger'];
    }
    if ($quantity <= $lowThreshold) {
        return ['Low Stock', 'badge-warning'];
    }
    return ['Available', 'badge-success'];
}

/** Human-friendly badge class for a reservation status. */
function reservation_badge(string $status): string
{
    return [
        'pending'   => 'badge-warning',
        'confirmed' => 'badge-info',
        'collected' => 'badge-success',
        'cancelled' => 'badge-muted',
        'expired'   => 'badge-danger',
    ][$status] ?? 'badge-muted';
}

/** Dashboard home path for the current role. */
function role_home(?string $role): string
{
    switch ($role) {
        case 'admin':    return base_url('admin/dashboard.php');
        case 'pharmacy': return base_url('pharmacy/dashboard.php');
        case 'user':     return base_url('user/dashboard.php');
        default:         return base_url('index.php');
    }
}

/**
 * Validate and store an uploaded medicine image.
 * Returns the stored filename on success, '' if no file was sent,
 * or throws RuntimeException with a user-facing message on error.
 * Files are saved under /uploads/medicine/.
 */
function handle_image_upload(string $field): string
{
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return '';
    }
    $file = $_FILES[$field];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Image upload failed. Please try again.');
    }
    if ($file['size'] > 2 * 1024 * 1024) {
        throw new RuntimeException('Image must be 2 MB or smaller.');
    }

    // Verify the real MIME type, not the client-supplied one.
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Only JPG, PNG, WEBP or GIF images are allowed.');
    }

    $dir = __DIR__ . '/../uploads/medicine';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $name = 'med_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        throw new RuntimeException('Could not save the uploaded image.');
    }
    return $name;
}
