<?php
declare(strict_types=1);

namespace Modules\hotelflow\Controllers;

use PDO;
use Throwable;

final class RoomsController extends BaseController
{
    /* ───────────────────────────── core helpers (multi-tenant safe) ───────────────────────────── */

    

    

    

    /* ───────────────────────────── ROOMS: index (list + filters) ───────────────────────────── */

    /** GET /rooms */
    public function index(array $ctx): void
    {
        $c     = $this->ctx($ctx);
        $pdo   = $this->pdo();
        $orgId = (int)$c['org_id'];

        // filters
        $q           = trim((string)($_GET['q'] ?? ''));
        $roomTypeId  = (int)($_GET['room_type_id'] ?? 0);
        $floor       = (string)($_GET['floor'] ?? '');
        $hk          = trim((string)($_GET['hk'] ?? ''));          // clean/dirty/inspected/...
        $status      = trim((string)($_GET['status'] ?? ''));      // ok/out_of_order/out_of_service

        // Column existence
        $hasRtTable  = $this->tableExists($pdo, 'hms_room_types');
        $hasRoomNo   = $this->colExists($pdo,'hms_rooms','room_no');
        $hasName     = $this->colExists($pdo,'hms_rooms','name');
        $hasRtId     = $this->colExists($pdo,'hms_rooms','room_type_id');
        $hasFloor    = $this->colExists($pdo,'hms_rooms','floor');
        $hasHk       = $this->colExists($pdo,'hms_rooms','hk_status');
        $hasRstatus  = $this->colExists($pdo,'hms_rooms','room_status');
        $hasNotes    = $this->colExists($pdo,'hms_rooms','notes');

        // Build SELECT
        $select = ["r.id", "r.org_id"];
        if ($hasRoomNo)  $select[] = "r.room_no";
        if ($hasName)    $select[] = "r.name";
        if ($hasRtId)    $select[] = "r.room_type_id";
        if ($hasRtTable) $select[] = "COALESCE(rt.name,'') AS room_type_name";
        if ($hasFloor)   $select[] = "r.floor";
        if ($hasHk)      $select[] = "r.hk_status";
        if ($hasRstatus) $select[] = "r.room_status";
        if ($hasNotes)   $select[] = "r.notes";

        $sql = "SELECT ".implode(', ', $select)." FROM hms_rooms r";
        if ($hasRtTable && $hasRtId) {
            $sql .= " LEFT JOIN hms_room_types rt ON rt.id = r.room_type_id AND rt.org_id = r.org_id";
        }

        $where = ["r.org_id = :o"];
        $bind  = [':o'=>$orgId];

        if ($q !== '') {
            $like = '%'.$q.'%';
            if ($hasRoomNo && $hasName) {
                $where[] = "(r.room_no LIKE :q OR r.name LIKE :q)";
                $bind[':q'] = $like;
            } elseif ($hasRoomNo) {
                $where[] = "r.room_no LIKE :q";
                $bind[':q'] = $like;
            } elseif ($hasName) {
                $where[] = "r.name LIKE :q";
                $bind[':q'] = $like;
            }
        }
        if ($roomTypeId>0 && $hasRtId) { $where[] = "r.room_type_id = :rt"; $bind[':rt'] = $roomTypeId; }
        if ($floor !== '' && $hasFloor){ $where[] = "r.floor = :fl";        $bind[':fl'] = $floor; }
        if ($hk !== '' && $hasHk)      { $where[] = "r.hk_status = :hk";    $bind[':hk'] = $hk; }
        if ($status !== '' && $hasRstatus) { $where[] = "r.room_status = :rs"; $bind[':rs'] = $status; }

        $sql .= " WHERE ".implode(' AND ',$where)
              ." ORDER BY ".($hasRoomNo ? 'r.room_no' : 'r.id')." ASC LIMIT 500";

        $rows = [];
        try {
            $st = $pdo->prepare($sql);
            $st->execute($bind);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            $rows = [];
        }

        // normalize for view
        foreach ($rows as &$r) {
            $r['id']             = (int)($r['id'] ?? 0);
            $r['room_no']        = (string)($r['room_no']        ?? '');
            $r['name']           = (string)($r['name']           ?? '');
            $r['room_type_name'] = (string)($r['room_type_name'] ?? '');
            $r['floor']          = (string)($r['floor']          ?? '');
            $r['hk_status']      = (string)($r['hk_status']      ?? '');
            $r['room_status']    = (string)($r['room_status']    ?? '');
            $r['notes']          = (string)($r['notes']          ?? '');
        }
        unset($r);

        // lookups
        $roomTypes = $this->fetchRoomTypes($pdo, $orgId);
        $floors    = $this->fetchFloors($pdo, $orgId, $hasFloor);

        $this->view('rooms/index', [
            'title'     => 'Rooms',
            'rows'      => $rows,
            'filters'   => [
                'q'            => $q,
                'room_type_id' => $roomTypeId,
                'floor'        => $floor,
                'hk'           => $hk,
                'status'       => $status,
            ],
            'roomTypes' => $roomTypes,
            'floors'    => $floors,
        ], $c);
    }

    /* ───────────────────────────── ROOMS: create/edit ───────────────────────────── */

    /** GET /rooms/create */
    public function create(array $ctx): void
    {
        $c     = $this->ctx($ctx);
        $pdo   = $this->pdo();
        $orgId = (int)$c['org_id'];

        $roomTypes = $this->fetchRoomTypes($pdo, $orgId);
        $floors    = $this->fetchFloors($pdo, $orgId, $this->colExists($pdo,'hms_rooms','floor'));

        $this->view('rooms/form', [
            'title'     => 'Add Room',
            'mode'      => 'create',
            'row'       => null,
            'roomTypes' => $roomTypes,
            'floors'    => $floors,
        ], $c);
    }

    /** GET /rooms/{id}/edit */
    public function edit(array $ctx, int $id): void
    {
        $c     = $this->ctx($ctx);
        $pdo   = $this->pdo();
        $orgId = (int)$c['org_id'];

        $row = $this->fetchRoom($pdo, $orgId, $id);
        if (!$row) {
            $this->notFound('Room not found');
            return;
        }

        $roomTypes = $this->fetchRoomTypes($pdo, $orgId);
        $floors    = $this->fetchFloors($pdo, $orgId, $this->colExists($pdo,'hms_rooms','floor'));

        $this->view('rooms/form', [
            'title'     => 'Edit Room',
            'mode'      => 'edit',
            'row'       => $row,
            'roomTypes' => $roomTypes,
            'floors'    => $floors,
        ], $c);
    }

    /* ───────────────────────────── ROOMS: store/update ───────────────────────────── */

    /** POST /rooms */
    public function store(array $ctx): void
    {
        $c     = $this->ctx($ctx);
        $pdo   = $this->pdo();
        $orgId = (int)$c['org_id'];

        $payload = $this->roomPayloadFromPost($pdo);
        [$fields,$params,$table] = $this->buildInsertForRooms($pdo, $orgId, $payload);

        if (!$table) {
            $this->abort500('Rooms table (hms_rooms) is missing.');
            return;
        }

        try {
            $sql = "INSERT INTO hms_rooms (".implode(',',$fields).") VALUES (".implode(',', array_keys($params)).")";
            $st  = $pdo->prepare($sql);
            $st->execute($params);
            $this->redirect($this->moduleBase($c).'/rooms', $c);
        } catch (Throwable $e) {
            $this->abort500('Save failed: '.$e->getMessage());
        }
    }

    /** POST /rooms/{id}/update */
    public function update(array $ctx, int $id): void
    {
        $c     = $this->ctx($ctx);
        $pdo   = $this->pdo();
        $orgId = (int)$c['org_id'];

        $payload = $this->roomPayloadFromPost($pdo);
        [$sets,$params] = $this->buildUpdateForRooms($pdo, $payload);

        if (!$sets) {
            $this->redirect($this->moduleBase($c).'/rooms/'.$id, $c);
            return;
        }

        try {
            $params[':o']  = $orgId;
            $params[':id'] = $id;
            $sql = "UPDATE hms_rooms SET ".implode(', ',$sets)." WHERE org_id=:o AND id=:id";
            $st  = $pdo->prepare($sql);
            $st->execute($params);
            $this->redirect($this->moduleBase($c).'/rooms/'.$id, $c);
        } catch (Throwable $e) {
            $this->abort500('Update failed: '.$e->getMessage());
        }
    }

    /* ───────────────────────────── ROOM TYPES CRUD ───────────────────────────── */

    /** GET /rooms/types */
    public function typesIndex(array $ctx): void
    {
        $c     = $this->ctx($ctx);
        $pdo   = $this->pdo();
        $orgId = (int)$c['org_id'];

        $exists = $this->tableExists($pdo, 'hms_room_types');
        $hasName = $exists && $this->colExists($pdo, 'hms_room_types', 'name');
        $hasDesc = $exists && $this->colExists($pdo, 'hms_room_types', 'description');

        $rows = [];
        if ($exists) {
            $cols = ['id'];
            if ($hasName) $cols[] = 'name';
            if ($hasDesc) $cols[] = 'description';
            $sql = "SELECT ".implode(',', $cols)." FROM hms_room_types WHERE org_id=:o ORDER BY name, id";
            try {
                $st = $pdo->prepare($sql);
                $st->execute([':o'=>$orgId]);
                $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (Throwable $e) {
                $rows = [];
            }
        }

        $this->view('rooms/types', [
            'title'  => 'Room Types',
            'rows'   => $rows,
            'schema' => [
                'exists'      => $exists,
                'has_name'    => $hasName,
                'has_desc'    => $hasDesc,
            ],
        ], $c);
    }

    /** POST /rooms/types/store */
    public function typesStore(array $ctx): void
    {
        $c     = $this->ctx($ctx);
        $pdo   = $this->pdo();
        $orgId = (int)$c['org_id'];

        if (!$this->tableExists($pdo,'hms_room_types')) {
            $this->abort500('hms_room_types is missing.');
            return;
        }

        $name = trim((string)($_POST['name'] ?? ''));
        $desc = trim((string)($_POST['description'] ?? ''));

        $cols  = ['org_id'];
        $vals  = [':o'=>$orgId];
        $place = [':o'];

        if ($this->colExists($pdo,'hms_room_types','name')) {
            $cols[]   = 'name';
            $vals[':n'] = $name ?: null;
            $place[]  = ':n';
        }
        if ($this->colExists($pdo,'hms_room_types','description')) {
            $cols[]   = 'description';
            $vals[':d'] = $desc ?: null;
            $place[]  = ':d';
        }

        $sql = "INSERT INTO hms_room_types (".implode(',',$cols).") VALUES (".implode(',',$place).")";
        try {
            $st = $pdo->prepare($sql);
            $st->execute($vals);
        } catch (Throwable $e) {
            $this->abort500('Save failed: '.$e->getMessage());
            return;
        }

        $this->redirect($this->moduleBase($c).'/rooms/types', $c);
    }

    /** POST /rooms/types/{id}/update */
    public function typesUpdate(array $ctx, int $id): void
    {
        $c     = $this->ctx($ctx);
        $pdo   = $this->pdo();
        $orgId = (int)$c['org_id'];

        if (!$this->tableExists($pdo,'hms_room_types')) {
            $this->abort500('hms_room_types is missing.');
            return;
        }

        $sets = [];
        $vals = [':o'=>$orgId, ':id'=>$id];

        if ($this->colExists($pdo,'hms_room_types','name')) {
            $sets[] = "name=:n";
            $vals[':n'] = trim((string)($_POST['name'] ?? '')) ?: null;
        }
        if ($this->colExists($pdo,'hms_room_types','description')) {
            $sets[] = "description=:d";
            $vals[':d'] = trim((string)($_POST['description'] ?? '')) ?: null;
        }

        if (!$sets) {
            $this->redirect($this->moduleBase($c).'/rooms/types', $c);
            return;
        }

        $sql = "UPDATE hms_room_types SET ".implode(', ',$sets)." WHERE org_id=:o AND id=:id";
        try {
            $st = $pdo->prepare($sql);
            $st->execute($vals);
        } catch (Throwable $e) {
            // soft fail
        }

        $this->redirect($this->moduleBase($c).'/rooms/types', $c);
    }

    /** POST /rooms/types/{id}/delete */
    public function typesDelete(array $ctx, int $id): void
    {
        $c     = $this->ctx($ctx);
        $pdo   = $this->pdo();
        $orgId = (int)$c['org_id'];

        if (!$this->tableExists($pdo,'hms_room_types')) {
            $this->redirect($this->moduleBase($c).'/rooms/types', $c);
            return;
        }

        try {
            $st = $pdo->prepare("DELETE FROM hms_room_types WHERE org_id=:o AND id=:id");
            $st->execute([':o'=>$orgId, ':id'=>$id]);
        } catch (Throwable $e) {
            // ignore
        }

        $this->redirect($this->moduleBase($c).'/rooms/types', $c);
    }

    /* ───────────────────────────── FLOORS ───────────────────────────── */

    /** GET /rooms/floors */
    public function floorsIndex(array $ctx): void
    {
        $c     = $this->ctx($ctx);
        $pdo   = $this->pdo();
        $orgId = (int)$c['org_id'];

        $hasFloorsTable = $this->tableExists($pdo,'hms_floors');
        $hasName  = $hasFloorsTable && $this->colExists($pdo,'hms_floors','name');
        $hasLabel = $hasFloorsTable && $this->colExists($pdo,'hms_floors','label');
        $hasSort  = $hasFloorsTable && $this->colExists($pdo,'hms_floors','sort_order');

        $rows = [];
        if ($hasFloorsTable) {
            $cols = ['id'];
            if ($hasName)  $cols[] = 'name';
            if ($hasLabel) $cols[] = 'label';
            if ($hasSort)  $cols[] = 'sort_order';
            $sql = "SELECT ".implode(',',$cols)." FROM hms_floors WHERE org_id=:o ORDER BY "
                 . ($hasSort ? 'sort_order, ' : '')
                 . ($hasLabel ? 'label' : 'name')
                 . ", id";
            try {
                $st = $pdo->prepare($sql);
                $st->execute([':o'=>$orgId]);
                $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (Throwable $e) {
                $rows = [];
            }
        } else {
            // derive from rooms
            $floors = $this->fetchFloors($pdo, $orgId, $this->colExists($pdo,'hms_rooms','floor'));
            $rows = array_map(static fn($f) => ['derived'=>true,'label'=>(string)$f], $floors);
        }

        $this->view('rooms/floors', [
            'title'  => 'Floors',
            'rows'   => $rows,
            'schema' => [
                'table' => $hasFloorsTable,
                'name'  => $hasName,
                'label' => $hasLabel,
                'sort'  => $hasSort,
            ],
        ], $c);
    }

    /** POST /rooms/floors/store */
    public function floorsStore(array $ctx): void
    {
        $c     = $this->ctx($ctx);
        $pdo   = $this->pdo();
        $orgId = (int)$c['org_id'];

        if (!$this->tableExists($pdo,'hms_floors')) {
            $this->abort500('hms_floors is missing.');
            return;
        }

        $name  = trim((string)($_POST['name'] ?? ''));
        $label = trim((string)($_POST['label'] ?? ''));
        $sort  = (int)($_POST['sort_order'] ?? 0);

        $cols  = ['org_id'];
        $vals  = [':o'=>$orgId];
        $place = [':o'];

        if ($this->colExists($pdo,'hms_floors','name')) {
            $cols[]   = 'name';
            $vals[':n'] = $name ?: null;
            $place[]  = ':n';
        }
        if ($this->colExists($pdo,'hms_floors','label')) {
            $cols[]   = 'label';
            $vals[':l'] = $label ?: null;
            $place[]  = ':l';
        }
        if ($this->colExists($pdo,'hms_floors','sort_order')) {
            $cols[]   = 'sort_order';
            $vals[':s'] = $sort ?: 0;
            $place[]  = ':s';
        }

        $sql = "INSERT INTO hms_floors (".implode(',',$cols).") VALUES (".implode(',',$place).")";
        try {
            $st = $pdo->prepare($sql);
            $st->execute($vals);
        } catch (Throwable $e) {
            $this->abort500('Save failed: '.$e->getMessage());
            return;
        }

        $this->redirect($this->moduleBase($c).'/rooms/floors', $c);
    }

    /** POST /rooms/floors/{id}/delete */
    public function floorsDelete(array $ctx, int $id): void
    {
        $c     = $this->ctx($ctx);
        $pdo   = $this->pdo();
        $orgId = (int)$c['org_id'];

        if (!$this->tableExists($pdo,'hms_floors')) {
            $this->redirect($this->moduleBase($c).'/rooms/floors', $c);
            return;
        }

        try {
            $st = $pdo->prepare("DELETE FROM hms_floors WHERE org_id=:o AND id=:id");
            $st->execute([':o'=>$orgId, ':id'=>$id]);
        } catch (Throwable $e) {
            // ignore
        }

        $this->redirect($this->moduleBase($c).'/rooms/floors', $c);
    }

    /* ───────────────────────────── quick actions ───────────────────────────── */

    /** Toggle Out-of-Order */
    public function toggleOoo(array $ctx, int $id): void
    {
        $c     = $this->ctx($ctx);
        $pdo   = $this->pdo();
        $orgId = (int)$c['org_id'];

        if (!$this->colExists($pdo,'hms_rooms','room_status')) {
            $this->redirect($this->moduleBase($c).'/rooms', $c);
            return;
        }

        try {
            $st = $pdo->prepare(
                "UPDATE hms_rooms
                 SET room_status = IF(room_status='out_of_order', NULL, 'out_of_order')
                 WHERE org_id=:o AND id=:id"
            );
            $st->execute([':o'=>$orgId, ':id'=>$id]);
        } catch (Throwable $e) {
            // ignore
        }

        $this->redirect($this->moduleBase($c).'/rooms', $c);
    }

    /** Toggle Out-of-Service */
    public function toggleOos(array $ctx, int $id): void
    {
        $c     = $this->ctx($ctx);
        $pdo   = $this->pdo();
        $orgId = (int)$c['org_id'];

        if (!$this->colExists($pdo,'hms_rooms','room_status')) {
            $this->redirect($this->moduleBase($c).'/rooms', $c);
            return;
        }

        try {
            $st = $pdo->prepare(
                "UPDATE hms_rooms
                 SET room_status = IF(room_status='out_of_service', NULL, 'out_of_service')
                 WHERE org_id=:o AND id=:id"
            );
            $st->execute([':o'=>$orgId, ':id'=>$id]);
        } catch (Throwable $e) {
            // ignore
        }

        $this->redirect($this->moduleBase($c).'/rooms', $c);
    }

    /** POST /rooms/{id}/hk-status */
    public function setHkStatus(array $ctx, int $id): void
    {
        $c     = $this->ctx($ctx);
        $pdo   = $this->pdo();
        $orgId = (int)$c['org_id'];

        if (!$this->colExists($pdo,'hms_rooms','hk_status')) {
            $this->redirect($this->moduleBase($c).'/rooms', $c);
            return;
        }

        $hk = trim((string)($_POST['hk'] ?? ''));
        try {
            $st = $pdo->prepare("UPDATE hms_rooms SET hk_status=:hk WHERE org_id=:o AND id=:id");
            $st->execute([':hk'=>$hk ?: null, ':o'=>$orgId, ':id'=>$id]);
        } catch (Throwable $e) {
            // ignore
        }

        $this->redirect($this->moduleBase($c).'/rooms', $c);
    }

    /* ───────────────────────────── helpers ───────────────────────────── */

    private function fetchRoom(PDO $pdo, int $orgId, int $id): ?array
    {
        $hasRoomNo = $this->colExists($pdo,'hms_rooms','room_no');
        $cols = ['r.id','r.org_id'];
        if ($hasRoomNo) $cols[]='r.room_no';
        if ($this->colExists($pdo,'hms_rooms','name'))         $cols[]='r.name';
        if ($this->colExists($pdo,'hms_rooms','room_type_id')) $cols[]='r.room_type_id';
        if ($this->colExists($pdo,'hms_rooms','floor'))        $cols[]='r.floor';
        if ($this->colExists($pdo,'hms_rooms','hk_status'))    $cols[]='r.hk_status';
        if ($this->colExists($pdo,'hms_rooms','room_status'))  $cols[]='r.room_status';
        if ($this->colExists($pdo,'hms_rooms','notes'))        $cols[]='r.notes';
        if ($this->colExists($pdo,'hms_rooms','amenities'))    $cols[]='r.amenities';

        $sql = "SELECT ".implode(', ',$cols)." FROM hms_rooms r WHERE r.org_id=:o AND r.id=:id LIMIT 1";
        try {
            $st = $pdo->prepare($sql);
            $st->execute([':o'=>$orgId, ':id'=>$id]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }

    private function fetchRoomTypes(PDO $pdo, int $orgId): array
    {
        if (!$this->tableExists($pdo,'hms_room_types')) return [];
        try {
            $st = $pdo->prepare(
                "SELECT id, name
                 FROM hms_room_types
                 WHERE org_id = :o
                 ORDER BY name"
            );
            $st->execute([':o'=>$orgId]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
            return $rows ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    private function fetchFloors(PDO $pdo, int $orgId, bool $hasFloor): array
    {
        if (!$hasFloor) return [];
        try {
            $st = $pdo->prepare(
                "SELECT DISTINCT floor
                 FROM hms_rooms
                 WHERE org_id=:o
                   AND floor IS NOT NULL
                   AND floor<>'' 
                 ORDER BY 1"
            );
            $st->execute([':o'=>$orgId]);
            $vals = [];
            foreach ($st->fetchAll(PDO::FETCH_COLUMN, 0) ?: [] as $f) {
                $vals[] = (string)$f;
            }
            return $vals;
        } catch (Throwable $e) {
            return [];
        }
    }

    private function roomPayloadFromPost(PDO $pdo): array
    {
        return [
            'room_no'      => trim((string)($_POST['room_no'] ?? '')),
            'name'         => trim((string)($_POST['name'] ?? '')),
            'room_type_id' => (int)($_POST['room_type_id'] ?? 0),
            'floor'        => trim((string)($_POST['floor'] ?? '')),
            'hk_status'    => trim((string)($_POST['hk_status'] ?? '')),
            'room_status'  => trim((string)($_POST['room_status'] ?? '')),
            'amenities'    => trim((string)($_POST['amenities'] ?? '')), // CSV or JSON
            'notes'        => trim((string)($_POST['notes'] ?? '')),
        ];
    }

    private function buildInsertForRooms(PDO $pdo, int $orgId, array $p): array
    {
        if (!$this->tableExists($pdo,'hms_rooms')) {
            return [[], [], null];
        }

        $fields = ['org_id'];
        $params = [':o'=>$orgId];

        if ($this->colExists($pdo,'hms_rooms','room_no')) {
            $fields[] = 'room_no';
            $params[':room_no'] = ($p['room_no'] !== '' ? $p['room_no'] : null);
        }
        if ($this->colExists($pdo,'hms_rooms','name')) {
            $fields[] = 'name';
            $params[':name'] = ($p['name'] !== '' ? $p['name'] : null);
        }
        if ($this->colExists($pdo,'hms_rooms','room_type_id')) {
            $fields[] = 'room_type_id';
            $params[':rt'] = ($p['room_type_id']>0 ? $p['room_type_id'] : null);
        }
        if ($this->colExists($pdo,'hms_rooms','floor')) {
            $fields[] = 'floor';
            $params[':floor'] = ($p['floor'] !== '' ? $p['floor'] : null);
        }
        if ($this->colExists($pdo,'hms_rooms','hk_status')) {
            $fields[] = 'hk_status';
            $params[':hk'] = ($p['hk_status'] !== '' ? $p['hk_status'] : null);
        }
        if ($this->colExists($pdo,'hms_rooms','room_status')) {
            $fields[] = 'room_status';
            $params[':rs'] = ($p['room_status'] !== '' ? $p['room_status'] : null);
        }
        if ($this->colExists($pdo,'hms_rooms','amenities')) {
            $fields[] = 'amenities';
            $params[':am'] = ($p['amenities'] !== '' ? $p['amenities'] : null);
        }
        if ($this->colExists($pdo,'hms_rooms','notes')) {
            $fields[] = 'notes';
            $params[':notes'] = ($p['notes'] !== '' ? $p['notes'] : null);
        }

        return [$fields, $params, 'hms_rooms'];
    }

    private function buildUpdateForRooms(PDO $pdo, array $p): array
    {
        $sets   = [];
        $params = [];

        if ($this->colExists($pdo,'hms_rooms','room_no')) {
            $sets[] = "room_no=:room_no";
            $params[':room_no'] = ($p['room_no'] !== '' ? $p['room_no'] : null);
        }
        if ($this->colExists($pdo,'hms_rooms','name')) {
            $sets[] = "name=:name";
            $params[':name'] = ($p['name'] !== '' ? $p['name'] : null);
        }
        if ($this->colExists($pdo,'hms_rooms','room_type_id')) {
            $sets[] = "room_type_id=:rt";
            $params[':rt'] = ($p['room_type_id']>0 ? $p['room_type_id'] : null);
        }
        if ($this->colExists($pdo,'hms_rooms','floor')) {
            $sets[] = "floor=:floor";
            $params[':floor'] = ($p['floor'] !== '' ? $p['floor'] : null);
        }
        if ($this->colExists($pdo,'hms_rooms','hk_status')) {
            $sets[] = "hk_status=:hk";
            $params[':hk'] = ($p['hk_status'] !== '' ? $p['hk_status'] : null);
        }
        if ($this->colExists($pdo,'hms_rooms','room_status')) {
            $sets[] = "room_status=:rs";
            $params[':rs'] = ($p['room_status'] !== '' ? $p['room_status'] : null);
        }
        if ($this->colExists($pdo,'hms_rooms','amenities')) {
            $sets[] = "amenities=:am";
            $params[':am'] = ($p['amenities'] !== '' ? $p['amenities'] : null);
        }
        if ($this->colExists($pdo,'hms_rooms','notes')) {
            $sets[] = "notes=:notes";
            $params[':notes'] = ($p['notes'] !== '' ? $p['notes'] : null);
        }

        return [$sets, $params];
    }

    /* schema helpers */

    private function colExists(PDO $pdo, string $table, string $column): bool
    {
        try {
            $st = $pdo->prepare(
                "SELECT 1
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME   = :t
                   AND COLUMN_NAME  = :c
                 LIMIT 1"
            );
            $st->execute([':t'=>$table, ':c'=>$column]);
            return (bool)$st->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }

    private function tableExists(PDO $pdo, string $table): bool
    {
        try {
            $st = $pdo->prepare(
                "SELECT 1
                 FROM INFORMATION_SCHEMA.TABLES
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME   = :t
                 LIMIT 1"
            );
            $st->execute([':t'=>$table]);
            return (bool)$st->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }
}