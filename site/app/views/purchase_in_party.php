<?php
$barTitle = 'Purchase In';
$parties = array_values(array_unique(array_column(rows('SELECT name FROM parties WHERE is_active = 1 ORDER BY name LIMIT 5000'), 'name')));
?>
<main class="page">
  <div class="doc-bar"><div class="doc-head">
    <a class="icon-back" href="/purchase-in/pending-party" aria-label="Back" title="Back"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M11 6l-6 6 6 6"/></svg></a>
    <div class="doc-name"><h1>Sl. No. <?= (int) $entry['sl_no'] ?></h1></div>
  </div></div>
  <?php if ($error): ?><p class="error" role="alert"><?= e($error) ?></p><?php endif; ?>
  <form method="post" action="/purchase-in/<?= (int) $entry['id'] ?>/party" class="pin-form">
    <?= csrf_field() ?>
    <div class="pin-fields">
      <div class="field pin-wide"><span class="choice-title">Typed Party Name</span><div class="input" aria-readonly="true"><?= e($entry['party_name']) ?></div></div>
      <div class="field pin-wide"><label for="party-pick">Party Name from Party Master</label><select id="party-pick" name="party_name" class="input" required><option value="">Select</option><?php foreach ($parties as $party): ?><option><?= e($party) ?></option><?php endforeach; ?></select></div>
    </div>
    <div class="form-foot"><button class="btn primary">Save</button></div>
  </form>
</main>
