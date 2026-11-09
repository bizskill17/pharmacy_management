<?php
$hasField = function (string $key) use ($register) {
    foreach ($register['template'] as $column) {
        if ($column[1] === $key) {
            return true;
        }
    }
    return false;
};
$fields = [[$register['party'], $document['party'] ?? ''], [$register['number'], $document['doc_no']], [$register['date'], display_date($document['doc_date'])], [$register['amount'], money($document['amount'])]];
if ($hasField('receipt')) { $fields[] = ['Receipt', $document['receipt_ref'] ?? '']; }
if ($hasField('items')) { $fields[] = ['No of Item', $document['item_count'] ?? '']; }
if ($hasField('mr')) { array_push($fields, ['MR', $document['mr_name'] ?? ''], ['Area', $document['area'] ?? ''], ['Address', $document['address'] ?? '']); }
array_push($fields, ['Uploaded by', $document['uploader'] ?? ''], ['Upload date', display_date($document['upload_date'])]);
if ($register['type'] === 'PURCHASE') { $fields[] = ['Remarks', $document['remarks'] ?? '']; }
$missing = [];
foreach ($register['groups'] as $group) {
    if ($group['required'] && empty($photos[$group['kind']])) {
        $missing[] = $group['label'];
    }
}
$deleted = (bool) $document['deleted_at'];
$state = $deleted ? 'DELETED' : $document['status'];
$id = (int) $document['id'];
$viewUrl = '/r/' . $register['slug'] . '/' . $id;
$editing = isset($_GET['edit']) && !$deleted;
$barTitle = $register['label'];
?>
<main class="page" id="document" data-id="<?= $id ?>">
  <div class="row between">
    <div id="doc-head">
      <?php if ($editing): ?>
      <a class="icon-back" href="<?= e($viewUrl) ?>" aria-label="Back to details" title="Back to details"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M11 6l-6 6 6 6"/></svg></a>
      <?php else: ?>
      <a class="icon-back" href="/r/<?= e($register['slug']) ?>" aria-label="Back to <?= e($register['label']) ?>" title="Back to <?= e($register['label']) ?>"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M11 6l-6 6 6 6"/></svg></a>
      <?php endif; ?>
      <div class="row" style="margin-top:8px;gap:12px">
        <h1><?= e($document['doc_no']) ?></h1>
        <span class="pill <?= e($state) ?>"><?= e(ucfirst(strtolower($state))) ?></span>
      </div>
      <?php if ($missing): ?><p class="muted"><?= e('Still needed: ' . implode(', ', $missing)) ?></p><?php endif; ?>
    </div>
    <?php if (!$editing): ?>
    <div class="row">
      <?php if ($deleted && $manage): ?>
      <form method="post" action="/documents/<?= $id ?>/restore"><?= csrf_field() ?><button class="btn primary">Restore document</button></form>
      <?php elseif (!$deleted): ?>
      <a class="btn primary" href="<?= e($viewUrl) ?>?edit=1">Update</a>
      <?php if ($manage): ?>
      <form method="post" action="/documents/<?= $id ?>/delete" class="row" id="delete-form">
        <?= csrf_field() ?>
        <button type="button" class="btn danger" data-confirm-delete>Delete</button>
        <button type="button" class="btn" data-keep hidden>Keep</button>
        <button class="btn danger" data-do-delete hidden>Confirm delete</button>
      </form>
      <?php endif; ?>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>

  <?php if (!$editing): ?>
  <div class="split">
    <div class="side">
      <section class="card flush" id="doc-details">
        <h2>Details</h2>
        <?php foreach ($fields as [$label, $value]): ?><div class="kv"><span><?= e($label) ?></span><span><?= e($value) ?></span></div><?php endforeach; ?>
      </section>
    </div>
    <section class="card form" aria-label="Photos">
      <h2>Photos</h2>
      <?php foreach ($register['groups'] as $group): $items = $photos[$group['kind']] ?? []; ?>
      <div class="field">
        <h3><?= e($group['max'] === 1 ? $group['label'] : $group['label'] . 's') ?></h3>
        <?php if (!$items): ?>
        <p class="muted">No photo added.</p>
        <?php else: ?>
        <div class="slots">
          <?php foreach ($items as $index => $photoId): $name = slot_label($group['label'], $index + 1); ?>
          <div class="slot">
            <div class="slot-name"><?= e($name) ?></div>
            <a href="/api/files/<?= (int) $photoId ?>" data-photo="<?= e($name) ?>" aria-label="Open <?= e($name) ?>"><img src="/api/files/<?= (int) $photoId ?>" alt="<?= e($name) ?> preview"></a>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </section>
  </div>
  <?php else: ?>
  <div class="split">
    <section class="card form" aria-label="<?= e($register['label']) ?> update form">
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
        </div>
        <div class="slots">
          <?php foreach ($items as $index => $photoId): $name = slot_label($group['label'], $index + 1); ?>
          <div class="slot">
            <div class="slot-name"><?= e($name) ?></div>
            <img src="/api/files/<?= (int) $photoId ?>" alt="<?= e($name) ?> preview">
            <?php if ($index === $count - 1): ?><button type="button" class="btn" data-remove="<?= (int) $photoId ?>">Remove</button><?php endif; ?>
          </div>
          <?php endforeach; ?>
          <?php if ($count < $group['max']): $name = slot_label($group['label'], $count + 1); ?>
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
      <div class="field"><label for="remarks">Remarks</label><textarea id="remarks" class="input" maxlength="2000"><?= e($document['remarks']) ?></textarea></div>
      <?php endif; endforeach; ?>
      <div class="row"><button type="button" class="btn primary" id="save-update">Save update</button></div>
    </section>
  </div>
  <?php endif; ?>
</main>
