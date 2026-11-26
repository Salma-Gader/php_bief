<?php
declare(strict_types=1);

/**
 * POST admin/status.php: confirm or cancel a booking, then go back to the list.
 *
 * Protected (signed-in staff only) and CSRF-checked. The result is shown on
 * the list as a one-time message (Post/Redirect/Get: refreshing the list
 * never re-sends the action).
 */

require __DIR__ . '/../../src/bootstrap.php';

Auth::requireAdmin();

// Return to the list with the same filters the admin was looking at.
// Only known keys are kept, and the target is always index.php.
$text = static fn (string $key): string => is_string($_POST[$key] ?? null) ? $_POST[$key] : '';
$back = admin_list_url([
    'date'   => $text('filter_date'),
    'status' => $text('filter_status'),
    'page'   => (int) $text('page') > 1 ? (int) $text('page') : 1,
]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

if (!Csrf::isValid()) {
    flash('error', 'Your session expired, so nothing was changed. Try again.');
    redirect($back);
}

$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$newStatus = $text('new_status');

if ($id === false) {
    flash('error', 'That booking could not be found.');
    redirect($back);
}

try {
    $outcome = (new BookingService(Database::connection()))->changeStatus($id, $newStatus);
} catch (SlotBusyException) {
    flash('error', 'Guests are booking this sitting right now. Try again in a few seconds.');
    redirect($back);
} catch (Throwable $e) {
    error_log((string) $e);
    flash('error', 'The status could not be changed because of a problem on our side. Try again.');
    redirect($back);
}

$ref = booking_reference($id);
$booking = $outcome['booking'] ?? null;

// Confirmed: tell the guest by email. This runs after the slot lock is released,
// so a slow mail server never holds up guest bookings. The confirmation stands
// even if the email fails.
$emailNote = '';
if ($outcome['result'] === 'updated' && $newStatus === 'confirmed' && BookingMailer::isEnabled()) {
    $emailNote = BookingMailer::sendBookingConfirmed($booking, $ref)
        ? " Confirmation email sent to {$booking['email']}."
        : ' The confirmation email could not be sent (see the error log); contact the guest by phone.';
}

match ($outcome['result']) {
    'updated' => flash('success', $newStatus === 'confirmed'
        ? "{$ref} confirmed.{$emailNote}"
        : "{$ref} cancelled. Its {$booking['guests']} " . ($booking['guests'] === 1 ? 'seat is' : 'seats are') . ' free again.'),
    'unchanged' => flash('info', "{$ref} is already {$newStatus}."),
    'full' => flash('error', sprintf(
        '%s cannot be confirmed: the %s sitting on %s %s, and this booking is for %d guests.',
        $ref,
        $booking['time_slot'],
        format_day($booking['booking_date']),
        match ($outcome['remaining']) {
            0       => 'is full',
            1       => 'has only 1 seat left',
            default => "has only {$outcome['remaining']} seats left",
        },
        $booking['guests']
    )),
    'not_found' => flash('error', 'That booking could not be found.'),
    default => flash('error', 'Choose Confirm or Cancel.'),
};

redirect($back);
