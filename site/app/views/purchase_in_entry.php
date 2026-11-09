<?php
$barTitle = 'Purchase In';
$id = (int) $entry['id'];
$done = $entry['status'] === 'DONE';
$fields = [
    ['Date', display_date($entry['entry_date'])], ['Timestamp', time_text($entry['entry_time'])], ['Sl. No.', $entry['sl_no']], ['Party Name', $entry['party_name']],
    ['No. of Packets', $entry['packets']], ['Number Of Bill', $entry['bill_count']], ['Reference No.', $entry['reference_no']], ['Bill Amount', money($entry['bill_amount'])],
    ['Party No', $entry['party_no']], ['Timestamp Challan', time_text($entry['challan_time'])], ['Challan No.', $entry['challan_no']], ['Entry By', $entry['entry_name']],
];
$back = $done ? '/purchase-in/master' : '/purchase-in/pending';
$photos = array_filter(range(1, PURCHASE_IN_PHOTOS), fn ($slot) => !empty($entry["photo_$slot"]));
?>
<main class="page">
  <div class="doc-bar">
    <div class="doc-head">
      <a class="icon-back" href="<?= $back ?>" aria-label="Back" title="Back"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M11 6l-6 6 6 6"/></svg></a>
      <div class="doc-name"><h1><?= (int) $entry['sl_no'] ?></h1><span class="pill <?= $done ? 'UPLOADED' : 'PENDING' ?>"><?= $done ? 'Done' : 'Pending' ?></span></div>
    </div>
  </div>
  <?php if (!$done): ?>
  <form method="post" action="/purchase-in/<?= $id ?>/challan" class="pin-challan">
    <?= csrf_field() ?>
    <div class="field grow"><label for="challan-no">Challan No.</label><input id="challan-no" name="challan_no" class="input" required maxlength="50"></div>
    <button class="btn primary">Save challan</button>
  </form>
  <?php endif; ?>
  <div class="split">
    <div class="side">
      <section class="card flush" id="doc-details">
        <h2>Details</h2>
        <?php foreach ($fields as [$label, $value]): if (trim((string) $value) === '') { continue; } ?><div class="kv"><span><?= e($label) ?></span><span><?= e($value) ?></span></div><?php endforeach; ?>
      </section>
    </div>
    <section class="card form" id="doc-photos" aria-label="Photos">
      <h2>Photos</h2>
      <div class="field">
        <?php if (!$photos): ?><p class="muted">No photo added.</p><?php else: ?>
        <div class="slots">
          <?php foreach ($photos as $slot): ?>
          <div class="slot"><div class="slot-name">Photo <?= $slot ?></div><a href="/api/purchase-in/<?= $id ?>/<?= $slot ?>" data-photo="Photo <?= $slot ?>" aria-label="Open Photo <?= $slot ?>"><img src="/api/purchase-in/<?= $id ?>/<?= $slot ?>" alt="Photo <?= $slot ?> preview"></a></div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </section>
  </div>
</main>
