<?php
/** @var array $org */
/** @var array $branches */
/** @var string $csrf */
/** @var ?string $error */
$h = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>

<div class="max-w-5xl mx-auto p-6 space-y-6">

  <!-- Header -->
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-semibold text-gray-900">
        Branches — <?= $h($org['name']) ?>
      </h1>
      <p class="text-sm text-gray-500 mt-1">
        Manage physical locations for this organization. Branch users can be assigned per branch.
      </p>
    </div>

    <a href="/cp/organizations/<?= (int)$org['id'] ?>/branches/create"
       class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-[#228B22] text-white text-sm font-medium shadow hover:bg-green-700">
      <i class="fa fa-code-branch"></i>
      New Branch
    </a>
  </div>

  <!-- Error -->
  <?php if (!empty($error)): ?>
    <div class="bg-red-100 text-red-700 text-sm px-4 py-2 rounded">
      <?= nl2br($h($error)) ?>
    </div>
  <?php endif; ?>

  <!-- Table -->
  <div class="bg-white border rounded-xl shadow-sm overflow-hidden">
    <div class="px-4 py-3 border-b flex items-center justify-between">
      <div class="font-semibold text-gray-800">Branches</div>
      <a href="/cp/organizations/<?= (int)$org['id'] ?>/users"
         class="text-sm text-[#228B22] hover:underline">
        Manage branch users
      </a>
    </div>

    <?php if (!$branches): ?>
      <div class="px-4 py-6 text-sm text-gray-500">
        No branches created yet. Click “New Branch” to create the first one.
      </div>
    <?php else: ?>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 border-b text-gray-500">
            <tr>
              <th class="px-4 py-2 text-left">Code</th>
              <th class="px-4 py-2 text-left">Name</th>
              <th class="px-4 py-2 text-left">Status</th>
              <th class="px-4 py-2 text-left">Created</th>
              <th class="px-4 py-2 text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($branches as $b): ?>
              <tr class="border-b last:border-b-0 hover:bg-gray-50">
                <td class="px-4 py-2 font-mono text-xs text-gray-800">
                  <?= $h($b['code']) ?>
                </td>
                <td class="px-4 py-2 text-gray-800 font-medium">
                  <?= $h($b['name']) ?>
                </td>
                <td class="px-4 py-2">
                  <?php if (!empty($b['is_active'])): ?>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-green-100 text-green-700 text-xs">
                      active
                    </span>
                  <?php else: ?>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-red-100 text-red-700 text-xs">
                      inactive
                    </span>
                  <?php endif; ?>
                </td>
                <td class="px-4 py-2 text-xs text-gray-500 whitespace-nowrap">
                  <?= $h($b['created_at'] ?? '') ?>
                </td>
                <td class="px-4 py-2 text-right whitespace-nowrap space-x-2">
                  <a href="/cp/organizations/<?= (int)$org['id'] ?>/branches/<?= (int)$b['id'] ?>/edit"
                     class="text-sm text-blue-600 hover:underline">Edit</a>

                  <form action="/cp/organizations/<?= (int)$org['id'] ?>/branches/<?= (int)$b['id'] ?>/delete"
                        method="POST"
                        class="inline-block"
                        onsubmit="return confirm('Delete this branch? Users linked to it will block deletion.');">
                    <input type="hidden" name="_csrf" value="<?= $h($csrf) ?>">
                    <button type="submit"
                            class="text-sm text-red-600 hover:underline">
                      Delete
                    </button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>