<?php
$hasField = function (string $key) use ($register) {
    foreach ($register['template'] as $column) {
        if ($column[1] === $key) {
            return true;
        }
    }
    return false;
};
$fields = [[$register['party'], $document['party'] ?? '-'], [$register['number'], $document['doc_no']], [$register['date'], display_date($document['doc_date'])], [$register['amount'], money($document['amount'])]];
if ($hasField('receipt')) { $fields[] = ['Receipt', $document['receipt_ref'] ?? '-']; }
if ($hasField('items')) { $fields[] = ['No of Item', $document['item_count'] ?? '-']; }
if ($hasField('mr')) { array_push($fields, ['MR', $document['mr_name'] ?? '-'], ['Area', $document['area'] ?? '-'], ['Address', $document['address'] ?? '-']); }
array_push($fields, ['Uploaded by', $document['uploader'] ?? '-'], ['Upload date', display_date($document['upload_date'])]);
$missing = [];
foreach ($register['groups'] as $group) {
    if ($group['required'] && empty($photos[$group['kind']])) {
        $missing[] = $group['label'];
    }
}
$deleted = (bool) $document['deleted_at'];
$state = $deleted ? 'DELETED' : $document['status'];
$id = (int) $document['id'];
?>
<main class="page" id="document" data-id="<?= $id ?>">
  <div id="doc-head">
    <a class="doc-link" href="/r/<?= e($register['slug']) ?>">Back to <?= e($register['label']) ?></a>
    <div class="row" style="margin-top:8px;gap:12px">
      <h1><?= e($document['doc_no']) ?></h1>
      <span class="pill <?= e($state) ?>"><?= e(ucfirst(strtolower($state))) ?></span>
    </div>
    <p class="muted"><?= $missing ? e('Still needed: ' . implode(', ', $missing)) : 'All required files are attached.' ?></p>
  </div>
  <?php if ($deleted && $manage): ?>
  <form method="post" action="/documents/<?= $id ?>/restore"><?= csrf_field() ?><button class="btn primary">Restore document</button></form>
  <?php endif; ?>
  <div class="split">
    <div class="side">
      <section class="card flush" id="doc-details">
        <h2>Details</h2>
        <?php foreach ($fields as [$label, $value]): ?><div class="kv"><span><?= e($label) ?></span><span><?= e($value) ?></span></div><?php endforeach; ?>
      </section>
      <?php if ($manage && !$deleted): ?>
      <details class="card">
        <summary><strong>Correct details or delete</strong></summary>
        <form method="post" action="/documents/<?= $id ?>/correct" class="stack">
          <?= csrf_field() ?>
          <div class="field"><label for="c-date">Date</label><input id="c-date" name="date" class="input" data-date value="<?= e(display_date($document['doc_date'])) ?>" required></div>
          <div class="field"><label for="c-party"><?= e($register['party']) ?></label><input id="c-party" name="party" class="input" value="<?= e($document['party_name_raw'] ?? $document['party']) ?>" required maxlength="200"></div>
          <div class="field"><label for="c-amount">Amount</label><input id="c-amount" name="amount" class="input" inputmode="decimal" value="<?= e((float) $document['amount']) ?>" required></div>
          <?php if ($hasField('items')): ?><div class="field"><label for="c-items">No of Item</label><input id="c-items" name="items" class="input" inputmode="numeric" value="<?= e($document['item_count']) ?>"></div><?php endif; ?>
          <?php if ($hasField('receipt')): ?><div class="field"><label for="c-receipt">Receipt</label><input id="c-receipt" name="receipt" class="input" maxlength="50" value="<?= e($document['receipt_ref']) ?>"></div><?php endif; ?>
          <?php if ($hasField('mr')): ?>
          <div class="field"><label for="c-mr">MR</label><input id="c-mr" name="mr" class="input" maxlength="120" value="<?= e($document['mr_name']) ?>"></div>
          <div class="field"><label for="c-area">Area</label>
            <select id="c-area" name="area" class="input"><option value="">None</option><?php foreach ($areas as $area): ?><option value="<?= (int) $area['id'] ?>"<?= (int) $area['id'] === (int) $document['area_id'] ? ' selected' : '' ?>><?= e($area['name']) ?></option><?php endforeach; ?></select>
          </div>
          <div class="field"><label for="c-address">Address</label><input id="c-address" name="address" class="input" maxlength="255" value="<?= e($document['address']) ?>"></div>
          <?php endif; ?>
          <button class="btn primary">Save correction</button>
        </form>
        <form method="post" action="/documents/<?= $id ?>/delete" class="row" id="delete-form">
          <?= csrf_field() ?>
          <button type="button" class="btn danger" data-confirm-delete>Delete document</button>
          <button type="button" class="btn" data-keep hidden>Keep</button>
          <button class="btn danger" data-do-delete hidden>Confirm delete</button>
        </form>
      </details>
      <?php endif; ?>
    </div>
    <section class="card form" aria-label="<?= e($register['label']) ?> update form">
      <div>
        <h2><?= e($register['label']) ?> update form</h2>
        <p class="muted">The next photo opens after the one before it is added. Photos save as soon as they are added.</p>
      </div>
      <p id="form-message" role="status" hidden></p>
      <?php foreach ($register['form'] as [$section, $kind]):
          if ($section === 'photos'):
              $group = group_for($register, $kind);
              $items = $photos[$kind] ?? [];
              $count = count($items);
      ?>
      <div class="field photo-group" id="group-<?= e($kind) ?>">
        <div class="row between">
          <h3<?= $group['required'] ? ' class="req"' : '' ?>><?= e($group['max'] === 1 ? $group['label'] : $group['label'] . 's') ?></h3>
          <span class="muted"><?= $group['required'] ? 'Required' : 'Optional' ?> &middot; <?= $count ?> of <?= (int) $group['max'] ?> added</span>
        </div>
        <div class="slots">
          <?php foreach ($items as $index => $photoId): $name = slot_label($group['label'], $index + 1); ?>
          <div class="slot">
            <div class="slot-name"><?= e($name) ?></div>
            <img src="/api/files/<?= (int) $photoId ?>" alt="<?= e($name) ?> preview">
            <?php if ($index === $count - 1 && !$deleted): ?><button type="button" class="btn" data-remove="<?= (int) $photoId ?>">Remove</button><?php endif; ?>
          </div>
          <?php endforeach; ?>
          <?php if ($count < $group['max'] && !$deleted): $name = slot_label($group['label'], $count + 1); ?>
          <div class="slot">
            <div class="slot-name"><?= e($name) ?></div>
            <input class="sr-input" type="file" accept="image/jpeg,image/png,image/webp" data-kind="<?= e($kind) ?>" tabindex="-1" aria-label="Upload <?= e($name) ?>">
            <input class="sr-input" type="file" accept="image/*" capture="environment" data-kind="<?= e($kind) ?>" tabindex="-1" aria-label="Click <?= e($name) ?>">
            <button type="button" class="btn" data-pick="0">Upload photo</button>
            <button type="button" class="btn primary" data-pick="1">Click photo</button>
          </div>
          <?php endif; ?>
        </div>
      </div>
      <?php elseif ($section === 'auto'): ?>
      <div class="grid-auto">
        <div class="field"><label for="upload-date">Upload Date</label><input id="upload-date" class="input" readonly value="<?= e(date('d/m/Y')) ?>"></div>
        <div class="field"><label for="uploaded-by">Uploaded By (Employee)</label><input id="uploaded-by" class="input" readonly value="<?= e($user['full_name']) ?>"></div>
      </div>
      <?php else: ?>
      <div class="field"><label for="remarks">Remarks</label><textarea id="remarks" class="input" maxlength="2000"<?= $deleted ? ' disabled' : '' ?>><?= e($document['remarks']) ?></textarea></div>
      <?php endif; endforeach; ?>
      <?php if (!$deleted): ?><div class="row"><button type="button" class="btn primary" id="save-update">Save update</button></div><?php endif; ?>
    </section>
  </div>
</main>
