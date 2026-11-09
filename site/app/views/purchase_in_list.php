<?php
$barTitle = 'Purchase In';
$link = fn (int $number) => $path . '?' . http_build_query(array_filter(['q' => $query, 'page' => $number]));
?>
<main class="page">
  <form method="get" data-live-filter>
    <label for="q" class="visually-hidden">Search</label>
    <input id="q" name="q" class="input" type="search" placeholder="Search Sl. No., party, reference or challan" value="<?= e($query) ?>">
  </form>
  <div id="live-results">
  <?php if (!$entries): ?><p class="muted">No entries found.</p><?php else: ?>
  <div class="table-box">
    <table class="data">
      <thead><tr><th>Date</th><th>Timestamp</th><th>Sl. No.</th><th>Party Name</th><th class="right">No. of Packets</th><th class="right">Number Of Bill</th><th>Reference No.</th><th class="right">Bill Amount</th><th>Timestamp Challan</th><th>Challan No.</th><th>Status</th><th>Entry By</th><?php for ($slot = 1; $slot <= PURCHASE_IN_PHOTOS; $slot++): ?><th>Photo <?= $slot ?></th><?php endfor; ?><th>Party No</th></tr></thead>
      <tbody>
      <?php foreach ($entries as $entry): $id = (int) $entry['id']; ?>
        <tr class="row-link" data-href="/purchase-in/<?= $id ?>">
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
