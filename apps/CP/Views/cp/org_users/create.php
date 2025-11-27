<?php
/** @var array $org */
/** @var array $branches */
/** @var array $old */
/** @var string $csrf */
/** @var ?string $error */
$h = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>

<div class="max-w-3xl mx-auto p-6 space-y-6">

  <!-- Header -->
  <div class="flex items-center justify-between">
    <h1 class="text-2xl font-semibold text-gray-800">
      Add Branch User — <?= $h($org['name']) ?>
    </h1>
  </div>

  <!-- Error Alert -->
  <?php if (!empty($error)): ?>
    <div class="bg-red-100 text-red-700 px-4 py-2 rounded">
      <?= nl2br($h($error)) ?>
    </div>
  <?php endif; ?>

  <!-- Form -->
  <form method="POST" class="bg-white border rounded-xl p-6 shadow-sm space-y-5">
    <input type="hidden" name="_csrf" value="<?= $h($csrf) ?>">

    <!-- Name -->
    <div>
      <label class="block text-sm font-medium mb-1">Full Name</label>
      <input type="text" name="name"
             value="<?= $h($old['name'] ?? '') ?>"
             class="kf-input w-full" required>
    </div>

    <!-- Email -->
    <div>
      <label class="block text-sm font-medium mb-1">Email</label>
      <input type="email" name="email"
             value="<?= $h($old['email'] ?? '') ?>"
             class="kf-input w-full" required>
    </div>

    <!-- Username -->
    <div>
      <label class="block text-sm font-medium mb-1">Username (optional)</label>
      <input type="text" name="username"
             value="<?= $h($old['username'] ?? '') ?>"
             class="kf-input w-full">
      <p class="text-xs text-gray-500 mt-1">
        If empty, username will auto-generate from email.
      </p>
    </div>

    <!-- Mobile -->
    <div>
      <label class="block text-sm font-medium mb-1">Mobile (optional)</label>
      <input type="text" name="mobile"
             value="<?= $h($old['mobile'] ?? '') ?>"
             class="kf-input w-full">
    </div>

    <!-- Role -->
    <div>
      <label class="block text-sm font-medium mb-1">Role</label>
      <select name="role" class="kf-input w-full">
        <?php
        $roles = ['owner'=>'Owner','admin'=>'Admin','member'=>'User','branch_staff'=>'Branch Staff'];
        $sel = $old['role'] ?? 'member';
        ?>
        <?php foreach ($roles as $k=>$v): ?>
          <option value="<?= $k ?>" <?= $sel===$k?'selected':'' ?>>
            <?= $v ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Branches -->
    <div>
      <label class="block text-sm font-medium mb-1">Assign Branches *</label>

      <?php if (!$branches): ?>
        <p class="text-gray-500 text-sm">No branches created yet.</p>
      <?php else: ?>
        <div class="space-y-2">
          <?php
          $oldSel = (array)($old['branch_ids'] ?? []);
          ?>
          <?php foreach ($branches as $b): ?>
            <label class="flex items-center gap-2">
              <input type="checkbox"
                     name="branch_ids[]"
                     value="<?= (int)$b['id'] ?>"
                     <?= in_array($b['id'], $oldSel)?'checked':'' ?>
                     class="rounded border-gray-300">
              <span><?= $h($b['name']) ?> (<?= $h($b['code']) ?>)</span>
            </label>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Status -->
    <div>
      <label class="flex items-center gap-2">
        <input type="checkbox" name="is_active"
               value="1"
               <?= !empty($old['active'])?'checked':'' ?>
               class="rounded border-gray-300">
        <span>Active user</span>
      </label>
    </div>

    <!-- Submit -->
    <div class="pt-4">
      <button class="px-6 py-2.5 bg-[#228B22] text-white rounded-xl shadow hover:bg-green-700">
        Create Branch User
      </button>

      <a href="/cp/organizations/<?= (int)$org['id'] ?>/users"
         class="ml-3 text-gray-600 hover:underline">
         Cancel
      </a>
    </div>

  </form>

</div>