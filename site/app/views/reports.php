<main class="page">
  <div class="row between">
    <h1>Reports</h1>
    <a class="btn" href="/api/reports/export">Export to Excel</a>
  </div>
  <div class="grid-auto">
    <section class="card flush">
      <h2>Pending by date</h2>
      <?php if (!$pending): ?><p class="muted">Nothing pending.</p><?php endif; ?>
      <?php foreach ($pending as $row): ?><div class="kv"><span><?= e($row['date']) ?> &middot; <?= e($row['label']) ?></span><span><?= (int) $row['count'] ?></span></div><?php endforeach; ?>
    </section>
    <section class="card flush">
      <h2>Uploaded today</h2>
      <?php foreach ($today as $row): ?><div class="kv"><span><?= e($row['label']) ?></span><span><?= (int) $row['count'] ?></span></div><?php endforeach; ?>
    </section>
    <section class="card flush">
      <h2>Missing numbers</h2>
      <?php if (!$missing): ?><p class="muted">No gaps found.</p><?php endif; ?>
      <?php foreach ($missing as $row): ?><div class="kv"><span><?= e($row['label']) ?>: <?= e($row['from'] === $row['to'] ? $row['from'] : $row['from'] . ' to ' . $row['to']) ?></span><span><?= (int) $row['count'] ?></span></div><?php endforeach; ?>
    </section>
  </div>
</main>
