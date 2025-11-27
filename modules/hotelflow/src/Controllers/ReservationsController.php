<?php
declare(strict_types=1);

namespace Modules\hotelflow\Controllers;

use PDO;
use DateTimeImmutable;
use Throwable;
use Modules\hotelflow\Services\BiometricService;

final class ReservationsController extends BaseController
{
  
  	/* ---------------------------------------------------------
     * Small helpers (safe for multi-tenant)
     * ------------------------------------------------------- */
	    /** Link guest to reservation as primary if not already linked */
    private function ensurePrimaryReservationGuest(PDO $pdo, int $orgId, int $reservationId, int $guestId): void
    {
        if ($orgId <= 0 || $reservationId <= 0 || $guestId <= 0) return;

        try {
            // already exists?
            $chk = $pdo->prepare("
                SELECT id
                  FROM hms_reservation_guests
                 WHERE org_id = :o
                   AND reservation_id = :r
                   AND guest_id = :g
                 LIMIT 1
            ");
            $chk->execute([
                ':o' => $orgId,
                ':r' => $reservationId,
                ':g' => $guestId,
            ]);
            if ($chk->fetchColumn()) {
                return;
            }

            $ins = $pdo->prepare("
                INSERT INTO hms_reservation_guests (
                    org_id,
                    reservation_id,
                    guest_id,
                    role,
                    requires_checkin,
                    checkin_status
                ) VALUES (
                    :o,
                    :r,
                    :g,
                    'primary',
                    1,
                    'pending'
                )
            ");
            $ins->execute([
                ':o' => $orgId,
                ':r' => $reservationId,
                ':g' => $guestId,
            ]);
        } catch (\Throwable $e) {
            // non-fatal – reservation will still work
        }
    }

    /**
     * Calculate reservation-level checkin_status from guest rows.
     * - all checkin_status = 'checked_in'        → 'all_checked_in'
     * - any 'checked_in' but some other pending → 'partial'
     * - otherwise                               → 'not_started'
     */
    private function recomputeReservationCheckinStatus(PDO $pdo, int $orgId, int $reservationId): void
    {
        try {
            $q = $pdo->prepare("
                SELECT checkin_status
                  FROM hms_reservation_guests
                 WHERE org_id = :o
                   AND reservation_id = :r
            ");
            $q->execute([':o' => $orgId, ':r' => $reservationId]);
            $rows = $q->fetchAll(PDO::FETCH_COLUMN, 0) ?: [];

            if (!$rows) {
                $status = null;
            } else {
                $allChecked = !in_array('pending', $rows, true)
                              && !in_array('no_show', $rows, true)
                              && !in_array('not_required', $rows, true)
                              && count(array_unique($rows)) === 1
                              && $rows[0] === 'checked_in';

                $anyChecked = in_array('checked_in', $rows, true);

                if ($allChecked) {
                    $status = 'all_checked_in';
                } elseif ($anyChecked) {
                    $status = 'partial';
                } else {
                    $status = 'not_started';
                }
            }

            $u = $pdo->prepare("
                UPDATE hms_reservations
                   SET checkin_status = :cs,
                       updated_at     = NOW()
                 WHERE org_id = :o
                   AND id     = :r
            ");
            $u->execute([
                ':cs' => $status,
                ':o'  => $orgId,
                ':r'  => $reservationId,
            ]);
        } catch (\Throwable $e) {
            // ignore
        }
    }
    
  
  
    /* ---------------------------------------------------------
     * NEW: Confirm a pre-arrival reservation
     * URL: POST /reservations/{id}/confirm-prearrival
     *  - Uses BaseController helpers: ctx(), orgId(), pdo(),
     *    notify(), redirect()
     * ------------------------------------------------------- */
    public function confirmPrearrival(array $ctx, int $id): void
    {
        $c     = $this->ctx($ctx);
        $orgId = $this->orgId($c);
        $pdo   = $this->pdo();

        if ($orgId <= 0) {
            $this->notify($c, 'Organisation context missing; cannot confirm reservation.', 'error');
            $this->redirect('reservations', $c);
            return;
        }

        try {
            $pdo->beginTransaction();

            // 1) Load reservation and lock row
            $q = $pdo->prepare("
                SELECT *
                  FROM hms_reservations
                 WHERE id = :id
                   AND org_id = :org
                 FOR UPDATE
                 LIMIT 1
            ");
            $q->execute([
                ':id'  => $id,
                ':org' => $orgId,
            ]);
            $res = $q->fetch(PDO::FETCH_ASSOC);

            if (!$res) {
                $pdo->rollBack();
                $this->notify($c, 'Reservation not found for this organisation.', 'error');
                $this->redirect('reservations', $c);
                return;
            }

            // Only allow confirm for pre-arrival pending reservations
            if ((string)$res['status'] !== 'pending_confirmation') {
                $pdo->rollBack();
                $this->notify($c, 'Only pending pre-arrival reservations can be confirmed.', 'error');
                $this->redirect('reservations', $c);
                return;
            }

            // 2) Flip status to booked (now it joins arrivals / in-house pipeline)
            $u = $pdo->prepare("
                UPDATE hms_reservations
                   SET status = 'booked',
                       updated_at = NOW()
                 WHERE id = :id
                   AND org_id = :org
                 LIMIT 1
            ");
            $u->execute([
                ':id'  => $id,
                ':org' => $orgId,
            ]);

            $pdo->commit();

            $this->notify($c, 'Pre-arrival reservation confirmed and added to arrivals.', 'success');
            $this->redirect('reservations', $c);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $this->notify($c, 'Failed to confirm reservation: ' . $e->getMessage(), 'error');
            $this->redirect('reservations', $c);
        }
    }

  	    /* ---------------------------------------------------------
     * Cancel a reservation with reason (POST /reservations/{id}/cancel)
     *  - Logs the reason into notes field
     * ------------------------------------------------------- */
    public function cancel(array $ctx, int $id): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $this->flash('Invalid request method for cancellation.', 'error');
            $this->redirectToReservations($ctx);
        }

        $c     = $this->ctx($ctx);
        $orgId = $this->orgIdFromCtx($c);
        $pdo   = $this->pdo();

        if ($orgId <= 0) {
            $this->flash('Organisation context missing; cannot cancel reservation.', 'error');
            $this->redirectToReservations($c);
        }

        $reason = trim((string)($_POST['reason'] ?? ''));
        if ($reason === '') {
            $reason = 'Cancelled from reservations screen.';
        }

        $now = new DateTimeImmutable('now');
        $ts  = $now->format('Y-m-d H:i:s');

        try {
            $pdo->beginTransaction();

            // Lock the reservation row
            $q = $pdo->prepare("
                SELECT *
                  FROM hms_reservations
                 WHERE id = :id
                   AND org_id = :org
                 FOR UPDATE
                 LIMIT 1
            ");
            $q->execute([
                ':id'  => $id,
                ':org' => $orgId,
            ]);
            $res = $q->fetch(PDO::FETCH_ASSOC);

            if (!$res) {
                $pdo->rollBack();
                $this->flash('Reservation not found for this organisation.', 'error');
                $this->redirectToReservations($c);
            }

            $currentStatus = (string)($res['status'] ?? '');
            if ($currentStatus === 'cancelled') {
                $pdo->rollBack();
                $this->flash('Reservation is already cancelled.', 'info');
                $this->redirectToReservations($c);
            }

            // Append reason into notes (simple log)
            $existingNotes = (string)($res['notes'] ?? '');
            $logLine       = '[' . $ts . '] CANCELLED: ' . $reason;
            $newNotes      = trim(
                $existingNotes !== ''
                    ? ($existingNotes . "\n\n" . $logLine)
                    : $logLine
            );

            $u = $pdo->prepare("
                UPDATE hms_reservations
                   SET status     = 'cancelled',
                       notes      = :notes,
                       updated_at = :ts
                 WHERE id = :id
                   AND org_id = :org
                 LIMIT 1
            ");
            $u->execute([
                ':notes' => $newNotes,
                ':ts'    => $ts,
                ':id'    => $id,
                ':org'   => $orgId,
            ]);

            $pdo->commit();
            $this->flash('Reservation cancelled.', 'success');
            $this->redirectToReservations($c);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $this->flash('Failed to cancel reservation: ' . $e->getMessage(), 'error');
            $this->redirectToReservations($c);
        }
    }
  
		/* -------------------------------------------------------------
 * LIST /reservations
 * ----------------------------------------------------------- */
public function index(array $ctx): void
{
    $c   = $this->ctx($ctx);
    $pdo = $this->pdo();

    // ---------- org_id resolve (no external helper) ----------
    $orgId = isset($c['org_id']) ? (int)$c['org_id'] : 0;

    if ($orgId <= 0) {
        if (\PHP_SESSION_ACTIVE !== \session_status()) {
            @\session_start();
        }
        $org   = $_SESSION['tenant_org'] ?? null;
        $orgId = (int)($org['id'] ?? 0);
    }

    if ($orgId <= 0) {
        http_response_code(400);
        echo 'Organisation context missing for reservations list.';
        return;
    }

    // ---------- main query (list + room info + balance) ----------
    $sql = "
        SELECT
            r.id,
            r.code,
            r.status,
            r.check_in,
            r.check_out,
            r.adults,
            r.children,
            r.channel,
            r.notes,
            r.created_at,

            /* extra columns used in index view */
            r.room_type_name,
            r.room_qty,
            r.balance_due,

            g.full_name AS guest_name
        FROM hms_reservations r
        LEFT JOIN hms_guests g
               ON g.id     = r.guest_id
              AND g.org_id = r.org_id
        WHERE r.org_id = :o
        ORDER BY r.id DESC
        LIMIT 200
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':o' => $orgId]);
    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

    $this->view('reservations/index', [
        'title'   => 'Reservations',
        'rows'    => $rows,
        'total'   => count($rows),
        'page'    => 1,
        'limit'   => 200,
        'filters' => [], // filter wiring later
    ], $c);
}
  
    
    /* -------------------------------------------------------------
     * GET /reservations/create  (NEW GUEST FLOW)
     * ----------------------------------------------------------- */
    public function create(array $ctx): void
    {
        $c     = $this->ctx($ctx);
        $orgId = (int)$c['org_id'];
        $pdo   = $this->pdo();

        $roomTypes = [];
        try {
            $rtStmt = $pdo->prepare("
                SELECT id, name
                  FROM hms_room_types
                 WHERE org_id = :o
                 ORDER BY name
            ");
            $rtStmt->execute([':o' => $orgId]);
            $roomTypes = $rtStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            $roomTypes = [];
        }

        $ratePlans = [];
        try {
            $rpStmt = $pdo->prepare("
                SELECT id, name
                  FROM hms_rate_plans
                 WHERE org_id = :o
                 ORDER BY name
            ");
            $rpStmt->execute([':o' => $orgId]);
            $ratePlans = $rpStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            $ratePlans = [];
        }

        $this->view('reservations/create', [
            'title'      => 'New Reservation',
            'roomTypes'  => $roomTypes,
            'ratePlans'  => $ratePlans,
        ], $c);
    }

    /* -------------------------------------------------------------
     * GET /reservations/create-existing  (EXISTING GUEST LOOKUP)
     * ----------------------------------------------------------- */
    public function createExisting(array $ctx): void
    {
        $c    = $this->ctx($ctx);
        $base = $this->moduleBase($c);

        $this->view('reservations/create-existing', [
            'title'       => 'Reservation — Existing Guest',
            'module_base' => $base,
        ], $c);
    }
       
  
     public function walkin(array $ctx): void
{
    $c = $this->ctx($ctx);
    $this->view('reservations/walkin', [
        'title'       => 'Walk-in guest',
    ], $c);
}
  
    /* -------------------------------------------------------------
     * GET or POST /reservations/{id}  (view + save notes)
     * ----------------------------------------------------------- */
    public function show(array $ctx, int $id): void
    {
        $c     = $this->ctx($ctx);
        $orgId = (int)$c['org_id'];
        $pdo   = $this->pdo();

        // ----- Save notes on POST -----
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $notes = trim((string)($_POST['notes'] ?? ''));
            $u = $pdo->prepare("
                UPDATE hms_reservations
                   SET notes = :n
                 WHERE id = :id AND org_id = :o
            ");
            $u->execute([
                ':n'  => $notes,
                ':id' => $id,
                ':o'  => $orgId,
            ]);

            $this->redirect("reservations/{$id}", $c);
            return;
        }

        // ----- Base reservation + guest + photo/ID fields -----
        $stmt = $pdo->prepare("
            SELECT
                r.*,
                g.full_name         AS guest_name,
                g.bio_face_path     AS bio_face_path,
                g.bio_id_front_path AS bio_id_front_path,
                g.bio_id_back_path  AS bio_id_back_path
            FROM hms_reservations r
            LEFT JOIN hms_guests g
                   ON g.id     = r.guest_id
                  AND g.org_id = r.org_id
            WHERE r.org_id = :o
              AND r.id     = :id
            LIMIT 1
        ");
        $stmt->execute([
            ':o'  => $orgId,
            ':id' => $id,
        ]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$res) {
            http_response_code(404);
            echo 'Reservation not found';
            return;
        }

        // For now keep these empty (wire later)
        $rooms    = [];
        $charges  = [];
        $payments = [];
        $events   = [];

        $this->view('reservations/show', [
            'title'    => 'Reservation '.$res['code'],
            'res'      => $res,
            'rooms'    => $rooms,
            'charges'  => $charges,
            'payments' => $payments,
            'events'   => $events,
        ], $c);
    }

     
  
    /* -------------------------------------------------------------
     * POST /reservations  (save from create form)
     * ----------------------------------------------------------- */
    public function store(array $ctx): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $this->redirect('reservations', $ctx);
            return;
        }

        $c     = $this->ctx($ctx);
        $orgId = (int)$c['org_id'];
        $pdo   = $this->pdo();

        $now = new DateTimeImmutable('now');
        $ts  = $now->format('Y-m-d H:i:s');

        // -------- property resolve --------
        $propertyId = (int)($c['property_id'] ?? 0);
        if ($propertyId <= 0) {
            $q = $pdo->prepare("
                SELECT id
                  FROM hms_properties
                 WHERE org_id = :o
                 ORDER BY id
                 LIMIT 1
            ");
            $q->execute([':o' => $orgId]);
            $row = $q->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $propertyId = (int)$row['id'];
            }
        }
        if ($propertyId <= 0) {
            http_response_code(400);
            echo '<div style="padding:16px;font-family:system-ui">'
               . '<h2 style="margin:0 0 8px;color:#b91c1c">Failed to save reservation</h2>'
               . '<div style="color:#4b5563;font-size:14px;">'
               . 'No property is configured for this organisation. Please create an entry in hms_properties first.'
               . '</div></div>';
            return;
        }

        // -------- biometric paths from form --------
        $bioIdFront = trim((string)($_POST['bio_id_front_path'] ?? ''));
        $bioIdBack  = trim((string)($_POST['bio_id_back_path'] ?? ''));
        $bioFace    = trim((string)($_POST['bio_face_path'] ?? ''));

        $guestMode = (string)($_POST['guest_mode'] ?? 'new');

        $pdo->beginTransaction();
        try {
            /* =====================================================
             * 1) GUEST
             * ===================================================*/
            if ($guestMode === 'existing') {
                $guestId   = (int)($_POST['guest_id'] ?? 0);
                $guestName = trim((string)($_POST['guest_name'] ?? ''));

                if ($guestId <= 0) {
                    throw new \RuntimeException('Guest not selected.');
                }

                if ($guestName !== '') {
                    $gUpd = $pdo->prepare("
                        UPDATE hms_guests
                           SET full_name = :n
                         WHERE id = :id AND org_id = :o
                    ");
                    $gUpd->execute([
                        ':n'  => $guestName,
                        ':id' => $guestId,
                        ':o'  => $orgId,
                    ]);
                }

                if ($bioIdFront !== '' || $bioIdBack !== '' || $bioFace !== '') {
                    $gBio = $pdo->prepare("
                        UPDATE hms_guests
                           SET bio_id_front_path = COALESCE(NULLIF(:id_front,''), bio_id_front_path),
                               bio_id_back_path  = COALESCE(NULLIF(:id_back,''),  bio_id_back_path),
                               bio_face_path     = COALESCE(NULLIF(:face,''),     bio_face_path)
                         WHERE id = :id AND org_id = :o
                    ");
                    $gBio->execute([
                        ':id_front' => $bioIdFront,
                        ':id_back'  => $bioIdBack,
                        ':face'     => $bioFace,
                        ':id'       => $guestId,
                        ':o'        => $orgId,
                    ]);
                }

            } else {
                // guest popup form
                $fullName    = trim((string)($_POST['ng_name'] ?? ''));
                $country     = trim((string)($_POST['ng_country'] ?? ''));
                $mobile      = trim((string)($_POST['ng_mobile'] ?? ''));
                $email       = trim((string)($_POST['ng_email'] ?? ''));
                $address     = trim((string)($_POST['ng_address'] ?? ''));
                $channel     = trim((string)($_POST['channel'] ?? 'Direct'));
                $idType      = trim((string)($_POST['ng_id_type'] ?? ''));
                $idNumber    = trim((string)($_POST['ng_id_number'] ?? ''));

                $ageRaw       = trim((string)($_POST['ng_age'] ?? ''));
                $age          = ($ageRaw !== '' ? (int)$ageRaw : null);
                $gender       = trim((string)($_POST['ng_gender'] ?? ''));
                $nationality  = trim((string)($_POST['ng_nationality'] ?? 'Bangladesh'));

                if ($fullName === '' || $mobile === '') {
                    throw new \RuntimeException('Guest name and mobile are required.');
                }

                $gIns = $pdo->prepare("
                    INSERT INTO hms_guests (
                        org_id,
                        full_name,
                        age,
                        gender,
                        nationality,
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
                        :age,
                        :gender,
                        :nationality,
                        :country,
                        :mobile,
                        :email,
                        :address,
                        :channel,
                        :id_type,
                        :id_number,
                        :bio_face,
                        :bio_front,
                        :bio_back,
                        :ts,
                        :ts
                    )
                ");

                $gIns->execute([
                    ':o'           => $orgId,
                    ':full_name'   => $fullName,
                    ':age'         => $age,
                    ':gender'      => ($gender !== '' ? $gender : null),
                    ':nationality' => ($nationality !== '' ? $nationality : 'Bangladesh'),
                    ':country'     => ($country !== '' ? $country : null),
                    ':mobile'      => $mobile,
                    ':email'       => ($email !== '' ? $email : null),
                    ':address'     => ($address !== '' ? $address : null),
                    ':channel'     => ($channel !== '' ? $channel : 'Direct'),
                    ':id_type'     => ($idType !== '' ? $idType : null),
                    ':id_number'   => ($idNumber !== '' ? $idNumber : null),
                    ':bio_face'    => ($bioFace !== '' ? $bioFace : null),
                    ':bio_front'   => ($bioIdFront !== '' ? $bioIdFront : null),
                    ':bio_back'    => ($bioIdBack !== '' ? $bioIdBack : null),
                    ':ts'          => $ts,
                ]);

                $guestId = (int)$pdo->lastInsertId();
                if ($guestId <= 0) {
                    throw new \RuntimeException('Failed to create guest.');
                }
            }

            /* =====================================================
             * 2) RESERVATION
             * ===================================================*/
            $checkIn  = trim((string)($_POST['check_in'] ?? ''));
            $checkOut = trim((string)($_POST['check_out'] ?? ''));
            $adults   = (int)($_POST['adults'] ?? 1);
            $children = (int)($_POST['children'] ?? 0);
            $notes    = trim((string)($_POST['notes'] ?? ''));
            $channel  = trim((string)($_POST['channel'] ?? 'Direct'));

            if ($checkIn === '' || $checkOut === '') {
                throw new \RuntimeException('Check-in and Check-out dates are required.');
            }

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
                    :created_at,
                    :updated_at
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
                ':channel'     => ($channel !== '' ? $channel : 'Direct'),
                ':notes'       => ($notes !== '' ? $notes : null),
                ':status'      => 'booked',
                ':created_at'  => $ts,
                ':updated_at'  => $ts,
            ]);

            $reservationId = (int)$pdo->lastInsertId();
            if ($reservationId <= 0) {
                throw new \RuntimeException('Failed to create reservation.');
            }

            // simple code generator
            $code  = 'RES-' . $now->format('Ymd') . '-' . $reservationId;
            $rCode = $pdo->prepare("
                UPDATE hms_reservations
                   SET code = :code
                 WHERE id = :id AND org_id = :o
            ");
            $rCode->execute([
                ':code' => $code,
                ':id'   => $reservationId,
                ':o'    => $orgId,
            ]);

            /* =====================================================
             * 3) QUEUE BIOMETRIC JOBS (NON-FATAL)
             * ===================================================*/
            if ($bioIdFront !== '' || $bioIdBack !== '' || $bioFace !== '') {
                try {
                    $svc = new BiometricService($pdo);
                    $svc->queueForReservation(
                        $orgId,
                        $guestId,
                        $reservationId,
                        [
                            'id_front' => $bioIdFront,
                            'id_back'  => $bioIdBack,
                            'face'     => $bioFace,
                        ]
                    );
                } catch (Throwable $bioErr) {
                    // biometric fail korleo reservation save thambena
                }
            }

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();

            http_response_code(400);
            echo '<div style="padding:16px;font-family:system-ui">'
               . '<h2 style="margin:0 0 8px;color:#b91c1c">Failed to save reservation</h2>'
               . '<div style="color:#4b5563;font-size:14px;">'
               . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
               . '</div></div>';
            return;
        }

        $this->redirect('reservations/'.$reservationId, $c);
    }
}