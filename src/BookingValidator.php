<?php
declare(strict_types=1);

/**
 * Server-side validation of a booking.
 *
 * Never trust the browser: JavaScript can be switched off or bypassed with a
 * tool like curl, so every rule from booking.js is checked again here, with
 * the same messages.
 */
final class BookingValidator
{
    // Letters from any language, spaces, apostrophes, dots and hyphens.
    private const NAME_PATTERN = "/^[\\p{L}\\p{M}' ’.-]+$/u";
    // Moroccan: 0612345678 or +212612345678 (5, 6 or 7 after the prefix).
    private const MOROCCAN_PHONE = '/^(?:\+212|0)[5-7]\d{8}$/';
    // Any other country: + then 8 to 15 digits.
    private const INTERNATIONAL_PHONE = '/^\+[1-9]\d{7,14}$/';
    private const MESSAGE_MAX_LENGTH = 500;

    /**
     * @param array $input Raw input, usually $_POST.
     * @return array{0: array, 1: array<string, string>} [clean booking data, errors by field name]
     */
    public static function validate(array $input, ?DateTimeImmutable $now = null): array
    {
        $now ??= new DateTimeImmutable();
        $errors = [];

        // Only accept plain strings: "full_name[]=x" would arrive as an array.
        $text = static fn (string $key): string => is_string($input[$key] ?? null) ? trim($input[$key]) : '';

        // --- Full name ---
        $name = $text('full_name');
        $nameLength = mb_strlen($name);
        if ($name === '') {
            $errors['full_name'] = 'Enter your full name.';
        } elseif ($nameLength < 2 || $nameLength > 100) {
            $errors['full_name'] = 'Your name must be between 2 and 100 characters.';
        } elseif (!preg_match(self::NAME_PATTERN, $name)) {
            $errors['full_name'] = 'Use letters, spaces, apostrophes or hyphens only.';
        }

        // --- Phone (stored without spaces, e.g. +212612345678) ---
        $phone = self::normalisePhone($text('phone'));
        if ($phone === '') {
            $errors['phone'] = 'Enter a phone number so we can reach you.';
        } elseif (!preg_match(self::MOROCCAN_PHONE, $phone) && !preg_match(self::INTERNATIONAL_PHONE, $phone)) {
            $errors['phone'] = 'Enter a valid phone number, for example 06 12 34 56 78 or +212 6 12 34 56 78.';
        }

        // --- Email ---
        $email = $text('email');
        if ($email === '') {
            $errors['email'] = 'Enter your email address.';
        } elseif (mb_strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Enter a valid email address, for example name@example.com.';
        }

        // --- Date ---
        $date = $text('booking_date');
        $dateIsValid = false;
        if ($date === '') {
            $errors['booking_date'] = 'Choose a date.';
        } else {
            // "!" resets the time to 00:00; comparing the result with the input
            // rejects impossible dates such as 2026-02-31.
            $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            $today = $now->format('Y-m-d');
            $lastDay = BookingCalendar::lastBookableDate($now)->format('Y-m-d');

            if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
                $errors['booking_date'] = 'Enter a valid date.';
            } elseif ((int) $parsed->format('N') !== (int) config('booking.open_weekday')) {
                $errors['booking_date'] = 'Brunch is served on Sundays only. Choose a Sunday.';
            } elseif ($date < $today) { // Y-m-d strings sort like dates
                $errors['booking_date'] = 'This date has passed. Choose an upcoming Sunday.';
            } elseif ($date > $lastDay) {
                $errors['booking_date'] = sprintf('You can book up to %d days ahead. Choose an earlier Sunday.', config('booking.booking_window_days'));
            } else {
                $dateIsValid = true;
            }
        }

        // --- Sitting ---
        $slot = $text('time_slot');
        if ($slot === '') {
            $errors['time_slot'] = 'Choose a sitting.';
        } elseif (!in_array($slot, config('booking.slots'), true)) {
            $errors['time_slot'] = 'Choose one of the sittings shown.';
        } elseif ($dateIsValid && BookingCalendar::slotHasStarted($date, $slot, $now)) {
            $errors['time_slot'] = 'This sitting has already started. Choose a later sitting or another Sunday.';
        }

        // --- Guests ---
        $minGuests = (int) config('booking.min_guests');
        $maxGuests = (int) config('booking.max_guests');
        $guests = filter_var($text('guests'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => $minGuests, 'max_range' => $maxGuests],
        ]);
        if ($guests === false) {
            $errors['guests'] = sprintf('Choose between %d and %d guests.', $minGuests, $maxGuests);
        }

        // --- Message (optional) ---
        $message = $text('message');
        if (mb_strlen($message) > self::MESSAGE_MAX_LENGTH) {
            $errors['message'] = sprintf('Keep your message under %d characters.', self::MESSAGE_MAX_LENGTH);
        }

        $booking = [
            'full_name'    => $name,
            'phone'        => $phone,
            'email'        => $email,
            'booking_date' => $date,
            'time_slot'    => $slot,
            'guests'       => (int) $guests,
            'message'      => $message === '' ? null : $message,
        ];

        return [$booking, $errors];
    }

    /** Remove spaces, dots, dashes and brackets; turn a leading 00 into +. */
    private static function normalisePhone(string $raw): string
    {
        $phone = preg_replace('/[\s.\-()]/', '', $raw);

        return str_starts_with($phone, '00') ? '+' . substr($phone, 2) : $phone;
    }
}
