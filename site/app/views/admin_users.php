<?php $roleOptions = ['UPLOADER' => 'Uploader', 'SUPERVISOR' => 'Supervisor', 'ADMIN' => 'Admin']; ?>
<main class="page">
  <?php require __DIR__ . '/admin_nav.php'; ?>
  <form method="post" action="/admin/users" class="card">
    <?= csrf_field() ?>
    <h2>Add user</h2>
    <div class="grid-auto">
      <div class="field"><label for="u-name">Name</label><input id="u-name" name="name" class="input" required maxlength="120"></div>
      <div class="field"><label for="u-phone">Phone number</label><input id="u-phone" name="phone" class="input" inputmode="tel" required maxlength="15"></div>
      <div class="field"><label for="u-password">Password</label><input id="u-password" name="password" type="password" class="input" required minlength="8" autocomplete="new-password"></div>
      <div class="field"><label for="u-role">Role</label><select id="u-role" name="role" class="input"><?php foreach ($roleOptions as $value => $label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?></select></div>
    </div>
    <div><button class="btn primary">Add user</button></div>
  </form>
  <?php foreach ($users as $listed): $listedId = (int) $listed['id']; ?>
  <form method="post" action="/admin/users/update" class="card">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $listedId ?>">
    <div class="row between">
      <div><strong><?= e($listed['full_name']) ?></strong><p class="muted"><?= e($listed['phone']) ?></p></div>
      <label class="row"><input type="checkbox" name="active"<?= $listed['is_active'] ? ' checked' : '' ?>> Active</label>
    </div>
    <div class="grid-auto">
      <div class="field"><label for="role-<?= $listedId ?>">Role</label><select id="role-<?= $listedId ?>" name="role" class="input"><?php foreach ($roleOptions as $value => $label): ?><option value="<?= e($value) ?>"<?= $listed['role'] === $value ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label for="password-<?= $listedId ?>">New password (optional)</label><input id="password-<?= $listedId ?>" name="password" type="password" class="input" minlength="8" autocomplete="new-password"></div>
    </div>
    <div><button class="btn">Save</button></div>
  </form>
  <?php endforeach; ?>
</main>
