<main class="page">
  <?php require __DIR__ . '/admin_nav.php'; ?>
  <form method="post" action="/admin/users/<?= (int) $employee['id'] ?>" enctype="multipart/form-data" class="card">
    <?= csrf_field() ?>
    <div class="row between">
      <h2><?= e($employee['full_name']) ?></h2>
      <span class="muted">Missing Document: <?= e(employee_missing($employee)) ?></span>
    </div>
    <?php require __DIR__ . '/employee_form.php'; ?>
    <div class="row"><button class="btn primary">Save</button><a class="icon-back" href="/admin/users" aria-label="Back to users" title="Back to users"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M11 6l-6 6 6 6"/></svg></a></div>
  </form>
</main>
