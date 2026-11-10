<?php
$barTitle = 'Sales Challan';
$link = fn (int $number) => $path . '?' . http_build_query(array_filter(['q' => $query, 'page' => $number]));
?>
<main class="page">
  <form method="get" data-live-filter>
    <label for="q" class="visually-hidden">Search</label>
    <input id="q" name="q" class="input" type="search" placeholder="Search Challan No. or party" value="<?= e($query) ?>">
  </form>
  <div id="live-results">
  <?php if (!$challans): ?><p class="muted">No challans found.</p><?php else: ?>
  <div class="table-box">
    <table class="data">
      <thead><tr><th>Challan No.</th><th>Challan Date</th><th>Party Name</th><th class="right">Amount</th><th class="right">No. of Items</th><th>Challan Made By</th><th>Pick Challan Timestamp</th><th>Picked By</th><th>Match Timestamp</th><th>Matched By</th><th class="right">Match Quantity</th><?php for ($slot = 1; $slot <= PURCHASE_IN_PHOTOS; $slot++): ?><th>Photo <?= $slot ?></th><?php endfor; ?></tr></thead>
      <tbody>
      <?php foreach ($challans as $challan): $id = (int) $challan['id']; ?>
        <tr class="row-link" data-href="/sales-challan/<?= $id ?>">
          <td><a class="doc-link" href="/sales-challan/<?= $id ?>"><?= e($challan['challan_no']) ?></a></td>
          <td><?= e(display_date($challan['challan_date'])) ?></td>
          <td><?= e($challan['party_name']) ?></td>
          <td class="right"><?= e(money($challan['amount'])) ?></td>
          <td class="right"><?= (int) $challan['item_count'] ?></td>
          <td><?= e($challan['made_name']) ?></td>
          <td><?= e(time_text($challan['pick_time'])) ?></td>
          <td><?= e($challan['picked_name']) ?></td>
          <td><?= e(time_text($challan['match_time'])) ?></td>
          <td><?= e($challan['matched_name']) ?></td>
          <td class="right"><?= $challan['match_quantity'] === null ? '' : (int) $challan['match_quantity'] ?></td>
          <?php for ($slot = 1; $slot <= PURCHASE_IN_PHOTOS; $slot++): ?>
          <td><?php if ($challan["photo_$slot"]): ?><a href="/api/sales-challan/<?= $id ?>/<?= $slot ?>" data-photo="Photo <?= $slot ?>" aria-label="Open Photo <?= $slot ?>"><img class="thumb" src="/api/sales-challan/<?= $id ?>/<?= $slot ?>?thumb=1" alt="Photo <?= $slot ?> preview" loading="lazy"></a><?php endif; ?></td>
          <?php endfor; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
  <?= pager($page, $total, $link) ?>
  </div>
</main>
