<?php
declare(strict_types=1);

/**
 * Loaded first by every entry point (public pages, API, admin, CLI scripts).
 * It loads the configuration, sets the timezone and error handling,
 * registers the class autoloader and sends basic security headers.
 */

define('APP_ROOT', dirname(__DIR__));

if (PHP_VERSION_ID < 80100) {
    exit('Sunset Brunch needs PHP 8.1 or newer (developed on PHP 8.4).' . PHP_EOL);
}

// --- Configuration ---------------------------------------------------------
$configFile = APP_ROOT . '/config/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    exit('Missing config/config.php. Copy config/config.example.php to config/config.php and edit it.' . PHP_EOL);
}

// The configuration is stored in a constant so it cannot be changed at runtime.
define('APP_CONFIG', (require $configFile) + ['booking' => require APP_ROOT . '/config/booking.php']);

require APP_ROOT . '/src/helpers.php';

// --- Time and text ---------------------------------------------------------
date_default_timezone_set(config('app.timezone', 'Africa/Casablanca'));
mb_internal_encoding('UTF-8');

// --- Errors ----------------------------------------------------------------
// Report everything, but only display errors in debug mode. Always log them.
error_reporting(E_ALL);
ini_set('display_errors', config('app.debug') ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', APP_ROOT . '/storage/logs/php-error.log');

// Last line of defence for an uncaught exception: log the details, show a
// generic message (details only in debug mode, never to real visitors).
set_exception_handler(function (Throwable $e): void {
    error_log((string) $e);

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, 'Error: ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo config('app.debug') ? $e->getMessage() : 'Something went wrong. Please try again later.';
});

// --- Autoloader ------------------------------------------------------------
// "Database" loads src/Database.php, "Csrf" loads src/Csrf.php, and so on.
spl_autoload_register(function (string $class): void {
    $file = APP_ROOT . '/src/' . $class . '.php';
    if (is_file($file)) {
        require $file;
    }
});

// Composer packages (PHPMailer). Optional: without `composer install` the site
// still works, it just sends no confirmation email.
if (is_file(APP_ROOT . '/vendor/autoload.php')) {
    require APP_ROOT . '/vendor/autoload.php';
}

// --- Security headers (web requests only) ----------------------------------
if (PHP_SAPI !== 'cli') {
    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: DENY');
    // Only allow resources from our own site. No inline scripts and no third-party code.
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");

    // A folder asked for without its trailing slash ("/admin" instead of "/admin/"):
    // PHP's built-in server still runs admin/index.php but, unlike Apache, does not
    // add the slash. The browser then resolves relative links (status.php,
    // logout.php...) from the site root, and forms land on the home page.
    // Redirect to the address with the slash, as Apache does.
    $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $scriptFolder = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    if ($scriptFolder !== '' && $requestPath === $scriptFolder && basename($_SERVER['SCRIPT_NAME']) === 'index.php') {
        $query = $_SERVER['QUERY_STRING'] ?? '';
        header('Location: ' . $scriptFolder . '/' . ($query !== '' ? '?' . $query : ''), true, 301);
        exit;
    }
}
