<?php
declare(strict_types=1);

/**
 * Every SQL query on the reservations table lives here.
 * All of them are prepared statements: values are sent separately from the
 * SQL, so user input can never change the query (no SQL injection).
 */
final class ReservationRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Guests already booked for a date and sitting. Cancelled bookings free their seats.
     */
    public function activeGuests(string $date, string $slot): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(guests), 0)
               FROM reservations
              WHERE booking_date = ? AND time_slot = ? AND status IN ('new', 'confirmed')"
        );
        $stmt->execute([$date, $slot]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Save a new booking (status "new" by default) and return its id.
     */
    public function create(array $booking, string $ipHash): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO reservations (full_name, phone, email, booking_date, time_slot, guests, message, ip_hash)
             VALUES (:full_name, :phone, :email, :booking_date, :time_slot, :guests, :message, :ip_hash)'
        );
        $stmt->execute([
            'full_name'    => $booking['full_name'],
            'phone'        => $booking['phone'],
            'email'        => $booking['email'],
            'booking_date' => $booking['booking_date'],
            'time_slot'    => $booking['time_slot'],
            'guests'       => $booking['guests'],
            'message'      => $booking['message'],
            'ip_hash'      => $ipHash,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Bookings saved from one visitor in the last few minutes (for the rate limit).
     */
    public function countRecentFromIp(string $ipHash, int $minutes): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM reservations WHERE ip_hash = ? AND created_at >= NOW() - INTERVAL ? MINUTE'
        );
        $stmt->execute([$ipHash, $minutes]);

        return (int) $stmt->fetchColumn();
    }
}
