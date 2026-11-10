<?php
/** Fields shared by Add and Edit. Expects $v (field reader), $users, $current (Challan No. being edited, or ''). */
$parties = array_values(array_unique(array_column(rows('SELECT name FROM parties WHERE is_active = 1 ORDER BY name LIMIT 5000'), 'name')));
$date = $v('challan_date') ?: date('d/m/Y');
$iso = parse_date($date);
$choices = $iso ? challan_choices($iso) : null;
$chosen = $v('challan_no') ?: $current;
$list = $choices ?? [];
if ($current !== '' && !in_array($current, $list, true)) { array_unshift($list, $current); }
?>
<div class="field"><label for="sc-number">Challan No.</label><select id="sc-number" name="challan_no" class="input" required data-current="<?= e($current) ?>"><option value="">Choose</option><?php foreach ($list as $number): ?><option<?= $number === $chosen ? ' selected' : '' ?>><?= e($number) ?></option><?php endforeach; ?></select></div>
<div class="field"><label for="sc-date">Challan Date</label><input id="sc-date" name="challan_date" class="input" required data-date value="<?= e($date) ?>"></div>
<?php $partyNow = $v('party_name'); if ($partyNow !== '' && !in_array($partyNow, $parties, true)) { array_unshift($parties, $partyNow); } ?>
<div class="field pin-wide"><label for="sc-party">Party Name</label><select id="sc-party" name="party_name" class="input" required><option value="">Choose</option><?php foreach ($parties as $party): ?><option<?= $party === $partyNow ? ' selected' : '' ?>><?= e($party) ?></option><?php endforeach; ?></select></div>
<div class="field"><label for="sc-amount">Amount</label><input id="sc-amount" name="amount" class="input" required inputmode="decimal" value="<?= e($v('amount')) ?>"></div>
<div class="field"><label for="sc-items">No. of Items</label><input id="sc-items" name="item_count" class="input" required inputmode="numeric" pattern="\d{1,7}" value="<?= e($v('item_count')) ?>"></div>
<div class="field pin-wide"><label for="sc-made">Challan Made By</label><select id="sc-made" name="made_by" class="input" required><?php foreach ($users as $person): ?><option value="<?= (int) $person['id'] ?>"<?= (int) $person['id'] === (int) ($v('made_by') ?: $defaultMade) ? ' selected' : '' ?>><?= e($person['full_name']) ?></option><?php endforeach; ?></select></div>
<p class="error pin-span" id="sc-number-msg"<?= $choices === null ? '' : ' hidden' ?>><?= $choices === null && $iso ? 'Set the first Challan No. for FY ' . e(challan_fy($iso)) . ' in Masters > Challan No.' : '' ?></p>
