<?php
$barTitle = 'Payment Due';
$dateText = fn (string $iso) => $iso === '' ? '' : display_date($iso);
$query = array_filter(['q' => $filter['q'], 'from' => $dateText($filter['from']), 'to' => $dateText($filter['to']), 'area' => $filter['area'], 'mr' => $filter['mr'], 'band' => $filter['band'], 'group' => $filter['group'] === 'none' ? '' : $filter['group']]);
$link = fn (int $number) => '/payment-tracker/due?' . http_build_query($query + ['page' => $number]);
$here = '/payment-tracker/due' . ($query ? '?' . http_build_query($query) : '') . (requested_page() > 1 ? ($query ? '&' : '?') . 'page=' . $page : '');
$position = array_flip(array_keys($groups));
$groupLast = null;
$color = 0;
?>
<main class="page">
  <form method="get" action="/payment-tracker/due" data-live-filter data-filter-form>
    <div class="search-row">
      <label for="q" class="visually-hidden">Search</label>
      <input id="q" name="q" class="input" type="search" placeholder="Search party name or phone" value="<?= e($filter['q']) ?>">
      <a class="icon-button" href="/payment-tracker/due?<?= e(http_build_query($query + ['export' => 1])) ?>" aria-label="Download as Excel" title="Download as Excel"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4v12M7 11l5 5 5-5"/><path d="M4 20h16"/></svg></a>
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
      <div class="field"><label for="from">From date</label><input id="from" name="from" class="input" data-date value="<?= e($dateText($filter['from'])) ?>"></div>
      <div class="field"><label for="to">Due up to date</label><input id="to" name="to" class="input" data-date value="<?= e($dateText($filter['to'])) ?>"></div>
      <div class="field"><label for="area">Area</label><select id="area" name="area" class="input"><option value="">All Areas</option><?php foreach ($areas as $area): ?><option<?= $area === $filter['area'] ? ' selected' : '' ?>><?= e($area) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label for="mr">MR</label><select id="mr" name="mr" class="input"><option value="">All MR</option><?php foreach ($mrs as $mr): ?><option<?= $mr === $filter['mr'] ? ' selected' : '' ?>><?= e($mr) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label for="band">Net Balance</label><select id="band" name="band" class="input"><option value="">Any amount</option><?php foreach (PT_BANDS as $key => [$label]): ?><option value="<?= e($key) ?>"<?= $key === $filter['band'] ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
      <div class="field" role="radiogroup" aria-labelledby="group-label">
        <span id="group-label" class="choice-title">Group By</span>
        <div class="choice-row">
          <?php foreach (['mr' => 'MR', 'area' => 'Area', 'none' => 'No Group'] as $value => $label): ?>
          <label class="choice"><input class="sr-input" type="radio" name="group" value="<?= e($value) ?>" data-default="none"<?= $filter['group'] === $value ? ' checked' : '' ?>><span><?= e($label) ?></span></label>
          <?php endforeach; ?>
        </div>
      </div>
      <button type="button" class="btn" data-filter-clear>Clear filters</button>
    </aside>
  </form>
  <div id="live-results">
  <div class="pt-summary">
    <div class="pt-stat"><span>Total Net Balance Due</span><b><?= e(money($net)) ?></b></div>
    <div class="pt-stat amber"><span>Parties</span><b><?= number_format($total) ?></b></div>
    <div class="pt-stat green"><span>Last Upload</span><b><?= $upload ? e(time_text($upload['uploaded_at'])) : 'Not uploaded yet' ?></b></div>
  </div>
  <?php if (!$parties): ?><p class="muted"><?= $upload ? 'No parties with a balance due.' : 'Upload the outstanding file to see Payment Due.' ?></p><?php else: ?>
  <div class="table-box">
    <table class="data">
      <thead><tr><th>Party Name</th><th class="right">Total Balance</th><th class="right">Negative After Due Date</th><th class="right">Net Balance</th><th>Area</th><th>MR</th><th>Last Follow Up</th><th class="right">Promised</th><th>Expected Date</th><th>Remarks</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($parties as $party):
          $href = '/payment-tracker/party?' . http_build_query(['name' => $party['party_name'], 'back' => $here]);
          if ($filter['group'] !== 'none'):
              $groupKey = (string) $party[$filter['group']];
              if ($groupKey !== $groupLast):
                  $groupLast = $groupKey;
                  $color = ($position[$groupKey] ?? 0) % 10;
                  $info = $groups[$groupKey] ?? ['parties' => 0, 'net' => 0];
      ?>
        <tr class="group-row g<?= $color ?>"><td colspan="11"><span><strong><?= e($groupKey === '' ? 'Blank' : $groupKey) ?></strong> &middot; <?= number_format($info['parties']) ?> &middot; <?= e(money($info['net'])) ?></span></td></tr>
      <?php endif; endif; ?>
        <tr class="row-link<?= $filter['group'] !== 'none' ? ' g' . $color : '' ?>" data-href="<?= e($href) ?>">
          <td><a class="doc-link" href="<?= e($href) ?>"><?= e($party['party_name']) ?></a></td>
          <td class="right"><?= signed_money($party['total']) ?></td>
          <td class="right"><?= signed_money($party['negative']) ?></td>
          <td class="right"><strong><?= e(money($party['net'])) ?></strong></td>
          <td><?= e($party['area']) ?></td>
          <td><?= e($party['mr']) ?></td>
          <td><?= e(display_date($party['last_date'])) ?><?= $party['last_by'] ? ' &middot; ' . e($party['last_by']) : '' ?></td>
          <td class="right"><?= $party['last_promised'] === null ? '' : e(money($party['last_promised'])) ?></td>
          <td><?= e(display_date($party['last_expected'])) ?></td>
          <td><?= e($party['last_remarks']) ?></td>
          <td><div class="pt-actions">
            <?= pt_contact($party['phone'], preg_replace('/\s+/', ' ', $party['party_name']), pt_party_message(preg_replace('/\s+/', ' ', $party['party_name']), (float) $party['net'])) ?>
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
