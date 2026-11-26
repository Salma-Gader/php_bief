<?php
declare(strict_types=1);

/**
 * Gives every part of the app the SAME PDO connection during one request.
 *
 * Why one shared connection matters: MySQL's GET_LOCK() belongs to the
 * connection that takes it. The booking endpoint and the admin "confirm"
 * action will take the slot lock, check capacity and write the row on this
 * one connection, so the lock really protects "check + write" together.
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo === null) {
            self::$pdo = self::connect();
        }

        return self::$pdo;
    }

    private static function connect(): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            config('db.host'),
            config('db.port'),
            config('db.name')
        );

        $pdo = new PDO($dsn, config('db.user'), config('db.pass'), [
            // Throw exceptions on SQL errors instead of failing silently.
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            // Return rows as ['column' => value] arrays.
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Use real server-side prepared statements: the SQL and the data
            // travel separately, which is what blocks SQL injection.
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        // Give MySQL the same clock as PHP (Africa/Casablanca), so NOW() and
        // created_at match PHP dates. A numeric offset such as "+01:00" is used
        // because named time zones need tables that XAMPP does not install.
        $offset = (new DateTimeImmutable())->format('P');
        $pdo->exec('SET time_zone = ' . $pdo->quote($offset));

        // Strict mode: invalid data is rejected instead of silently truncated.
        $pdo->exec("SET SESSION sql_mode = 'STRICT_ALL_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'");

        return $pdo;
    }
}
