<?php
/** Shared employee fields. Expects $employee (array or null). */
$v = fn (string $key) => $employee[$key] ?? '';
$isEdit = $employee !== null;
$roleOptions = ['UPLOADER' => 'Uploader', 'SUPERVISOR' => 'Supervisor', 'ADMIN' => 'Admin'];
$currentRole = $v('role') ?: 'UPLOADER';
$active = !$isEdit || $employee['is_active'];
$documents = ['photo' => ['Photo', 'photo_key'], 'pan' => ['PAN Card', 'pan_key'], 'aadhaar_front' => ['Aadhar Card Front', 'aadhaar_front_key'], 'aadhaar_back' => ['Aadhar Card Back', 'aadhaar_back_key']];
?>
<div class="grid-auto">
  <div class="field"><label for="u-name">Employee Name</label><input id="u-name" name="name" class="input" required maxlength="120" value="<?= e($v('full_name')) ?>"></div>
  <div class="field"><label for="u-phone">Phone No</label><input id="u-phone" name="phone" class="input" inputmode="tel" required maxlength="15" value="<?= e($v('phone')) ?>"></div>
  <div class="field"><label for="u-email">Email</label><input id="u-email" name="email" type="email" class="input" maxlength="150" value="<?= e($v('email')) ?>"></div>
  <div class="field"><label for="u-address">Address</label><input id="u-address" name="address" class="input" maxlength="255" value="<?= e($v('address')) ?>"></div>
  <div class="field"><label for="u-designation">Designation</label><input id="u-designation" name="designation" class="input" maxlength="100" value="<?= e($v('designation')) ?>"></div>
  <div class="field"><label for="u-password">Passcode<?= $isEdit ? ' (leave blank to keep)' : '' ?></label><input id="u-password" name="password" type="password" class="input"<?= $isEdit ? '' : ' required' ?> minlength="8" autocomplete="new-password"></div>
  <div class="field"><label for="u-salary">Salary</label><input id="u-salary" name="salary" class="input" inputmode="decimal" value="<?= e($v('salary') === '' ? '' : (float) $v('salary')) ?>"></div>
  <div class="field"><label for="u-joining">Joining Date</label><input id="u-joining" name="joining_date" type="date" class="input" value="<?= e($v('joining_date')) ?>"></div>
  <div class="field"><label for="u-status">Status</label><select id="u-status" name="status" class="input"><option<?= $active ? ' selected' : '' ?>>Active</option><option<?= $active ? '' : ' selected' ?>>Inactive</option></select></div>
  <div class="field"><label for="u-role">Role</label><select id="u-role" name="role" class="input" required><?php foreach ($roleOptions as $value => $label): ?><option value="<?= e($value) ?>"<?= $currentRole === $value ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
</div>
<div class="grid-auto">
  <?php foreach ($documents as $field => [$label, $column]): ?>
  <div class="field">
    <label for="u-<?= e($field) ?>"><?= e($label) ?></label>
    <?php if ($isEdit && !empty($employee[$column])): ?>
    <a href="/api/users/<?= (int) $employee['id'] ?>/<?= e($field) ?>" target="_blank" rel="noopener"><img class="thumb" src="/api/users/<?= (int) $employee['id'] ?>/<?= e($field) ?>" alt="<?= e($label) ?> preview"></a>
    <?php endif; ?>
    <input id="u-<?= e($field) ?>" name="<?= e($field) ?>" type="file" class="input" accept="image/jpeg,image/png,image/webp" data-compress>
  </div>
  <?php endforeach; ?>
</div>
