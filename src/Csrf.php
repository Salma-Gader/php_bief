<?php
declare(strict_types=1);

/**
 * Protection against CSRF (cross-site request forgery).
 *
 * The attack: a malicious site makes a signed-in admin's browser submit a
 * form to our back-office (for example "cancel booking 12"). The browser
 * sends the session cookie automatically, so the request looks legitimate.
 *
 * The defence: every admin form carries a random secret token stored in the
 * session. Another site cannot read it, so it cannot forge a valid form.
 */
final class Csrf
{
    private const SESSION_KEY = 'csrf_token';
    public const FIELD_NAME = 'csrf_token';

    /** The token for this session, created on first use. */
    public static function token(): string
    {
        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::SESSION_KEY];
    }

    /** Hidden <input> to print inside every admin <form method="post">. */
    public static function field(): string
    {
        return '<input type="hidden" name="' . self::FIELD_NAME . '" value="' . e(self::token()) . '">';
    }

    /** True when the submitted form carries the right token. */
    public static function isValid(): bool
    {
        $sent = $_POST[self::FIELD_NAME] ?? null;

        // hash_equals compares in constant time, so timing reveals nothing.
        return is_string($sent) && hash_equals(self::token(), $sent);
    }
}
