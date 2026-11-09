<main class="page">
  <?php require __DIR__ . '/admin_nav.php'; ?>
  <details class="card">
    <summary><strong>Add employee</strong></summary>
    <form method="post" action="/admin/users" enctype="multipart/form-data" class="stack">
      <?= csrf_field() ?>
      <?php $employee = null; require __DIR__ . '/employee_form.php'; ?>
      <div><button class="btn primary">Add user</button></div>
    </form>
  </details>
  <div class="table-box">
    <table class="data">
      <thead><tr>
        <th>Employee Name</th><th>Phone No</th><th>Email</th><th>Address</th><th>Photo</th><th>PAN Card</th><th>Aadhar Card Front</th><th>Aadhar Card Back</th><th>Designation</th><th>Missing Document</th><th>Passcode</th><th class="right">Salary</th><th>Joining Date</th><th>Status</th><th>Role</th>
      </tr></thead>
      <tbody>
      <?php foreach ($users as $listed): $listedId = (int) $listed['id']; ?>
        <tr>
          <td><a class="doc-link" href="/admin/users/<?= $listedId ?>"><?= e($listed['full_name']) ?></a></td>
          <td><?= e($listed['phone']) ?></td>
          <td><?= e($listed['email'] ?? '-') ?></td>
          <td><?= e($listed['address'] ?? '-') ?></td>
          <?php foreach (EMPLOYEE_FILES as $field => $column): ?>
          <td><?php if (!empty($listed[$column])): ?><a href="/api/users/<?= $listedId ?>/<?= e($field) ?>" data-photo="<?= e(EMPLOYEE_FILE_LABELS[$field]) ?>" aria-label="Open <?= e(EMPLOYEE_FILE_LABELS[$field]) ?>"><img class="thumb" src="/api/users/<?= $listedId ?>/<?= e($field) ?>" alt="<?= e(EMPLOYEE_FILE_LABELS[$field]) ?> preview" loading="lazy"></a><?php else: ?>-<?php endif; ?></td>
          <?php endforeach; ?>
          <td><?= e($listed['designation'] ?? '-') ?></td>
          <td><?= e(employee_missing($listed)) ?></td>
          <td>Hidden</td>
          <td class="right"><?= $listed['salary'] === null ? '-' : e(money($listed['salary'])) ?></td>
          <td><?= e(display_date($listed['joining_date'])) ?></td>
          <td><span class="pill <?= $listed['is_active'] ? 'UPLOADED' : 'DELETED' ?>"><?= $listed['is_active'] ? 'Active' : 'Inactive' ?></span></td>
          <td><?= e(ucfirst(strtolower($listed['role']))) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</main>
