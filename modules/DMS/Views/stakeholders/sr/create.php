<?php
declare(strict_types=1);

/** minimal HTML escaper */
if (!function_exists('h')) {
  function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

$isEdit  = isset($s) && is_array($s);
$action  = $module_base . '/stakeholders' . ($isEdit ? '/'.(int)$s['id'] : '');
$title   = $isEdit ? 'Edit Stakeholder' : 'Create Stakeholder';

/**
 * Expected (optional) arrays from controller:
 * - $supplierOptions = [ ['id'=>1,'name'=>'Parul Agro Inc','code'=>'SUP-2025-0001'], ... ]
 * - $managerOptions  = [ ['id'=>9,'name'=>'Anwar Hossain','role'=>'Supervisor'], ... ]
 * They are safe to omit; the view will still render with a single current option.
 */
$roleOptions = [
  'sr'           => 'SR (Sales Representative)',
  'dsr'          => 'DSR (Distributor SR)',
  'supervisor'   => 'Supervisor',
  'manager'      => 'Sales Manager',
  'merchandiser' => 'Merchandiser',
];
$statusOptions = ['active' => 'Active', 'inactive' => 'Inactive'];
$idTypes = ['NID' => 'National ID', 'Passport' => 'Passport', 'Driving License' => 'Driving License', 'Other' => 'Other'];

/** helpers to render option lists safely */
$renderSupplierOptions = function(array $opts, $currentId, $currentName): string {
  if (!$opts) {
    $label = $currentName ? $currentName : ($currentId ? ('#'.$currentId) : '— Select —');
    $idVal = $currentId ?: '';
    return '<option value="'.h($idVal).'" selected>'.h($label).'</option>';
  }
  $html = '<option value="">— Select supplier —</option>';
  foreach ($opts as $r) {
    $id   = (int)($r['id'] ?? 0);
    $name = trim((string)($r['name'] ?? ''));
    $code = trim((string)($r['code'] ?? ''));
    $label = $code ? "{$name} ({$code})" : $name;
    $sel = ((int)$currentId === $id) ? ' selected' : '';
    $html .= '<option value="'.h($id).'" data-name="'.h($name).'"'.$sel.'>'.h($label).'</option>';
  }
  return $html;
};

$renderManagerOptions = function(array $opts, $currentId, $currentName): string {
  if (!$opts) {
    $label = $currentName ? $currentName : ($currentId ? ('#'.$currentId) : '— Select —');
    $idVal = $currentId ?: '';
    return '<option value="'.h($idVal).'" selected>'.h($label).'</option>';
  }
  $html = '<option value="">— Select line manager —</option>';
  foreach ($opts as $r) {
    $id   = (int)($r['id'] ?? 0);
    $name = trim((string)($r['name'] ?? ''));
    $role = trim((string)($r['role'] ?? ''));
    $label = $role ? "{$name} — {$role}" : $name;
    $sel = ((int)$currentId === $id) ? ' selected' : '';
    $html .= '<option value="'.h($id).'" data-name="'.h($name).'"'.$sel.'>'.h($label).'</option>';
  }
  return $html;
};

$curSupplierId   = (int)($s['supplier_id'] ?? 0);
$curSupplierName = (string)($s['supplier_name'] ?? '');
$curManagerId    = (int)($s['line_manager_id'] ?? 0);
$curManagerName  = (string)($s['line_manager_name'] ?? '');
?>
<h1 class="text-xl font-semibold mb-4"><?= h($title) ?></h1>

<form method="POST" action="<?= h($action) ?>" enctype="multipart/form-data" class="space-y-8"
      x-data="{
        supplierName: '<?= h($curSupplierName) ?>',
        managerName:  '<?= h($curManagerName) ?>',
        syncSupplier(e){ const o=e.target.selectedOptions[0]; this.supplierName = o?.dataset?.name || ''; },
        syncManager(e){ const o=e.target.selectedOptions[0]; this.managerName  = o?.dataset?.name || ''; }
      }">

  <!-- Header block -->
  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div>
      <label class="block text-sm font-medium mb-1">Code</label>
      <input name="code" value="<?= h($s['code'] ?? '') ?>" placeholder="Auto if blank"
             class="w-full rounded-lg border px-3 py-2 bg-slate-50"
             readonly>
      <p class="text-xs text-slate-500 mt-1">Auto format: SR/DSR-YYYY-00001</p>
    </div>

    <div>
      <label class="block text-sm font-medium mb-1">Role</label>
      <select name="role" class="w-full rounded-lg border px-3 py-2">
        <?php
          $curRole = strtolower($s['role'] ?? 'sr');
          foreach ($roleOptions as $val => $label) {
            $sel = $curRole === $val ? 'selected' : '';
            echo "<option value='".h($val)."' $sel>".h($label)."</option>";
          }
        ?>
      </select>
    </div>

    <div>
      <label class="block text-sm font-medium mb-1">Status</label>
      <select name="status" class="w-full rounded-lg border px-3 py-2">
        <?php
          $curStatus = $s['status'] ?? 'active';
          foreach ($statusOptions as $val => $label) {
            $sel = $curStatus === $val ? 'selected' : '';
            echo "<option value='".h($val)."' $sel>".h($label)."</option>";
          }
        ?>
      </select>
    </div>
  </div>

  <!-- Basic info -->
  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div>
      <label class="block text-sm font-medium mb-1">Full Name <span class="text-rose-600">*</span></label>
      <input name="name" value="<?= h($s['name'] ?? '') ?>" required class="w-full rounded-lg border px-3 py-2">
    </div>
    <div>
      <label class="block text-sm font-medium mb-1">Phone</label>
      <input name="phone" value="<?= h($s['phone'] ?? '') ?>" class="w-full rounded-lg border px-3 py-2">
    </div>
    <div>
      <label class="block text-sm font-medium mb-1">Email</label>
      <input name="email" type="email" value="<?= h($s['email'] ?? '') ?>" class="w-full rounded-lg border px-3 py-2">
    </div>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div>
      <label class="block text-sm font-medium mb-1">Territory</label>
      <input name="territory" value="<?= h($s['territory'] ?? '') ?>" class="w-full rounded-lg border px-3 py-2">
    </div>
    <div class="md:col-span-2">
      <label class="block text-sm font-medium mb-1">Notes</label>
      <input name="notes" value="<?= h($s['notes'] ?? '') ?>" class="w-full rounded-lg border px-3 py-2">
    </div>
  </div>

  <!-- Supplier linkage + monthly target (DROPDOWN) -->
  <fieldset class="border rounded-xl p-4">
    <legend class="px-2 text-sm font-semibold text-slate-700">Supplier & Target</legend>
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-2">
      <div class="md:col-span-2">
        <label class="block text-sm font-medium mb-1">Supplier</label>
        <select name="supplier_id" class="w-full rounded-lg border px-3 py-2"
                @change="syncSupplier($event)">
          <?= $renderSupplierOptions($supplierOptions ?? [], $curSupplierId, $curSupplierName) ?>
        </select>
        <!-- Keep supplier_name in sync for server (display & reporting convenience) -->
        <input type="hidden" name="supplier_name" :value="supplierName">
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Monthly Target</label>
        <input name="monthly_target" type="number" min="0" step="0.01"
               value="<?= h($s['monthly_target'] ?? '') ?>"
               class="w-full rounded-lg border px-3 py-2" placeholder="e.g. 200000.00">
      </div>
    </div>
  </fieldset>

  <!-- Line manager (DROPDOWN) -->
  <fieldset class="border rounded-xl p-4">
    <legend class="px-2 text-sm font-semibold text-slate-700">Line Manager</legend>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-2">
      <div class="md:col-span-2">
        <label class="block text-sm font-medium mb-1">Manager</label>
        <select name="line_manager_id" class="w-full rounded-lg border px-3 py-2"
                @change="syncManager($event)">
          <?= $renderManagerOptions($managerOptions ?? [], $curManagerId, $curManagerName) ?>
        </select>
        <input type="hidden" name="line_manager_name" :value="managerName">
      </div>
    </div>
  </fieldset>

  <!-- Emergency contact -->
  <fieldset class="border rounded-xl p-4">
    <legend class="px-2 text-sm font-semibold text-slate-700">Emergency Contact</legend>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-2">
      <div>
        <label class="block text-sm font-medium mb-1">Contact Name</label>
        <input name="emergency_contact_name"
               value="<?= h($s['emergency_contact_name'] ?? '') ?>"
               class="w-full rounded-lg border px-3 py-2">
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Phone</label>
        <input name="emergency_contact_phone"
               value="<?= h($s['emergency_contact_phone'] ?? '') ?>"
               class="w-full rounded-lg border px-3 py-2">
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Relation</label>
        <input name="emergency_contact_relation"
               value="<?= h($s['emergency_contact_relation'] ?? '') ?>"
               class="w-full rounded-lg border px-3 py-2" placeholder="e.g. Spouse, Parent">
      </div>
    </div>
  </fieldset>

  <!-- Identity proof + photo -->
  <fieldset class="border rounded-xl p-4">
    <legend class="px-2 text-sm font-semibold text-slate-700">Identity & Photo</legend>
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-2">
      <div>
        <label class="block text-sm font-medium mb-1">ID Type</label>
        <select name="id_proof_type" class="w-full rounded-lg border px-3 py-2">
          <?php
            $curType = $s['id_proof_type'] ?? '';
            foreach ($idTypes as $val => $label) {
              $sel = $curType === $val ? 'selected' : '';
              echo "<option value='".h($val)."' $sel>".h($label)."</option>";
            }
          ?>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">ID Number</label>
        <input name="id_proof_no" value="<?= h($s['id_proof_no'] ?? '') ?>"
               class="w-full rounded-lg border px-3 py-2">
      </div>
      <div class="md:col-span-2">
        <label class="block text-sm font-medium mb-1">Upload ID (PDF/JPG/PNG)</label>
        <input type="file" name="id_proof_file" accept=".pdf,.jpg,.jpeg,.png"
               class="w-full rounded-lg border px-3 py-2 bg-white">
        <?php if (!empty($s['id_proof_path'])): ?>
          <input type="hidden" name="id_proof_path_existing" value="<?= h($s['id_proof_path']) ?>">
          <p class="text-xs mt-1">
            Current: <a class="text-emerald-700 underline" target="_blank" href="<?= h($s['id_proof_path']) ?>">view</a>
          </p>
        <?php endif; ?>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
      <div>
        <label class="block text-sm font-medium mb-1">Profile Photo (JPG/PNG)</label>
        <input type="file" name="photo_file" accept=".jpg,.jpeg,.png"
               class="w-full rounded-lg border px-3 py-2 bg-white">
        <?php if (!empty($s['photo_path'])): ?>
          <input type="hidden" name="photo_path_existing" value="<?= h($s['photo_path']) ?>">
          <p class="text-xs mt-1">
            Current: <a class="text-emerald-700 underline" target="_blank" href="<?= h($s['photo_path']) ?>">view</a>
          </p>
        <?php endif; ?>
      </div>
      <div class="text-xs text-slate-500 self-end">
        Uploads are stored under:<br>
        <code>modules/DMS/storage/uploads/images/<?= h($org['id'] ?? '{orgId}') ?>/stakeholders/<?= h($s['id'] ?? '{id}') ?>/</code><br>
        <span class="block mt-1">Files will be named like <code>photo.jpg</code> and <code>id_card.jpg</code> (or <code>.pdf</code>).</span>
      </div>
    </div>
  </fieldset>

  <!-- Footer actions -->
  <div class="flex justify-end gap-2">
    <a href="<?= h($module_base) ?>/stakeholders" class="px-3 py-2 rounded-lg border">Cancel</a>
    <button class="px-4 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">
      <?= $isEdit ? 'Update' : 'Save' ?>
    </button>
  </div>
</form>