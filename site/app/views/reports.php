<?php $barTitle = $register['label'] . ' Reports'; ?>
<main class="page">
  <div class="row"><a class="btn" href="/api/reports/export?type=<?= e($register['slug']) ?>">Export to Excel</a></div>
  <div class="grid-auto">
    <section class="card flush">
      <h2>Pending by date</h2>
      <?php if (!$pending): ?><p class="muted">Nothing pending.</p><?php endif; ?>
      <?php foreach ($pending as $row): ?><div class="kv"><span><?= e($row['date']) ?></span><span><?= (int) $row['count'] ?></span></div><?php endforeach; ?>
    </section>
    <section class="card flush">
      <h2>Uploaded today</h2>
      <div class="kv"><span><?= e($register['label']) ?></span><span><?= (int) $today ?></span></div>
    </section>
    <section class="card flush">
      <h2>Missing numbers</h2>
      <?php if (!$missing): ?><p class="muted">No gaps found.</p><?php endif; ?>
      <?php foreach ($missing as $row): ?><div class="kv"><span><?= e($row['label']) ?>: <?= e($row['from'] === $row['to'] ? $row['from'] : $row['from'] . ' to ' . $row['to']) ?></span><span><?= (int) $row['count'] ?></span></div><?php endforeach; ?>
    </section>
  </div>
</main>
