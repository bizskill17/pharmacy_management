<?php require __DIR__ . '/admin_nav.php'; ?>
<main class="page">
  <?php if ($error): ?><p class="error" role="alert"><?= e($error) ?></p><?php endif; ?>
  <section class="card">
    <h2>Bulk upload employees</h2>
    <p class="muted">Columns: Employee Name, Phone No, Email, Address, Passcode, Salary, Status. Everyone is added as Uploader.</p>
    <form method="post" action="/admin/users/bulk" enctype="multipart/form-data" class="row">
      <?= csrf_field() ?>
      <a class="btn" href="/admin/users/template">Download template</a>
      <input type="hidden" name="action" value="check">
      <input class="input pin-file" type="file" name="file" accept=".xlsx,.csv" required aria-label="Filled file">
      <button class="btn primary">Check file</button>
    </form>
  </section>
  <?php if ($rows !== null):
      $count = fn (string $outcome) => count(array_filter($rows, fn ($row) => $row['outcome'] === $outcome));
  ?>
  <section class="card">
    <div class="row between">
      <div><h2>Check the file</h2><p class="muted"><?= $count('ready') ?> ready, <?= $count('skipped') ?> skipped, <?= $count('failed') ?> failed</p></div>
      <?php if ($count('ready') > 0): ?><form method="post" action="/admin/users/bulk"><?= csrf_field() ?><input type="hidden" name="action" value="save"><button class="btn primary">Save valid rows</button></form><?php endif; ?>
    </div>
    <div class="table-box"><table class="data fill master-table">
      <thead><tr><th>Row</th><th>Employee Name</th><th>Phone No</th><th>Email</th><th>Address</th><th>Salary</th><th>Status</th><th>Result</th></tr></thead>
      <tbody><?php foreach ($rows as $row): ?><tr><td><?= (int) $row['row'] ?></td><td><?= e($row['name']) ?></td><td><?= e($row['phone']) ?></td><td><?= e($row['email']) ?></td><td><?= e($row['address']) ?></td><td><?= $row['salary'] === null ? '' : e(money($row['salary'])) ?></td><td><?= $row['active'] ? 'Active' : 'Inactive' ?></td><td><?= e(ucfirst($row['outcome']) . ($row['reason'] ? ': ' . $row['reason'] : '')) ?></td></tr><?php endforeach; ?></tbody>
    </table></div>
  </section>
  <?php endif; ?>
</main>
