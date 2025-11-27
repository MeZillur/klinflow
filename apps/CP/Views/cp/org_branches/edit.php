<?php
/** @var array $org */
/** @var array $branch */
/** @var string $csrf */
/** @var ?string $error */
$h = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>

<div class="max-w-3xl mx-auto p-6 space-y-6">

  <div class="flex items-center justify-between">
    <h1 class="text-2xl font-semibold text-gray-900">
      Edit Branch — <?= $h($org['name']) ?>
    </h1>
  </div>

  <?php if (!empty($error)): ?>
    <div class="bg-red-100 text-red-700 text-sm px-4 py-2 rounded">
      <?= nl2br($h($error)) ?>
    </div>
  <?php endif; ?>

  <form method="POST"
        action="/cp/organizations/<?= (int)$org['id'] ?>/branches/<?= (int)$branch['id'] ?>/update"
        class="bg-white border rounded-xl shadow-sm p-6 space-y-5">
    <input type="hidden" name="_csrf" value="<?= $h($csrf) ?>">

    <div>
      <label class="block text-sm font-medium mb-1">Branch Code</label>
      <input type="text"
             name="code"
             value="<?= $h($branch['code']) ?>"
             class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#228B22]"
             required>
    </div>

    <div>
      <label class="block text-sm font-medium mb-1">Branch Name</label>
      <input type="text"
             name="name"
             value="<?= $h($branch['name']) ?>"
             class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#228B22]"
             required>
    </div>

    <div>
      <label class="inline-flex items-center gap-2 text-sm">
        <input type="checkbox"
               name="is_active"
               value="1"
               <?= !empty($branch['is_active']) ? 'checked' : '' ?>
               class="rounded border-gray-300">
        <span>Active branch</span>
      </label>
    </div>

    <div class="pt-4">
      <button type="submit"
              class="px-5 py-2.5 rounded-xl bg-[#228B22] text-white text-sm font-medium shadow hover:bg-green-700">
        Save Changes
      </button>
      <a href="/cp/organizations/<?= (int)$org['id'] ?>/branches"
         class="ml-3 text-sm text-gray-600 hover:underline">
        Cancel
      </a>
    </div>
  </form>
</div>