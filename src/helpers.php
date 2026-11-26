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
