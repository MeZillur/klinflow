<?php
declare(strict_types=1);

namespace Modules\hotelflow\Services;

final class EmailService
{
    /** Build subject/body for pre-arrival invite */
    public function buildPrearrivalMail(string $guestName, string $link): array
    {
        $guestName = trim($guestName);
        if ($guestName === '') {
            $guestName = 'Guest';
        }

        $subject = 'Complete your reservation details (HotelFlow)';
        $bodyLines = [
            "Dear {$guestName},",
            "",
            "Thank you for choosing our property.",
            "Please complete your reservation details using the secure link below:",
            $link,
            "",
            "This link will expire in 24 hours.",
            "",
            "Best regards,",
            "Front Desk Team",
        ];

        $body = implode("\n", $bodyLines);

        return [
            'subject' => $subject,
            'body'    => $body,
        ];
    }

    /** Build mailto: href so staff can send from their own Gmail/Outlook/etc. */
    public function buildMailtoHref(string $to, string $subject, string $body): string
    {
        $toEnc      = rawurlencode(trim($to));
        $subjectEnc = rawurlencode($subject);
        $bodyEnc    = rawurlencode($body);

        return "mailto:{$toEnc}?subject={$subjectEnc}&body={$bodyEnc}";
    }
}