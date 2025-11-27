<?php
declare(strict_types=1);

namespace Modules\hotelflow\Services;

final class EmailService
{
    /**
     * Build subject + body for pre-arrival invite email.
     *
     * @return array{0:string,1:string} [subject, body]
     */
    public static function buildPreArrivalInvite(string $guestName, string $link): array
    {
        $subject = 'Complete Your HotelFlow Reservation';

        $bodyLines = [
            "Dear {$guestName},",
            "",
            "Thank you for choosing our property.",
            "To confirm your booking, please complete your reservation details using the secure link below:",
            "",
            $link,
            "",
            "The link is valid for 24 hours.",
            "",
            "If you did not request this, you can ignore this email.",
            "",
            "Best regards,",
            "HotelFlow Frontdesk",
        ];

        $body = implode("\n", $bodyLines);

        return [$subject, $body];
    }
}