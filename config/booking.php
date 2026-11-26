<?php
/**
 * Business rules for bookings.
 *
 * These are not secrets, so they are committed. The landing page, the
 * JavaScript validation and the PHP endpoint all read these values, so the
 * front end and the server can never disagree.
 */

return [
    // Must match the ENUM of reservations.time_slot in database/schema.sql.
    'slots' => ['12:00', '13:30', '15:00'],

    // Total guests allowed per date + slot. Active bookings (new + confirmed)
    // plus the new booking must never exceed this number.
    'slot_capacity' => 40,

    'min_guests' => 1,
    'max_guests' => 12,

    // Assumption (not in the brief): guests can book at most this many days ahead.
    'booking_window_days' => 90,

    // ISO-8601 day of week: 1 = Monday ... 7 = Sunday.
    'open_weekday' => 7,
];
