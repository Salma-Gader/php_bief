<?php
declare(strict_types=1);

/**
 * Back-office authentication.
 *
 * - Passwords are only stored as password_hash() output and checked with password_verify().
 * - The session cookie is HttpOnly (JavaScript cannot read it) and SameSite=Lax.
 * - The session id changes at login (prevents session fixation).
 * - After 5 failed attempts from one IP, sign-in is blocked for 15 minutes.
 * - A session ends after 30 minutes without activity.
 */
final class Auth
{
    private const MAX_FAILED_ATTEMPTS = 5;
    private const LOCKOUT_MINUTES = 15;
    private const IDLE_TIMEOUT_SECONDS = 30 * 60;

    // A real bcrypt hash of a random, discarded password. Checked when the username
    // does not exist, so that case takes as long as a wrong password.
    private const DUMMY_HASH = '$2y$12$4kW0U7IiBNPqHBQpRJFS/O3kWgB1UJAUulYmU6U4/mVdINnsVtjCq';

    /** Start the admin session with safe cookie settings. Call at the top of every admin page. */
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        ini_set('session.use_strict_mode', '1'); // ignore session ids the server did not create
        ini_set('session.use_only_cookies', '1'); // never accept a session id in the URL

        session_name('sunset_admin');
        session_set_cookie_params([
            'lifetime' => 0, // ends when the browser closes
            'path'     => '/',
            'secure'   => ($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off', // HTTPS only, when available
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();

        // Admin pages must never be cached (the Back button after logout must not show bookings)
        // or indexed by search engines.
        header('Cache-Control: no-store');
        header('X-Robots-Tag: noindex, nofollow');
    }

    /**
     * Try to sign in.
     *
     * @return string 'ok', 'invalid' or 'locked'
     */
    public static function attempt(string $username, string $password): string
    {
        $pdo = Database::connection();
        $ipHash = client_ip_hash();

        if (self::failedAttempts($ipHash) >= self::MAX_FAILED_ATTEMPTS) {
            return 'locked';
        }

        $stmt = $pdo->prepare('SELECT id, username, password_hash FROM admins WHERE username = ?');
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        // When the username does not exist we still run password_verify on a dummy
        // hash, so the response time does not reveal which usernames exist.
        $hash = $admin['password_hash'] ?? self::DUMMY_HASH;
        $isValid = password_verify($password, $hash) && $admin !== false;

        if (!$isValid) {
            $pdo->prepare('INSERT INTO login_attempts (ip_hash) VALUES (?)')->execute([$ipHash]);
            return 'invalid';
        }

        // Success: forget this IP's failed attempts and refresh the hash if PHP's default got stronger.
        $pdo->prepare('DELETE FROM login_attempts WHERE ip_hash = ?')->execute([$ipHash]);
        if (password_needs_rehash($admin['password_hash'], PASSWORD_DEFAULT)) {
            $pdo->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($password, PASSWORD_DEFAULT), $admin['id']]);
        }
        $pdo->prepare('UPDATE admins SET last_login_at = NOW() WHERE id = ?')->execute([$admin['id']]);

        // New session id at login, so an id planted before login is useless.
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int) $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        $_SESSION['last_activity'] = time();

        return 'ok';
    }

    /** True when an admin is signed in and has been active recently. */
    public static function check(): bool
    {
        if (empty($_SESSION['admin_id'])) {
            return false;
        }
        if (time() - ($_SESSION['last_activity'] ?? 0) > self::IDLE_TIMEOUT_SECONDS) {
            self::logout();
            self::startSession(); // fresh, empty session so the sign-in form gets a new CSRF token
            return false;
        }
        $_SESSION['last_activity'] = time();

        return true;
    }

    /** Put at the top of every protected page: visitors who are not signed in go to the login page. */
    public static function requireAdmin(): void
    {
        self::startSession();
        $wasSignedIn = !empty($_SESSION['admin_id']);

        if (!self::check()) {
            redirect('login.php' . ($wasSignedIn ? '?expired=1' : ''));
        }
    }

    public static function username(): string
    {
        return (string) ($_SESSION['admin_username'] ?? '');
    }

    /** End the session completely: data, cookie and server file. */
    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 3600,
                'path'     => $params['path'],
                'secure'   => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'],
            ]);
        }
        session_destroy();
    }

    private static function failedAttempts(string $ipHash): int
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM login_attempts WHERE ip_hash = ? AND attempted_at >= NOW() - INTERVAL ? MINUTE'
        );
        $stmt->execute([$ipHash, self::LOCKOUT_MINUTES]);

        return (int) $stmt->fetchColumn();
    }
}
