<?php
declare(strict_types=1);

namespace Modules\hotelflow\Controllers;

use PDO;
use Throwable;

final class LookupController extends BaseController
{
    /* -------------------------------------------------------
     * Small helpers
     * ----------------------------------------------------- */
    private function orgId(array $c): int
    {
        $orgId = (int)($c['org_id'] ?? 0);

        if ($orgId <= 0) {
            if (\PHP_SESSION_ACTIVE !== \session_status()) {
                @\session_start();
            }
            $org   = $_SESSION['tenant_org'] ?? null;
            $orgId = (int)($org['id'] ?? 0);
        }

        return $orgId;
    }

    private function q(): string
    {
        $q = trim((string)($_GET['q'] ?? ''));
        return mb_substr($q, 0, 80);
    }

    private function limit(): int
    {
        $raw = (int)($_GET['limit'] ?? 50);
        if ($raw <= 0)  return 20;
        if ($raw > 200) return 200;
        return $raw;
    }

    private function json($payload, int $status = 200): void
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function tableExists(PDO $pdo, string $table): bool
    {
        try {
            $st = $pdo->prepare("
                SELECT 1
                  FROM INFORMATION_SCHEMA.TABLES
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME   = :t
                 LIMIT 1
            ");
            $st->execute([':t' => $table]);
            return (bool)$st->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }

    /* -------------------------------------------------------
     * Entry point: /api/lookup/{entity}
     * ----------------------------------------------------- */
    public function handle(array $ctx, string $entity): void
    {
        $entity = strtolower(trim($entity));

        switch ($entity) {
            case 'guests':        $this->guests($ctx);        return;
            case 'rooms':         $this->rooms($ctx);         return;
            case 'reservations':  $this->reservations($ctx);  return;
            case 'staff':         $this->staff($ctx);         return;
            case 'folios':        $this->folios($ctx);        return;
            default:
                $this->json(['items' => []]);
        }
    }

    /* =======================================================
     * 1) GUEST LOOKUP
     *    /api/lookup/guests?q=
     *    Used in: reservation create, walk-in, folios, payments
     * ===================================================== */
    private function guests(array $ctx): void
    {
        $c     = $this->ctx($ctx);
        $pdo   = $this->pdo();
        $orgId = $this->orgId($c);
        $q     = $this->q();
        $limit = $this->limit();

        if ($orgId <= 0 || !$this->tableExists($pdo, 'hms_guests')) {
            $this->json(['items' => []]);
            return;
        }

        try {
            if ($q === '') {
                $st = $pdo->prepare("
                    SELECT id, full_name, mobile, email, country
                      FROM hms_guests
                     WHERE org_id = :o
                     ORDER BY id DESC
                     LIMIT :lim
                ");
                $st->bindValue(':o',   $orgId, PDO::PARAM_INT);
                $st->bindValue(':lim', $limit, PDO::PARAM_INT);
            } else {
                $like = '%'.$q.'%';
                $st   = $pdo->prepare("
                    SELECT id, full_name, mobile, email, country
                      FROM hms_guests
                     WHERE org_id = :o
                       AND (
                            full_name LIKE :q
                         OR mobile    LIKE :q
                         OR email     LIKE :q
                       )
                     ORDER BY full_name ASC
                     LIMIT :lim
                ");
                $st->bindValue(':o',   $orgId, PDO::PARAM_INT);
                $st->bindValue(':q',   $like,  PDO::PARAM_STR);
                $st->bindValue(':lim', $limit, PDO::PARAM_INT);
            }

            $st->execute();
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $items = [];
            foreach ($rows as $r) {
                $name   = trim((string)($r['full_name'] ?? ''));
                $mobile = trim((string)($r['mobile'] ?? ''));
                $email  = trim((string)($r['email'] ?? ''));
                $country= trim((string)($r['country'] ?? ''));

                $parts = [];
                if ($name   !== '') $parts[] = $name;
                if ($mobile !== '') $parts[] = $mobile;
                if ($email  !== '') $parts[] = $email;
                if ($country!== '') $parts[] = $country;

                $items[] = [
                    'id'       => (int)$r['id'],
                    'label'    => $name !== '' ? $name : ($mobile ?: 'Guest #'.$r['id']),
                    'code'     => $mobile,
                    'sublabel' => implode(' • ', array_slice($parts, 1)),
                ];
            }

            $this->json(['items' => $items]);
        } catch (Throwable $e) {
            $this->json(['items' => []]);
        }
    }

    /* =======================================================
     * 2) ROOM LOOKUP
     *    /api/lookup/rooms?q=
     *    Used in: check-in (assign room), frontdesk, housekeeping
     * ===================================================== */
    private function rooms(array $ctx): void
    {
        $c     = $this->ctx($ctx);
        $pdo   = $this->pdo();
        $orgId = $this->orgId($c);
        $q     = $this->q();
        $limit = $this->limit();

        if ($orgId <= 0 || !$this->tableExists($pdo, 'hms_rooms')) {
            $this->json(['items' => []]);
            return;
        }

        try {
            $sql = "
                SELECT
                    r.id,
                    r.room_no,
                    r.status,
                    rt.name AS room_type
                  FROM hms_rooms r
             LEFT JOIN hms_room_types rt
                    ON rt.id     = r.room_type_id
                   AND rt.org_id = r.org_id
                 WHERE r.org_id = :o
            ";

            $params = [':o' => $orgId];

            if ($q !== '') {
                $sql .= " AND (r.room_no LIKE :q OR rt.name LIKE :q) ";
                $params[':q'] = '%'.$q.'%';
            }

            $sql .= " ORDER BY r.room_no ASC LIMIT :lim";
            $st = $pdo->prepare($sql);
            foreach ($params as $k => $v) {
                $st->bindValue($k, $v, $k === ':o' ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $st->bindValue(':lim', $limit, PDO::PARAM_INT);

            $st->execute();
            $rows  = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $items = [];

            foreach ($rows as $r) {
                $roomNo   = (string)($r['room_no'] ?? '');
                $roomType = (string)($r['room_type'] ?? '');
                $status   = (string)($r['status'] ?? '');

                $title = $roomNo !== '' ? $roomNo : ('Room #'.$r['id']);
                $sub   = trim(($roomType ?: '') . ($status ? " • {$status}" : ''));

                $items[] = [
                    'id'       => (int)$r['id'],
                    'label'    => $title,
                    'code'     => $roomNo,
                    'sublabel' => $sub,
                ];
            }

            $this->json(['items' => $items]);
        } catch (Throwable $e) {
            $this->json(['items' => []]);
        }
    }

    /* =======================================================
     * 3) RESERVATION LOOKUP
     *    /api/lookup/reservations?q=
     *    Used in: check-in desk, payments, folios, reports
     * ===================================================== */
    private function reservations(array $ctx): void
    {
        $c     = $this->ctx($ctx);
        $pdo   = $this->pdo();
        $orgId = $this->orgId($c);
        $q     = $this->q();
        $limit = $this->limit();

        if ($orgId <= 0 || !$this->tableExists($pdo, 'hms_reservations')) {
            $this->json(['items' => []]);
            return;
        }

        try {
            $sql = "
                SELECT
                    r.id,
                    r.code,
                    r.status,
                    r.check_in,
                    r.check_out,
                    r.channel,
                    COALESCE(g.full_name, '') AS guest_name
              FROM hms_reservations r
         LEFT JOIN hms_guests g
                ON g.id     = r.guest_id
               AND g.org_id = r.org_id
             WHERE r.org_id = :o
            ";

            $params = [':o' => $orgId];

            if ($q !== '') {
                $id = ctype_digit($q) ? (int)$q : 0;
                $sql .= "
                  AND (
                        r.code LIKE :q
                     OR g.full_name LIKE :q
                     OR r.channel LIKE :q
                     OR r.id = :id
                  )
                ";
                $params[':q']  = '%'.$q.'%';
                $params[':id'] = $id;
            }

            $sql .= " ORDER BY r.id DESC LIMIT :lim";
            $st = $pdo->prepare($sql);

            foreach ($params as $k => $v) {
                if ($k === ':o' || $k === ':id') {
                    $st->bindValue($k, $v, PDO::PARAM_INT);
                } else {
                    $st->bindValue($k, $v, PDO::PARAM_STR);
                }
            }
            $st->bindValue(':lim', $limit, PDO::PARAM_INT);

            $st->execute();
            $rows  = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $items = [];

            foreach ($rows as $r) {
                $code   = (string)($r['code'] ?? '');
                $guest  = (string)($r['guest_name'] ?? '');
                $status = (string)($r['status'] ?? '');
                $ci     = (string)($r['check_in'] ?? '');
                $co     = (string)($r['check_out'] ?? '');
                $chan   = (string)($r['channel'] ?? '');

                $titleParts = [];
                if ($code !== '') $titleParts[] = $code;
                if ($guest !== '') $titleParts[] = $guest;

                $title = $titleParts ? implode(' • ', $titleParts) : 'Reservation #'.$r['id'];

                $subParts = [];
                if ($ci || $co) $subParts[] = trim($ci.' → '.$co);
                if ($status)    $subParts[] = strtoupper($status);
                if ($chan)      $subParts[] = $chan;

                $items[] = [
                    'id'       => (int)$r['id'],
                    'label'    => $title,
                    'code'     => $code !== '' ? $code : (string)$r['id'],
                    'sublabel' => implode(' • ', $subParts),
                ];
            }

            $this->json(['items' => $items]);
        } catch (Throwable $e) {
            $this->json(['items' => []]);
        }
    }

    /* =======================================================
     * 4) STAFF LOOKUP
     *    /api/lookup/staff?q=
     *    Used in: housekeeping, frontdesk, night-audit, approvals
     * ===================================================== */
    private function staff(array $ctx): void
    {
        $c     = $this->ctx($ctx);
        $pdo   = $this->pdo();
        $orgId = $this->orgId($c);
        $q     = $this->q();
        $limit = $this->limit();

        if ($orgId <= 0) {
            $this->json(['items' => []]);
            return;
        }

        // Prefer hms_staff if it exists, fallback later if you like to cp_users
        $table = $this->tableExists($pdo, 'hms_staff') ? 'hms_staff' : null;
        if ($table === null) {
            $this->json(['items' => []]);
            return;
        }

        try {
            $sql = "
                SELECT id, full_name, role, mobile, email
                  FROM {$table}
                 WHERE org_id = :o
            ";
            $params = [ ':o' => $orgId ];

            if ($q !== '') {
                $like = '%'.$q.'%';
                $sql .= " AND (full_name LIKE :q OR mobile LIKE :q OR email LIKE :q OR role LIKE :q) ";
                $params[':q'] = $like;
            }

            $sql .= " ORDER BY full_name ASC LIMIT :lim";
            $st = $pdo->prepare($sql);

            foreach ($params as $k => $v) {
                if ($k === ':o') {
                    $st->bindValue($k, $v, PDO::PARAM_INT);
                } else {
                    $st->bindValue($k, $v, PDO::PARAM_STR);
                }
            }
            $st->bindValue(':lim', $limit, PDO::PARAM_INT);

            $st->execute();
            $rows  = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $items = [];

            foreach ($rows as $r) {
                $name  = (string)($r['full_name'] ?? '');
                $role  = (string)($r['role'] ?? '');
                $phone = (string)($r['mobile'] ?? '');
                $email = (string)($r['email'] ?? '');

                $title = $name !== '' ? $name : 'Staff #'.$r['id'];

                $subParts = [];
                if ($role)  $subParts[] = $role;
                if ($phone) $subParts[] = $phone;
                if ($email) $subParts[] = $email;

                $items[] = [
                    'id'       => (int)$r['id'],
                    'label'    => $title,
                    'code'     => $phone,
                    'sublabel' => implode(' • ', $subParts),
                ];
            }

            $this->json(['items' => $items]);
        } catch (Throwable $e) {
            $this->json(['items' => []]);
        }
    }

    /* =======================================================
     * 5) FOLIO LOOKUP
     *    /api/lookup/folios?q=
     *    Used in: payments, accounting, reports
     * ===================================================== */
    private function folios(array $ctx): void
    {
        $c     = $this->ctx($ctx);
        $pdo   = $this->pdo();
        $orgId = $this->orgId($c);
        $q     = $this->q();
        $limit = $this->limit();

        if ($orgId <= 0 || !$this->tableExists($pdo, 'hms_folios')) {
            $this->json(['items' => []]);
            return;
        }

        try {
            $sql = "
                SELECT
                    f.id,
                    f.number,
                    f.status,
                    f.balance,
                    COALESCE(g.full_name, '') AS guest_name
              FROM hms_folios f
         LEFT JOIN hms_guests g
                ON g.id     = f.guest_id
               AND g.org_id = f.org_id
             WHERE f.org_id = :o
            ";

            $params = [':o' => $orgId];

            if ($q !== '') {
                $like = '%'.$q.'%';
                $id   = ctype_digit($q) ? (int)$q : 0;
                $sql .= "
                  AND (
                        f.number LIKE :q
                     OR g.full_name LIKE :q
                     OR f.id = :id
                  )
                ";
                $params[':q']  = $like;
                $params[':id'] = $id;
            }

            $sql .= " ORDER BY f.id DESC LIMIT :lim";
            $st = $pdo->prepare($sql);

            foreach ($params as $k => $v) {
                if ($k === ':o' || $k === ':id') {
                    $st->bindValue($k, $v, PDO::PARAM_INT);
                } else {
                    $st->bindValue($k, $v, PDO::PARAM_STR);
                }
            }
            $st->bindValue(':lim', $limit, PDO::PARAM_INT);

            $st->execute();
            $rows  = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $items = [];

            foreach ($rows as $r) {
                $num   = (string)($r['number'] ?? '');
                $guest = (string)($r['guest_name'] ?? '');
                $status= (string)($r['status'] ?? '');
                $bal   = (string)($r['balance'] ?? '');

                $titleParts = [];
                if ($num)   $titleParts[] = $num;
                if ($guest) $titleParts[] = $guest;

                $title = $titleParts ? implode(' • ', $titleParts) : 'Folio #'.$r['id'];

                $subParts = [];
                if ($status) $subParts[] = strtoupper($status);
                if ($bal !== '') $subParts[] = 'Balance: '.$bal.' BDT';

                $items[] = [
                    'id'       => (int)$r['id'],
                    'label'    => $title,
                    'code'     => $num !== '' ? $num : (string)$r['id'],
                    'sublabel' => implode(' • ', $subParts),
                ];
            }

            $this->json(['items' => $items]);
        } catch (Throwable $e) {
            $this->json(['items' => []]);
        }
    }
}