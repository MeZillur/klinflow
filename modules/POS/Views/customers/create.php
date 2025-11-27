<?php
declare(strict_types=1);
/** @var string $base */
$h   = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$old = $_SESSION['pos_old']    ?? [];
$err = $_SESSION['pos_errors'] ?? [];
unset($_SESSION['pos_old'], $_SESSION['pos_errors']);

$defaultCode = $old['code'] ?? ('CUS-'.date('Y').'-00001');
?>
<section class="max-w-5xl mx-auto px-4 sm:px-6 py-4 sm:py-6">
  <div class="flex items-center justify-between mb-4 sm:mb-6">
    <div>
      <h1 class="text-xl sm:text-2xl font-semibold">New Customer</h1>
      <p class="text-xs text-gray-500 dark:text-slate-400 mt-1">
        Fields marked with <span class="text-red-500">*</span> are required.
      </p>
    </div>
    <a href="<?= $h($base) ?>/customers" class="btn btn-outline">
      Back to Customers
    </a>
  </div>

  <div class="card">
    <form
      method="post"
      action="<?= $h($base) ?>/customers"
      class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6"
      id="customer-create-form"
    >
      <!-- Code (auto-generated) -->
      <div>
        <label class="block text-sm font-medium mb-1">
          Code
          <span class="text-xs text-gray-400 dark:text-slate-500">(auto)</span>
        </label>
        <div class="flex gap-2 items-center">
          <input
            id="cust-code"
            name="code"
            value="<?= $h($defaultCode) ?>"
            class="input"
          >
          <button type="button" id="cust-code-regenerate" class="btn btn-outline text-xs px-2 py-1">
            Regenerate
          </button>
        </div>
        <?php if (isset($err['code'])): ?>
          <p class="text-xs text-red-500 mt-1"><?= $h($err['code']) ?></p>
        <?php endif; ?>
      </div>

      <!-- Name -->
      <div>
        <label class="block text-sm font-medium mb-1">
          Name <span class="text-red-500">*</span>
        </label>
        <input
          name="name"
          value="<?= $h($old['name'] ?? '') ?>"
          class="input"
          required
        >
        <?php if (isset($err['name'])): ?>
          <p class="text-xs text-red-500 mt-1"><?= $h($err['name']) ?></p>
        <?php endif; ?>
      </div>

      <!-- Phone / Mobile -->
      <div>
        <label class="block text-sm font-medium mb-1">
          Phone / Mobile <span class="text-red-500">*</span>
        </label>
        <input
          name="phone"
          value="<?= $h($old['phone'] ?? '') ?>"
          class="input"
          required
        >
      </div>

      <!-- Email -->
      <div>
        <label class="block text-sm font-medium mb-1">
          Email
        </label>
        <input
          type="email"
          name="email"
          value="<?= $h($old['email'] ?? '') ?>"
          class="input"
        >
        <?php if (isset($err['email'])): ?>
          <p class="text-xs text-red-500 mt-1"><?= $h($err['email']) ?></p>
        <?php endif; ?>
      </div>

      <!-- Country -->
      <div>
        <label class="block text-sm font-medium mb-1">
          Country <span class="text-red-500">*</span>
        </label>
        <input
          name="country"
          value="<?= $h($old['country'] ?? '') ?>"
          class="input"
          required
        >
      </div>

      <!-- City -->
      <div>
        <label class="block text-sm font-medium mb-1">
          City <span class="text-red-500">*</span>
        </label>
        <input
          name="city"
          value="<?= $h($old['city'] ?? '') ?>"
          class="input"
          required
        >
      </div>

      <!-- Address -->
      <div class="md:col-span-2">
        <label class="block text-sm font-medium mb-1">
          Address
        </label>
        <input
          name="address"
          value="<?= $h($old['address'] ?? '') ?>"
          class="input"
        >
      </div>

      <!-- Notes -->
      <div class="md:col-span-2">
        <label class="block text-sm font-medium mb-1">
          Notes
        </label>
        <textarea
          name="notes"
          rows="3"
          class="input"
        ><?= $h($old['notes'] ?? '') ?></textarea>
      </div>

      <!-- Active -->
      <div class="md:col-span-2">
        <label class="inline-flex items-center gap-2 text-sm">
          <input
            type="checkbox"
            name="is_active"
            value="1"
            class="rounded border-gray-300 text-emerald-600"
            <?= !isset($old['is_active']) || $old['is_active'] ? 'checked' : '' ?>
          >
          <span>Active</span>
        </label>
      </div>

      <!-- Actions -->
      <div class="md:col-span-2 flex flex-wrap gap-3 pt-2">
        <button type="submit" class="btn btn-primary">
          Save Customer
        </button>
        <a href="<?= $h($base) ?>/customers" class="btn btn-outline">
          Cancel
        </a>
      </div>
    </form>
  </div>
</section>

<script>
(function() {
  const input  = document.getElementById('cust-code');
  const regen  = document.getElementById('cust-code-regenerate');

  if (!input || !regen) return;

  function generateCode() {
    const year = new Date().getFullYear();
    // Simple client-side sequence seed based on time (server can override if needed)
    const n = String(Math.floor(Date.now() / 1000) % 100000).padStart(5, '0');
    return `CUS-${year}-${n}`;
  }

  // If empty (or whitespace) on first load, populate default pattern
  if (input.value.trim() === '') {
    input.value = generateCode();
  }

  regen.addEventListener('click', function() {
    input.value = generateCode();
    input.focus();
    input.select();
  }, false);
})();
</script>