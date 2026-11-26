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
 * Absolute URL of a page or file, e.g. app_url('assets/img/og-image.jpg').
 * Needed where relative links are not allowed (canonical, Open Graph, JSON-LD).
 * Uses app.url from config; falls back to the current host for local development.
 */
function app_url(string $path = ''): string
{
    $base = (string) config('app.url', '');
    if ($base === '') {
        $https = ($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off';
        $base = ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }

    return rtrim($base, '/') . '/' . ltrim($path, '/');
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
 * Send the browser to another page, then stop the script.
 */
function redirect(string $url): never
{
    header('Location: ' . $url, true, 303); // 303: the next request is a GET
    exit;
}

/**
 * Public booking reference shown to guests and staff, e.g. 31 -> "SB-000031".
 */
function booking_reference(int $id): string
{
    return sprintf('SB-%06d', $id);
}

/**
 * "2027-01-31" -> "Sun 31 Jan 2027".
 */
function format_day(string $isoDate): string
{
    return DateTimeImmutable::createFromFormat('!Y-m-d', $isoDate)->format('D j M Y');
}

/**
 * Link to the back-office list with the given filters (empty values and page 1 are left out).
 */
function admin_list_url(array $filters, array $changes = []): string
{
    $query = array_filter(array_merge($filters, $changes), fn ($value) => $value !== '' && $value !== 1);

    return 'index.php' . ($query === [] ? '' : '?' . http_build_query($query));
}

/**
 * Read the back-office filters from the URL and keep only valid values.
 * Used by the bookings list and the CSV export, so both show the same bookings.
 *
 * @return array{0: array{date: string, status: string}, 1: string} [filters, notice for the admin or '']
 */
function read_list_filters(array $query): array
{
    $notice = '';

    $date = is_string($query['date'] ?? null) ? trim($query['date']) : '';
    if ($date !== '') {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            $notice = 'That date is not valid, so it was ignored.';
            $date = '';
        }
    }

    $status = is_string($query['status'] ?? null) ? $query['status'] : '';
    if (!in_array($status, ReservationRepository::STATUSES, true)) {
        $status = '';
    }

    return [['date' => $date, 'status' => $status], $notice];
}

/**
 * One-time message for the next page (stored in the session), e.g. "SB-000031 confirmed."
 */
function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/** Read the one-time message and remove it, so it is shown only once. */
function take_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);

    return $flash;
}

/**
 * A secret-salted hash of the visitor's IP address.
 * Lets us rate-limit by visitor without storing the real IP (privacy).
 */
function client_ip_hash(): string
{
    return hash_hmac('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown', (string) config('security.ip_salt'));
}
