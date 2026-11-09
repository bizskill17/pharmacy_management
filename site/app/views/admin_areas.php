<main class="page">
  <?php require __DIR__ . '/admin_nav.php'; ?>
  <form method="post" action="/admin/areas" class="card">
    <?= csrf_field() ?>
    <h2>Add area</h2>
    <div class="field"><label for="a-name">Area name</label><input id="a-name" name="name" class="input" required maxlength="100"></div>
    <div><button class="btn primary">Add area</button></div>
  </form>
  <?php if (!$areas): ?><p class="muted">No areas yet.</p><?php else: ?>
  <div class="table-box">
    <table class="data fill master-table">
      <thead><tr><th>Area name</th><th>Status</th><th>Action</th></tr></thead>
      <tbody>
      <?php foreach ($areas as $area): ?>
        <tr>
          <td><?= e($area['name']) ?></td>
          <td class="cell-center"><span class="pill <?= $area['is_active'] ? 'UPLOADED' : 'DELETED' ?>"><?= $area['is_active'] ? 'Active' : 'Inactive' ?></span></td>
          <td class="cell-center"><form method="post" action="/admin/areas/toggle"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $area['id'] ?>"><button class="btn"><?= $area['is_active'] ? 'Deactivate' : 'Activate' ?></button></form></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
  <?= pager($page, $total, fn ($number) => '/admin/areas?page=' . $number) ?>
</main>
