<?php $barTitle = 'MR Phones'; ?>
<main class="page">
  <section class="card">
    <h2>Upload MR phone numbers</h2>
    <p class="muted">Columns: MR, Phone Number. Download the MR tab of the Google Sheet as CSV and upload it here. Used for the MR WhatsApp and Call buttons.</p>
    <form method="post" action="/payment-tracker/mr-phones" enctype="multipart/form-data" class="row">
      <?= csrf_field() ?>
      <a class="btn" href="/payment-tracker/mr-phones/template">Download template</a>
      <input type="hidden" name="action" value="upload">
      <input class="input pin-file" type="file" name="file" accept=".xlsx,.csv" required aria-label="MR phone file">
      <button class="btn primary">Upload</button>
    </form>
  </section>
  <form method="post" action="/payment-tracker/mr-phones" class="card">
    <?= csrf_field() ?>
    <div class="row between center"><h2>MR list &middot; <?= count($list) ?></h2><button class="btn primary">Save</button></div>
    <?php if (!$list): ?><p class="muted">MR names appear here after the outstanding is uploaded.</p><?php else: ?>
    <div class="table-box"><table class="data">
      <thead><tr><th>MR</th><th>Phone Number</th></tr></thead>
      <tbody><?php foreach ($list as $index => $item): ?>
        <tr><td><?= e($item['mr']) ?><input type="hidden" name="mr[<?= $index ?>]" value="<?= e($item['mr']) ?>"></td><td><input class="input" name="phone[<?= $index ?>]" inputmode="tel" maxlength="20" value="<?= e($item['phone']) ?>" aria-label="Phone Number for <?= e($item['mr']) ?>"></td></tr>
      <?php endforeach; ?></tbody>
    </table></div>
    <?php endif; ?>
  </form>
</main>
