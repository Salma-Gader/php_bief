<?php
declare(strict_types=1);

/**
 * POST api/book.php: receives a booking from the landing page, answers in JSON.
 *
 * 1. Only POST is accepted.
 * 2. Honeypot: a hidden field that only bots fill in.
 * 3. Rate limit: too many bookings from one visitor are refused.
 * 4. Server-side validation of every field.
 * 5. Capacity check + save, under the slot lock.
 * 6. JSON answer the page can show.
 *
 * Answers: 201 booked, 422 invalid fields, 409 sitting full, 429 too many
 * bookings, 503 sitting busy, 405 wrong method, 500 server error.
 */

require __DIR__ . '/../../src/bootstrap.php';

// 1. Method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    json_response(405, ['ok' => false, 'message' => 'Send bookings with the booking form.']);
}

// 2. Honeypot: pretend it worked so the bot learns nothing, but save nothing.
if (($_POST['website'] ?? '') !== '') {
    error_log('Booking blocked by honeypot.');
    json_response(200, ['ok' => true, 'message' => 'Your booking request has been sent.']);
}

try {
    $pdo = Database::connection();
    $ipHash = client_ip_hash();

    // 3. Rate limit
    $maxBookings = (int) config('booking.rate_limit.max_bookings');
    $perMinutes = (int) config('booking.rate_limit.per_minutes');
    if ((new ReservationRepository($pdo))->countRecentFromIp($ipHash, $perMinutes) >= $maxBookings) {
        json_response(429, [
            'ok'      => false,
            'message' => 'Too many booking attempts. Wait a few minutes, then try again.',
        ]);
    }

    // 4. Validation
    [$booking, $errors] = BookingValidator::validate($_POST);
    if ($errors !== []) {
        json_response(422, [
            'ok'      => false,
            'message' => count($errors) === 1 ? 'Check the highlighted field.' : sprintf('Check the %d highlighted fields.', count($errors)),
            'errors'  => $errors,
        ]);
    }

    // 5. Capacity + save
    $result = (new BookingService($pdo))->reserve($booking, $ipHash);
} catch (SlotBusyException) {
    json_response(503, [
        'ok'      => false,
        'message' => 'Many guests are booking this sitting right now. Try again in a few seconds.',
    ]);
} catch (Throwable $e) {
    error_log((string) $e); // full details in the log, never in the answer
    json_response(500, [
        'ok'      => false,
        'message' => 'The booking could not be saved because of a problem on our side. Try again in a moment.',
    ]);
}

// 6. Answer
$day = DateTimeImmutable::createFromFormat('!Y-m-d', $booking['booking_date'])->format('l j F'); // "Sunday 6 December"
$sitting = "the {$booking['time_slot']} sitting on {$day}";

if ($result['status'] === 'full') {
    $remaining = $result['remaining'];
    $message = $remaining === 0
        ? ucfirst($sitting) . ' is fully booked. Choose another sitting or another Sunday.'
        : "Only {$remaining} " . ($remaining === 1 ? 'seat is' : 'seats are') . " left for {$sitting}. Choose fewer guests, another sitting or another Sunday.";

    json_response(409, [
        'ok'      => false,
        'message' => $message,
        'errors'  => ['time_slot' => $remaining === 0 ? 'This sitting is full.' : "Only {$remaining} " . ($remaining === 1 ? 'seat' : 'seats') . ' left.'],
    ]);
}

$reference = booking_reference($result['id']);
$guests = $booking['guests'] === 1 ? '1 guest' : "{$booking['guests']} guests";

// The booking is saved; the email is a bonus and never turns this into an error.
$emailSent = BookingMailer::sendRequestReceived($booking, $reference);

json_response(201, [
    'ok'        => true,
    'reference' => $reference,
    'message'   => "Thank you! Your request for {$guests} at {$sitting} has been received. Your reference is {$reference}. "
                 . ($emailSent ? "We sent the details to {$booking['email']}. " : '')
                 . 'Our team will confirm it soon.',
]);
