<?php
$barTitle = 'Purchase In';
$link = fn (int $number) => '/purchase-in/pending-party?' . http_build_query(array_filter(['q' => $filter['q'], 'page' => $number]));
$pencil = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>';
?>
<main class="page">
  <form method="get" data-live-filter>
    <label for="q" class="visually-hidden">Search</label>
    <input id="q" name="q" class="input" type="search" placeholder="Search Sl. No. or party" value="<?= e($filter['q']) ?>">
  </form>
  <div id="live-results">
  <?php if (!$entries): ?><p class="muted">Every entry has a party from Party Master.</p><?php else: ?>
  <div class="table-box">
    <table class="data fill master-table">
      <thead><tr><th>Sl. No.</th><th>Date</th><th>Party Name (typed)</th><th class="right">Bill Amount</th><th>Party No</th><th>Update</th></tr></thead>
      <tbody>
      <?php foreach ($entries as $entry): ?>
        <tr>
          <td><?= (int) $entry['sl_no'] ?></td>
          <td><?= e(display_date($entry['entry_date'])) ?></td>
          <td><?= e($entry['party_name']) ?></td>
          <td class="right"><?= e(money($entry['bill_amount'])) ?></td>
          <td><?= e($entry['party_no']) ?></td>
          <td class="cell-center"><a class="icon-button primary row-icon" href="/purchase-in/<?= (int) $entry['id'] ?>/party" aria-label="Update party for Sl. No. <?= (int) $entry['sl_no'] ?>" title="Update party"><?= $pencil ?></a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p><?= number_format($total) ?> waiting</p>
  <?php endif; ?>
  <?= pager($page, $total, $link) ?>
  </div>
</main>
