<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Unified mailer with Resend API / SMTP / Log transports.
 *
 * Usage:
 *   (new Mailer())->send([
 *     'to'      => ['user@example.com', 'Other <other@example.com>'],
 *     'subject' => 'Hi',
 *     'html'    => '<p>Hello</p>',
 *     // optional:
 *     'text'    => 'Hello',
 *     'from'    => 'welcome@mail.klinflow.com',
 *     'type'    => 'welcome',   // welcome|pass_reset|invite|billing|reminder (used if 'from' missing)
 *     'headers' => ['X-App: KlinFlow'],
 *   ]);
 */
final class Mailer
{
    private string $driver;
    private array $cfg;

    public function __construct(?array $override = null)
    {
        $this->cfg = $override ?? $this->loadConfig();
        $this->driver = strtolower((string)($this->cfg['driver'] ?? 'log'));
    }

    /** Public simple façade */
    public function send(array $m): bool
    {
        $envelope = $this->normalize($m);        // validate + fill defaults
        try {
            switch ($this->driver) {
                case 'resend': return $this->sendViaResend($envelope);
                case 'smtp':   return $this->sendViaSmtp($envelope);
                case 'log':
                default:       return $this->sendToLog($envelope);
            }
        } catch (\Throwable $e) {
            error_log('[Mailer] transport failed: '.$e->getMessage());
            // Last-chance safety: write to log even if transport fails.
            return $this->sendToLog($envelope);
        }
    }

    // ---------- Typed helpers (choose sender automatically) ----------

    public function sendWelcome(string $to, string $subject, string $html, ?string $text = null): bool
    {
        return $this->send(['to'=>[$to],'subject'=>$subject,'html'=>$html,'text'=>$text,'type'=>'welcome']);
    }
    public function sendPassReset(string $to, string $subject, string $html, ?string $text = null): bool
    {
        return $this->send(['to'=>[$to],'subject'=>$subject,'html'=>$html,'text'=>$text,'type'=>'pass_reset']);
    }
    public function sendInvite(string $to, string $subject, string $html, ?string $text = null): bool
    {
        return $this->send(['to'=>[$to],'subject'=>$subject,'html'=>$html,'text'=>$text,'type'=>'invites']);
    }
    public function sendBilling(string $to, string $subject, string $html, ?string $text = null): bool
    {
        return $this->send(['to'=>[$to],'subject'=>$subject,'html'=>$html,'text'=>$text,'type'=>'billing']);
    }
    public function sendReminder(string $to, string $subject, string $html, ?string $text = null): bool
    {
        return $this->send(['to'=>[$to],'subject'=>$subject,'html'=>$html,'text'=>$text,'type'=>'reminder']);
    }

    // ========================= Internals =============================

    private function loadConfig(): array
    {
        // Prefer config/mail.php but allow pure ENV fallback
        $root = dirname(__DIR__, 2); // project root
        $cfgFile = $root.'/config/mail.php';
        $cfg = is_file($cfgFile) ? (array)require $cfgFile : [];

        // ENV fallbacks (never override explicit config values)
        $env = [
            'driver'       => getenv('MAIL_DRIVER') ?: null,     // resend|smtp|log
            'api_key'      => getenv('RESEND_API_KEY') ?: null,  // for driver=resend
            'host'         => getenv('MAIL_HOST') ?: null,       // smtp
            'port'         => getenv('MAIL_PORT') ?: null,
            'username'     => getenv('MAIL_USERNAME') ?: null,
            'password'     => getenv('MAIL_PASSWORD') ?: null,
            'encryption'   => getenv('MAIL_ENCRYPTION') ?: null, // tls|null
            'domain'       => getenv('MAIL_DOMAIN') ?: null,     // e.g., mail.klinflow.com
            'from'         => [
                'name'  => getenv('MAIL_FROM_NAME') ?: null,
                'email' => getenv('MAIL_FROM_EMAIL') ?: null,
            ],
            'senders'      => [
                'welcome'    => getenv('MAIL_SENDER_WELCOME')    ?: null,
                'pass_reset' => getenv('MAIL_SENDER_PASSRESET')  ?: null,
                'invite'     => getenv('MAIL_SENDER_INVITES')    ?: null,
                'billing'    => getenv('MAIL_SENDER_BILLING')    ?: null,
                'reminder'   => getenv('MAIL_SENDER_REMINDER')   ?: null,
            ],
        ];

        // merge env into cfg only where cfg is missing
        $cfg = $this->deepMergePreferLeft($cfg, $env);

        // Defaults
        $cfg['driver']   = $cfg['driver']   ?? 'log';
        $cfg['from']     = $cfg['from']     ?? ['name'=>'KlinFlow','email'=>'no-reply@mail.klinflow.com'];
        $cfg['senders']  = $cfg['senders']  ?? [];
        return $cfg;
    }

    private function deepMergePreferLeft(array $left, array $right): array
    {
        foreach ($right as $k => $v) {
            if (!array_key_exists($k, $left) || $left[$k] === null || $left[$k] === '') {
                $left[$k] = $v;
            } elseif (is_array($left[$k]) && is_array($v)) {
                $left[$k] = $this->deepMergePreferLeft($left[$k], $v);
            }
        }
        return $left;
    }

    /** Validate, flatten, and fill defaults. */
    private function normalize(array $m): array
    {
        // recipients
        $to = array_values(array_filter(array_map('strval', (array)($m['to'] ?? []))));
        if (!$to) throw new \InvalidArgumentException('Mailer: "to" is required.');

        $subject = trim((string)($m['subject'] ?? ''));
        if ($subject === '') throw new \InvalidArgumentException('Mailer: "subject" is required.');

        $html = (string)($m['html'] ?? '');
        $text = (string)($m['text'] ?? '');

        if ($html === '' && $text === '') {
            $text = '(no content)';
        }

        // from: explicit > typed sender > default
        $from = trim((string)($m['from'] ?? ''));
        if ($from === '') {
            $type = (string)($m['type'] ?? '');
            $from = $this->pickSenderForType($type) ?: ($this->cfg['from']['email'] ?? 'no-reply@mail.klinflow.com');
        }

        // optional headers
        $headers = [];
        foreach ((array)($m['headers'] ?? []) as $h) {
            $h = trim((string)$h);
            if ($h !== '') $headers[] = $h;
        }

        return compact('to','subject','html','text','from','headers');
    }

    private function pickSenderForType(string $type): ?string
    {
        $map = (array)($this->cfg['senders'] ?? []);
        return $map[$type] ?? null;
    }

    // ---------------------- Transports ----------------------

    private function sendViaResend(array $env): bool
    {
        $key = trim((string)($this->cfg['api_key'] ?? getenv('RESEND_API_KEY') ?: ''));
        if ($key === '') throw new \RuntimeException('Resend API key missing.');

        $payload = [
            'from'    => $this->formatFrom($env['from']),
            'to'      => $env['to'],
            'subject' => $env['subject'],
        ];
        if ($env['html'] !== '') $payload['html'] = $env['html'];
        if ($env['text'] !== '') $payload['text'] = $env['text'];

        $ch = curl_init('https://api.resend.com/emails');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer '.$key,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT        => 20,
        ]);
        $res  = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($res === false || $code >= 400) {
            error_log('[Mailer:Resend] HTTP '.$code.' payload='.substr(json_encode($payload),0,500).' err='.$err.' res='.$res);
            return false;
        }
        return true;
    }

    /** Minimal SMTP using PHP's mail() as a portability fallback. */
    private function sendViaSmtp(array $env): bool
    {
        // If your stack already wires PHPMailer/SwiftMailer, you can swap in here.
        // For now, do a standards-compliant mail() with headers.
        $headers  = "MIME-Version: 1.0\r\n";
        if ($env['html'] !== '') {
            $headers .= "Content-type: text/html; charset=UTF-8\r\n";
            $body = $env['html'];
        } else {
            $headers .= "Content-type: text/plain; charset=UTF-8\r\n";
            $body = $env['text'];
        }
        $headers .= 'From: '.$this->formatFrom($env['from'])."\r\n";
        foreach ($env['headers'] as $h) $headers .= $h."\r\n";

        // Send to each recipient so failures are isolated
        $ok = true;
        foreach ($env['to'] as $rcpt) {
            $ok = @mail($rcpt, $env['subject'], $body, $headers) && $ok;
        }
        if (!$ok) error_log('[Mailer:SMTP] mail() reported failure.');
        return $ok;
    }

    private function sendToLog(array $env): bool
    {
        $line = "[Mailer:LOG] ".date('c')." from={$env['from']} to=".implode(',', $env['to'])." subj=\"{$env['subject']}\"";
        error_log($line);
        return true;
    }

    private function formatFrom(string $from): string
    {
        // accept "Name <email@x>" or "email@x"
        return $from;
    }
}