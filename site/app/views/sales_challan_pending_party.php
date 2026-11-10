<?php
$barTitle = 'Sales Challan';
$link = fn (int $number) => '/sales-challan/pending-party?' . http_build_query(array_filter(['q' => $query, 'page' => $number]));
$pencil = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>';
?>
<main class="page">
  <form method="get" data-live-filter>
    <label for="q" class="visually-hidden">Search</label>
    <input id="q" name="q" class="input" type="search" placeholder="Search Challan No. or party" value="<?= e($query) ?>">
  </form>
  <div id="live-results">
  <?php if (!$challans): ?><p class="muted">Every challan has a party from Party Master.</p><?php else: ?>
  <div class="table-box">
    <table class="data fill master-table">
      <thead><tr><th>Challan No.</th><th>Challan Date</th><th>Party Name (typed)</th><th class="right">Amount</th><th>Update</th></tr></thead>
      <tbody>
      <?php foreach ($challans as $challan): ?>
        <tr>
          <td><?= e($challan['challan_no']) ?></td>
          <td><?= e(display_date($challan['challan_date'])) ?></td>
          <td><?= e($challan['party_name']) ?></td>
          <td class="right"><?= e(money($challan['amount'])) ?></td>
          <td class="cell-center"><a class="icon-button primary row-icon" href="/sales-challan/<?= (int) $challan['id'] ?>/party" aria-label="Update party for <?= e($challan['challan_no']) ?>" title="Update party"><?= $pencil ?></a></td>
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
