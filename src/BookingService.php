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
}
