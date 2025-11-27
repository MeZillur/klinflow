<?php $h = fn($x)=>htmlspecialchars($x, ENT_QUOTES); ?>

<h1 class="text-2xl font-bold mb-4">Edit Branch</h1>

<form method="post" action="<?= $h($base) ?>/branches/<?= (int)$branch['id'] ?>">
  <input type="hidden" name="_return" value="<?= $h($base) ?>/branches">

  <label>Name</label>
  <input name="name" class="sr-input" value="<?= $h($branch['name']) ?>" required>

  <label>Address</label>
  <textarea name="address" class="sr-input" rows="3"><?= $h($branch['address']) ?></textarea>

  <label>Phone</label>
  <input name="phone" class="sr-input" value="<?= $h($branch['phone']) ?>">

  <label>Email</label>
  <input name="email" class="sr-input" value="<?= $h($branch['email']) ?>">

  <div class="mt-4">
    <button class="btn btn-primary">Update</button>
    <a href="<?= $h($base) ?>/branches" class="btn btn-muted">Cancel</a>
  </div>
</form>