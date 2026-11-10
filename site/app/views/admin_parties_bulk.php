<?php require __DIR__ . '/admin_nav.php'; ?>
<main class="page">
  <?php if ($error): ?><p class="error" role="alert"><?= e($error) ?></p><?php endif; ?>
  <section class="card">
    <h2>Bulk upload <?= e(PARTY_KINDS[$kind]['label']) ?></h2>
    <p class="muted">Columns: Party Name (required), Area.</p>
    <form method="post" action="/admin/parties/<?= e($kind) ?>/bulk" enctype="multipart/form-data" class="row">
      <?= csrf_field() ?>
      <a class="btn" href="/admin/parties/<?= e($kind) ?>/template">Download template</a>
      <input type="hidden" name="action" value="check">
      <input class="input pin-file" type="file" name="file" accept=".xlsx,.csv" required aria-label="Filled file">
      <button class="btn primary">Check file</button>
    </form>
  </section>
  <?php if ($rows !== null):
      $count = fn (string $outcome) => count(array_filter($rows, fn ($row) => $row['outcome'] === $outcome));
  ?>
  <section class="card">
    <div class="row between">
      <div><h2>Check the file</h2><p class="muted"><?= $count('ready') ?> ready, <?= $count('skipped') ?> skipped, <?= $count('failed') ?> failed</p></div>
      <?php if ($count('ready') > 0): ?><form method="post" action="/admin/parties/<?= e($kind) ?>/bulk"><?= csrf_field() ?><input type="hidden" name="action" value="save"><button class="btn primary">Save valid rows</button></form><?php endif; ?>
    </div>
    <div class="table-box"><table class="data fill master-table">
      <thead><tr><th>Row</th><th>Party Name</th><th>Area</th><th>Result</th></tr></thead>
      <tbody><?php foreach ($rows as $row): ?><tr><td><?= (int) $row['row'] ?></td><td><?= e($row['name']) ?></td><td><?= e($row['area']) ?></td><td><?= e(ucfirst($row['outcome']) . ($row['reason'] ? ': ' . $row['reason'] : '')) ?></td></tr><?php endforeach; ?></tbody>
    </table></div>
  </section>
  <?php endif; ?>
</main>
