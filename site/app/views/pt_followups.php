<?php
$barTitle = ['today' => 'Today Collection', 'tomorrow' => 'Tomorrow', 'broken' => 'Broken Promises', 'all' => 'Follow Ups'][$mode];
$dateText = fn (string $iso) => $iso === '' ? '' : display_date($iso);
$groupChoices = $mode === 'all' ? ['date' => 'Date', 'caller' => 'Caller', 'mr' => 'MR', 'none' => 'No Group'] : ['mr' => 'MR', 'caller' => 'Caller', 'none' => 'No Group'];
$defaultGroup = array_key_first($groupChoices);
$query = array_filter(['q' => $filter['q'], 'from' => $dateText($filter['from']), 'to' => $dateText($filter['to']), 'group' => $filter['group'] === $defaultGroup ? '' : $filter['group']]);
$link = fn (int $number) => $path . '?' . http_build_query($query + ['page' => $number]);
$here = $path . ($query ? '?' . http_build_query($query) : '');
$position = array_flip(array_keys($groups));
$groupLast = null;
$color = 0;
$groupField = ['date' => 'followup_date', 'caller' => 'followup_by_name', 'mr' => 'mr'][$filter['group']] ?? null;
?>
<main class="page">
  <form method="get" action="<?= e($path) ?>" data-live-filter data-filter-form>
    <div class="search-row">
      <label for="q" class="visually-hidden">Search</label>
      <input id="q" name="q" class="input" type="search" placeholder="Search party, caller, MR, phone or remarks" value="<?= e($filter['q']) ?>">
      <button type="button" class="icon-button" data-filter-open aria-label="Open filters" title="Filters" aria-expanded="false">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 5h18l-7 8v6l-4 2v-8z"/></svg>
        <span class="filter-count" data-filter-count hidden></span>
      </button>
    </div>
    <aside class="filter-panel" data-filter-panel aria-label="Filters" hidden>
      <div class="row between center">
        <h2>Filters</h2>
        <button type="button" class="icon-button" data-filter-close aria-label="Close filters" title="Close"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button>
      </div>
      <?php if ($mode === 'all'): ?>
      <div class="field"><label for="from">Follow Up from date</label><input id="from" name="from" class="input" data-date value="<?= e($dateText($filter['from'])) ?>"></div>
      <div class="field"><label for="to">Follow Up to date</label><input id="to" name="to" class="input" data-date value="<?= e($dateText($filter['to'])) ?>"></div>
      <?php endif; ?>
      <div class="field" role="radiogroup" aria-labelledby="group-label">
        <span id="group-label" class="choice-title">Group By</span>
        <div class="choice-row">
          <?php foreach ($groupChoices as $value => $label): ?>
          <label class="choice"><input class="sr-input" type="radio" name="group" value="<?= e($value) ?>" data-default="<?= e($defaultGroup) ?>"<?= $filter['group'] === $value ? ' checked' : '' ?>><span><?= e($label) ?></span></label>
          <?php endforeach; ?>
        </div>
      </div>
      <button type="button" class="btn" data-filter-clear>Clear filters</button>
    </aside>
  </form>
  <div id="live-results">
  <div class="pt-summary">
    <div class="pt-stat"><span>Follow ups</span><b><?= number_format($total) ?></b></div>
    <div class="pt-stat amber"><span>Amount Promised</span><b><?= e(money($summary['promised'])) ?></b></div>
    <div class="pt-stat green"><span>Received</span><b><?= e(money($summary['received'])) ?></b></div>
  </div>
  <?php if (!$entries): ?><p class="muted"><?= ['today' => 'No payments promised for today.', 'tomorrow' => 'No payments promised for tomorrow.', 'broken' => 'No broken promises.', 'all' => 'No follow ups found.'][$mode] ?></p><?php else: ?>
  <div class="table-box">
    <table class="data">
      <thead><tr><th>Follow Up Date</th><th>Party Name</th><th class="right">Amount Due</th><th class="right">Amount Promised</th><th>Expected Payment Date</th><th>Follow Up By</th><th>Remarks</th><th>Time</th><th>Area</th><th>MR</th><th>Phone</th><th class="right">Received Amount</th><th>Amount Received Date</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($entries as $entry):
          $plain = preg_replace('/\s+/', ' ', $entry['party_name']);
          $href = '/payment-tracker/party?' . http_build_query(['name' => $entry['party_name'], 'back' => $here]);
          if ($groupField):
              $groupKey = (string) $entry[$groupField];
              if ($groupKey !== $groupLast):
                  $groupLast = $groupKey;
                  $color = ($position[$groupKey] ?? 0) % 10;
                  $info = $groups[$groupKey] ?? ['entries' => 0, 'promised' => 0];
                  $mrPhone = $filter['group'] === 'mr' ? ($mrPhones[mb_strtolower($groupKey)] ?? '') : '';
      ?>
        <tr class="group-row g<?= $color ?>"><td colspan="14"><span class="pt-actions"><strong><?= e($groupKey === '' ? 'Blank' : ($filter['group'] === 'date' ? display_date($groupKey) : $groupKey)) ?></strong> &middot; <?= number_format($info['entries']) ?> &middot; <?= e(money($info['promised'])) ?><?php if ($mrPhone !== '' && isset($messages[$groupKey])): ?> <?= pt_contact($mrPhone, $groupKey, $messages[$groupKey]) ?><?php endif; ?></span></td></tr>
      <?php endif; endif; ?>
        <tr class="row-link<?= $groupField ? ' g' . $color : '' ?>" data-href="<?= e($href) ?>">
          <td><?= e(display_date($entry['followup_date'])) ?></td>
          <td><a class="doc-link" href="<?= e($href) ?>"><?= e($entry['party_name']) ?></a></td>
          <td class="right"><?= $entry['amount_due'] === null ? '' : signed_money($entry['amount_due']) ?></td>
          <td class="right"><?= $entry['amount_promised'] === null ? '' : e(money($entry['amount_promised'])) ?></td>
          <td><?= e(display_date($entry['expected_date'])) ?></td>
          <td><?= e($entry['followup_by_name']) ?></td>
          <td><?= e($entry['remarks']) ?></td>
          <td><?= e($entry['time_slot']) ?></td>
          <td><?= e($entry['area']) ?></td>
          <td><?= e($entry['mr']) ?></td>
          <td><?= e($entry['phone']) ?></td>
          <td class="right"><?= $entry['received_amount'] === null ? '' : e(money($entry['received_amount'])) ?></td>
          <td><?= e(display_date($entry['received_date'])) ?></td>
          <td><div class="pt-actions">
            <?= pt_contact($entry['phone'], $plain, pt_party_message($plain, (float) ($entry['amount_promised'] ?? $entry['amount_due'] ?? 0))) ?>
            <a class="pt-icon ok" href="/payment-tracker/followups/<?= (int) $entry['id'] ?>/received?<?= e(http_build_query(['back' => $here])) ?>" aria-label="Mark received for <?= e($plain) ?>" title="Mark received"><?= pt_icon('ok') ?></a>
          </div></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
  <?= pager($page, $total, $link) ?>
  </div>
</main>
