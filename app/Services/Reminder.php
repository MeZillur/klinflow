<?php
declare(strict_types=1);

namespace App\Services;

final class Reminder
{
    /**
     * Fire-and-forget reminder that hits both email & SMS.
     * - $emailTemplate is a filesystem path (e.g. apps/Emails/trial_10day_notice.php).
     * - $smsText is a plain string (keep it short).
     */
    public static function emailAndSms(
        string $emailTo,
        string $emailSubject,
        string $emailTemplate,
        array  $emailData,
        string $emailFrom,
        ?string $smsToE164 = null,
        ?string $smsText   = null
    ): void {
        // Email (ignore failure silently to not block caller)
        if ($emailTo) {
            Mailer::sendTemplate($emailTo, $emailSubject, $emailTemplate, $emailData, $emailFrom, 'KlinFlow');
        }

        // SMS (optional)
        if ($smsToE164 && $smsText) {
            Sms::send($smsToE164, $smsText, 'reminder');
        }
    }
}