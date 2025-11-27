<?php
/** @var array $rows @var array $schema @var string $module_base */
$h=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
$base=rtrim((string)($module_base??'/apps/hotelflow'),'/');
$active='tasks'; include __DIR__.'/_tabs.php';
?>
<div class="max-w-[1100px] mx-auto space-y-6">
  <div class="flex items-center justify-between">
    <h1 class="text-2xl font-extrabold">HK Tasks</h1>
    <?php if ($schema['tasks'] ?? false): ?>
      <form method="post" action="<?= $h($base) ?>/hk/tasks/store" class="flex flex-wrap gap-2">
        <input name="title" required placeholder="Task title" class="px-3 py-2 rounded-lg border border-slate-300">
        <input name="room_no" placeholder="Room #" class="px-3 py-2 rounded-lg border border-slate-300 w-28">
        <select name="priority" class="px-3 py-2 rounded-lg border border-slate-300">
          <option value="low">Low</option><option value="normal" selected>Normal</option><option value="high">High</option>
        </select>
        <input type="date" name="due_date" class="px-3 py-2 rounded-lg border border-slate-300">
        <input name="assignee" placeholder="Assign to" class="px-3 py-2 rounded-lg border border-slate-300">
        <input name="notes" placeholder="Notes" class="px-3 py-2 rounded-lg border border-slate-300 min-w-[280px]">
        <button class="px-4 py-2 rounded-lg text-white" style="background:var(--brand)">Add</button>
      </form>
    <?php endif; ?>
  </div>

  <?php if (!($schema['tasks'] ?? false)): ?>
    <div class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm">
      <div class="font-semibold mb-1">Table <code>hms_hk_tasks</code> not found.</div>
      <pre class="mt-3 p-3 bg-white rounded border overflow-auto text-xs"><?=
$h("CREATE TABLE hms_hk_tasks (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  org_id INT UNSIGNED NOT NULL,
  title VARCHAR(160) NOT NULL,
  priority ENUM('low','normal','high') NOT NULL DEFAULT 'normal',
  status ENUM('open','done') NOT NULL DEFAULT 'open',
  room_no VARCHAR(30) NULL,
  assignee VARCHAR(120) NULL,
  due_date DATE NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  KEY idx_org (org_id),
  KEY idx_status (status),
  KEY idx_due (due_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;")?></pre>
  </div>
  <?php endif; ?>

  <div class="overflow-x-auto rounded-xl border border-slate-200">
    <table class="min-w-full text-sm">
      <thead class="bg-slate-50">
        <tr>
          <th class="px-3 py-2 text-left w-14">#</th>
          <th class="px-3 py-2 text-left">Title</th>
          <th class="px-3 py-2">Room</th>
          <th class="px-3 py-2">Priority</th>
          <th class="px-3 py-2">Due</th>
          <th class="px-3 py-2">Assignee</th>
          <th class="px-3 py-2">Status</th>
          <th class="px-3 py-2 text-right w-56">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="8" class="px-3 py-8 text-center text-slate-500">No tasks yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $r): ?>
          <tr class="border-t">
            <td class="px-3 py-2"><?= (int)$r['id'] ?></td>
            <td class="px-3 py-2">
              <div class="font-medium"><?= $h((string)($r['title'] ?? '')) ?></div>
              <?php if (!empty($r['notes'])): ?><div class="text-xs text-slate-500"><?= $h((string)$r['notes']) ?></div><?php endif; ?>
            </td>
            <td class="px-3 py-2 text-center"><?= $h((string)($r['room_no'] ?? '')) ?></td>
            <td class="px-3 py-2 text-center"><?= $h((string)($r['priority'] ?? '')) ?></td>
            <td class="px-3 py-2 text-center"><?= $h((string)($r['due_date'] ?? '')) ?></td>
            <td class="px-3 py-2 text-center"><?= $h((string)($r['assignee'] ?? '')) ?></td>
            <td class="px-3 py-2 text-center"><?= $h((string)($r['status'] ?? '')) ?></td>
            <td class="px-3 py-2 text-right">
              <form method="post" action="<?= $h($base) ?>/hk/tasks/<?= (int)$r['id'] ?>/done" class="inline">
                <button class="px-3 py-1 rounded border">Done</button>
              </form>
              <form method="post" action="<?= $h($base) ?>/hk/tasks/<?= (int)$r['id'] ?>/delete"
                    onsubmit="return confirm('Delete this task?')" class="inline">
                <button class="px-3 py-1 rounded border text-red-600">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>