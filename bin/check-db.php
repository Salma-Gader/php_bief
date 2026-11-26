<?php
declare(strict_types=1);

/**
 * Checks the local setup: PHP version, database connection, timezone
 * alignment between PHP and MySQL, tables, and GET_LOCK support.
 *
 * Usage:  php bin/check-db.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../src/bootstrap.php';

function check(string $label, bool $ok, string $detail = ''): bool
{
    echo ($ok ? '[ OK ] ' : '[FAIL] ') . $label . ($detail !== '' ? " - {$detail}" : '') . PHP_EOL;
    return $ok;
}

$allOk = true;

$allOk = check('PHP version', PHP_VERSION_ID >= 80100, PHP_VERSION) && $allOk;
$allOk = check('pdo_mysql extension', extension_loaded('pdo_mysql')) && $allOk;

try {
    $pdo = Database::connection();
} catch (PDOException $e) {
    check('Database connection', false, $e->getMessage());
    exit(1);
}

$server = $pdo->query('SELECT VERSION() AS version, DATABASE() AS db, NOW() AS db_now')->fetch();
check('Database connection', true, "{$server['version']}, database '{$server['db']}'");

// PHP and MySQL must agree on the current time (Africa/Casablanca).
$phpNow = new DateTimeImmutable();
$diff   = abs($phpNow->getTimestamp() - (new DateTimeImmutable($server['db_now']))->getTimestamp());
$allOk = check('PHP and MySQL clocks match', $diff <= 5, "PHP {$phpNow->format('Y-m-d H:i:s')} / MySQL {$server['db_now']}") && $allOk;

$tables  = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
$missing = array_diff(['reservations', 'admins', 'login_attempts'], $tables);
$allOk = check('Tables present', $missing === [], $missing ? 'missing: ' . implode(', ', $missing) : implode(', ', $tables)) && $allOk;

// GET_LOCK returns 1 when acquired, RELEASE_LOCK returns 1 when released.
$got      = (int) $pdo->query("SELECT GET_LOCK('sunset:self-test', 0)")->fetchColumn();
$released = (int) $pdo->query("SELECT RELEASE_LOCK('sunset:self-test')")->fetchColumn();
$allOk = check('GET_LOCK / RELEASE_LOCK', $got === 1 && $released === 1) && $allOk;

if ($missing === []) {
    $admins = (int) $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();
    // Not a failure: a fresh install has no admin until you create one.
    echo $admins > 0
        ? "[ OK ] Admin account - {$admins} found" . PHP_EOL
        : '[NOTE] No admin yet - run: php bin/create-admin.php <username>' . PHP_EOL;
}

if (config('security.ip_salt') === 'change-me') {
    check('ip_salt configured', false, 'set security.ip_salt in config/config.php');
    $allOk = false;
}

echo PHP_EOL . ($allOk ? 'All checks passed.' : 'Some checks failed.') . PHP_EOL;
exit($allOk ? 0 : 1);
