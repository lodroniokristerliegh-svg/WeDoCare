<?php
/**
 * WeDoCare — Database Configuration
 * Northeastern Mindanao Colleges — Capstone Project and Research 1
 *
 * Edit the constants below to match your local MySQL setup
 * (e.g. XAMPP/WAMP default: host=localhost, user=root, password='').
 */

// Don't show raw PHP warnings/errors to end users (doctor/patient) — log
// them instead. Set display_errors back to '1' temporarily if you need to
// debug something during development.
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
if (!is_dir(__DIR__ . '/logs')) { @mkdir(__DIR__ . '/logs', 0755, true); }
ini_set('error_log', __DIR__ . '/logs/php_errors.log');

define('DB_HOST', 'localhost');
define('DB_NAME', 'wedocare_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// --- Compatibility shim for very old PHP builds (pre-5.5) --------------
// password_hash()/password_verify() are core PHP since 5.5.0. If your
// WAMP install is running an older PHP, they show up as "undefined
// function" fatal errors even though nothing in this codebase is wrong.
// This block only activates if those functions are genuinely missing —
// on any normal, up-to-date PHP install it does nothing at all.
if (!function_exists('password_hash')) {
    define('PASSWORD_BCRYPT', 1);
    define('PASSWORD_DEFAULT', PASSWORD_BCRYPT);

    function password_hash($password, $algo, array $options = array()) {
        if (!function_exists('crypt')) {
            trigger_error('crypt() is required for password_hash() to work', E_USER_WARNING);
            return null;
        }
        $cost = isset($options['cost']) ? (int) $options['cost'] : 10;
        if ($cost < 4 || $cost > 31) {
            trigger_error('password_hash(): invalid cost parameter', E_USER_WARNING);
            return null;
        }
        $raw = '';
        for ($i = 0; $i < 18; $i++) { $raw .= chr(mt_rand(0, 255)); }
        $salt = substr(str_replace('+', '.', base64_encode($raw)), 0, 22);
        $hash = crypt($password, sprintf('$2y$%02d$', $cost) . $salt);
        return (is_string($hash) && strlen($hash) >= 60) ? $hash : false;
    }

    function password_verify($password, $hash) {
        if (!function_exists('crypt')) {
            trigger_error('crypt() is required for password_verify() to work', E_USER_WARNING);
            return false;
        }
        $check = crypt($password, $hash);
        if (!is_string($check) || strlen($check) !== strlen($hash)) {
            return false;
        }
        // Constant-time comparison so this doesn't leak timing info.
        $diff = 0;
        for ($i = 0; $i < strlen($check); $i++) {
            $diff |= ord($check[$i]) ^ ord($hash[$i]);
        }
        return $diff === 0;
    }
}
// -------------------------------------------------------------------------

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die('Database connection failed: ' . htmlspecialchars($e->getMessage()));
}

// Start the PHP session used for login/logout state across all pages.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Self-healing database check: the booking, fee and payment pages need these columns.
// If the database was created from an older schema, add them once (safe to repeat).
// This fixes: "Unknown column 'doctor_id' in 'where clause'".
if (empty($_SESSION['schema_checked_v2'])) {
    try {
        $neededColumns = array(
            array('appointments', 'doctor_id',        'INT NULL'),
            array('users',        'consultation_fee', 'DECIMAL(10,2) NULL'),
            array('users',        'medcert_fee',      'DECIMAL(10,2) NULL'),
        );
        foreach ($neededColumns as $c) {
            $has = $pdo->query("SHOW COLUMNS FROM `" . $c[0] . "` LIKE '" . $c[1] . "'")->fetch();
            if (!$has) { $pdo->exec("ALTER TABLE `" . $c[0] . "` ADD COLUMN `" . $c[1] . "` " . $c[2]); }
        }
        $_SESSION['schema_checked_v2'] = 1;
    } catch (Exception $e) {
        error_log('WeDoCare schema check failed: ' . $e->getMessage());
    }
}
