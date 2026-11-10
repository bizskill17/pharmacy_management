<?php
$barTitle = 'Purchase In';
$old = fn (string $key) => (string) ($old[$key] ?? '');
$uploadIcon = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 16V4M7 9l5-5 5 5"/><path d="M4 16v4h16v-4"/></svg>';
$cameraIcon = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 8h3l2-3h6l2 3h3v11H4z"/><circle cx="12" cy="13" r="3.5"/></svg>';
$trashIcon = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>';
?>
<main class="page">
  <?php if ($error): ?><p class="error" role="alert"><?= e($error) ?></p><?php endif; ?>
  <form method="post" action="/purchase-in/add" enctype="multipart/form-data" id="pin-form" class="pin-form" data-pin-photos data-sl="<?= (int) $nextSl ?>">
    <?= csrf_field() ?>
    <div class="pin-fields">
      <div class="field"><label for="pin-date">Date</label><input id="pin-date" class="input" readonly value="<?= e(date('d/m/Y')) ?>"></div>
      <div class="field"><label for="pin-sl">Sl. No.</label><input id="pin-sl" class="input" readonly value="<?= (int) $nextSl ?>"></div>
      <?php $partyNow = $old('party_name'); if ($partyNow !== '' && !in_array($partyNow, $parties, true)) { array_unshift($parties, $partyNow); } ?>
      <div class="field pin-wide"><label for="pin-party">Party Name</label><select id="pin-party" name="party_name" class="input" required data-allow-new><option value="">Choose</option><?php foreach (array_unique($parties) as $party): ?><option<?= $party === $partyNow ? ' selected' : '' ?>><?= e($party) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label for="pin-packets">No. of Packets</label><input id="pin-packets" name="packets" class="input" required inputmode="numeric" pattern="\d{1,7}" value="<?= e($old('packets')) ?>"></div>
      <div class="field"><label for="pin-bills">Number Of Bill</label><input id="pin-bills" name="bill_count" class="input" required type="number" min="1" max="<?= PURCHASE_IN_MAX_BILLS ?>" value="<?= e($old('bill_count')) ?>"></div>
      <div class="field"><label for="pin-ref">Reference No.</label><input id="pin-ref" name="reference_no" class="input" maxlength="100" value="<?= e($old('reference_no')) ?>"></div>
      <div class="field"><label for="pin-amount">Bill Amount</label><input id="pin-amount" name="bill_amount" class="input" required inputmode="decimal" value="<?= e($old('bill_amount')) ?>"></div>
      <div class="field pin-wide"><label for="pin-party-no">Party No</label><input id="pin-party-no" class="input" readonly value=""></div>
    </div>
    <div class="photo-group pin-photos">
      <h3 class="photo-head">Photos</h3>
      <div class="pin-slots">
        <?php for ($slot = 1; $slot <= PURCHASE_IN_PHOTOS; $slot++): ?>
        <div class="pin-slot"<?= $slot > 1 ? ' hidden' : '' ?> data-pin-slot>
          <span class="slot-name">Photo <?= $slot ?></span>
          <input class="sr-input" type="file" name="photo<?= $slot ?>" accept="image/jpeg,image/png,image/webp" data-compress tabindex="-1" aria-label="Upload Photo <?= $slot ?>">
          <input class="sr-input" type="file" name="photo<?= $slot ?>_cam" accept="image/*" capture="environment" data-compress tabindex="-1" aria-label="Click Photo <?= $slot ?>">
          <div class="slot-actions" data-pin-actions>
            <button type="button" class="slot-pick" data-pin-pick="0" aria-label="Upload Photo <?= $slot ?>" title="Upload photo"><?= $uploadIcon ?></button>
            <button type="button" class="slot-pick main" data-pin-pick="1" aria-label="Click Photo <?= $slot ?>" title="Click photo"><?= $cameraIcon ?></button>
          </div>
          <img class="pin-preview" alt="Photo <?= $slot ?> preview" hidden>
          <button type="button" class="slot-remove" data-pin-remove aria-label="Remove Photo <?= $slot ?>" title="Remove" hidden><?= $trashIcon ?></button>
        </div>
        <?php endfor; ?>
      </div>
    </div>
    <div class="form-foot"><button class="btn primary" id="pin-save">Save</button></div>
  </form>
</main>
