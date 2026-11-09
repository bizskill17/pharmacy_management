<?php
$barTitle = 'Sales Challan';
$id = (int) $challan['id'];
$defaults = ['challan_date' => display_date($challan['challan_date']), 'party_name' => $challan['party_name'], 'amount' => (string) (float) $challan['amount'], 'item_count' => $challan['item_count'], 'made_by' => $challan['made_by'], 'picked_by' => $challan['picked_by'], 'matched_by' => $challan['matched_by'], 'match_quantity' => $challan['match_quantity']];
$v = fn (string $key) => (string) ($old[$key] ?? $defaults[$key] ?? '');
$current = $challan['challan_no'];
$defaultMade = (int) $challan['made_by'];
$select = function (string $name, string $label) use ($users, $v) {
    $html = '';
    foreach ($users as $person) {
        $html .= '<option value="' . (int) $person['id'] . '"' . ((int) $person['id'] === (int) $v($name) ? ' selected' : '') . '>' . e($person['full_name']) . '</option>';
    }
    return "<div class=\"field pin-wide\"><label for=\"$name\">$label</label><select id=\"$name\" name=\"$name\" class=\"input\" required>$html</select></div>";
};
$existing = [];
for ($slot = 1; $slot <= PURCHASE_IN_PHOTOS; $slot++) {
    if ($challan["photo_$slot"]) { $existing[$slot] = "/api/sales-challan/$id/$slot"; }
}
?>
<main class="page">
  <div class="doc-bar doc-bar-wide"><div class="doc-head">
    <a class="icon-back" href="/sales-challan/<?= $id ?>" aria-label="Back" title="Back"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M11 6l-6 6 6 6"/></svg></a>
    <div class="doc-name"><h1><?= e($challan['challan_no']) ?></h1></div>
  </div></div>
  <?php if ($error): ?><p class="error" role="alert"><?= e($error) ?></p><?php endif; ?>
  <form method="post" action="/sales-challan/<?= $id ?>/edit" enctype="multipart/form-data" id="sc-form" class="pin-form"<?= $challan['match_time'] ? ' data-pin-photos data-need-photo' : '' ?>>
    <?= csrf_field() ?>
    <div class="pin-fields">
      <?php require __DIR__ . '/sales_challan_fields.php'; ?>
      <?php if ($challan['pick_time']): ?><?= $select('picked_by', 'Picked By') ?><?php endif; ?>
      <?php if ($challan['match_time']): ?><?= $select('matched_by', 'Matched By') ?><div class="field pin-wide"><label for="sc-quantity">Match Quantity</label><input id="sc-quantity" name="match_quantity" class="input" required inputmode="numeric" pattern="\d{1,7}" value="<?= e($v('match_quantity')) ?>"></div><?php endif; ?>
    </div>
    <?php if ($challan['match_time']): $photoRequired = true; require __DIR__ . '/photo_slots.php'; ?><p class="error" data-need-photo-msg hidden>Keep or add at least Photo 1.</p><?php endif; ?>
    <div class="form-foot"><button class="btn primary" id="sc-save">Save</button></div>
  </form>
</main>
