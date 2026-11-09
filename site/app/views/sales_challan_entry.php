<?php
$barTitle = 'Sales Challan';
$id = (int) $challan['id'];
$status = $challan['status'];
$label = ['PACKING' => 'Pending Packing', 'MATCHING' => 'Matching', 'DONE' => 'Done'][$status];
$back = ['PACKING' => '/sales-challan/packing', 'MATCHING' => '/sales-challan/matching', 'DONE' => '/sales-challan/master'][$status];
$fields = [
    ['Challan No.', $challan['challan_no']], ['Challan Date', display_date($challan['challan_date'])], ['Party Name', $challan['party_name']], ['Amount', money($challan['amount'])],
    ['No. of Items', $challan['item_count']], ['Challan Made By', $challan['made_name']], ['Pick Challan Timestamp', time_text($challan['pick_time'])], ['Picked By', $challan['picked_name']],
    ['Match Timestamp', time_text($challan['match_time'])], ['Matched By', $challan['matched_name']], ['Match Quantity', $challan['match_quantity']],
];
$photos = array_filter(range(1, PURCHASE_IN_PHOTOS), fn ($slot) => !empty($challan["photo_$slot"]));
$svg = fn (string $paths) => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths . '</svg>';
$me = (int) current_user()['id'];
$people = function (string $name) use ($users, $me) {
    $html = '';
    foreach ($users as $person) {
        $html .= '<option value="' . (int) $person['id'] . '"' . ((int) $person['id'] === $me ? ' selected' : '') . '>' . e($person['full_name']) . '</option>';
    }
    return "<select id=\"$name\" name=\"$name\" class=\"input\" required>$html</select>";
};
?>
<main class="page">
  <div class="doc-bar doc-bar-wide">
    <div class="doc-head">
      <a class="icon-back" href="<?= $back ?>" aria-label="Back" title="Back"><?= $svg('<path d="M19 12H5M11 6l-6 6 6 6"/>') ?></a>
      <div class="doc-name"><h1><?= e($challan['challan_no']) ?></h1><span class="pill <?= $status === 'DONE' ? 'UPLOADED' : 'PENDING' ?>"><?= e($label) ?></span></div>
    </div>
    <?php if ($manager): ?>
    <div class="doc-actions">
      <a class="icon-button primary" href="/sales-challan/<?= $id ?>/edit" aria-label="Edit" title="Edit"><?= $svg('<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>') ?></a>
      <form method="post" action="/sales-challan/<?= $id ?>/delete" data-two-step>
        <?= csrf_field() ?>
        <button type="button" class="icon-button danger" data-step-ask aria-label="Delete" title="Delete"><?= $svg('<path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/>') ?></button>
        <button type="button" class="btn" data-step-keep hidden>Keep</button>
        <button class="btn danger" data-step-do hidden>Confirm delete</button>
      </form>
    </div>
    <?php endif; ?>
  </div>
  <?php if ($status === 'PACKING'): ?>
  <form method="post" action="/sales-challan/<?= $id ?>/pick" class="pin-challan">
    <?= csrf_field() ?>
    <div class="field"><label for="picked_by">Picked By</label><?= $people('picked_by') ?></div>
    <button class="btn primary">Save</button>
  </form>
  <?php elseif ($status === 'MATCHING'): ?>
  <form method="post" action="/sales-challan/<?= $id ?>/match" enctype="multipart/form-data" class="pin-form" data-pin-photos data-need-photo id="sc-match" data-items="<?= (int) $challan['item_count'] ?>">
    <?= csrf_field() ?>
    <div class="pin-fields">
      <div class="field pin-wide"><label for="matched_by">Matched By</label><?= $people('matched_by') ?></div>
      <div class="field pin-wide"><label for="sc-quantity">Match Quantity</label><input id="sc-quantity" name="match_quantity" class="input" required inputmode="numeric" pattern="\d{1,7}"></div>
      <p class="error pin-span" id="sc-quantity-warn" hidden>Match Quantity is different from No. of Items (<?= (int) $challan['item_count'] ?>). You can still save.</p>
    </div>
    <?php $existing = []; $photoRequired = true; require __DIR__ . '/photo_slots.php'; ?>
    <p class="error" data-need-photo-msg hidden>Add Photo 1.</p>
    <div class="form-foot"><button class="btn primary">Save</button></div>
  </form>
  <?php endif; ?>
  <div class="split">
    <div class="side">
      <section class="card flush" id="doc-details">
        <h2>Details</h2>
        <?php foreach ($fields as [$name, $value]): if (trim((string) $value) === '') { continue; } ?><div class="kv"><span><?= e($name) ?></span><span><?= e($value) ?></span></div><?php endforeach; ?>
      </section>
    </div>
    <section class="card form" id="doc-photos" aria-label="Photos">
      <h2>Photos</h2>
      <div class="field">
        <?php if (!$photos): ?><p class="muted">No photo added.</p><?php else: ?>
        <div class="slots">
          <?php foreach ($photos as $slot): ?>
          <div class="slot"><div class="slot-name">Photo <?= $slot ?></div><a href="/api/sales-challan/<?= $id ?>/<?= $slot ?>" data-photo="Photo <?= $slot ?>" aria-label="Open Photo <?= $slot ?>"><img src="/api/sales-challan/<?= $id ?>/<?= $slot ?>" alt="Photo <?= $slot ?> preview"></a></div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </section>
  </div>
</main>
