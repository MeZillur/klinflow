<?php
declare(strict_types=1);

namespace Modules\hotelflow\Controllers\Api;

use Modules\hotelflow\Controllers\BaseController;
use DateTimeImmutable;
use PDO;

final class OpsController extends BaseController
{
    /* ============================================================
     * GET /api/kpis
     * ============================================================ */
    public function kpis(array $ctx): void
    {
        $c     = $this->ctx($ctx);
        $orgId = (int)$c['org_id'];
        $pdo   = $this->pdo();
        $today = (new DateTimeImmutable('today'))->format('Y-m-d');

        $scalar = function (string $sql, array $params = []) use ($pdo) {
            try {
                $st = $pdo->prepare($sql);
                foreach ($params as $k=>$v) $st->bindValue(is_int($k)?$k+1:":$k", $v);
                $st->execute();
                $val = $st->fetchColumn();
                return is_numeric($val) ? (float)$val : (int)$val;
            } catch (\Throwable $e) { return 0; }
        };

        $safeRow = function (string $sql, array $params = []) use ($pdo): array {
            try {
                $st = $pdo->prepare($sql);
                foreach ($params as $k=>$v) $st->bindValue(is_int($k)?$k+1:":$k", $v);
                $st->execute();
                $row = $st->fetch(PDO::FETCH_ASSOC);
                return $row ?: [];
            } catch (\Throwable $e) { return []; }
        };

        $roomsTotal = $scalar("SELECT COUNT(*) FROM hms_rooms WHERE org_id=:o", ['o'=>$orgId]);
        $roomsOOO   = $scalar("SELECT COUNT(*) FROM hms_rooms WHERE org_id=:o AND status IN ('ooo','oos','out_of_order','out_of_service')", ['o'=>$orgId]);

        $res30d = $scalar("
            SELECT COUNT(*) FROM hms_reservations
            WHERE org_id=:o AND status IN ('confirmed','guaranteed','in_house','checked_in')
              AND check_in <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
              AND check_out >= CURDATE()
        ", ['o'=>$orgId]);

        $arrivals   = $scalar("SELECT COUNT(*) FROM hms_reservations WHERE org_id=:o AND DATE(check_in)=CURDATE() AND status IN ('confirmed','guaranteed')", ['o'=>$orgId]);
        $inhouse    = $scalar("SELECT COUNT(*) FROM hms_stays WHERE org_id=:o AND status='in_house'", ['o'=>$orgId]);
        $departures = $scalar("SELECT COUNT(*) FROM hms_reservations WHERE org_id=:o AND DATE(check_out)=CURDATE() AND status IN ('in_house','checked_in','confirmed','guaranteed')", ['o'=>$orgId]);

        $roomRevToday = $scalar("SELECT ROUND(SUM(amount),2) FROM hms_folio_lines WHERE org_id=:o AND DATE(service_date)=CURDATE() AND line_type='charge'", ['o'=>$orgId]);
        $paymentsToday= $scalar("SELECT ROUND(SUM(amount),2) FROM hms_payments WHERE org_id=:o AND DATE(created_at)=CURDATE()", ['o'=>$orgId]);

        $occRow = $safeRow("
            SELECT SUM(CASE WHEN i.total_rooms>0 THEN i.sold_rooms END) AS sold,
                   SUM(i.total_rooms) AS total
            FROM hms_inventory i
            WHERE i.org_id=:o AND i.date=:d
        ", ['o'=>$orgId, 'd'=>$today]);

        $occPct = 0.0;
        if (($occRow['total'] ?? 0) > 0) {
            $occPct = round(100.0 * (float)($occRow['sold'] ?? 0) / (float)$occRow['total'], 1);
        } elseif ($roomsTotal > 0) {
            $sold = $scalar("SELECT COUNT(*) FROM hms_stays WHERE org_id=:o AND status='in_house'", ['o'=>$orgId]);
            $occPct = round(100.0 * $sold / (float)$roomsTotal, 1);
        }

        $this->json([
            'date'              => $today,
            'rooms_total'       => (int)$roomsTotal,
            'rooms_ooo'         => (int)$roomsOOO,
            'res_next_30d'      => (int)$res30d,
            'arrivals_today'    => (int)$arrivals,
            'inhouse_today'     => (int)$inhouse,
            'departures_today'  => (int)$departures,
            'occ_pct'           => (float)$occPct,
            'room_revenue_today'=> (float)$roomRevToday,
            'payments_today'    => (float)$paymentsToday,
        ]);
    }

    /* ============================================================
     * The routes file also referenced these—implemented for you.
     * ============================================================ */

    // GET /api/arrivals
    public function arrivals(array $ctx): void
    {
        $c = $this->ctx($ctx); $orgId = (int)$c['org_id']; $pdo = $this->pdo();
        $rows = $this->safeRows($pdo, "
            SELECT r.id, r.code, r.guest_name, r.check_in, r.check_out, r.room_type_name
            FROM hms_reservations r
            WHERE r.org_id=:o AND DATE(r.check_in)=CURDATE() AND r.status IN ('confirmed','guaranteed')
            ORDER BY r.check_in ASC LIMIT 50
        ", ['o'=>$orgId]);
        $this->json(['data'=>$rows]);
    }

    // GET /api/inhouse
    public function inhouse(array $ctx): void
    {
        $c = $this->ctx($ctx); $orgId = (int)$c['org_id']; $pdo = $this->pdo();
        $rows = $this->safeRows($pdo, "
            SELECT s.id, s.reservation_id, s.room_no, s.check_in_at, s.planned_check_out, g.name AS guest_name
            FROM hms_stays s LEFT JOIN hms_guests g ON g.id = s.guest_id
            WHERE s.org_id=:o AND s.status='in_house'
            ORDER BY s.check_in_at DESC LIMIT 50
        ", ['o'=>$orgId]);
        $this->json(['data'=>$rows]);
    }

    // GET /api/departures
    public function departures(array $ctx): void
    {
        $c = $this->ctx($ctx); $orgId = (int)$c['org_id']; $pdo = $this->pdo();
        $rows = $this->safeRows($pdo, "
            SELECT r.id, r.code, r.guest_name, r.check_in, r.check_out, r.room_no
            FROM hms_reservations r
            WHERE r.org_id=:o AND DATE(r.check_out)=CURDATE() AND r.status IN ('in_house','checked_in','confirmed','guaranteed')
            ORDER BY r.check_out ASC LIMIT 50
        ", ['o'=>$orgId]);
        $this->json(['data'=>$rows]);
    }

    // GET /api/availability?date=YYYY-MM-DD
    public function availability(array $ctx): void
    {
        $c = $this->ctx($ctx); $orgId = (int)$c['org_id']; $pdo = $this->pdo();
        $date = (string)($_GET['date'] ?? date('Y-m-d'));
        $rows = $this->safeRows($pdo, "
            SELECT i.date, i.room_type_id, i.total_rooms, i.sold_rooms, (i.total_rooms - i.sold_rooms) AS free_rooms
            FROM hms_inventory i
            WHERE i.org_id=:o AND i.date=:d
            ORDER BY i.room_type_id
        ", ['o'=>$orgId,'d'=>$date]);
        $this->json(['date'=>$date,'data'=>$rows]);
    }

    /* ---------- local helpers ---------- */
    private function safeRows(PDO $pdo, string $sql, array $params = []): array
    {
        try {
            $st = $pdo->prepare($sql);
            foreach ($params as $k=>$v) $st->bindValue(is_int($k)?$k+1:":$k", $v);
            $st->execute();
            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) { return []; }
    }
    
    // GET /api/health
public function health(array $ctx): void
{
    $c = $this->ctx($ctx);
    $ok = true; $checks = [];
    try { $this->pdo()->query('SELECT 1'); $checks['db'] = 'ok'; } catch (\Throwable $e) { $ok=false; $checks['db']='fail'; }
    $this->json(['ok'=>$ok,'checks'=>$checks,'time'=>date('c')], $ok?200:500);
}

// GET /api/arrivals?date=YYYY-MM-DD
public function arrivals(array $ctx): void
{
    $c = $this->ctx($ctx); $orgId = (int)$c['org_id']; $pdo = $this->pdo();
    $date = (string)($_GET['date'] ?? date('Y-m-d'));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');

    $rows = $this->safeRows($pdo, "
        SELECT r.id, r.code, r.guest_name, r.check_in, r.check_out,
               COALESCE(r.room_no,'') AS room_no,
               COALESCE(r.room_type_name,'') AS room_type_name
        FROM hms_reservations r
        WHERE r.org_id=:o AND DATE(r.check_in)=:d AND r.status IN ('confirmed','guaranteed')
        ORDER BY r.check_in ASC, r.id ASC LIMIT 200
    ", ['o'=>$orgId,'d'=>$date]);

    $this->json(['data'=>$rows,'date'=>$date]);
}

    public function roomStatus(array $ctx): void
{
    $c     = $this->ctx($ctx);
    $orgId = (int)$c['org_id'];
    $pdo   = $this->pdo();

    $status = trim((string)($_GET['status'] ?? ''));
    $rtId   = (int)($_GET['room_type_id'] ?? 0);
    $floor  = strlen((string)($_GET['floor'] ?? '')) ? (int)$_GET['floor'] : null;
    $search = trim((string)($_GET['q'] ?? ''));

    $rows = [];
    try {
        $sql = "
          SELECT
            r.id,
            COALESCE(r.room_no, r.number, CONCAT('R', r.id))     AS room_no,
            r.floor_no,
            COALESCE(rt.name, r.room_type, r.room_type_name, '') AS room_type,
            COALESCE(r.status,'vacant')                          AS room_status,
            COALESCE(hk.status,'unknown')                        AS hk_status,
            st.id                                                AS stay_id,
            st.reservation_id,
            COALESCE(g.name, st.guest_name, r.guest_name, '')    AS guest_name,
            st.planned_check_out
          FROM hms_rooms r
          LEFT JOIN hms_room_types rt ON rt.id = r.room_type_id
          LEFT JOIN hms_housekeeping_status hk ON hk.room_id = r.id
          LEFT JOIN hms_stays st ON st.room_id = r.id AND st.status IN ('in_house','checked_in')
          LEFT JOIN hms_guests g ON g.id = st.guest_id
          WHERE r.org_id = :o
        ";
        $bind = [':o'=>$orgId];

        if ($status !== '') {
            switch (strtolower($status)) {
                case 'occupied': $sql .= " AND st.id IS NOT NULL "; break;
                case 'vacant':   $sql .= " AND st.id IS NULL AND COALESCE(r.status,'vacant') NOT IN ('out_of_order','out_of_service') "; break;
                case 'ooo':      $sql .= " AND COALESCE(r.status,'') = 'out_of_order' "; break;
                case 'oos':      $sql .= " AND COALESCE(r.status,'') = 'out_of_service' "; break;
                default:         $sql .= " AND COALESCE(r.status,'') = :st_any "; $bind[':st_any']=$status; break;
            }
        }
        if ($rtId > 0) { $sql .= " AND r.room_type_id = :rt "; $bind[':rt']=$rtId; }
        if ($floor !== null) { $sql .= " AND r.floor_no = :fl "; $bind[':fl']=$floor; }
        if ($search !== '') { $sql .= " AND (r.room_no LIKE :q OR r.number LIKE :q) "; $bind[':q'] = '%'.$search.'%'; }

        $sql .= " ORDER BY r.floor_no ASC, room_no ASC LIMIT 500 ";

        $st = $pdo->prepare($sql);
        $st->execute($bind);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (\Throwable $e) {
        $rows = [];
    }

    $this->json(['data'=>$rows]);
}

<?php
declare(strict_types=1);

namespace Modules\hotelflow\Controllers\Api;

use PDO;
use Throwable;

final class OpsController extends BaseApiController
{
    /* …your existing methods (kpis, arrivals, inhouse, departures, roomStatus)… */

    // GET /api/room?id=123
    public function roomDetails(array $ctx): void
    {
        $c     = $this->ctx($ctx);
        $orgId = (int)$c['org_id'];
        $pdo   = $this->pdo();

        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) { $this->json(['error'=>'Invalid room id'], 400); return; }

        $exists = fn(string $t) => $this->tableExists($pdo, $t);

        $room = null; $stay = null; $folio = null; $recent = [];
        try {
            // Room core
            $room = $this->fetchOne($pdo, "
                SELECT r.id, COALESCE(r.room_no, r.number, CONCAT('R', r.id)) AS room_no,
                       r.floor_no, r.status AS room_status,
                       COALESCE(rt.name, r.room_type, r.room_type_name) AS room_type
                FROM hms_rooms r
                LEFT JOIN hms_room_types rt ON rt.id=r.room_type_id
                WHERE r.org_id=:o AND r.id=:id
                LIMIT 1
            ", ['o'=>$orgId, 'id'=>$id]);

            // Active stay (if any)
            if ($exists('hms_stays')) {
                $stay = $this->fetchOne($pdo, "
                    SELECT s.id, s.reservation_id, s.guest_id,
                           s.check_in_at, s.planned_check_out, s.actual_check_out,
                           s.status, COALESCE(g.name, s.guest_name) AS guest_name
                    FROM hms_stays s
                    LEFT JOIN hms_guests g ON g.id=s.guest_id
                    WHERE s.org_id=:o AND s.room_id=:id AND s.status IN ('in_house','checked_in')
                    ORDER BY s.id DESC LIMIT 1
                ", ['o'=>$orgId,'id'=>$id]);
            }

            // Folio (if schema exists)
            if ($stay && $exists('hms_folios')) {
                $folio = $this->fetchOne($pdo, "
                    SELECT f.id, f.status, f.balance_due, f.currency, f.number
                    FROM hms_folios f
                    WHERE f.org_id=:o AND (f.stay_id=:sid OR f.reservation_id=:rid)
                    ORDER BY f.id DESC LIMIT 1
                ", ['o'=>$orgId,'sid'=>$stay['id']??0,'rid'=>$stay['reservation_id']??0]);
            }

            // Recent folio lines (optional)
            if ($folio && $exists('hms_folio_lines')) {
                $recent = $this->fetchAll($pdo, "
                    SELECT id, service_date, code, description, amount, tax_amount
                    FROM hms_folio_lines
                    WHERE org_id=:o AND folio_id=:fid
                    ORDER BY id DESC LIMIT 10
                ", ['o'=>$orgId,'fid'=>$folio['id']]);
            }
        } catch (Throwable $e) {}

        $this->json([
            'room'   => $room,
            'stay'   => $stay,
            'folio'  => $folio,
            'recent' => $recent,
        ]);
    }

    // POST /api/hk/set-status  (room_id, status)
    public function hkSetStatus(array $ctx): void
    {
        $c     = $this->ctx($ctx);
        $orgId = (int)$c['org_id'];
        $pdo   = $this->pdo();

        $roomId = (int)($_POST['room_id'] ?? 0);
        $status = trim((string)($_POST['status'] ?? ''));
        if ($roomId<=0 || $status==='') { $this->json(['error'=>'Missing room_id or status'], 400); return; }

        try {
            if (!$this->tableExists($pdo,'hms_housekeeping_status')) {
                $this->json(['ok'=>true,'message'=>'(Simulated) HK updated — table missing']); return;
            }
            $st = $pdo->prepare("
                INSERT INTO hms_housekeeping_status (org_id, room_id, status, updated_at)
                VALUES (:o,:r,:s, CURRENT_TIMESTAMP)
                ON DUPLICATE KEY UPDATE status=VALUES(status), updated_at=CURRENT_TIMESTAMP
            ");
            $st->execute([':o'=>$orgId, ':r'=>$roomId, ':s'=>$status]);
            $this->json(['ok'=>true,'message'=>'Housekeeping status updated']);
        } catch (Throwable $e) {
            $this->json(['ok'=>false,'error'=>$e->getMessage()], 500);
        }
    }

    // POST /api/keycard/encode (room_id, from, to)
    public function keycardEncode(array $ctx): void
    {
        $c     = $this->ctx($ctx);
        $orgId = (int)$c['org_id'];
        $pdo   = $this->pdo();

        $roomId = (int)($_POST['room_id'] ?? 0);
        $from   = (string)($_POST['from'] ?? '');
        $to     = (string)($_POST['to'] ?? '');
        if ($roomId<=0 || !preg_match('/^\d{4}-\d{2}-\d{2}/',$from) || !preg_match('/^\d{4}-\d{2}-\d{2}/',$to)) {
            $this->json(['error'=>'Invalid input'], 400); return;
        }

        try {
            if (!$this->tableExists($pdo,'hms_keycards')) {
                $this->json(['ok'=>true,'message'=>'(Simulated) keycard encoded']); return;
            }
            $st = $pdo->prepare("
                INSERT INTO hms_keycards (org_id, room_id, valid_from, valid_to, payload, created_at)
                VALUES (:o,:r,:vf,:vt, :payload, CURRENT_TIMESTAMP)
            ");
            $payload = json_encode(['algo'=>'sim','room_id'=>$roomId,'from'=>$from,'to'=>$to], JSON_UNESCAPED_SLASHES);
            $st->execute([':o'=>$orgId, ':r'=>$roomId, ':vf'=>$from, ':vt'=>$to, ':payload'=>$payload]);
            $this->json(['ok'=>true,'message'=>'Keycard encoded']);
        } catch (Throwable $e) {
            $this->json(['ok'=>false,'error'=>$e->getMessage()], 500);
        }
    }

    // POST /api/folio/post-charge (stay_id OR folio_id, code, amount, note)
    public function folioPostCharge(array $ctx): void
    {
        $c     = $this->ctx($ctx);
        $orgId = (int)$c['org_id'];
        $pdo   = $this->pdo();

        $stayId  = (int)($_POST['stay_id'] ?? 0);
        $folioId = (int)($_POST['folio_id'] ?? 0);
        $code    = trim((string)($_POST['code'] ?? 'ROOM'));
        $amount  = (float)($_POST['amount'] ?? 0);
        $note    = trim((string)($_POST['note'] ?? ''));

        if ($amount <= 0) { $this->json(['error'=>'Amount must be > 0'], 400); return; }

        try {
            if (!$this->tableExists($pdo,'hms_folio_lines')) {
                $this->json(['ok'=>true,'message'=>'(Simulated) charge posted']); return;
            }

            // Resolve folio if only stay_id provided
            if ($folioId <= 0 && $stayId > 0 && $this->tableExists($pdo,'hms_folios')) {
                $row = $this->fetchOne($pdo, "
                    SELECT id FROM hms_folios WHERE org_id=:o AND stay_id=:s ORDER BY id DESC LIMIT 1
                ", ['o'=>$orgId,'s'=>$stayId]);
                $folioId = (int)($row['id'] ?? 0);
            }

            if ($folioId <= 0) { $this->json(['error'=>'No folio found to post into'], 400); return; }

            $st = $pdo->prepare("
                INSERT INTO hms_folio_lines (org_id, folio_id, code, description, amount, tax_amount, service_date, created_at)
                VALUES (:o,:f,:c,:d,:a,0, CURRENT_DATE, CURRENT_TIMESTAMP)
            ");
            $st->execute([':o'=>$orgId, ':f'=>$folioId, ':c'=>$code, ':d'=>$note ?: $code, ':a'=>$amount]);

            $this->json(['ok'=>true,'message'=>'Charge posted','folio_id'=>$folioId]);
        } catch (Throwable $e) {
            $this->json(['ok'=>false,'error'=>$e->getMessage()], 500);
        }
    }

    /* ---------- tiny helpers ---------- */
    private function tableExists(PDO $pdo, string $t): bool {
        try {
            $q=$pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME=:t");
            $q->execute([':t'=>$t]); return (bool)$q->fetchColumn();
        } catch (Throwable $e) { return false; }
    }
    private function fetchOne(PDO $pdo, string $sql, array $b): ?array {
        $s=$pdo->prepare($sql); $s->execute($b); $r=$s->fetch(PDO::FETCH_ASSOC); return $r?:null;
    }
    private function fetchAll(PDO $pdo, string $sql, array $b): array {
        $s=$pdo->prepare($sql); $s->execute($b); return $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}


// GET /api/typeahead/guests?q=ali&limit=10
public function typeaheadGuests(array $ctx): void
{
    $c = $this->ctx($ctx); $orgId = (int)$c['org_id'];
    $q = trim((string)($_GET['q'] ?? ''));
    $limit = max(1, min(25, (int)($_GET['limit'] ?? 10)));

    if ($q === '') { $this->json(['data'=>[]]); return; }

    $sql = "SELECT id, name, email, mobile
            FROM hms_guests
            WHERE org_id=:o AND (name LIKE :q OR email LIKE :q OR mobile LIKE :q)
            ORDER BY name ASC LIMIT :lim";
    try {
        $st = $this->pdo()->prepare($sql);
        $like = '%'.$q.'%';
        $st->bindValue(':o', $orgId, \PDO::PARAM_INT);
        $st->bindValue(':q', $like, \PDO::PARAM_STR);
        $st->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        $this->json(['data'=>$rows]);
    } catch (\Throwable $e) {
        $this->json(['data'=>[]]);
    }
}
    
}