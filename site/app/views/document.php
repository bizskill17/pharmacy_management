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
$deleted = (bool) $document['deleted_at'];
$state = $deleted ? 'DELETED' : $document['status'];
$id = (int) $document['id'];
$viewUrl = '/r/' . $register['slug'] . '/' . $id;
$editing = isset($_GET['edit']) && !$deleted;
$barTitle = $register['label'];
?>
<main class="page" id="document" data-id="<?= $id ?>">
  <div class="doc-bar">
    <div id="doc-head" class="doc-head">
      <?php if ($editing): ?>
      <a class="icon-back" href="<?= e($viewUrl) ?>" aria-label="Back to details" title="Back to details"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M11 6l-6 6 6 6"/></svg></a>
      <?php else: ?>
      <a class="icon-back" href="/r/<?= e($register['slug']) ?>" aria-label="Back to <?= e($register['label']) ?>" title="Back to <?= e($register['label']) ?>"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M11 6l-6 6 6 6"/></svg></a>
      <?php endif; ?>
      <div class="doc-name">
        <h1><?= e($document['doc_no']) ?></h1>
        <span class="pill <?= e($state) ?>"><?= e(ucfirst(strtolower($state))) ?></span>
      </div>
    </div>
    <?php if (!$editing): ?>
    <div class="doc-actions">
      <?php if ($deleted && $manage): ?>
      <form method="post" action="/documents/<?= $id ?>/restore"><?= csrf_field() ?><button class="btn primary">Restore document</button></form>
      <?php elseif (!$deleted): ?>
      <a class="icon-button primary" href="<?= e($viewUrl) ?>?edit=1" aria-label="Update" title="Update"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg></a>
      <?php if ($manage): ?>
      <form method="post" action="/documents/<?= $id ?>/delete" class="row" id="delete-form">
        <?= csrf_field() ?>
        <button type="button" class="icon-button danger" data-confirm-delete aria-label="Delete" title="Delete"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg></button>
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
  <?php else:
      $formIcon = fn (string $paths) => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths . '</svg>';
      $uploadIcon = $formIcon('<path d="M12 16V4M7 9l5-5 5 5"/><path d="M4 16v4h16v-4"/>');
      $cameraIcon = $formIcon('<path d="M4 8h3l2-3h6l2 3h3v11H4z"/><circle cx="12" cy="13" r="3.5"/>');
      $trashIcon = $formIcon('<path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/>');
      $hasAuto = in_array('auto', array_column($register['form'], 0), true);
  ?>
  <section class="update-form" aria-label="<?= e($register['label']) ?> update form">
    <p id="form-message" role="status" hidden></p>
    <?php foreach ($register['form'] as [$section, $kind]):
        if ($section === 'photos'):
            $group = group_for($register, $kind);
            $items = $photos[$kind] ?? [];
            $count = count($items);
            $showNames = $group['max'] > 1;
    ?>
    <div class="photo-group" id="group-<?= e($kind) ?>">
      <h3 class="photo-head<?= $group['required'] ? ' req' : '' ?>"><?= e($group['max'] === 1 ? $group['label'] : $group['label'] . 's') ?></h3>
      <div class="slots">
        <?php foreach ($items as $index => $photoId): $name = slot_label($group['label'], $index + 1); ?>
        <div class="slot">
          <?php if ($showNames): ?><div class="slot-name"><?= e($name) ?></div><?php endif; ?>
          <a href="/api/files/<?= (int) $photoId ?>" data-photo="<?= e($name) ?>" aria-label="Open <?= e($name) ?>"><img src="/api/files/<?= (int) $photoId ?>" alt="<?= e($name) ?> preview"></a>
          <?php if ($index === $count - 1): ?><button type="button" class="slot-remove" data-remove="<?= (int) $photoId ?>"><?= $trashIcon ?>Remove</button><?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php if ($count < $group['max']): $name = slot_label($group['label'], $count + 1); ?>
        <div class="slot">
          <?php if ($showNames): ?><div class="slot-name"><?= e($name) ?></div><?php endif; ?>
          <input class="sr-input" type="file" accept="image/jpeg,image/png,image/webp" data-kind="<?= e($kind) ?>" tabindex="-1" aria-label="Upload <?= e($name) ?>">
          <input class="sr-input" type="file" accept="image/*" capture="environment" data-kind="<?= e($kind) ?>" tabindex="-1" aria-label="Click <?= e($name) ?>">
          <div class="slot-actions">
            <button type="button" class="slot-pick" data-pick="0"><?= $uploadIcon ?>Upload photo</button>
            <button type="button" class="slot-pick main" data-pick="1"><?= $cameraIcon ?>Click photo</button>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <?php elseif ($section === 'auto'): continue; else: ?>
    <div class="field form-wide"><label for="remarks">Remarks</label><textarea id="remarks" class="input" maxlength="2000"><?= e($document['remarks']) ?></textarea></div>
    <?php endif; endforeach; ?>
    <div class="form-foot">
      <?php if ($hasAuto): ?>
      <div class="auto-info">
        <span>Upload Date <b><?= e(date('d/m/Y')) ?></b></span>
        <span>Uploaded By <b><?= e($user['full_name']) ?></b></span>
      </div>
      <?php endif; ?>
      <button type="button" class="btn primary" id="save-update">Save update</button>
    </div>
  </section>
  <?php endif; ?>
</main>
