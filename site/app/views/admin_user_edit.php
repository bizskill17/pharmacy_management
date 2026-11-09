<main class="page">
  <?php require __DIR__ . '/admin_nav.php'; ?>
  <form method="post" action="/admin/users/<?= (int) $employee['id'] ?>" enctype="multipart/form-data" class="card">
    <?= csrf_field() ?>
    <div class="row between">
      <h2><?= e($employee['full_name']) ?></h2>
      <span class="muted">Missing Document: <?= e(employee_missing($employee)) ?></span>
    </div>
    <?php require __DIR__ . '/employee_form.php'; ?>
    <div class="row"><button class="btn primary">Save</button><a class="btn" href="/admin/users">Back to users</a></div>
  </form>
</main>
