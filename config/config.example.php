<?php
/**
 * Environment configuration TEMPLATE.
 *
 * Copy this file to config/config.php and fill in real values.
 * config/config.php is git-ignored, so credentials never reach the repository.
 */

return [
    'app' => [
        'name'     => 'Sunset Brunch',
        // Keep false in production: errors are then logged, never shown to visitors.
        'debug'    => false,
        'timezone' => 'Africa/Casablanca',
    ],

    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'sunset_brunch',
        // Use a dedicated low-privilege user, not root (see README).
        'user' => 'sunset_app',
        'pass' => 'change-me',
    ],

    'security' => [
        // Secret salt used to hash visitor IPs (rate limiting without storing raw IPs).
        // Generate one with: php -r "echo bin2hex(random_bytes(32));"
        'ip_salt' => 'change-me',
    ],
];
