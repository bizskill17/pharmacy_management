<?php
require __DIR__ . '/admin_nav.php';
$link = fn (int $number) => '/admin/challan-numbers?' . http_build_query(array_filter(['fy' => $fy, 'state' => $state, 'q' => $query, 'page' => $number]));
?>
<main class="page">
  <form method="post" action="/admin/challan-numbers" class="pin-challan">
    <?= csrf_field() ?>
    <div class="field"><label for="first-no">First Challan No. of the FY</label><input id="first-no" name="first_no" class="input" required maxlength="30" placeholder="FY27-28CA000001"></div>
    <button class="btn primary">Set first number</button>
  </form>
  <?php if ($summary): ?>
  <div class="table-box"><table class="data fill master-table">
    <thead><tr><th>FY</th><th>Series</th><th>Unused</th><th>Used</th></tr></thead>
    <tbody><?php foreach ($summary as $row): ?><tr><td><?= e($row['fy']) ?></td><td><?= e($row['prefix']) ?></td><td><?= number_format((int) $row['unused']) ?></td><td><?= number_format((int) $row['used']) ?></td></tr><?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
  <form method="get" class="pin-filters" data-live-filter>
    <div class="field"><label for="q">Search</label><input id="q" name="q" class="input" type="search" value="<?= e($query) ?>"></div>
    <div class="field"><label for="fy">FY</label><select id="fy" name="fy" class="input"><option value="">All</option><?php foreach ($summary as $row): ?><option<?= $row['fy'] === $fy ? ' selected' : '' ?>><?= e($row['fy']) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label for="state">Status</label><select id="state" name="state" class="input"><option value="">All</option><option value="unused"<?= $state === 'unused' ? ' selected' : '' ?>>Unused</option><option value="used"<?= $state === 'used' ? ' selected' : '' ?>>Used</option></select></div>
  </form>
  <div id="live-results">
  <?php if (!$numbers): ?><p class="muted">No Challan No. yet.</p><?php else: ?>
  <div class="table-box"><table class="data fill master-table">
    <thead><tr><th>Challan No.</th><th>FY</th><th>Status</th><th>Party Name</th><th>Challan Date</th></tr></thead>
    <tbody>
    <?php foreach ($numbers as $number): $used = $number['used_by'] !== null; ?>
      <tr<?= $used ? ' class="row-link" data-href="/sales-challan/' . (int) $number['used_by'] . '"' : '' ?>><td><?= e($number['challan_no']) ?></td><td><?= e($number['fy']) ?></td><td><span class="pill <?= $used ? 'UPLOADED' : 'PENDING' ?>"><?= $used ? 'Used' : 'Unused' ?></span></td><td><?= e($number['party_name']) ?></td><td><?= e(display_date($number['challan_date'])) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
  <?= pager($page, $total, $link) ?>
  </div>
</main>
