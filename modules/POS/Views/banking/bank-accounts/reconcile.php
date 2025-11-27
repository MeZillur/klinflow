<div class="max-w-3xl mx-auto p-6">
  <h1 class="text-2xl font-bold mb-4">Bank Reconciliation</h1>
  <p class="text-sm text-gray-600 mb-4">Upload a statement or start a reconciliation session. (Stub UI)</p>
  <form method="post" action="<?= $base ?>/banking/reconcile" enctype="multipart/form-data" class="space-y-3">
    <input type="file" name="statement" class="border rounded p-2 w-full">
    <button class="px-4 py-2 rounded bg-emerald-600 text-white">Start</button>
  </form>
</div>