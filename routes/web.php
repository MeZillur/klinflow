<?php
// --- Minimal shims so routes can render even if global helpers aren't loaded yet ---
if (!defined('BASE_PATH')) {
    // Resolve to project root predictably
    $bp = dirname(__DIR__);                   // e.g. /home/klinflow/htdocs/www.klinflow.com
    define('BASE_PATH', realpath($bp) ?: $bp);
}

if (!function_exists('view')) {
    /**
     * view('site/start')            -> apps/Public/Views/site/start.php
     * view('Public/pages/about')    -> apps/Public/Views/pages/about.php
     */
    function view(string $path, array $data = []): void {
        $rel = str_replace(['\\', '.'], ['/', '/'], $path);

        // If user passed "Public/..." we assume full path after /apps/
        $file = str_starts_with($rel, 'Public/')
            ? BASE_PATH . '/apps/' . $rel . '.php'
            : BASE_PATH . '/apps/Public/Views/' . $rel . '.php';

        if (!is_file($file)) {
            http_response_code(500);
            echo "View not found: {$path}\nResolved: {$file}";
            return;
        }

        // Make $data keys available but never overwrite existing vars
        extract($data, EXTR_SKIP);
        include $file;
    }
}

// --- Public/marketing routes (keep these above tenant/cp slug routes) ---
return [
    ['GET', '/',        fn () => view('Public/Views/index')],
    ['GET', '/about',   fn () => view('Public/Views/about')],
    ['GET', '/pricing', fn () => view('Public/Views/pricing')],
    ['GET', '/contact', fn () => view('Public/Views/contact')],
    ['GET', '/ticket',  fn () => view('Public/Views/ticket')],   // <-- missing comma fixed ✅

    // (optional) trailing-slash niceties
    ['GET', '/contact/', fn () => view('Public/Views/contact')],
    ['GET', '/about/',   fn () => view('Public/Views/about')],
    ['GET', '/pricing/', fn () => view('Public/Views/pricing')],
  	['GET', '/signup', fn () => view('Public/Views/signup')],
 	['GET', '/why', fn () => view('Public/Views/why')],
  	['GET', '/terms', fn () => view('Public/Views/terms')],
  	['GET', '/privacy', fn () => view('Public/Views/privacy')],
  	['GET', '/help', fn () => view('Public/Views/help')],
  	['GET', '/security', fn () => view('Public/Views/security')],
  	['GET', '/changelog', fn () => view('Public/Views/changelog')],

  // RSS 2.0 feed  → /changelog.xml
  ['GET', '/changelog.xml', fn () => require __DIR__ . '/apps/Public/Feeds/changelog.xml.php'],

  // Atom 1.0 feed → /changelog.atom
  ['GET', '/changelog.atom', fn () => require __DIR__ . '/apps/Public/Feeds/changelog.atom.php'],

  // Subscribe API (AJAX POST)
  ['POST', '/api/subscribe', fn () => require __DIR__ . '/apps/Public/Api/subscribe.php'],

  
    

    ['POST','/contact', fn () => null, ['csrf']],

    // ---------- Hard-proof mail diagnostic using Resend API (no other files needed) ----------
    ['GET','/mail-test', function () {
        header('Content-Type: text/plain; charset=utf-8');

        // 0) Small helper to stream progress even if output buffering is on.
        $step = function (string $msg) { echo $msg . "\n"; @flush(); @ob_flush(); };

        try {
            $step("Mail test: start");

            // 1) Inputs
            $to    = isset($_GET['to']) ? (string)$_GET['to'] : 'mezillur1983@gmail.com';
            $name  = 'Zillur Rahman';

            // From must match your VERIFIED domain on Resend (e.g., no-reply@mail.klinflow.com)
            $apiKey = getenv('RESEND_API_KEY') ?: 're_2JqgAeVb_PtTDSjEJcLUaJryy1hYTSYoK';
            $from   = getenv('RESEND_FROM')     ?: 'no-reply@mail.klinflow.com';

            if (!$apiKey || stripos($apiKey, 're_') !== 0) {
                $step("ERROR: Resend API key missing/invalid. Set RESEND_API_KEY in env.");
                exit;
            }
            if (strpos($from, '@') === false) {
                $step("ERROR: From address invalid. Expected something@mail.klinflow.com");
                exit;
            }

            $step("Using from={$from}, to={$to}");

            // 2) Build payload for Resend API
            $payload = [
                'from'    => "KlinFlow <{$from}>",
                'to'      => [$to],
                'subject' => 'KlinFlow · Resend diag',
                'html'    => '<p>Hello from <strong>KlinFlow</strong> via Resend API ✅</p>',
                'text'    => 'Hello from KlinFlow via Resend API ✅',
            ];
            $json = json_encode($payload);

            // 3) cURL call
            $step("Sending via Resend…");
            $ch = curl_init('https://api.resend.com/emails');
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_HTTPHEADER     => [
                    'Authorization: Bearer ' . $apiKey,
                    'Content-Type: application/json',
                ],
                CURLOPT_POSTFIELDS     => $json,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 20,
            ]);
            $resp = curl_exec($ch);
            $err  = curl_error($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            // 4) Result
            if ($err) {
                $step("cURL error: {$err}");
                exit;
            }

            $step("HTTP {$code}");
            $step("Response: " . $resp);

            if ($code >= 200 && $code < 300) {
                $step("OK: email accepted by Resend. Check your inbox/spam.");
            } else {
                $step("FAIL: API did not accept the message. See HTTP/Response above.");
            }
        } catch (\Throwable $e) {
            // Show the real reason instead of a blank page
            echo "Exception: ".$e->getMessage()." @ ".$e->getFile().":".$e->getLine()."\n";
        }
        exit;
    }],
];