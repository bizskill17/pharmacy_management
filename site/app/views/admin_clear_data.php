<?php require __DIR__ . '/admin_nav.php'; ?>
<main class="page">
  <?php if ($error): ?><p class="error" role="alert"><?= e($error) ?></p><?php endif; ?>
  <section class="card">
    <h2>Clear all data except Users</h2>
    <p>This permanently deletes everything below, including the photos. It cannot be undone. Users (<?= number_format($users) ?>) and their documents are kept.</p>
    <div class="table-box"><table class="data fill master-table">
      <thead><tr><th>Data</th><th class="right">Records</th></tr></thead>
      <tbody><?php foreach ($counts as $label => $count): ?><tr><td><?= e($label) ?></td><td class="right"><?= number_format($count) ?></td></tr><?php endforeach; ?><tr><td>Purchase In last Sl. No.</td><td class="right">Reset to 0</td></tr></tbody>
    </table></div>
    <form method="post" action="/admin/clear-data" class="pin-challan">
      <?= csrf_field() ?>
      <div class="field"><label for="confirm">Type CLEAR to confirm</label><input id="confirm" name="confirm" class="input" required autocomplete="off"></div>
      <button class="btn danger">Clear all data</button>
    </form>
  </section>
</main>
