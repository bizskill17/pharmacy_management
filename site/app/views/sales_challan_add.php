<?php
$barTitle = 'Sales Challan';
$v = fn (string $key) => (string) ($old[$key] ?? '');
$current = '';
$defaultMade = (int) current_user()['id'];
?>
<main class="page">
  <?php if ($error): ?><p class="error" role="alert"><?= e($error) ?></p><?php endif; ?>
  <form method="post" action="/sales-challan/add" id="sc-form" class="pin-form">
    <?= csrf_field() ?>
    <div class="pin-fields"><?php require __DIR__ . '/sales_challan_fields.php'; ?></div>
    <div class="form-foot"><button class="btn primary" id="sc-save">Save</button></div>
  </form>
</main>
