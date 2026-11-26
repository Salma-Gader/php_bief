<?php
declare(strict_types=1);

/**
 * Small global helpers used everywhere.
 */

/**
 * Read a configuration value with "dot" notation, e.g. config('db.host').
 */
function config(string $key, mixed $default = null): mixed
{
    $value = APP_CONFIG;

    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }

    return $value;
}

/**
 * Escape a value for safe output in HTML (prevents XSS).
 * Every piece of user data printed in a page must go through e().
 */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Send a JSON answer with an HTTP status code, then stop the script.
 */
function json_response(int $status, array $payload): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * A secret-salted hash of the visitor's IP address.
 * Lets us rate-limit by visitor without storing the real IP (privacy).
 */
function client_ip_hash(): string
{
    return hash_hmac('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown', (string) config('security.ip_salt'));
}
