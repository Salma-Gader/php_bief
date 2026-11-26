<?php
declare(strict_types=1);

/**
 * Date rules for bookings, in one place.
 *
 * Used by the landing page (to set the date picker's min/max) and, in the
 * next phases, by the server-side validation, so both apply the same rules.
 * All dates use the app timezone (Africa/Casablanca) set in bootstrap.php.
 */
final class BookingCalendar
{
    /**
     * True if the sitting has already started, e.g. ('2026-11-29', '12:00') at 12:05.
     */
    public static function slotHasStarted(string $date, string $slot, ?DateTimeImmutable $now = null): bool
    {
        $now ??= new DateTimeImmutable();

        return new DateTimeImmutable("{$date} {$slot}") <= $now;
    }

    /**
     * The first date a guest can choose.
     * Today counts when today is the open day (Sunday) and its last sitting has not started.
     */
    public static function firstBookableDate(?DateTimeImmutable $now = null): DateTimeImmutable
    {
        $now      ??= new DateTimeImmutable();
        $today    = $now->setTime(0, 0);
        $openDay  = (int) config('booking.open_weekday');
        $slots    = config('booking.slots');
        $lastSlot = $slots[count($slots) - 1];

        // Look at today and the next 7 days: one of them is always the open day.
        for ($i = 0; $i <= 7; $i++) {
            $day = $today->modify("+{$i} days");

            if ((int) $day->format('N') !== $openDay) {
                continue;
            }
            if ($i === 0 && self::slotHasStarted($day->format('Y-m-d'), $lastSlot, $now)) {
                continue; // Today is Sunday, but every sitting has started.
            }
            return $day;
        }

        throw new LogicException('No bookable date found; check booking.open_weekday.');
    }

    /**
     * The last date a guest can choose (today + booking_window_days).
     */
    public static function lastBookableDate(?DateTimeImmutable $now = null): DateTimeImmutable
    {
        $now ??= new DateTimeImmutable();
        $days = (int) config('booking.booking_window_days');

        return $now->setTime(0, 0)->modify("+{$days} days");
    }
}
