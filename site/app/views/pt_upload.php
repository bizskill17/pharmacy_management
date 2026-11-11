<?php $barTitle = 'Upload Outstanding'; ?>
<main class="page">
  <?php if ($error): ?><p class="error" role="alert"><?= e($error) ?></p><?php endif; ?>
  <section class="card">
    <h2>Upload outstanding</h2>
    <p class="muted">Use the Invoice Receipts file from the billing software. Columns needed: Party Name, Balance, Invoice No, Invoice Amount, Invoice Date, MR, Area, Phone, Closing Balance. Saving replaces the old outstanding; follow ups are kept.</p>
    <?php if ($last): ?><p>Last upload: <strong><?= e(time_text($last['uploaded_at'])) ?></strong> by <?= e($last['full_name']) ?> &middot; <?= number_format((int) $last['row_count']) ?> rows, <?= number_format((int) $last['party_count']) ?> parties</p><?php endif; ?>
    <form method="post" action="/payment-tracker/upload" enctype="multipart/form-data" class="row">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="check">
      <input class="input pin-file" type="file" name="file" accept=".xlsx,.csv" required aria-label="Outstanding file">
      <button class="btn primary">Check file</button>
    </form>
  </section>
  <?php if ($summary): ?>
  <section class="card">
    <div class="row between">
      <div>
        <h2>Check the file</h2>
        <p class="muted"><?= number_format($summary['rows']) ?> rows ready &middot; <?= number_format($summary['parties']) ?> parties &middot; total balance <?= signed_money($summary['total']) ?><?= $summary['failed_count'] ? ' &middot; ' . number_format($summary['failed_count']) . ' rows failed' : '' ?><?= $summary['no_date'] ? ' &middot; ' . number_format($summary['no_date']) . ' rows without an Invoice Date' : '' ?></p>
      </div>
      <form method="post" action="/payment-tracker/upload"><?= csrf_field() ?><input type="hidden" name="action" value="save"><button class="btn primary">Save and replace</button></form>
    </div>
    <div class="table-box"><table class="data">
      <thead><tr><th>Party Name</th><th>Invoice No</th><th>Invoice Date</th><th class="right">Invoice Amount</th><th class="right">Balance</th><th>MR</th><th>Area</th><th>Phone</th><th class="right">Closing Balance</th></tr></thead>
      <tbody><?php foreach ($summary['preview'] as $row): ?><tr><td><?= e($row[0]) ?></td><td><?= e($row[3]) ?></td><td><?= e(display_date($row[5])) ?></td><td class="right"><?= $row[4] === null ? '' : e(money($row[4])) ?></td><td class="right"><?= signed_money($row[2]) ?></td><td><?= e($row[6]) ?></td><td><?= e($row[7]) ?></td><td><?= e($row[8]) ?></td><td class="right"><?= signed_money($row[9]) ?></td></tr><?php endforeach; ?></tbody>
    </table></div>
    <p class="muted">Showing the first <?= count($summary['preview']) ?> rows.</p>
    <?php if ($summary['failed']): ?>
    <h3>Rows that will be left out</h3>
    <div class="table-box"><table class="data"><thead><tr><th>Row</th><th>Party Name</th><th>Reason</th></tr></thead>
      <tbody><?php foreach ($summary['failed'] as $row): ?><tr><td><?= (int) $row['row'] ?></td><td><?= e($row['party']) ?></td><td><?= e($row['reason']) ?></td></tr><?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
  </section>
  <?php endif; ?>
  <?php if ($history): ?>
  <section class="card flush">
    <h2>Upload history</h2>
    <div class="table-box"><table class="data"><thead><tr><th>Uploaded</th><th>By</th><th>File</th><th class="right">Rows</th><th class="right">Parties</th><th class="right">Total Balance</th></tr></thead>
      <tbody><?php foreach ($history as $item): ?><tr><td><?= e(time_text($item['uploaded_at'])) ?></td><td><?= e($item['full_name']) ?></td><td><?= e($item['file_name']) ?></td><td class="right"><?= number_format((int) $item['row_count']) ?></td><td class="right"><?= number_format((int) $item['party_count']) ?></td><td class="right"><?= signed_money($item['total_balance']) ?></td></tr><?php endforeach; ?></tbody></table></div>
  </section>
  <?php endif; ?>
</main>
