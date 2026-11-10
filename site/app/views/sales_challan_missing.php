<?php
$barTitle = 'Sales Challan';
$link = fn (int $number) => '/sales-challan/missing?' . http_build_query(array_filter(['q' => $query, 'page' => $number]));
?>
<main class="page">
  <form method="get" data-live-filter>
    <label for="q" class="visually-hidden">Search</label>
    <input id="q" name="q" class="input" type="search" placeholder="Search Challan No." value="<?= e($query) ?>">
  </form>
  <div id="live-results">
  <?php if (!$numbers): ?><p class="muted">No missing Challan No.</p><?php else: ?>
  <div class="table-box">
    <table class="data fill master-table">
      <thead><tr><th>Challan No.</th><th>FY</th></tr></thead>
      <tbody><?php foreach ($numbers as $number): ?><tr><td><?= e($number['challan_no']) ?></td><td><?= e($number['fy']) ?></td></tr><?php endforeach; ?></tbody>
    </table>
  </div>
  <p><?= number_format($total) ?> missing</p>
  <?php endif; ?>
  <?= pager($page, $total, $link) ?>
  </div>
</main>
