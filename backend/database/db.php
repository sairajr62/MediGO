<?php
/**
 * database/db.php
 * -----------------------------------------------------------
 * MySQLi database connection used by every page.
 * Always use prepared statements for any query that includes
 * user-supplied input.
 */

// Report errors as exceptions during development so mistakes are
// visible. Comment the next line out in production if you prefer
// to log errors quietly instead of showing them.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';           // XAMPP's MySQL uses an empty root password by default.
$DB_NAME = 'medicine_checker';

try {
    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    // Do not leak connection details to the browser.
    error_log('DB connection failed: ' . $e->getMessage());
    http_response_code(500);
    die('Database connection failed. Please try again later.');
}
