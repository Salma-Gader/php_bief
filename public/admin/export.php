<?php
declare(strict_types=1);

/**
 * GET admin/export.php?date=...&status=...: download the bookings as a CSV file.
 *
 * Exports exactly what the list shows with the same filters (all pages).
 * Protected: signed-in staff only.
 *
 * - UTF-8 with a BOM, so Excel shows accents and Arabic names correctly.
 * - Semicolon separator: what Excel expects on French-language Windows (common in Morocco).
 * - Protected against "CSV injection" (see csv_safe below).
 */

require __DIR__ . '/../../src/bootstrap.php';

Auth::requireAdmin();

[$filters] = read_list_filters($_GET);
$repo = new ReservationRepository(Database::connection());
$bookings = $repo->search($filters, max(1, $repo->count($filters)), 0);

/**
 * A cell starting with = + - @ (or tab / carriage return) is run as a formula
 * by Excel. A guest could type "=HYPERLINK(...)" as their name. Prefixing an
 * apostrophe makes Excel treat the cell as plain text.
 */
function csv_safe(mixed $value): string
{
    $value = (string) $value;

    return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
}

// File name describes the filters: sunset-brunch-bookings-2026-11-29-confirmed.csv
$fileName = implode('-', array_filter(['sunset-brunch-bookings', $filters['date'], $filters['status']])) . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $fileName . '"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // UTF-8 byte order mark, for Excel

$columns = ['Reference', 'Received', 'Brunch date', 'Sitting', 'Guests', 'Status', 'Full name', 'Phone', 'Email', 'Message'];
fputcsv($out, $columns, ';', '"', '');

foreach ($bookings as $b) {
    fputcsv($out, array_map('csv_safe', [
        booking_reference($b['id']),
        $b['created_at'],
        $b['booking_date'],
        $b['time_slot'],
        $b['guests'],
        $b['status'],
        $b['full_name'],
        $b['phone'],
        $b['email'],
        $b['message'] ?? '',
    ]), ';', '"', '');
}

fclose($out);
