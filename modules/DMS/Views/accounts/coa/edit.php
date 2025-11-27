<?php /** @var array|null $account */ ?>
<h1 class="text-xl font-semibold mb-4">Edit Account</h1>
<?php if (!$account): ?>
  <div class="text-gray-500">Account not found.</div>
<?php else: ?>
<form method="post" action="#">
  <div class="grid md:grid-cols-3 gap-3">
    <label>Code <input name="code" class="input" value="<?= h($account['code']) ?>"></label>
    <label>Name <input name="name" class="input" value="<?= h($account['name']) ?>"></label>
    <label>Type
      <select name="type" class="input">
        <?php foreach (['asset','liability','equity','income','expense'] as $t): ?>
          <option value="<?= $t ?>" <?= $t===$account['type']?'selected':'' ?>><?= $t ?></option>
        <?php endforeach; ?>
      </select>
    </label>
  </div>
  <button class="btn btn--primary mt-4" disabled>Update (stub)</button>
</form>
<?php endif; ?>