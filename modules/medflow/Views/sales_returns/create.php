<?php $h = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); ?>
<div class="max-w-md mx-auto p-6">
  <h1 class="text-xl font-semibold mb-3">Start a Return</h1>
  <form method="post" action="<?= $h(($module_base ?? '').'/sales/returns') ?>" class="space-y-4">
    <div>
      <label class="block text-sm font-medium mb-1">Sale ID</label>
      <input type="number" name="sale_id" min="1" required
             class="w-full border rounded-lg px-3 py-2 dark:bg-gray-900 dark:border-gray-700"
             placeholder="Enter Sale ID">
      <p class="text-xs text-gray-500 mt-1">We’ll create a draft return for this sale.</p>
    </div>
    <button class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-white" style="background:#10b981">
      <i class="fa fa-check"></i><span>Create</span>
    </button>
  </form>
</div>