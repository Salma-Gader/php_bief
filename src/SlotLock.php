<?php
declare(strict_types=1);

/**
 * Lets only one request at a time work on a given date + sitting.
 *
 * The problem it solves: two guests book the last seats at the same moment.
 * Both read "36 booked", both think 4 seats are left, both save: 44 guests.
 *
 * GET_LOCK('name', timeout) is a MySQL/MariaDB named lock:
 *  - It belongs to the database connection that took it, so the check and the
 *    insert must run on that same PDO connection (Database::connection()).
 *  - Another connection asking for the same name waits up to `timeout`
 *    seconds, then gets 0. Different sittings use different names, so they
 *    never wait for each other.
 *  - It is released by RELEASE_LOCK, or automatically if the connection closes
 *    (for example if PHP crashes), so it can never stay stuck.
 *
 * Used by the booking endpoint and (Phase 7) by the admin "confirm" action.
 */
final class SlotLock
{
    /**
     * Run $work while holding the lock for this date and sitting.
     *
     * @throws SlotBusyException when the lock could not be taken in time.
     */
    public static function run(PDO $pdo, string $date, string $slot, callable $work): mixed
    {
        $name = "sunset_brunch:slot:{$date}|{$slot}";

        $stmt = $pdo->prepare('SELECT GET_LOCK(?, ?)');
        $stmt->execute([$name, (int) config('booking.lock_timeout_seconds', 5)]);

        // 1 = acquired, 0 = timed out, NULL = error.
        if ((int) $stmt->fetchColumn() !== 1) {
            throw new SlotBusyException("Could not lock {$name}");
        }

        try {
            return $work();
        } finally {
            // Always release, even if $work throws an exception.
            $pdo->prepare('SELECT RELEASE_LOCK(?)')->execute([$name]);
        }
    }
}
