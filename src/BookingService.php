<?php
declare(strict_types=1);

/**
 * The capacity rule:
 * active guests (new + confirmed) + new guests must never exceed slot_capacity (40).
 */
final class BookingService
{
    private ReservationRepository $reservations;

    public function __construct(private PDO $pdo)
    {
        $this->reservations = new ReservationRepository($pdo);
    }

    /**
     * Save the booking if the sitting still has room.
     *
     * Counting the guests and saving happen inside the slot lock, so no other
     * booking for this sitting can slip in between the two steps.
     *
     * @return array{status: string, id?: int, remaining?: int}
     *         ['status' => 'booked', 'id' => 12] or ['status' => 'full', 'remaining' => 3]
     */
    public function reserve(array $booking, string $ipHash): array
    {
        $capacity = (int) config('booking.slot_capacity');

        return SlotLock::run(
            $this->pdo,
            $booking['booking_date'],
            $booking['time_slot'],
            function () use ($booking, $ipHash, $capacity): array {
                $booked = $this->reservations->activeGuests($booking['booking_date'], $booking['time_slot']);

                if ($booked + $booking['guests'] > $capacity) {
                    return ['status' => 'full', 'remaining' => max(0, $capacity - $booked)];
                }

                return ['status' => 'booked', 'id' => $this->reservations->create($booking, $ipHash)];
            }
        );
    }

    /**
     * Back-office: confirm or cancel a booking.
     *
     * Runs under the same slot lock as reserve(), so a guest booking and an
     * admin action on the same sitting can never overlap.
     *
     * @return array{result: string, booking?: array, remaining?: int}
     *         result = 'updated' | 'unchanged' | 'full' | 'not_found' | 'invalid'
     */
    public function changeStatus(int $id, string $newStatus): array
    {
        // Staff can only confirm or cancel; "new" is set by the booking form.
        if (!in_array($newStatus, ['confirmed', 'cancelled'], true)) {
            return ['result' => 'invalid'];
        }

        $booking = $this->reservations->find($id);
        if ($booking === null) {
            return ['result' => 'not_found'];
        }

        $capacity = (int) config('booking.slot_capacity');

        return SlotLock::run(
            $this->pdo,
            $booking['booking_date'],
            $booking['time_slot'],
            function () use ($id, $newStatus, $capacity): array {
                // Read again inside the lock: someone may have changed it a moment ago.
                $booking = $this->reservations->find($id);
                if ($booking === null) {
                    return ['result' => 'not_found'];
                }
                if ($booking['status'] === $newStatus) {
                    return ['result' => 'unchanged', 'booking' => $booking];
                }

                // A cancelled booking does not hold seats. Confirming it takes them
                // again, so the 40-guest limit is checked like for a new booking.
                if ($booking['status'] === 'cancelled') {
                    $booked = $this->reservations->activeGuests($booking['booking_date'], $booking['time_slot']);
                    if ($booked + $booking['guests'] > $capacity) {
                        return ['result' => 'full', 'booking' => $booking, 'remaining' => max(0, $capacity - $booked)];
                    }
                }

                $this->reservations->updateStatus($id, $newStatus);

                return ['result' => 'updated', 'booking' => $booking];
            }
        );
    }
}
