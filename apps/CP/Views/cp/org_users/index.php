<?php
/** @var array $org */
/** @var array $branches */
/** @var array $users */
/** @var string $csrf */
$h = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>

<div class="max-w-7xl mx-auto p-6 space-y-6">

  <!-- Header -->
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-semibold text-gray-800">
        Branches & Users — <?= $h($org['name']) ?>
      </h1>
      <p class="text-gray-500 text-sm mt-1">
        Manage all branch locations and their assigned users.
      </p>
    </div>

    <a href="/cp/organizations/<?= (int)$org['id'] ?>/users/create"
       class="inline-flex items-center gap-2 bg-[#228B22] text-white px-5 py-2.5 rounded-xl shadow-sm hover:bg-green-700 transition">
       <i class="fa fa-user-plus"></i>
       Add Branch User
    </a>
  </div>

  <!-- Branch List -->
  <div class="bg-white border rounded-xl p-5 shadow-sm">
    <h2 class="text-lg font-semibold mb-3">Branches</h2>

    <?php if (!$branches): ?>
      <p class="text-gray-500 text-sm">No branches created yet.</p>
    <?php else: ?>
      <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php foreach ($branches as $b): ?>
          <div class="border rounded-lg p-4 bg-gray-50">
            <div class="font-semibold text-gray-800"><?= $h($b['name']) ?></div>
            <div class="text-sm text-gray-600"><?= $h($b['code']) ?></div>
            <div class="mt-1 text-xs rounded px-2 py-0.5 inline-block
                        <?= $b['is_active'] ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
              <?= $b['is_active'] ? 'Active' : 'Inactive' ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Users -->
  <div class="bg-white border rounded-xl p-5 shadow-sm">
    <h2 class="text-lg font-semibold mb-3">Branch Users</h2>

    <?php if (!$users): ?>
      <p class="text-gray-500">No users found for this organization.</p>
    <?php else: ?>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="text-left bg-gray-100 border-b">
              <th class="p-3">Name</th>
              <th class="p-3">Email</th>
              <th class="p-3">Role</th>
              <th class="p-3">Branches</th>
              <th class="p-3">Status</th>
              <th class="p-3">Created</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $u): ?>
              <tr class="border-b hover:bg-gray-50">
                <td class="p-3 font-medium text-gray-800"><?= $h($u['name']) ?></td>
                <td class="p-3 text-gray-700"><?= $h($u['email']) ?></td>
                <td class="p-3 text-gray-700"><?= $h($u['role']) ?></td>
                <td class="p-3 text-gray-700">
                  <?= $u['branch_labels'] ? $h($u['branch_labels']) : '—' ?>
                </td>
                <td class="p-3">
                  <?php if ($u['is_active']): ?>
                    <span class="bg-green-100 text-green-700 text-xs px-2 py-0.5 rounded">Active</span>
                  <?php else: ?>
                    <span class="bg-red-100 text-red-700 text-xs px-2 py-0.5 rounded">Inactive</span>
                  <?php endif; ?>
                </td>
                <td class="p-3 text-gray-500 text-xs"><?= $h($u['created_at']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

  </div>

</div>