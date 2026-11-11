<?php $barTitle = 'Import History'; ?>
<main class="page">
  <?php if ($error): ?><p class="error" role="alert"><?= e($error) ?></p><?php endif; ?>
  <?php if ($result): ?>
  <p class="success" role="status"><?= number_format($result['added']) ?> follow ups added<?= $result['skipped'] ? ', ' . number_format($result['skipped']) . ' already imported' : '' ?><?= $result['failed'] ? ', ' . number_format(count($result['failed'])) . ' left out' : '' ?>.</p>
  <?php foreach (array_slice($result['failed'], 0, 50) as $line): ?><p class="muted"><?= e($line) ?></p><?php endforeach; ?>
  <?php endif; ?>
  <section class="card">
    <h2>Import old follow ups</h2>
    <p class="muted">Open the Payment Follow Ups tab in the Google Sheet, choose File, Download, CSV, and upload that file here. Rows already imported (same Follow Up Id) are skipped, so the same file can be uploaded again safely. Follow ups now in the app: <?= number_format($count) ?>.</p>
    <form method="post" action="/payment-tracker/import" enctype="multipart/form-data" class="row">
      <?= csrf_field() ?>
      <a class="btn" href="/payment-tracker/import/template">Download template</a>
      <input class="input pin-file" type="file" name="file" accept=".xlsx,.csv" required aria-label="Follow ups file">
      <button class="btn primary">Import</button>
    </form>
  </section>
</main>
