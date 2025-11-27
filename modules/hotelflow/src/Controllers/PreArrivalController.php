<?php
declare(strict_types=1);

namespace Modules\hotelflow\Controllers;

use PDO;
use DateTimeImmutable;
use Throwable;
use Modules\hotelflow\Services\EmailService;


final class PreArrivalController extends BaseController
{
    /* ---------------------------------------------------------
     * 0. Helpers (use BaseController’s context)
     * ------------------------------------------------------- */

    /** org_id via BaseController helper */
    protected function orgIdFromCtx(array $c): int
    {
        // if BaseController already has orgId(), you can simply call:
        if (method_exists($this, 'orgId')) {
            /** @var callable $fn */
            $fn = [$this, 'orgId'];
            return (int)\call_user_func($fn, $c);
        }

        if (isset($c['org_id']) && (int)$c['org_id'] > 0) {
            return (int)$c['org_id'];
        }
        if (\PHP_SESSION_ACTIVE !== \session_status()) {
            @\session_start();
        }
        $org = $_SESSION['tenant_org'] ?? null;
        return (int)($org['id'] ?? 0);
    }

    protected function moduleBaseFromCtx(array $c): string
    {
        $base = (string)($c['module_base'] ?? '/apps/hotelflow');
        return rtrim($base, '/');
    }

    protected function notify(array $c, string $msg, string $type = 'info'): void
    {
        if (method_exists($this, 'toast')) {
            $this->toast($msg, $type);
            return;
        }
        if (\PHP_SESSION_ACTIVE !== \session_status()) {
            @\session_start();
        }
        $_SESSION['hf_flash'][] = ['type' => $type, 'msg' => $msg];
    }

    protected function resolvePropertyId(PDO $pdo, int $orgId): int
    {
        if ($orgId <= 0) return 0;
        $q = $pdo->prepare("
            SELECT id
              FROM hms_properties
             WHERE org_id = :o
             ORDER BY id
             LIMIT 1
        ");
        $q->execute([':o' => $orgId]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        return $row ? (int)$row['id'] : 0;
    }

    protected function e(string $v): string
    {
        return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
    }

    /* ---------------------------------------------------------
     * 1. Launch screen  (GET /reservations/prearrival-launch)
     * ------------------------------------------------------- */
    public function launch(array $ctx): void
    {
        $c = $this->ctx($ctx);
        $this->view('reservations/prearrival-launch', [
            'title' => 'Pre-arrival invite',
        ], $c);
    }

    /* ---------------------------------------------------------
     * 2. Staff-side send (POST /reservations/prearrival/send)
     * ------------------------------------------------------- */
    public function send(array $ctx): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        $this->notify($ctx, 'Invalid request method for pre-arrival invite.', 'error');
        $this->redirect('reservations', $ctx);
        return;
    }

    $c     = $this->ctx($ctx);
    $orgId = $this->orgIdFromCtx($c);
    $pdo   = $this->pdo();

    if ($orgId <= 0) {
        $this->notify($c, 'Organisation context missing; cannot send pre-arrival invite.', 'error');
        $this->redirect('reservations', $c);
        return;
    }

    $name   = trim((string)($_POST['name']   ?? ''));
    $email  = trim((string)($_POST['email']  ?? ''));
    $mobile = trim((string)($_POST['mobile'] ?? ''));

    if ($name === '' || $email === '' || $mobile === '') {
        $this->notify($c, 'Name, email and mobile are required to send pre-arrival link.', 'error');
        $this->redirect('reservations/prearrival-launch', $c);
        return;
    }

    // token + expiry
    try {
        $token = \bin2hex(\random_bytes(32));
    } catch (Throwable) {
        $token = \bin2hex(\random_bytes(16));
    }

    $now     = new DateTimeImmutable('now');
    $created = $now->format('Y-m-d H:i:s');
    $expires = $now->modify('+24 hours')->format('Y-m-d H:i:s');

    try {
        $stmt = $pdo->prepare("
            INSERT INTO hms_prearrival_tokens (
                org_id,
                lead_name,
                lead_mobile,
                lead_email,
                token,
                expires_at,
                created_at
            ) VALUES (
                :o,
                :n,
                :m,
                :e,
                :t,
                :x,
                :c
            )
        ");
        $stmt->execute([
            ':o' => $orgId,
            ':n' => $name,
            ':m' => $mobile,
            ':e' => $email,
            ':t' => $token,
            ':x' => $expires,
            ':c' => $created,
        ]);
    } catch (Throwable $e) {
        $this->notify($c, 'Failed to store pre-arrival token: '.$e->getMessage(), 'error');
        $this->redirect('reservations/prearrival-launch', $c);
        return;
    }

    // org-based base like /t/{slug}/apps/hotelflow
    $base = $this->moduleBaseFromCtx($c);

    // start with relative path
    $publicLink = $base . '/prearrival?token=' . urlencode($token);

    // upgrade to absolute URL: https://klinflow.com/t/{slug}/apps/hotelflow/…
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host   = $_SERVER['HTTP_HOST'] ?? '';
    if ($host !== '') {
        $publicLink = $scheme . $host . $publicLink;
    }

    // build mail subject/body in service
    $mailer  = new EmailService();
    $payload = $mailer->buildPrearrivalMail($name, $publicLink);

    // prepare a mailto: link instead of sending directly
    $mailto = $mailer->buildMailtoHref($email, $payload['subject'], $payload['body']);

    // flash + redirect back to launch so staff can click the mailto
    if (\PHP_SESSION_ACTIVE !== \session_status()) {
        @\session_start();
    }
    $_SESSION['hf_prearrival_last'] = [
        'name'        => $name,
        'email'       => $email,
        'mobile'      => $mobile,
        'link'        => $publicLink,
        'mailto_href' => $mailto,
    ];

    $this->notify($c, 'Pre-arrival link generated. Click the email button to send.', 'success');
    $this->redirect('reservations/prearrival-launch', $c);
}

    /* ---------------------------------------------------------
 * 3. Guest form + submit (form() and submit())
 * ------------------------------------------------------- */
public function form(array $ctx): void
{
    $token = trim((string)($_GET['token'] ?? ''));
    if ($token === '') {
        http_response_code(400);
        echo 'Invalid pre-arrival link.';
        return;
    }

    $c    = $this->ctx($ctx);
    $pdo  = $this->pdo();
    $base = $this->moduleBaseFromCtx($c);

    $stmt = $pdo->prepare("
        SELECT *
          FROM hms_prearrival_tokens
         WHERE token = :t
           AND used_at IS NULL
           AND expires_at > NOW()
         LIMIT 1
    ");
    $stmt->execute([':t' => $token]);
    $lead = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$lead) {
        http_response_code(410);
        echo 'This pre-arrival link is expired or already used.';
        return;
    }

    $leadName   = $this->e((string)$lead['lead_name']);
    $leadEmail  = $this->e((string)$lead['lead_email']);
    $leadMobile = $this->e((string)$lead['lead_mobile']);
    $tokenH     = $this->e($token);
    $action     = $this->e($base . '/prearrival?token=' . urlencode($token));

    echo <<<HTML
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Complete Your Reservation – HotelFlow</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{
      font-family:system-ui,-apple-system,Segoe UI,Roboto,Inter,Arial;
      background:#eef2f7;
      margin:0;
      padding:24px;
      color:#0f172a
    }
    .card{
      max-width:640px;
      margin:0 auto;
      background:#fff;
      border-radius:20px;
      border:1px solid #e2e8f0;
      padding:24px 24px 28px;
      box-shadow:0 12px 30px rgba(15,23,42,.08)
    }
    h1{margin:0 0 8px;font-size:22px}
    p{margin:0 0 10px;font-size:14px;color:#64748b}
    label{font-size:13px;color:#0f172a;display:block;margin-bottom:4px}
    input,textarea,select{
      width:100%;
      box-sizing:border-box;
      padding:8px 10px;
      border-radius:10px;
      border:1px solid #cbd5f5;
      font-size:14px;
      background:#fff
    }
    input:focus,textarea:focus,select:focus{
      outline:none;
      border-color:#22a638;
      box-shadow:0 0 0 1px #22a63833
    }
    .row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    .mt{margin-top:12px}
    .btn{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      gap:6px;
      margin-top:16px;
      padding:9px 16px;
      border-radius:999px;
      border:none;
      font-size:14px;
      font-weight:600;
      cursor:pointer;
      background:#228B22;
      color:#fff
    }
    .btn:disabled{opacity:.6;cursor:not-allowed}
    .meta{font-size:12px;color:#94a3b8;margin-top:4px}
    .readonly-box{
      background:#f8fafc;
      border-radius:12px;
      padding:8px 10px;
      font-size:13px;
      border:1px dashed #cbd5f5;
      margin-bottom:12px
    }
  </style>
</head>
<body>
  <div class="card">
    <h1>Complete your reservation</h1>
    <p>Hi {$leadName}, we saved your contact details. Please share your stay plan so our team can confirm your booking.</p>

    <div class="readonly-box">
      <div><strong>Name:</strong> {$leadName}</div>
      <div><strong>Mobile:</strong> {$leadMobile}</div>
      <div><strong>Email:</strong> {$leadEmail}</div>
      <div class="meta">These details were collected by our frontdesk over phone.</div>
    </div>

    <form method="post" action="{$action}">
      <div class="row">
        <div>
          <label>Check-in date</label>
          <input type="date" name="check_in" required>
        </div>
        <div>
          <label>Check-out date</label>
          <input type="date" name="check_out" required>
        </div>
      </div>

      <div class="row mt">
        <div>
          <label>Adults</label>
          <input type="number" name="adults" min="1" value="1">
        </div>
        <div>
          <label>Children</label>
          <input type="number" name="children" min="0" value="0">
        </div>
      </div>

      <div class="row mt">
        <div>
          <label>Preferred room type (optional)</label>
          <select name="room_type_hint">
            <option value="">Select room type...</option>
            <option>Standard Single</option>
            <option>Standard Double</option>
            <option>Deluxe King</option>
            <option>Deluxe Twin</option>
            <option>Executive Room</option>
            <option>Family Suite</option>
            <option>Studio Suite</option>
            <option>Presidential Suite</option>
          </select>
        </div>
        <div>
          <label>Approx. arrival time (optional)</label>
          <input type="text" name="arrival_time" placeholder="e.g. 2:30 PM">
        </div>
      </div>

      <div class="mt">
        <label>Special requests / notes (optional)</label>
        <textarea name="notes" rows="3" placeholder="Airport pickup, late check-in, dietary needs..."></textarea>
      </div>

      <button class="btn" type="submit">
        <span>Submit reservation details</span>
      </button>
      <div class="meta">
        Your data is linked with a secure token (<code>{$tokenH}</code>) and will be used only to manage this reservation.
      </div>
    </form>
  </div>
</body>
</html>
HTML;
}
    /* ============================================================
     * 4. Guest-side: pre-arrival submit (POST)
     * ------------------------------------------------------------
     * POST /prearrival?token=xxxx
     * ========================================================== */
    public function submit(array $ctx): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            echo 'Method not allowed.';
            return;
        }

        $token = trim((string)($_GET['token'] ?? ''));
        if ($token === '') {
            http_response_code(400);
            echo 'Invalid pre-arrival link.';
            return;
        }

        $c   = $this->ctx($ctx);
        $pdo = $this->pdo();

        $pdo->beginTransaction();
        try {
            // 1) Lock token row
            $q = $pdo->prepare("
    		SELECT *
      		FROM hms_prearrival_tokens
     		WHERE token = :t
       		AND used_at IS NULL
       		AND expires_at > NOW()
     		ORDER BY id
     		LIMIT 1
     		FOR UPDATE
			");
            $q->execute([':t' => $token]);
            $lead = $q->fetch(PDO::FETCH_ASSOC);

            if (!$lead) {
                $pdo->rollBack();
                http_response_code(410);
                echo 'This pre-arrival link is expired or already used.';
                return;
            }

            $orgId = (int)$lead['org_id'];
            if ($orgId <= 0) {
                $pdo->rollBack();
                http_response_code(400);
                echo 'Organisation not found for this link.';
                return;
            }

            $checkIn   = trim((string)($_POST['check_in']   ?? ''));
            $checkOut  = trim((string)($_POST['check_out']  ?? ''));
            $adults    = (int)($_POST['adults']             ?? 1);
            $children  = (int)($_POST['children']           ?? 0);
            $roomHint  = trim((string)($_POST['room_type_hint'] ?? ''));
            $arrival   = trim((string)($_POST['arrival_time']   ?? ''));
            $notes     = trim((string)($_POST['notes']          ?? ''));

            if ($checkIn === '' || $checkOut === '') {
                throw new \RuntimeException('Check-in and check-out dates are required.');
            }

            // 2) Resolve property_id
            $propertyId = $this->resolvePropertyId($pdo, $orgId);
            if ($propertyId <= 0) {
                throw new \RuntimeException('No property is configured for this organisation.');
            }

            // 3) Create guest
            $gIns = $pdo->prepare("
                INSERT INTO hms_guests (
                    org_id,
                    full_name,
                    country,
                    mobile,
                    email,
                    address,
                    channel,
                    id_type,
                    id_number,
                    bio_face_path,
                    bio_id_front_path,
                    bio_id_back_path,
                    created_at,
                    updated_at
                ) VALUES (
                    :o,
                    :full_name,
                    NULL,
                    :mobile,
                    :email,
                    NULL,
                    :channel,
                    NULL,
                    NULL,
                    NULL,
                    NULL,
                    NULL,
                    NOW(),
                    NOW()
                )
            ");
            $gIns->execute([
                ':o'         => $orgId,
                ':full_name' => (string)$lead['lead_name'],
                ':mobile'    => (string)$lead['lead_mobile'],
                ':email'     => (string)$lead['lead_email'],
                ':channel'   => 'Pre-arrival',
            ]);
            $guestId = (int)$pdo->lastInsertId();
            if ($guestId <= 0) {
                throw new \RuntimeException('Failed to create guest record.');
            }

            // 4) Build notes/meta
            $meta = [];
            if ($roomHint !== '')  $meta['room_type_hint'] = $roomHint;
            if ($arrival  !== '')  $meta['arrival_time']   = $arrival;

            $combinedNotes = $notes;
            if (!empty($meta)) {
                $combinedNotes .= ($combinedNotes !== '' ? "\n\n" : '')
                                . 'Pre-arrival meta: ' . json_encode($meta, JSON_UNESCAPED_UNICODE);
            }

            // 5) Create reservation
            $rIns = $pdo->prepare("
                INSERT INTO hms_reservations (
                    org_id,
                    guest_id,
                    property_id,
                    check_in,
                    check_out,
                    adults,
                    children,
                    channel,
                    notes,
                    status,
                    created_at,
                    updated_at
                ) VALUES (
                    :o,
                    :guest_id,
                    :property_id,
                    :ci,
                    :co,
                    :adults,
                    :children,
                    :channel,
                    :notes,
                    :status,
                    NOW(),
                    NOW()
                )
            ");
            $rIns->execute([
                ':o'           => $orgId,
                ':guest_id'    => $guestId,
                ':property_id' => $propertyId,
                ':ci'          => $checkIn,
                ':co'          => $checkOut,
                ':adults'      => $adults,
                ':children'    => $children,
                ':channel'     => 'Pre-arrival',
                ':notes'       => ($combinedNotes !== '' ? $combinedNotes : null),
                ':status'      => 'pending_confirmation',
            ]);
            $reservationId = (int)$pdo->lastInsertId();
            if ($reservationId <= 0) {
                throw new \RuntimeException('Failed to create reservation.');
            }

            // 6) Generate reservation code
            $now   = new DateTimeImmutable('now');
            $code  = 'RES-' . $now->format('Ymd') . '-' . $reservationId;
            $uCode = $pdo->prepare("
                UPDATE hms_reservations
                   SET code = :code
                 WHERE id = :id AND org_id = :o
            ");
            $uCode->execute([
                ':code' => $code,
                ':id'   => $reservationId,
                ':o'    => $orgId,
            ]);

            // 7) Mark token used
            $uTok = $pdo->prepare("
                UPDATE hms_prearrival_tokens
                   SET used_at = NOW(),
                       reservation_id = :r
                 WHERE id = :id
            ");
            $uTok->execute([
                ':r'  => $reservationId,
                ':id' => (int)$lead['id'],
            ]);

            $pdo->commit();

            $codeH = $this->e($code);
            echo <<<HTML
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Reservation submitted – HotelFlow</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Inter,Arial;background:#f1f5f9;margin:0;padding:24px;color:#0f172a}
    .card{max-width:560px;margin:0 auto;background:#fff;border-radius:18px;border:1px solid #e2e8f0;padding:24px 24px 28px;box-shadow:0 12px 30px rgba(15,23,42,.08);text-align:center}
    h1{margin:0 0 8px;font-size:22px}
    p{margin:4px 0 0;font-size:14px;color:#64748b}
    .code{margin-top:14px;font-size:15px;font-weight:700;color:#14532d}
  </style>
</head>
<body>
  <div class="card">
    <h1>Thank you!</h1>
    <p>Your reservation details have been submitted successfully.</p>
    <p class="code">Your reference: {$codeH}</p>
    <p style="margin-top:10px;font-size:13px;color:#94a3b8">
      Our frontdesk team will review and confirm your booking shortly.
    </p>
  </div>
</body>
</html>
HTML;

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            http_response_code(400);
            echo 'Failed to submit reservation: ' . $this->e($e->getMessage());
        }
    }
}