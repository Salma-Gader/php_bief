<?php
declare(strict_types=1);

/**
 * Create a back-office admin, or reset an existing admin's password.
 *
 * Usage:  php bin/create-admin.php <username>
 *
 * The password is typed at a prompt, not passed as an argument, so it does
 * not end up in the shell history. Only its password_hash() is stored.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../src/bootstrap.php';

const MIN_PASSWORD_LENGTH = 12;

$username = $argv[1] ?? '';
if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
    fwrite(STDERR, "Usage: php bin/create-admin.php <username>\n");
    fwrite(STDERR, "Username: 3-50 characters, letters, digits, dot, dash or underscore.\n");
    exit(1);
}

function prompt(string $label): string
{
    echo $label;
    // Remove only the line ending: spaces inside a password are allowed.
    return rtrim((string) fgets(STDIN), "\r\n");
}

$password = prompt('Password (min ' . MIN_PASSWORD_LENGTH . ' characters): ');
if (mb_strlen($password) < MIN_PASSWORD_LENGTH) {
    fwrite(STDERR, 'Password too short.' . PHP_EOL);
    exit(1);
}
if (prompt('Repeat password: ') !== $password) {
    fwrite(STDERR, 'Passwords do not match.' . PHP_EOL);
    exit(1);
}

// PASSWORD_DEFAULT is currently bcrypt. PHP picks the salt and stores it inside the hash.
$hash = password_hash($password, PASSWORD_DEFAULT);
$pdo  = Database::connection();

$find = $pdo->prepare('SELECT id FROM admins WHERE username = ?');
$find->execute([$username]);

if ($find->fetchColumn() !== false) {
    $pdo->prepare('UPDATE admins SET password_hash = ? WHERE username = ?')->execute([$hash, $username]);
    echo "Password updated for admin '{$username}'." . PHP_EOL;
} else {
    $pdo->prepare('INSERT INTO admins (username, password_hash) VALUES (?, ?)')->execute([$username, $hash]);
    echo "Admin '{$username}' created." . PHP_EOL;
}
