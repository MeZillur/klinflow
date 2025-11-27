<?php
/** @var array $rows @var array $filters @var int $total @var int $page @var int $limit @var string $module_base */
$h=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
$base=rtrim((string)($module_base??'/apps/hotelflow'),'/');
$q=(string)($filters['q']??'');
?>
<div class="max-w-[1100px] mx-auto" x-data="{
  modal:false, cardHtml:'', async openCard(id){
    this.modal=true; this.cardHtml='<div class=\'p-6 text-sm text-slate-500\'>Loading…</div>';
    try{ const r=await fetch('<?= $h($base) ?>/guests/'+id+'/card'); this.cardHtml=await r.text(); }
    catch(e){ this.cardHtml='<div class=\'p-6 text-sm text-red-600\'>Failed to load.</div>'; }
  }}">
  <div class="flex items-center justify-between mb-4">
    <h1 class="text-2xl font-extrabold">Guests</h1>
    <a href="<?= $h($base) ?>/guests/create" class="px-3 py-2 rounded-lg text-white" style="background:var(--brand)">Add Guest</a>
  </div>

  <form class="mb-3 grid grid-cols-1 sm:grid-cols-[1fr_auto] gap-2">
    <input name="q" value="<?= $h($q) ?>" placeholder="Search name, phone, email…"
           class="px-3 py-2 rounded-lg border border-slate-300"/>
    <button class="px-4 py-2 rounded-lg border">Filter</button>
  </form>

  <div class="overflow-auto rounded-xl border border-slate-200">
    <table class="min-w-full text-sm">
      <thead class="bg-slate-50">
        <tr>
          <th class="text-left px-3 py-2">Name</th>
          <th class="text-left px-3 py-2">Mobile</th>
          <th class="text-left px-3 py-2">Email</th>
          <th class="text-left px-3 py-2">Country</th>
          <th class="px-3 py-2"></th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="5" class="px-3 py-6 text-center text-slate-500">No guests found.</td></tr>
      <?php endif; ?>
      <?php foreach ($rows as $g): ?>
        <tr class="border-t">
          <td class="px-3 py-2 font-medium"><?= $h((string)($g['name']??'')) ?></td>
          <td class="px-3 py-2"><?= $h((string)($g['mobile']??'')) ?></td>
          <td class="px-3 py-2"><?= $h((string)($g['email']??'')) ?></td>
          <td class="px-3 py-2"><?= $h((string)($g['country']??'')) ?></td>
          <td class="px-3 py-2 text-right">
            <a @click.prevent="openCard(<?= (int)$g['id'] ?>)" href="#" class="text-emerald-700 hover:underline">Quick view</a>
            <a href="<?= $h($base) ?>/guests/<?= (int)$g['id'] ?>" class="ml-3 text-slate-600 hover:underline">Open</a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- simple pager -->
  <div class="mt-3 text-sm text-slate-500">Total: <span class="font-medium"><?= (int)$total ?></span></div>

  <!-- Modal -->
  <div x-show="modal" x-cloak class="fixed inset-0 z-50 grid place-items-center">
    <div class="absolute inset-0 bg-black/40" @click="modal=false"></div>
    <div class="relative bg-white dark:bg-gray-900 rounded-2xl shadow-xl border border-slate-200 w-[420px] max-w-[95vw]">
      <button class="absolute top-2 right-2 text-slate-400 hover:text-slate-600" @click="modal=false"><i class="fa-solid fa-xmark"></i></button>
      <div x-html="cardHtml"></div>
    </div>
  </div>
</div>