<?php
declare(strict_types=1);

/**
 * Every SQL query on the reservations table lives here.
 * All of them are prepared statements: values are sent separately from the
 * SQL, so user input can never change the query (no SQL injection).
 */
final class ReservationRepository
{
    /** Every status a booking can have (matches the ENUM in schema.sql). */
    public const STATUSES = ['new', 'confirmed', 'cancelled'];

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Bookings for the back-office list, newest first.
     *
     * @param array{date: string, status: string} $filters Empty string = no filter.
     */
    public function search(array $filters, int $limit, int $offset): array
    {
        [$where, $params] = $this->filterSql($filters);

        // LIMIT/OFFSET cannot be bound as strings, so they are written in the SQL.
        // That is safe because PHP's type declarations guarantee they are integers.
        $stmt = $this->pdo->prepare(
            "SELECT id, full_name, phone, email, booking_date, time_slot, guests, message, status, created_at
               FROM reservations
               {$where}
           ORDER BY created_at DESC, id DESC
              LIMIT {$limit} OFFSET {$offset}"
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /** How many bookings match the filters (for pagination). */
    public function count(array $filters): int
    {
        [$where, $params] = $this->filterSql($filters);
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM reservations {$where}");
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Guests and bookings per sitting for one date, counting only active bookings.
     *
     * @return array<string, array{guests: int, bookings: int}> e.g. ['12:00' => ['guests' => 28, 'bookings' => 7], ...]
     */
    public function slotSummary(string $date): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT time_slot, SUM(guests) AS guests, COUNT(*) AS bookings
               FROM reservations
              WHERE booking_date = ? AND status IN ('new', 'confirmed')
           GROUP BY time_slot"
        );
        $stmt->execute([$date]);
        $rows = $stmt->fetchAll(PDO::FETCH_UNIQUE); // keyed by time_slot

        // Every sitting appears, even with no bookings yet.
        $summary = [];
        foreach (config('booking.slots') as $slot) {
            $summary[$slot] = [
                'guests'   => (int) ($rows[$slot]['guests'] ?? 0),
                'bookings' => (int) ($rows[$slot]['bookings'] ?? 0),
            ];
        }

        return $summary;
    }

    /**
     * Turn the filters into a WHERE clause with ? placeholders and its values.
     * Only known columns are used, and the values are bound, never pasted into SQL.
     */
    private function filterSql(array $filters): array
    {
        $conditions = [];
        $params = [];

        if (($filters['date'] ?? '') !== '') {
            $conditions[] = 'booking_date = ?';
            $params[] = $filters['date'];
        }
        if (($filters['status'] ?? '') !== '') {
            $conditions[] = 'status = ?';
            $params[] = $filters['status'];
        }

        $where = $conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions);

        return [$where, $params];
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

    /** One booking by id, or null if it does not exist. */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, full_name, booking_date, time_slot, guests, status FROM reservations WHERE id = ?'
        );
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public function updateStatus(int $id, string $status): void
    {
        $this->pdo->prepare('UPDATE reservations SET status = ? WHERE id = ?')->execute([$status, $id]);
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
