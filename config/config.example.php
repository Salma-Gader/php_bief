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
        // Public address of the site, used for canonical and social-sharing links.
        'url'      => 'http://127.0.0.1:8000',
    ],

    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'sunset_brunch',
        // Use a dedicated low-privilege user, not root (see README).
        'user' => 'sunset_app',
        'pass' => 'change-me',
    ],

    // Confirmation email sent to the guest after booking (PHPMailer over SMTP).
    // Local development: Mailpit catches every email (SMTP 127.0.0.1:1025, inbox at http://127.0.0.1:8025).
    // Production: your mail provider's SMTP settings, e.g. port 587 with encryption 'tls'.
    'mail' => [
        'enabled'    => true,
        'host'       => '127.0.0.1',
        'port'       => 1025,
        'encryption' => '',   // '', 'tls' (STARTTLS) or 'ssl'
        'username'   => '',
        'password'   => '',
        'from_email' => 'bookings@sunsetbrunch.example',
        'from_name'  => 'Sunset Brunch',
    ],

    'security' => [
        // Secret salt used to hash visitor IPs (rate limiting without storing raw IPs).
        // Generate one with: php -r "echo bin2hex(random_bytes(32));"
        'ip_salt' => 'change-me',
    ],
];
