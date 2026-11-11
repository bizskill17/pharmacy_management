<?php
$barTitle = 'Payment Reports';
$slots = array_merge(PT_HOURS, array_filter(['Other'], fn ($slot) => array_filter($calls, fn ($caller) => isset($caller[$slot]))));
?>
<main class="page">
  <form method="get" action="/payment-tracker/reports" class="row">
    <div class="field"><label for="day">Date</label><input id="day" name="day" class="input" data-date value="<?= e(display_date($day)) ?>"></div>
    <button class="btn primary end">Show</button>
  </form>
  <div class="pt-summary">
    <div class="pt-stat"><span>Follow ups on <?= e(display_date($day)) ?></span><b><?= number_format((int) $promises['entries']) ?></b></div>
    <div class="pt-stat amber"><span>Amount Promised</span><b><?= e(money($promises['promised'])) ?></b></div>
    <div class="pt-stat green"><span>Received</span><b><?= e(money($promises['received'])) ?></b></div>
  </div>
  <section class="card flush">
    <h2>Follow ups by caller and hour</h2>
    <?php if (!$calls): ?><p class="muted">No follow ups on this date.</p><?php else: ?>
    <div class="table-box"><table class="data pt-hours">
      <thead><tr><th>Name</th><?php foreach ($slots as $slot): ?><th><?= e($slot) ?></th><?php endforeach; ?><th>Total</th></tr></thead>
      <tbody><?php foreach ($calls as $caller => $hours): ?><tr><td style="text-align:left"><?= e($caller) ?></td><?php foreach ($slots as $slot): ?><td><?= isset($hours[$slot]) ? (int) $hours[$slot] : '' ?></td><?php endforeach; ?><td><strong><?= array_sum($hours) ?></strong></td></tr><?php endforeach; ?></tbody>
    </table></div>
    <?php endif; ?>
  </section>
  <div class="grid-auto">
    <section class="card flush">
      <h2>Balance groups</h2>
      <?php foreach ($bands as $key => $band): ?><div class="kv"><span><a href="/payment-tracker/due?band=<?= e($key) ?>"><?= e($band['label']) ?></a> &middot; <?= number_format($band['parties']) ?> parties</span><span><?= e(money($band['net'])) ?></span></div><?php endforeach; ?>
    </section>
    <section class="card flush">
      <h2>Outstanding by MR</h2>
      <?php if (!$byMr): ?><p class="muted">Nothing due.</p><?php endif; ?>
      <?php foreach ($byMr as $item): ?><div class="kv"><span><a href="/payment-tracker/due?<?= e(http_build_query(['mr' => $item['name']])) ?>"><?= e($item['name'] ?: 'Blank') ?></a> &middot; <?= number_format((int) $item['parties']) ?></span><span><?= e(money($item['net'])) ?></span></div><?php endforeach; ?>
    </section>
    <section class="card flush">
      <h2>Outstanding by Area</h2>
      <?php if (!$byArea): ?><p class="muted">Nothing due.</p><?php endif; ?>
      <?php foreach ($byArea as $item): ?><div class="kv"><span><a href="/payment-tracker/due?<?= e(http_build_query(['area' => $item['name']])) ?>"><?= e($item['name'] ?: 'Blank') ?></a> &middot; <?= number_format((int) $item['parties']) ?></span><span><?= e(money($item['net'])) ?></span></div><?php endforeach; ?>
    </section>
  </div>
</main>
