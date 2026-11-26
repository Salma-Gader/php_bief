<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Emails sent to the guest:
 *  1. "We received your booking request": right after the booking form is sent.
 *  2. "Your table is confirmed": when staff confirm the booking in the back-office.
 *
 * PHPMailer (installed with Composer) talks to an SMTP server set in config:
 * Mailpit on a development PC, the real mail provider in production.
 *
 * Emails are a bonus: if one fails, the booking or the confirmation still
 * stands. The error goes to the log and the method returns false.
 */
final class BookingMailer
{
    public static function isEnabled(): bool
    {
        return (bool) config('mail.enabled', false) && class_exists(PHPMailer::class);
    }

    /** Email 1: sent by api/book.php after a booking is saved (status "new"). */
    public static function sendRequestReceived(array $booking, string $reference): bool
    {
        return self::send(
            $booking,
            "Your Sunset Brunch booking request {$reference}",
            'We received your booking request',
            'thank you for booking Sunday brunch with us. Our team will review your request and confirm your table soon.',
            $reference
        );
    }

    /** Email 2: sent by admin/status.php when staff confirm a booking. */
    public static function sendBookingConfirmed(array $booking, string $reference): bool
    {
        return self::send(
            $booking,
            "Your table is confirmed: Sunset Brunch {$reference}",
            'Your table is confirmed',
            'good news: your table for Sunday brunch is confirmed. We look forward to welcoming you.',
            $reference
        );
    }

    /**
     * Build and send one email. Shared by both emails, so the SMTP settings,
     * the layout and the escaping exist in one place only.
     */
    private static function send(array $booking, string $subject, string $heading, string $intro, string $reference): bool
    {
        if (!self::isEnabled()) {
            return false;
        }

        try {
            $mail = new PHPMailer(true); // true = throw exceptions on errors

            $mail->isSMTP();
            $mail->Host = (string) config('mail.host', '127.0.0.1');
            $mail->Port = (int) config('mail.port', 1025);
            $mail->Username = (string) config('mail.username', '');
            $mail->Password = (string) config('mail.password', '');
            $mail->SMTPAuth = $mail->Username !== '';
            $mail->SMTPSecure = (string) config('mail.encryption', ''); // '', 'tls' or 'ssl'
            $mail->SMTPAutoTLS = $mail->SMTPSecure !== '';
            $mail->Timeout = 5; // seconds; PHPMailer's default (300) would make people wait minutes if the server is down
            $mail->CharSet = PHPMailer::CHARSET_UTF8;

            $mail->setFrom((string) config('mail.from_email'), (string) config('mail.from_name', 'Sunset Brunch'));
            $mail->addAddress($booking['email'], $booking['full_name']);

            $mail->Subject = $subject;
            $mail->isHTML(true);
            $mail->Body = self::htmlBody($booking, $heading, $intro, $reference);
            $mail->AltBody = self::textBody($booking, $heading, $intro, $reference); // for mail apps that show plain text

            $mail->send();

            return true;
        } catch (MailException $e) {
            error_log("Email \"{$heading}\" for {$reference} failed: " . $e->getMessage());

            return false;
        }
    }

    /** Label => value lines shared by the HTML and text versions. */
    private static function details(array $booking, string $reference): array
    {
        $day = DateTimeImmutable::createFromFormat('!Y-m-d', $booking['booking_date'])->format('l j F Y');

        return array_filter([
            'Reference' => $reference,
            'Date'      => $day,
            'Sitting'   => $booking['time_slot'],
            'Guests'    => (string) $booking['guests'],
            'Phone'     => $booking['phone'],
            'Message'   => $booking['message'] ?? '',
        ], fn ($value) => $value !== '');
    }

    private static function htmlBody(array $booking, string $heading, string $intro, string $reference): string
    {
        // Every guest value goes through e(): the email is HTML too, so the same XSS rule applies.
        $rows = '';
        foreach (self::details($booking, $reference) as $label => $value) {
            $rows .= '<tr><td style="padding:6px 16px 6px 0;color:#3F5873;vertical-align:top">' . e($label) . '</td>'
                   . '<td style="padding:6px 0;font-weight:600">' . nl2br(e($value)) . '</td></tr>';
        }

        return '<!doctype html><html lang="en"><body style="margin:0;padding:24px;background:#F4F7F9;font-family:Segoe UI,Arial,sans-serif;color:#0E2A4A">'
             . '<div style="max-width:520px;margin:0 auto;background:#fff;border-radius:12px;padding:28px">'
             . '<p style="margin:0;font-size:20px;font-weight:700;color:#12355B">Sunset Brunch</p>'
             . '<h1 style="margin:16px 0 8px;font-size:22px">' . e($heading) . '</h1>'
             . '<p style="margin:0 0 16px;line-height:1.5">Hello ' . e($booking['full_name']) . ', ' . e($intro) . '</p>'
             . '<table role="presentation" style="border-collapse:collapse;font-size:15px">' . $rows . '</table>'
             . '<p style="margin:20px 0 0;line-height:1.5;color:#3F5873">Need to change something? Reply to this email and include your reference.</p>'
             . '</div></body></html>';
    }

    private static function textBody(array $booking, string $heading, string $intro, string $reference): string
    {
        $lines = [$heading, '', "Hello {$booking['full_name']}, {$intro}", ''];
        foreach (self::details($booking, $reference) as $label => $value) {
            $lines[] = "{$label}: {$value}";
        }
        $lines[] = '';
        $lines[] = 'Need to change something? Reply to this email and include your reference.';

        return implode("\n", $lines);
    }
}
