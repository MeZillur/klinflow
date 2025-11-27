<?php
declare(strict_types=1);

namespace Modules\hotelflow\Controllers;

use PDO;
use Throwable;

final class HkController extends BaseController
{
    /* ───────────────────────────── Main board ───────────────────────────── */
    public function index(array $ctx): void
    {
        $c     = $this->ctx($ctx);
        $pdo   = $this->pdo();
        $orgId = (int)$c['org_id'];

        // Ensure HK tables exist (idempotent)
        $this->ensureHkSchema($pdo);

        $hasRooms = $this->tableExists($pdo, 'hms_rooms');

        $cols = ['id'];
        if ($this->colExists($pdo,'hms_rooms','room_no'))   $cols[]='room_no';
        if ($this->colExists($pdo,'hms_rooms','room_type')) $cols[]='room_type';
        if ($this->colExists($pdo,'hms_rooms','floor'))     $cols[]='floor';
        if ($this->colExists($pdo,'hms_rooms','hk_status')) $cols[]='hk_status';
        if ($this->colExists($pdo,'hms_rooms','hk_notes'))  $cols[]='hk_notes';

        $rooms = [];
        if ($hasRooms) {
            $sql = "SELECT ".implode(',', $cols)."
                      FROM hms_rooms
                     WHERE org_id = :o
                     ORDER BY floor, room_no, id";
            try {
                $st = $pdo->prepare($sql);
                $st->execute([':o' => $orgId]);
                $rooms = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (Throwable $e) {
                $rooms = [];
            }
        }

        // optional filters (status/floor)
        $status = trim((string)($_GET['status'] ?? '')); // clean, dirty, inspected, out_of_service
        $floor  = trim((string)($_GET['floor'] ?? ''));
        if ($rooms) {
            $rooms = array_values(array_filter(
                $rooms,
                function($r) use ($status,$floor){
                    if ($status !== '' && strtolower((string)($r['hk_status'] ?? '')) !== strtolower($status)) return false;
                    if ($floor  !== '' && (string)($r['floor'] ?? '') !== $floor) return false;
                    return true;
                }
            ));
        }

        $this->view('hk/index', [
            'title' => 'Housekeeping',
            'rooms' => $rooms,
            'schema'=> [
                'rooms'     => $hasRooms,
                'room_no'   => in_array('room_no',$cols,true),
                'room_type' => in_array('room_type',$cols,true),
                'floor'     => in_array('floor',$cols,true),
                'hk_status' => in_array('hk_status',$cols,true),
                'hk_notes'  => in_array('hk_notes',$cols,true),
            ],
            'filters'=> ['status'=>$status,'floor'=>$floor],
        ], $c);
    }

    /** POST: set hk_status for a room if column exists. */
    public function roomSetHkStatus(array $ctx, int $roomId): void
    {
        $c     = $this->ctx($ctx);
        $pdo   = $this->pdo();
        $orgId = (int)$c['org_id'];
        $new   = trim((string)($_POST['hk_status'] ?? ''));

        if (!$this->tableExists($pdo,'hms_rooms') || !$this->colExists($pdo,'hms_rooms','hk_status')) {
            $this->abort500('Housekeeping status column not available on hms_rooms.');
            return;
        }

        try {
            $st = $pdo->prepare("
                UPDATE hms_rooms
                   SET hk_status = :s,
                       updated_at = CURRENT_TIMESTAMP
                 WHERE org_id = :o
                   AND id     = :id
            ");
            $st->execute([
                ':s'  => $new ?: null,
                ':o'  => $orgId,
                ':id' => $roomId,
            ]);
        } catch (Throwable $e) {
            // ignore
        }

        $this->redirect($c['module_base'].'/housekeeping', $c);
    }

    /* ─────────────────────────────── Tasks ─────────────────────────────── */
    public function tasksIndex(array $ctx): void
    {
        $c     = $this->ctx($ctx);
        $pdo   = $this->pdo();
        $orgId = (int)$c['org_id'];

        $this->ensureHkSchema($pdo);

        $hasTasks = $this->tableExists($pdo,'hms_hk_tasks');
        $rows     = [];

        if ($hasTasks) {
            // prefer full set, but only select existing columns
            $cols = ['id','created_at'];
            foreach (['title','priority','status','room_no','assignee','due_date','notes'] as $col) {
                if ($this->colExists($pdo,'hms_hk_tasks',$col)) $cols[] = $col;
            }

            $sql = "SELECT ".implode(',',$cols)."
                      FROM hms_hk_tasks
                     WHERE org_id = :o
                     ORDER BY (CASE WHEN status='open' THEN 0 ELSE 1 END),
                              due_date,
                              id DESC";

            try {
                $st = $pdo->prepare($sql);
                $st->execute([':o' => $orgId]);
                $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (Throwable $e) {
                $rows = [];
            }
        }

        $this->view('hk/tasks', [
            'title' => 'HK Tasks',
            'rows'  => $rows,
            'schema'=> ['tasks' => $hasTasks],
        ], $c);
    }

    public function tasksStore(array $ctx): void
    {
        $c     = $this->ctx($ctx);
        $pdo   = $this->pdo();
        $orgId = (int)$c['org_id'];

        $this->ensureHkSchema($pdo);

        if (!$this->tableExists($pdo,'hms_hk_tasks')) {
            $this->abort500('hms_hk_tasks table not found.');
            return;
        }

        $title    = trim((string)($_POST['title'] ?? ''));
        $priority = trim((string)($_POST['priority'] ?? 'normal')); // low/normal/high
        $roomNo   = trim((string)($_POST['room_no'] ?? ''));
        $assignee = trim((string)($_POST['assignee'] ?? ''));
        $due      = trim((string)($_POST['due_date'] ?? ''));
        $notes    = trim((string)($_POST['notes'] ?? ''));

        $cols  = ['org_id'];
        $vals  = [':o' => $orgId];
        $place = [':o'];

        foreach ([
            'title'    => ':t',
            'priority' => ':p',
            'status'   => ':s',
            'room_no'  => ':r',
            'assignee' => ':a',
            'due_date' => ':d',
            'notes'    => ':n',
        ] as $col => $ph) {
            if ($col === 'status') { // default
                if ($this->colExists($pdo,'hms_hk_tasks','status')) {
                    $cols[]   = 'status';
                    $vals[$ph]= 'open';
                    $place[]  = $ph;
                }
                continue;
            }

            if ($this->colExists($pdo,'hms_hk_tasks',$col)) {
                $cols[]  = $col;
                $place[] = $ph;
                $vals[$ph] = match ($col) {
                    'title'    => $title ?: null,
                    'priority' => $priority ?: null,
                    'room_no'  => $roomNo ?: null,
                    'assignee' => $assignee ?: null,
                    'due_date' => ($due && preg_match('~^\d{4}-\d{2}-\d{2}$~',$due)) ? $due : null,
                    'notes'    => $notes ?: null,
                    default    => null,
                };
            }
        }

        $sql = "INSERT INTO hms_hk_tasks (".implode(',',$cols).")
                VALUES (".implode(',',$place).")";

        try {
            $st = $pdo->prepare($sql);
            $st->execute($vals);
        } catch (Throwable $e) {
            $this->abort500('Save failed: '.$e->getMessage());
            return;
        }

        $this->redirect($c['module_base'].'/housekeeping/tasks', $c);
    }

    public function tasksDone(array $ctx, int $id): void
    {
        $c     = $this->ctx($ctx);
        $pdo   = $this->pdo();
        $orgId = (int)$c['org_id'];

        if (!$this->tableExists($pdo,'hms_hk_tasks') || !$this->colExists($pdo,'hms_hk_tasks','status')) {
            $this->redirect($c['module_base'].'/housekeeping/tasks', $c);
            return;
        }

        try {
            $st = $pdo->prepare("
                UPDATE hms_hk_tasks
                   SET status = 'done',
                       updated_at = CURRENT_TIMESTAMP
                 WHERE org_id = :o
                   AND id     = :id
            ");
            $st->execute([':o' => $orgId, ':id' => $id]);
        } catch (Throwable $e) {
            // ignore
        }

        $this->redirect($c['module_base'].'/housekeeping/tasks', $c);
    }

    public function tasksDelete(array $ctx, int $id): void
    {
        $c     = $this->ctx($ctx);
        $pdo   = $this->pdo();
        $orgId = (int)$c['org_id'];

        if (!$this->tableExists($pdo,'hms_hk_tasks')) {
            $this->redirect($c['module_base'].'/housekeeping/tasks', $c);
            return;
        }

        try {
            $st = $pdo->prepare("
                DELETE FROM hms_hk_tasks
                 WHERE org_id = :o
                   AND id     = :id
            ");
            $st->execute([':o' => $orgId, ':id' => $id]);
        } catch (Throwable $e) {
            // ignore
        }

        $this->redirect($c['module_base'].'/housekeeping/tasks', $c);
    }

    /* ─────────────────────────── Lost & Found ─────────────────────────── */
    public function lostFoundIndex(array $ctx): void
    {
        $c     = $this->ctx($ctx);
        $pdo   = $this->pdo();
        $orgId = (int)$c['org_id'];

        $this->ensureHkSchema($pdo);

        $has  = $this->tableExists($pdo,'hms_lost_found');
        $rows = [];

        if ($has) {
            $cols = ['id','created_at'];
            foreach ([
                'date_found','room_no','place','item','description',
                'found_by','status','guest_name','contact','location','photo_path'
            ] as $cl) {
                if ($this->colExists($pdo,'hms_lost_found',$cl)) $cols[] = $cl;
            }

            $sql = "SELECT ".implode(',',$cols)."
                      FROM hms_lost_found
                     WHERE org_id = :o
                     ORDER BY id DESC
                     LIMIT 300";

            try {
                $st = $pdo->prepare($sql);
                $st->execute([':o' => $orgId]);
                $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (Throwable $e) {
                $rows = [];
            }
        }

        $this->view('hk/lost-found', [
            'title' => 'Lost & Found',
            'rows'  => $rows,
            'schema'=> ['lf' => $has],
        ], $c);
    }

    public function lostFoundStore(array $ctx): void
    {
        $c     = $this->ctx($ctx);
        $pdo   = $this->pdo();
        $orgId = (int)$c['org_id'];

        $this->ensureHkSchema($pdo);

        if (!$this->tableExists($pdo,'hms_lost_found')) {
            $this->abort500('hms_lost_found table not found.');
            return;
        }

        // collect fields if columns exist
        $vals  = [':o' => $orgId];
        $cols  = ['org_id'];
        $place = [':o'];

        $post = [
            'date_found'  => (string)($_POST['date_found'] ?? ''),
            'room_no'     => (string)($_POST['room_no'] ?? ''),
            'place'       => (string)($_POST['place'] ?? ''),
            'item'        => (string)($_POST['item'] ?? ''),
            'description' => (string)($_POST['description'] ?? ''),
            'found_by'    => (string)($_POST['found_by'] ?? ''),
            'status'      => (string)($_POST['status'] ?? 'logged'),
            'guest_name'  => (string)($_POST['guest_name'] ?? ''),
            'contact'     => (string)($_POST['contact'] ?? ''),
            'location'    => (string)($_POST['location'] ?? ''),
        ];

        foreach ($post as $k => $v) {
            if ($this->colExists($pdo,'hms_lost_found',$k)) {
                $cols[]  = $k;
                $ph      = ':'.substr($k,0,2);
                $place[] = $ph;

                if ($k === 'date_found' && $v && !preg_match('~^\d{4}-\d{2}-\d{2}$~',$v)) {
                    $v = null;
                }

                $vals[$ph] = $v !== '' ? $v : null;
            }
        }

        // optional photo upload
        $photoPath = $this->moveUpload($_FILES['photo'] ?? null, 'lostfound');
        if ($photoPath && $this->colExists($pdo,'hms_lost_found','photo_path')) {
            $cols[]  = 'photo_path';
            $place[] = ':pp';
            $vals[':pp'] = $photoPath;
        }

        $sql = "INSERT INTO hms_lost_found (".implode(',',$cols).")
                VALUES (".implode(',',$place).")";

        try {
            $st = $pdo->prepare($sql);
            $st->execute($vals);
        } catch (Throwable $e) {
            $this->abort500('Save failed: '.$e->getMessage());
            return;
        }

        $this->redirect($c['module_base'].'/housekeeping/lost-found', $c);
    }

    public function lostFoundSetStatus(array $ctx, int $id): void
    {
        $c     = $this->ctx($ctx);
        $pdo   = $this->pdo();
        $orgId = (int)$c['org_id'];

        if (!$this->tableExists($pdo,'hms_lost_found') || !$this->colExists($pdo,'hms_lost_found','status')) {
            $this->redirect($c['module_base'].'/housekeeping/lost-found', $c);
            return;
        }

        $stVal = trim((string)($_POST['status'] ?? 'logged'));

        try {
            $st = $pdo->prepare("
                UPDATE hms_lost_found
                   SET status = :s,
                       updated_at = CURRENT_TIMESTAMP
                 WHERE org_id = :o
                   AND id     = :id
            ");
            $st->execute([':s' => $stVal, ':o' => $orgId, ':id' => $id]);
        } catch (Throwable $e) {
            // ignore
        }

        $this->redirect($c['module_base'].'/housekeeping/lost-found', $c);
    }

    public function lostFoundDelete(array $ctx, int $id): void
    {
        $c     = $this->ctx($ctx);
        $pdo   = $this->pdo();
        $orgId = (int)$c['org_id'];

        if (!$this->tableExists($pdo,'hms_lost_found')) {
            $this->redirect($c['module_base'].'/housekeeping/lost-found', $c);
            return;
        }

        try {
            $st = $pdo->prepare("
                DELETE FROM hms_lost_found
                 WHERE org_id = :o
                   AND id     = :id
            ");
            $st->execute([':o' => $orgId, ':id' => $id]);
        } catch (Throwable $e) {
            // ignore
        }

        $this->redirect($c['module_base'].'/housekeeping/lost-found', $c);
    }

    /* ───────────────────────────── helpers ───────────────────────────── */

    private function moveUpload(?array $file, string $bucket): ?string
    {
        if (!$file || (int)($file['error'] ?? 0) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) return null;
        $max = 5 * 1024 * 1024;
        if (($file['size'] ?? 0) > $max) return null;

        $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','pdf'], true)) return null;

        $yyy = date('Y');
        $mm  = date('m');
        $rel = "/uploads/hotelflow/{$bucket}/{$yyy}/{$mm}";
        $root = rtrim($_SERVER['DOCUMENT_ROOT'] ?? getcwd(), '/');
        @mkdir($root.$rel, 0775, true);

        $name = bin2hex(random_bytes(8)).'.'.$ext;
        if (@move_uploaded_file($file['tmp_name'], $root.$rel.'/'.$name)) {
            return $rel.'/'.$name;
        }
        return null;
    }

    private function colExists(PDO $pdo, string $table, string $column): bool
    {
        $st = $pdo->prepare("
            SELECT 1
              FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = :t
               AND COLUMN_NAME  = :c
             LIMIT 1
        ");
        $st->execute([':t' => $table, ':c' => $column]);
        return (bool)$st->fetchColumn();
    }

    private function tableExists(PDO $pdo, string $table): bool
    {
        $st = $pdo->prepare("
            SELECT 1
              FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = :t
             LIMIT 1
        ");
        $st->execute([':t' => $table]);
        return (bool)$st->fetchColumn();
    }

    /** Ensure HK schema is present (idempotent) */
    private function ensureHkSchema(PDO $pdo): void
    {
        // Tasks
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS hms_hk_tasks (
              id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              org_id       INT UNSIGNED NOT NULL,
              title        VARCHAR(160) NOT NULL,
              priority     ENUM('low','normal','high') NOT NULL DEFAULT 'normal',
              status       ENUM('open','done') NOT NULL DEFAULT 'open',
              room_no      VARCHAR(30) NULL,
              assignee     VARCHAR(120) NULL,
              due_date     DATE NULL,
              notes        TEXT NULL,
              created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at   DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
              KEY idx_org   (org_id),
              KEY idx_status(status),
              KEY idx_due   (due_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // Optional: comments
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS hms_hk_task_comments (
              id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              org_id       INT UNSIGNED NOT NULL,
              task_id      BIGINT UNSIGNED NOT NULL,
              comment      TEXT NOT NULL,
              created_by   VARCHAR(120) NULL,
              created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              KEY idx_org  (org_id),
              KEY idx_task (task_id),
              CONSTRAINT fk_hk_task_comments_task
                FOREIGN KEY (task_id) REFERENCES hms_hk_tasks(id)
                ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // Lost & Found
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS hms_lost_found (
              id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              org_id        INT UNSIGNED NOT NULL,
              date_found    DATE NULL,
              room_no       VARCHAR(30) NULL,
              place         VARCHAR(120) NULL,
              item          VARCHAR(160) NULL,
              description   TEXT NULL,
              found_by      VARCHAR(120) NULL,
              status        ENUM('logged','returned','discarded') NOT NULL DEFAULT 'logged',
              guest_name    VARCHAR(160) NULL,
              contact       VARCHAR(160) NULL,
              location      VARCHAR(160) NULL,
              photo_path    VARCHAR(255) NULL,
              created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at    DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
              KEY idx_org   (org_id),
              KEY idx_status(status),
              KEY idx_date  (date_found)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }
}