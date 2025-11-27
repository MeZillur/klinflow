<?php
declare(strict_types=1);

namespace Modules\DMS\Controllers;

use PDO;

final class StakeholdersController extends BaseController
{
    /* ────────────────────────────── Helpers (stable & conflict-free) ────────────────────────────── */

/** Per-request caches */
private array $tableCache = [];
private array $colCache   = [];

/** Does a table exist in the current DB? (cached) */
private function hasTable(PDO $pdo, string $t): bool
{
    if (isset($this->tableCache[$t])) return $this->tableCache[$t];
    try {
        $q = $pdo->prepare(
            "SELECT 1 FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1"
        );
        $q->execute([$t]);
        return $this->tableCache[$t] = (bool)$q->fetchColumn();
    } catch (\Throwable $e) {
        return $this->tableCache[$t] = false;
    }
}

/** Does a column exist on a table? (cached) */
private function hasCol(PDO $pdo, string $table, string $col): bool
{
    $k = $table.'.'.$col;
    if (array_key_exists($k, $this->colCache)) return $this->colCache[$k];
    try {
        $s = $pdo->prepare("SHOW COLUMNS FROM `{$table}` LIKE ?");
        $s->execute([$col]);
        return $this->colCache[$k] = (bool)$s->fetch(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) {
        return $this->colCache[$k] = false;
    }
}

/* ─────────────── File locations (match your server layout) ───────────────
   We save under: modules/DMS/storage/uploads/images/{orgId}/stakeholders/{id}/
   Public URL mirrors the same path starting at /modules/... */

private function imagesBaseAbs(): string
{
    // Controller lives in modules/DMS/src/Controllers → go up 2 to modules/DMS
    $moduleRoot = dirname(__DIR__, 2); // .../modules/DMS
    return $moduleRoot.'/storage/uploads/images';
}

private function imagesBaseUrl(): string
{
    return '/modules/DMS/storage/uploads/images';
}

private function stakeholderDirAbs(int $orgId, int $id): string
{
    $dir = $this->imagesBaseAbs()."/{$orgId}/stakeholders/{$id}";
    if (!is_dir($dir)) { @mkdir($dir, 0770, true); }
    return $dir;
}

/** Save one uploaded file (photo_file | id_proof_file). Returns public URL or null. */
private function saveUpload(int $orgId, int $id, string $inputName, array $allowedExts): ?string
{
    if (empty($_FILES[$inputName]['tmp_name']) || !is_uploaded_file($_FILES[$inputName]['tmp_name'])) return null;

    $name = (string)($_FILES[$inputName]['name'] ?? '');
    $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExts, true)) return null;

    $basename = ($inputName === 'photo_file') ? 'photo' : 'id';
    $dirAbs   = $this->stakeholderDirAbs($orgId, $id);
    $destAbs  = "{$dirAbs}/{$basename}.{$ext}";

    if (!@move_uploaded_file($_FILES[$inputName]['tmp_name'], $destAbs)) return null;

    // Build public URL: /modules/DMS/storage/uploads/images/{org}/stakeholders/{id}/{file}
    $tail = "{$orgId}/stakeholders/{$id}/{$basename}.{$ext}";
    return rtrim($this->imagesBaseUrl(), '/').'/'.$tail;
}

/** If DB column empty, discover first matching file in folder and return public URL. */
private function discoverFirstFile(int $orgId, int $id, array $patterns): ?string
{
    $dirAbs = rtrim($this->stakeholderDirAbs($orgId, $id), '/');
    foreach ($patterns as $pat) {
        foreach (glob($dirAbs.'/'.$pat) ?: [] as $hit) {
            $tail = "{$orgId}/stakeholders/{$id}/".basename($hit);
            return rtrim($this->imagesBaseUrl(), '/').'/'.$tail;
        }
    }
    return null;
}

/** Update only columns that actually exist on dms_stakeholders */
private function updateOptionalCols(PDO $pdo, int $orgId, int $id, array $pairs): void
{
    $set = [];
    $args = [];
    foreach ($pairs as $col => $val) {
        if ($this->hasCol($pdo, 'dms_stakeholders', $col)) {
            $set[]  = "`{$col}` = ?";
            $args[] = ($val === '') ? null : $val;
        }
    }
    if ($set) {
        $args[] = $orgId;
        $args[] = $id;
        $sql = "UPDATE dms_stakeholders SET ".implode(', ', $set).", updated_at=NOW()
                WHERE org_id=? AND id=? LIMIT 1";
        $pdo->prepare($sql)->execute($args);
    }
}

/** Generate SR/DSR-YYYY-00001 (advances legacy numeric codes too) */
private function nextCode(PDO $pdo, int $orgId, string $role): string
{
    $role   = strtolower($role) === 'dsr' ? 'DSR' : 'SR';
    $year   = date('Y');
    $prefix = "{$role}-{$year}-";

    $st = $pdo->prepare("
        SELECT code
          FROM dms_stakeholders
         WHERE org_id = ? AND code LIKE CONCAT(?, '%')
         ORDER BY id DESC
         LIMIT 10
    ");
    $st->execute([$orgId, $prefix]);
    $rows = $st->fetchAll(PDO::FETCH_COLUMN, 0) ?: [];

    $maxSeq = 0;
    foreach ($rows as $c) {
        if (preg_match('/^'.preg_quote($prefix, '/').'(\d{5})$/', (string)$c, $m)) {
            $seq = (int)$m[1];
            if ($seq > $maxSeq) $maxSeq = $seq;
        }
    }

    if ($maxSeq === 0) {
        $legacy = $pdo->prepare("
            SELECT code
              FROM dms_stakeholders
             WHERE org_id = ?
             ORDER BY id DESC
             LIMIT 20
        ");
        $legacy->execute([$orgId]);
        foreach ($legacy->fetchAll(PDO::FETCH_COLUMN, 0) as $c) {
            if (preg_match('/^\d{4,6}$/', (string)$c)) {
                $maxSeq = max($maxSeq, (int)ltrim($c, '0'));
            }
        }
    }
    return sprintf('%s%05d', $prefix, $maxSeq + 1);
}

/** Resolve supplier table for compatibility */
private function supplierTable(PDO $pdo): string
{
    try {
        $q = $pdo->query("
            SELECT TABLE_NAME
              FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME IN ('dms_suppliers','dms_dealers','dms_stakeholders')
             ORDER BY FIELD(TABLE_NAME,'dms_suppliers','dms_dealers','dms_stakeholders')
             LIMIT 1
        ");
        $tbl = (string)($q->fetchColumn() ?: '');
        return $tbl !== '' ? $tbl : 'dms_suppliers';
    } catch (\Throwable $e) {
        return 'dms_suppliers';
    }
}

/* ─────────────── Option lists for dropdowns ─────────────── */

private function listStakeholders(PDO $pdo, int $orgId): array
{
    $st = $pdo->prepare("
        SELECT id, code, name
          FROM dms_stakeholders
         WHERE org_id=? AND role IN ('sr','dsr')
         ORDER BY name ASC
    ");
    $st->execute([$orgId]);
    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

private function listCustomers(PDO $pdo, int $orgId): array
{
    if (!$this->hasTable($pdo, 'dms_customers')) return [];
    $st = $pdo->prepare("
        SELECT id, name
          FROM dms_customers
         WHERE org_id=?
         ORDER BY name ASC
         LIMIT 1000
    ");
    $st->execute([$orgId]);
    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

private function listSuppliers(PDO $pdo, int $orgId): array
{
    $tbl = $this->supplierTable($pdo);
    if (!$this->hasTable($pdo, $tbl)) return [];
    $hasCode = $this->hasCol($pdo, $tbl, 'code');

    $sql = $hasCode
        ? "SELECT id, name, code FROM {$tbl} WHERE org_id=? ORDER BY name ASC LIMIT 1000"
        : "SELECT id, name, NULL AS code FROM {$tbl} WHERE org_id=? ORDER BY name ASC LIMIT 1000";
    $st = $pdo->prepare($sql);
    $st->execute([$orgId]);
    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

private function listManagers(PDO $pdo, int $orgId): array
{
    $st = $pdo->prepare("
        SELECT id, name, role
          FROM dms_stakeholders
         WHERE org_id=? AND role IN ('supervisor','manager')
         ORDER BY name ASC
    ");
    $st->execute([$orgId]);
    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

private function recentVisits(PDO $pdo, int $orgId): array
{
    if (!$this->hasTable($pdo, 'dms_visit_plans')) return [];
    $supTbl = $this->supplierTable($pdo);

    $sql = "
        SELECT v.id, v.visit_date, v.status, v.notes,
               sh.name AS stakeholder_name,
               c.name  AS customer_name,
               sup.name AS supplier_name
          FROM dms_visit_plans v
          LEFT JOIN dms_stakeholders sh ON sh.org_id=v.org_id AND sh.id=v.stakeholder_id
          LEFT JOIN dms_customers   c  ON c.org_id=v.org_id  AND c.id=v.customer_id
          LEFT JOIN {$supTbl}       sup ON sup.org_id=v.org_id AND sup.id=v.dealer_id
         WHERE v.org_id=? AND v.visit_date >= (CURRENT_DATE - INTERVAL 60 DAY)
         ORDER BY v.visit_date DESC, v.id DESC
         LIMIT 200
    ";
    $st = $pdo->prepare($sql);
    $st->execute([$orgId]);
    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

    /* ───────────────────────────── Index / CRUD ─────────────────────────── */
  
  
  		/** GET /stakeholders/create */
	public function create(array $ctx): void
	{
    $pdo   = $this->pdo();
    $orgId = $this->orgId($ctx);

    $this->view('stakeholders/create', [
        'title'      => 'Create Stakeholder',
        'active'     => 'stake',
        'subactive'  => 'stakeholders.create',
        // NEW: option lists for dropdowns
        'suppliers'  => $this->listSuppliers($pdo, $orgId),
        'managers'   => $this->listManagers($pdo, $orgId),
    ], $ctx);
}
  

    /** GET /stakeholders */
    public function index(array $ctx): void
    {
        $pdo   = $this->pdo();
        $orgId = $this->orgId($ctx);
        $q     = trim((string)($_GET['q'] ?? ''));
        $role  = strtolower(trim((string)($_GET['role'] ?? ''))); // '', 'sr', 'dsr'

        $sql  = "SELECT id, code, name, phone, email, role, territory, status
                   FROM dms_stakeholders
                  WHERE org_id = ?";
        $args = [$orgId];

        if ($role !== '' && in_array($role, ['sr','dsr'], true)) {
            $sql .= " AND role = ?";
            $args[] = $role;
        }
        if ($q !== '') {
            $like = "%$q%";
            $sql .= " AND (name LIKE ? OR phone LIKE ? OR code LIKE ? OR email LIKE ?)";
            array_push($args, $like, $like, $like, $like);
        }
        $sql .= " ORDER BY id DESC LIMIT 200";

        $st = $pdo->prepare($sql);
        $st->execute($args);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $this->view('stakeholders/index', [
            'title'     => 'Stakeholders (SR/DSR)',
            'rows'      => $rows,
            'q'         => $q,
            'role'      => $role,
            'active'    => 'stake',
            'subactive' => 'stakeholders.index',
        ], $ctx);
    }

    

    /** POST /stakeholders */
    public function store(array $ctx): void
{
    $pdo   = $this->pdo();
    $orgId = $this->orgId($ctx);
    $base  = $this->moduleBase($ctx);

    $name = trim((string)($_POST['name'] ?? ''));
    if ($name === '') {
        $_SESSION['flash_errors'] = ['Name is required.'];
        header('Location: '.$base.'/stakeholders/create');
        exit;
    }

    $role   = strtolower((string)($_POST['role'] ?? 'sr'));
    $phone  = trim((string)($_POST['phone'] ?? ''));
    $email  = trim((string)($_POST['email'] ?? ''));
    $terr   = trim((string)($_POST['territory'] ?? ''));
    $status = in_array(($_POST['status'] ?? 'active'), ['active','inactive'], true) ? $_POST['status'] : 'active';
    $notes  = trim((string)($_POST['notes'] ?? ''));

    // Key dropdowns / numeric fields
    $supplier_id     = (int)($_POST['supplier_id'] ?? 0);
    $line_manager_id = (int)($_POST['line_manager_id'] ?? 0);
    $monthly_target  = ($_POST['monthly_target'] ?? '') !== '' ? (float)$_POST['monthly_target'] : null;

    // ID fields
    $id_proof_type = trim((string)($_POST['id_proof_type'] ?? ''));
    $id_proof_no   = trim((string)($_POST['id_proof_no'] ?? ''));

    $code = trim((string)($_POST['code'] ?? ''));
    if ($code === '') $code = $this->nextCode($pdo, $orgId, $role);

    try {
        $pdo->beginTransaction();

        $sql = "INSERT INTO dms_stakeholders
            (org_id, code, name, phone, email, role, territory, status, notes,
             supplier_id, line_manager_id, monthly_target,
             id_proof_type, id_proof_no, created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $orgId, $code, $name, $phone ?: null, $email ?: null, $role,
            $terr ?: null, $status, $notes ?: null,
            $supplier_id ?: null, $line_manager_id ?: null, $monthly_target,
            $id_proof_type ?: null, $id_proof_no ?: null
        ]);

        $id = (int)$pdo->lastInsertId();

        // Uploads
        $photoUrl = $this->saveUpload($orgId, $id, 'photo_file', ['jpg','jpeg','png']);
        $idUrl    = $this->saveUpload($orgId, $id, 'id_proof_file', ['pdf','jpg','jpeg','png']);

        $updatePairs = [];
        if ($photoUrl) $updatePairs['photo_path'] = $photoUrl;
        if ($idUrl)    $updatePairs['id_proof_path'] = $idUrl;

        // Fetch supplier and manager names for snapshot
        if ($supplier_id > 0) {
            $supTbl = $this->supplierTable($pdo);
            $q = $pdo->prepare("SELECT name FROM {$supTbl} WHERE org_id=? AND id=?");
            $q->execute([$orgId, $supplier_id]);
            if ($n = $q->fetchColumn()) $updatePairs['supplier_name'] = $n;
        }
        if ($line_manager_id > 0) {
            $q = $pdo->prepare("SELECT name FROM dms_stakeholders WHERE org_id=? AND id=?");
            $q->execute([$orgId, $line_manager_id]);
            if ($n = $q->fetchColumn()) $updatePairs['line_manager_name'] = $n;
        }

        if ($updatePairs) $this->updateOptionalCols($pdo, $orgId, $id, $updatePairs);

        $pdo->commit();
        $_SESSION['flash_success'] = "Stakeholder {$code} created successfully.";
        header('Location: '.$base.'/stakeholders/'.$id);
        exit;
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['flash_errors'] = ['Failed to save: '.$e->getMessage()];
        header('Location: '.$base.'/stakeholders/create');
        exit;
    }
}

    /** GET /stakeholders/{id} */
public function show(array $ctx, int $id): void
{
    $pdo     = $this->pdo();
    $orgId   = $this->orgId($ctx);

    // Resolve supplier table (new/legacy) early
    $supTbl  = $this->supplierTable($pdo); // 'dms_suppliers' | 'dms_dealers' | 'dms_stakeholders'

    // ---- Header (must exist) ----
    try {
        $h = $pdo->prepare("SELECT * FROM dms_stakeholders WHERE org_id=? AND id=? LIMIT 1");
        $h->execute([$orgId, $id]);
        $s = $h->fetch(PDO::FETCH_ASSOC);
        if (!$s) { $this->abort404('Stakeholder not found.'); return; }
    } catch (\Throwable $e) {
        $this->abort500($e); return;
    }

    $assignments = [];
    $visits      = [];

    // ---- Assignments (guard missing tables) ----
    try {
        if ($this->hasTable($pdo, 'dms_stakeholder_assignments')) {
            $haveCustomers = $this->hasTable($pdo, 'dms_customers');
            $haveSuppliers = $this->hasTable($pdo, $supTbl);

            // Build SELECT with safe NULL fallbacks if a joined table doesn't exist
            $sel  = "SELECT a.id, a.assigned_on, a.customer_id, a.dealer_id";
            $sel .= $haveCustomers ? ", c.name AS customer_name" : ", NULL AS customer_name";
            $sel .= $haveSuppliers ? ", sup.name AS supplier_name" : ", NULL AS supplier_name";

            $sql  = $sel . " FROM dms_stakeholder_assignments a";
            if ($haveCustomers) $sql .= " LEFT JOIN dms_customers c ON c.org_id=a.org_id AND c.id=a.customer_id";
            if ($haveSuppliers) $sql .= " LEFT JOIN {$supTbl} sup ON sup.org_id=a.org_id AND sup.id=a.dealer_id";
            $sql .= " WHERE a.org_id=? AND a.stakeholder_id=? ORDER BY a.id DESC LIMIT 50";

            $as = $pdo->prepare($sql);
            $as->execute([$orgId, $id]);
            $assignments = $as->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
    } catch (\Throwable $e) {
        // keep page rendering; optionally log
        $assignments = [];
    }

    // ---- Recent visits (guard missing table) ----
    try {
        if ($this->hasTable($pdo, 'dms_visit_plans')) {
            $vp = $pdo->prepare("
                SELECT id, visit_date, status, customer_id, dealer_id
                FROM dms_visit_plans
                WHERE org_id=? AND stakeholder_id=?
                ORDER BY visit_date DESC, id DESC
                LIMIT 20
            ");
            $vp->execute([$orgId, $id]);
            $visits = $vp->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
    } catch (\Throwable $e) {
        $visits = [];
    }

    // ---- Render ----
    $this->view('stakeholders/show', [
        'title'       => 'Stakeholder: '.($s['name'] ?? ('#'.$id)),
        's'           => $s,
        'assignments' => $assignments,
        'visits'      => $visits,
        'active'      => 'stake',
        'subactive'   => 'stakeholders.index',
    ], $ctx);
}

    /** GET /stakeholders/{id}/edit */
public function edit(array $ctx, int $id): void
{
    $pdo   = $this->pdo();
    $orgId = $this->orgId($ctx);

    $st = $pdo->prepare("SELECT * FROM dms_stakeholders WHERE org_id=? AND id=?");
    $st->execute([$orgId, $id]);
    $s = $st->fetch(PDO::FETCH_ASSOC);
    if (!$s) $this->abort404('Not found.');

    $this->view('stakeholders/edit', [
        'title'      => 'Edit Stakeholder',
        's'          => $s,
        'active'     => 'stake',
        'subactive'  => 'stakeholders.index',
        // NEW: option lists for dropdowns
        'suppliers'  => $this->listSuppliers($pdo, $orgId),
        'managers'   => $this->listManagers($pdo, $orgId),
    ], $ctx);
}

    /* ───────────────────────────────── update() ───────────────────────────────── */

public function update(array $ctx, int $id): void
{
    $pdo   = $this->pdo();
    $orgId = $this->orgId($ctx);

    $code   = trim((string)($_POST['code'] ?? ''));
    $name   = trim((string)($_POST['name'] ?? ''));
    $role   = in_array(strtolower((string)($_POST['role'] ?? 'sr')), ['sr','dsr'], true) ? strtolower((string)$_POST['role']) : 'sr';
    $phone  = trim((string)($_POST['phone'] ?? ''));
    $email  = trim((string)($_POST['email'] ?? ''));
    $terr   = trim((string)($_POST['territory'] ?? ''));
    $status = in_array(($_POST['status'] ?? 'active'), ['active','inactive'], true) ? $_POST['status'] : 'active';
    $notes  = trim((string)($_POST['notes'] ?? ''));

    // Dropdowns
    $supplier_id     = (int)($_POST['supplier_id'] ?? 0);
    $line_manager_id = (int)($_POST['line_manager_id'] ?? 0);
    $monthly_target  = ($_POST['monthly_target'] ?? '') !== '' ? (float)$_POST['monthly_target'] : null;

    // ID fields
    $id_proof_type = trim((string)($_POST['id_proof_type'] ?? ''));
    $id_proof_no   = trim((string)($_POST['id_proof_no']   ?? ''));

    if ($name === '') $this->abort400('Name is required.');

    $sets = [
        'code=?','name=?','role=?','phone=?','email=?','territory=?','status=?','notes=?','updated_at=NOW()'
    ];
    $vals = [$code ?: null, $name, $role, $phone ?: null, $email ?: null, $terr ?: null, $status, $notes ?: null];

    if ($this->hasCol($pdo,'dms_stakeholders','supplier_id'))     { $sets[]='supplier_id=?';     $vals[] = $supplier_id ?: null; }
    if ($this->hasCol($pdo,'dms_stakeholders','line_manager_id')) { $sets[]='line_manager_id=?'; $vals[] = $line_manager_id ?: null; }
    if ($this->hasCol($pdo,'dms_stakeholders','monthly_target'))  { $sets[]='monthly_target=?';  $vals[] = $monthly_target; }
    if ($this->hasCol($pdo,'dms_stakeholders','id_proof_type'))   { $sets[]='id_proof_type=?';   $vals[] = $id_proof_type ?: null; }
    if ($this->hasCol($pdo,'dms_stakeholders','id_proof_no'))     { $sets[]='id_proof_no=?';     $vals[] = $id_proof_no   ?: null; }

    // Handle uploads (if a new file comes, overwrite path)
    $photoUrl = $this->saveUpload($orgId, $id, 'photo_file', ['jpg','jpeg','png','webp']);
    $idUrl    = $this->saveUpload($orgId, $id, 'id_proof_file', ['pdf','jpg','jpeg','png']);

    if ($photoUrl && $this->hasCol($pdo,'dms_stakeholders','photo_path'))   { $sets[]='photo_path=?';   $vals[]=$photoUrl; }
    if ($idUrl    && $this->hasCol($pdo,'dms_stakeholders','id_proof_path')) { $sets[]='id_proof_path=?'; $vals[]=$idUrl;   }

    // Optional snapshot names
    if ($this->hasCol($pdo,'dms_stakeholders','supplier_name')) {
        if ($supplier_id > 0) {
            $supTbl=$this->supplierTable($pdo);
            if ($this->hasTable($pdo,$supTbl)) {
                $q=$pdo->prepare("SELECT name FROM {$supTbl} WHERE org_id=? AND id=?");
                $q->execute([$orgId,$supplier_id]);
                $nm=(string)($q->fetchColumn() ?: '');
                $sets[]='supplier_name=?'; $vals[]= $nm ?: null;
            }
        } else { $sets[]='supplier_name=?'; $vals[]= null; }
    }
    if ($this->hasCol($pdo,'dms_stakeholders','line_manager_name')) {
        if ($line_manager_id > 0) {
            $q=$pdo->prepare("SELECT name FROM dms_stakeholders WHERE org_id=? AND id=?");
            $q->execute([$orgId,$line_manager_id]);
            $nm=(string)($q->fetchColumn() ?: '');
            $sets[]='line_manager_name=?'; $vals[]= $nm ?: null;
        } else { $sets[]='line_manager_name=?'; $vals[]= null; }
    }

    $vals[] = $orgId;
    $vals[] = $id;

    $sql = "UPDATE dms_stakeholders SET ".implode(',', $sets)." WHERE org_id=? AND id=? LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute($vals);

    $this->redirect($this->moduleBase($ctx).'/stakeholders/'.$id);
}

    /* ───────────────────────────── SR namespace ─────────────────────────── */
  
  
  /** GET /stakeholders/sr/create */
public function srCreate(array $ctx): void
{
    $pdo   = $this->pdo();
    $orgId = $this->orgId($ctx);

    // Preload dropdown options
    $supplierOptions = $this->listSuppliers($pdo, $orgId);  // [{id,name,code?}]
    $managerOptions  = $this->listManagers($pdo, $orgId);   // [{id,name,role}]

    // Standard select options used by the view
    $roleOptions   = [
        'sr'  => 'SR (Sales Representative)',
        'dsr' => 'DSR (Distributor SR)',
    ];
    $statusOptions = ['active' => 'Active', 'inactive' => 'Inactive'];
    $idTypes       = [
        'NID'             => 'National ID',
        'Passport'        => 'Passport',
        'Driving License' => 'Driving License',
        'Other'           => 'Other',
    ];

    // Restore previous input after a validation error (if any)
    $old = $_SESSION['form_old'] ?? [];
    unset($_SESSION['form_old']);

    $this->view('stakeholders/sr/create', [
        'title'            => 'Create SR/DSR',
        'supplierOptions'  => $supplierOptions,
        'managerOptions'   => $managerOptions,
        'roleOptions'      => $roleOptions,
        'statusOptions'    => $statusOptions,
        'idTypes'          => $idTypes,
        'old'              => $old,
        'active'           => 'stake',
        'subactive'        => 'stakeholders.sr.create',
        'module_base'      => $this->moduleBase($ctx),
    ], $ctx);
}
  
  

    /** GET /stakeholders/sr */
    public function srIndex(array $ctx): void
    {
        $pdo   = $this->pdo();
        $orgId = $this->orgId($ctx);
        $q     = trim((string)($_GET['q'] ?? ''));

        $sql  = "SELECT id, code, name, phone, email, role, territory, status
                   FROM dms_stakeholders
                  WHERE org_id=? AND role IN ('sr','dsr')";
        $args = [$orgId];

        if ($q !== '') {
            $like = "%$q%";
            $sql .= " AND (name LIKE ? OR phone LIKE ? OR code LIKE ? OR email LIKE ?)";
            array_push($args, $like, $like, $like, $like);
        }
        $sql .= " ORDER BY id DESC LIMIT 200";

        $st = $pdo->prepare($sql);
        $st->execute($args);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $this->view('stakeholders/sr/index', [
            'title'     => 'Sales Representatives',
            'rows'      => $rows,
            'q'         => $q,
            'active'    => 'stake',
            'subactive' => 'stakeholders.sr.index',
        ], $ctx);
    }

    
    /** POST /stakeholders/sr/create */
    public function srStore(array $ctx): void
    {
        $pdo   = $this->pdo();
        $orgId = $this->orgId($ctx);
        $base  = $this->moduleBase($ctx);

        if ($orgId <= 0) {
            $_SESSION['flash_errors'] = ['Organization context missing. Please re-open the app.'];
            header('Location: '.$base.'/stakeholders/sr/create'); exit;
        }

        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '') {
            $_SESSION['flash_errors'] = ['Name is required.'];
            $_SESSION['form_old']     = $_POST;
            header('Location: '.$base.'/stakeholders/sr/create'); exit;
        }

        $role   = in_array(strtolower((string)($_POST['role'] ?? 'sr')), ['sr','dsr'], true) ? strtolower((string)$_POST['role']) : 'sr';
        $phone  = trim((string)($_POST['phone'] ?? ''));
        $email  = trim((string)($_POST['email'] ?? ''));
        $terr   = trim((string)($_POST['territory'] ?? ''));
        $status = in_array(($_POST['status'] ?? 'active'), ['active','inactive'], true) ? $_POST['status'] : 'active';
        $notes  = trim((string)($_POST['notes'] ?? ''));

        $code = trim((string)($_POST['code'] ?? ''));
        if ($code === '') $code = $this->nextCode($pdo, $orgId, $role);

        $pdo->prepare("
            INSERT INTO dms_stakeholders
              (org_id, code, name, phone, email, role, territory, status, notes, created_at)
            VALUES (?,?,?,?,?,?,?,?,?, NOW())
        ")->execute([
            $orgId, $code, $name,
            $phone ?: null, $email ?: null, $role,
            $terr ?: null, $status, $notes ?: null
        ]);
        $_SESSION['flash_success'] = 'Stakeholder saved ('.$code.')';
        header('Location: '.$base.'/stakeholders/sr'); exit;
    }

    /* ───────────────────── Assignments (Customer/Supplier) ─────────────────── */

    /** GET /stakeholders/assign */
    public function assign(array $ctx): void
    {
        $this->view('stakeholders/assign', [
            'title'     => 'Assign Customers/Suppliers',
            'active'    => 'stake',
            'subactive' => 'stakeholders.assign',
        ], $ctx);
    }

    /** POST /stakeholders/assign */
    public function storeAssignment(array $ctx): void
    {
        $pdo   = $this->pdo();
        $orgId = $this->orgId($ctx);

        $stakeholder_id = (int)($_POST['stakeholder_id'] ?? 0);
        $customer_id    = (int)($_POST['customer_id'] ?? 0);
        $dealer_id      = (int)($_POST['dealer_id'] ?? 0); // supplier id in new schema
        $date           = (string)($_POST['assigned_on'] ?? date('Y-m-d'));
        $notes          = trim((string)($_POST['notes'] ?? ''));

        if ($stakeholder_id <= 0) $this->abort400('Stakeholder is required.');
        if ($customer_id <= 0 && $dealer_id <= 0) $this->abort400('Select a customer or a supplier.');

        $st = $pdo->prepare("
            INSERT IGNORE INTO dms_stakeholder_assignments
              (org_id, stakeholder_id, customer_id, dealer_id, assigned_on, notes, created_at)
            VALUES (?,?,?,?,?,?, NOW())
        ");
        $st->execute([$orgId, $stakeholder_id, $customer_id ?: null, $dealer_id ?: null, $date, $notes ?: null]);

        $this->redirect($this->moduleBase($ctx).'/stakeholders');
    }

    /* ─────────────────────── Route planning & mapping ────────────────────── */

    /** GET /stakeholders/route */
    public function route(array $ctx): void
    {
        $this->view('stakeholders/route', [
            'title'     => 'Route Planning',
            'active'    => 'stake',
            'subactive' => 'stakeholders.route',
        ], $ctx);
    }

    /** POST /stakeholders/route */
    public function storeRoute(array $ctx): void
    {
        $pdo   = $this->pdo();
        $orgId = $this->orgId($ctx);

        $name           = trim((string)($_POST['name'] ?? ''));
        $stakeholder_id = (int)($_POST['stakeholder_id'] ?? 0);
        if ($name === '' || $stakeholder_id <= 0) $this->abort400('Name and stakeholder required.');

        $pdo->beginTransaction();
        try {
            $h = $pdo->prepare("INSERT INTO dms_routes (org_id, stakeholder_id, name, active, created_at)
                                VALUES (?,?,?,?, NOW())");
            $h->execute([$orgId, $stakeholder_id, $name, 1]);
            $routeId = (int)$pdo->lastInsertId();

            // stops: [{customer_id?, dealer_id?, weekday?, notes?}]
            $stops = $_POST['stops'] ?? [];
            if (is_array($stops) && $stops) {
                $i = $pdo->prepare("
                    INSERT INTO dms_route_stops
                      (org_id, route_id, seq_no, customer_id, dealer_id, planned_weekday, notes)
                    VALUES (?,?,?,?,?,?,?)
                ");
                $seq = 1;
                foreach ($stops as $s) {
                    $i->execute([
                        $orgId, $routeId, $seq++,
                        (int)($s['customer_id'] ?? 0) ?: null,
                        (int)($s['dealer_id']   ?? 0) ?: null, // supplier id
                        (int)($s['weekday']     ?? 0) ?: null,
                        trim((string)($s['notes'] ?? '')) ?: null
                    ]);
                }
            }

            $pdo->commit();
            $this->redirect($this->moduleBase($ctx).'/stakeholders');
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $this->abort500($e);
        }
    }

    /** GET /stakeholders/mapping */
    public function mapping(array $ctx): void
    {
        $this->view('stakeholders/mapping', [
            'title'     => 'Route & Customer Mapping',
            'active'    => 'stake',
            'subactive' => 'stakeholders.mapping',
        ], $ctx);
    }

    /** POST /stakeholders/mapping */
    public function storeMapping(array $ctx): void
    {
        // Extend if you need to persist mapping info
        $this->redirect($this->moduleBase($ctx).'/stakeholders/mapping');
    }

    /* ───────────────────────────── Visit planning ────────────────────────── */

    /** GET /stakeholders/visit */
    public function visit(array $ctx): void
    {
        $pdo   = $this->pdo();
        $orgId = $this->orgId($ctx);

        $stakeholders = $this->listStakeholders($pdo, $orgId);
        $customers    = $this->listCustomers($pdo, $orgId);
        $suppliers    = $this->listSuppliers($pdo, $orgId);
        $visits       = $this->recentVisits($pdo, $orgId);

        $this->view('stakeholders/visit', [
            'title'        => 'Visit Plan',
            'stakeholders' => $stakeholders,
            'customers'    => $customers,
            'suppliers'    => $suppliers,
            'visits'       => $visits,
            'active'       => 'stake',
            'subactive'    => 'stakeholders.visit',
        ], $ctx);
    }

    /** POST /stakeholders/visit */
    public function storeVisit(array $ctx): void
    {
        $pdo   = $this->pdo();
        $orgId = $this->orgId($ctx);
        $base  = $this->moduleBase($ctx);

        $stakeholder_id = (int)($_POST['stakeholder_id'] ?? 0);
        $visit_date     = (string)($_POST['visit_date'] ?? date('Y-m-d'));
        $customer_id    = (int)($_POST['customer_id'] ?? 0);
        $dealer_id      = (int)($_POST['dealer_id'] ?? 0);
        $status         = strtolower((string)($_POST['status'] ?? 'planned'));
        $notes          = trim((string)($_POST['notes'] ?? ''));

        $_SESSION['form_old'] = $_POST;

        if ($stakeholder_id <= 0) {
            $_SESSION['flash_errors'] = ['Stakeholder is required.'];
            header('Location: '.$base.'/stakeholders/visit'); exit;
        }
        if ($customer_id <= 0 && $dealer_id <= 0) {
            $_SESSION['flash_errors'] = ['Select a customer or a supplier.'];
            header('Location: '.$base.'/stakeholders/visit'); exit;
        }
        if (!in_array($status, ['planned','done','missed','cancelled'], true)) {
            $status = 'planned';
        }
        if (!$this->hasTable($pdo, 'dms_visit_plans')) {
            $_SESSION['flash_errors'] = ['Visit plan table (dms_visit_plans) not found.'];
            header('Location: '.$base.'/stakeholders/visit'); exit;
        }

        $st = $pdo->prepare("
            INSERT INTO dms_visit_plans
              (org_id, stakeholder_id, visit_date, customer_id, dealer_id, status, notes, created_at)
            VALUES (?,?,?,?,?,?,?, NOW())
        ");
        $st->execute([
            $orgId, $stakeholder_id, $visit_date,
            $customer_id ?: null, $dealer_id ?: null, $status, $notes ?: null
        ]);

        unset($_SESSION['form_old']);
        $_SESSION['flash_success'] = 'Visit saved.';
        header('Location: '.$base.'/stakeholders/visit'); exit;
    }

    /* ───────────────────────────── Performance ───────────────────────────── */

    /**
     * GET /stakeholders/performance
     * Lightweight KPIs for visits and sales.
     */
    public function performance(array $ctx): void
    {
        $pdo   = $this->pdo();
        $orgId = $this->orgId($ctx);

        // SR/DSR list
        $sr = $pdo->prepare("SELECT id, code, name, role, territory, status
                               FROM dms_stakeholders
                              WHERE org_id=? AND role IN ('sr','dsr')
                              ORDER BY name ASC");
        $sr->execute([$orgId]);
        $stakeholders = $sr->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $kpis = [
            'visits' => [],   // [stakeholder_id => ['30'=>[...], '90'=>[...]]]
            'sales'  => [],   // [stakeholder_id => ['30'=>amount,'90'=>amount]]
        ];

        // Visit KPIs
        if ($this->hasTable($pdo, 'dms_visit_plans')) {
            foreach ([30, 90] as $days) {
                $vp = $pdo->prepare("
                    SELECT stakeholder_id,
                           SUM(status='planned')   AS planned,
                           SUM(status='done')      AS done,
                           SUM(status='missed')    AS missed,
                           SUM(status='cancelled') AS cancelled
                      FROM dms_visit_plans
                     WHERE org_id=? AND visit_date >= (CURRENT_DATE - INTERVAL {$days} DAY)
                     GROUP BY stakeholder_id
                ");
                $vp->execute([$orgId]);
                foreach ($vp->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $sid = (int)$row['stakeholder_id'];
                    $kpis['visits'][$sid] = $kpis['visits'][$sid] ?? [];
                    $kpis['visits'][$sid][(string)$days] = [
                        'planned'   => (int)($row['planned']   ?? 0),
                        'done'      => (int)($row['done']      ?? 0),
                        'missed'    => (int)($row['missed']    ?? 0),
                        'cancelled' => (int)($row['cancelled'] ?? 0),
                    ];
                }
            }
        }

        // Sales totals by stakeholder if columns/tables exist
        if ($this->hasTable($pdo, 'dms_sales')) {
            $hasStakeCol = $pdo->query("SHOW COLUMNS FROM dms_sales LIKE 'stakeholder_id'")->fetch(PDO::FETCH_ASSOC);
            $hasUserCol  = $pdo->query("SHOW COLUMNS FROM dms_sales LIKE 'user_id'")->fetch(PDO::FETCH_ASSOC);
            $idColumn    = $hasStakeCol ? 'stakeholder_id' : ($hasUserCol ? 'user_id' : null);

            if ($idColumn) {
                foreach ([30, 90] as $days) {
                    $stmt = $pdo->prepare("
                        SELECT {$idColumn} AS sid, SUM(grand_total) AS amount
                          FROM dms_sales
                         WHERE org_id=? AND created_at >= (NOW() - INTERVAL {$days} DAY)
                         GROUP BY {$idColumn}
                    ");
                    $stmt->execute([$orgId]);
                    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                        $sid = (int)($row['sid'] ?? 0);
                        if ($sid <= 0) continue;
                        $kpis['sales'][$sid] = $kpis['sales'][$sid] ?? [];
                        $kpis['sales'][$sid][(string)$days] = (float)($row['amount'] ?? 0.0);
                    }
                }
            }
        }

        $this->view('stakeholders/performance', [
            'title'        => 'Stakeholder Performance',
            'stakeholders' => $stakeholders,
            'kpis'         => $kpis,
            'active'       => 'stake',
            'subactive'    => 'stakeholders.performance',
        ], $ctx);
    }
}