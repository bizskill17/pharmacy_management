<?php
$barTitle = 'Purchase In';
$dateText = fn (string $iso) => $iso === '' ? '' : display_date($iso);
$link = fn (int $number) => $path . '?' . http_build_query(array_filter(['q' => $filter['q'], 'from' => $dateText($filter['from']), 'to' => $dateText($filter['to']), 'group' => $filter['group'] === 'date' ? '' : $filter['group'], 'page' => $number]));
$position = array_flip(array_keys($groups));
$groupLast = null;
$color = 0;
?>
<main class="page">
  <form method="get" data-live-filter data-filter-form>
    <div class="search-row">
      <label for="q" class="visually-hidden">Search</label>
      <input id="q" name="q" class="input" type="search" placeholder="Search Sl. No., party, reference or challan" value="<?= e($filter['q']) ?>">
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
      <div class="field"><label for="to">To date</label><input id="to" name="to" class="input" data-date value="<?= e($dateText($filter['to'])) ?>"></div>
      <div class="field" role="radiogroup" aria-labelledby="group-label">
        <span id="group-label" class="choice-title">Group By</span>
        <div class="choice-row">
          <?php foreach (['date' => 'Date', 'supplier' => 'Supplier'] as $value => $label): ?>
          <label class="choice"><input class="sr-input" type="radio" name="group" value="<?= e($value) ?>" data-default="date"<?= $filter['group'] === $value ? ' checked' : '' ?>><span><?= e($label) ?></span></label>
          <?php endforeach; ?>
        </div>
      </div>
      <button type="button" class="btn" data-filter-clear>Clear filters</button>
    </aside>
  </form>
  <div id="live-results">
  <?php if (!$entries): ?><p class="muted">No entries found.</p><?php else: ?>
  <div class="table-box">
    <table class="data">
      <thead><tr><th>Date</th><th>Timestamp</th><th>Sl. No.</th><th>Party Name</th><th class="right">No. of Packets</th><th class="right">Number Of Bill</th><th>Reference No.</th><th class="right">Bill Amount</th><th>Timestamp Challan</th><th>Challan No.</th><th>Status</th><th>Entry By</th><?php for ($slot = 1; $slot <= PURCHASE_IN_PHOTOS; $slot++): ?><th>Photo <?= $slot ?></th><?php endfor; ?><th>Party No</th></tr></thead>
      <tbody>
      <?php foreach ($entries as $entry):
          $id = (int) $entry['id'];
          $groupKey = (string) ($filter['group'] === 'date' ? $entry['entry_date'] : $entry['supplier_name']);
          if ($groupKey !== $groupLast):
              $groupLast = $groupKey;
              $color = ($position[$groupKey] ?? 0) % 10;
      ?>
        <tr class="group-row g<?= $color ?>"><td colspan="<?= 13 + PURCHASE_IN_PHOTOS ?>"><span><strong><?= e($filter['group'] === 'date' ? display_date($groupKey) : $groupKey) ?></strong> &middot; <?= number_format($groups[$groupKey] ?? 0) ?></span></td></tr>
      <?php endif; ?>
        <tr class="row-link g<?= $color ?>" data-href="/purchase-in/<?= $id ?>">
          <td><?= e(display_date($entry['entry_date'])) ?></td>
          <td><?= e(time_text($entry['entry_time'])) ?></td>
          <td><a class="doc-link" href="/purchase-in/<?= $id ?>"><?= (int) $entry['sl_no'] ?></a></td>
          <td><?= e($entry['party_name']) ?></td>
          <td class="right"><?= (int) $entry['packets'] ?></td>
          <td class="right"><?= (int) $entry['bill_count'] ?></td>
          <td><?= e($entry['reference_no']) ?></td>
          <td class="right"><?= e(money($entry['bill_amount'])) ?></td>
          <td><?= e(time_text($entry['challan_time'])) ?></td>
          <td><?= e($entry['challan_no']) ?></td>
          <td><span class="pill <?= $entry['status'] === 'DONE' ? 'UPLOADED' : 'PENDING' ?>"><?= $entry['status'] === 'DONE' ? 'Done' : 'Pending' ?></span></td>
          <td><?= e($entry['entry_name']) ?></td>
          <?php for ($slot = 1; $slot <= PURCHASE_IN_PHOTOS; $slot++): ?>
          <td><?php if ($entry["photo_$slot"]): ?><a href="/api/purchase-in/<?= $id ?>/<?= $slot ?>" data-photo="Photo <?= $slot ?>" aria-label="Open Photo <?= $slot ?>"><img class="thumb" src="/api/purchase-in/<?= $id ?>/<?= $slot ?>" alt="Photo <?= $slot ?> preview" loading="lazy"></a><?php endif; ?></td>
          <?php endfor; ?>
          <td><?= e($entry['party_no']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
  <?= pager($page, $total, $link) ?>
  </div>
</main>
