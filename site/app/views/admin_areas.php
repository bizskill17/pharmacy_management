<main class="page">
  <?php require __DIR__ . '/admin_nav.php'; ?>
  <form method="post" action="/admin/areas" class="card">
    <?= csrf_field() ?>
    <h2>Add area</h2>
    <div class="field"><label for="a-name">Area name</label><input id="a-name" name="name" class="input" required maxlength="100"></div>
    <div><button class="btn primary">Add area</button></div>
  </form>
  <section class="card flush">
    <h2>Areas</h2>
    <?php if (!$areas): ?><p class="muted">No areas yet.</p><?php endif; ?>
    <?php foreach ($areas as $area): ?>
    <form method="post" action="/admin/areas/toggle" class="kv center">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int) $area['id'] ?>">
      <span><?= e($area['name']) ?><?= $area['is_active'] ? '' : ' (inactive)' ?></span>
      <button class="btn"><?= $area['is_active'] ? 'Deactivate' : 'Activate' ?></button>
    </form>
    <?php endforeach; ?>
  </section>
</main>
