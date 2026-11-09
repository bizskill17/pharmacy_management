<main class="page">
  <?php require __DIR__ . '/admin_nav.php'; ?>
  <form method="get" class="row">
    <div class="field grow"><label for="party-search">Search party</label><input id="party-search" name="q" class="input" type="search" value="<?= e($query) ?>"></div>
    <button class="btn primary end">Search</button>
  </form>
  <p class="muted">Parties are added automatically by bulk upload.</p>
  <?php foreach ($parties as $party): $partyId = (int) $party['id']; ?>
  <form method="post" action="/admin/parties/update" class="card">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $partyId ?>">
    <div class="grid-auto">
      <div class="field"><label for="p-name-<?= $partyId ?>">Display name</label><input id="p-name-<?= $partyId ?>" name="name" class="input" value="<?= e($party['name']) ?>" required maxlength="120"></div>
      <div class="field"><label for="p-area-<?= $partyId ?>">Area</label><select id="p-area-<?= $partyId ?>" name="area" class="input"><option value="">None</option><?php foreach ($areas as $area): ?><option value="<?= (int) $area['id'] ?>"<?= (int) $area['id'] === (int) $party['area_id'] ? ' selected' : '' ?>><?= e($area['name']) ?></option><?php endforeach; ?></select></div>
    </div>
    <div class="row between">
      <span class="muted">Upload name: <?= e($party['source_name']) ?></span>
      <span class="row"><label class="row"><input type="checkbox" name="active"<?= $party['is_active'] ? ' checked' : '' ?>> Active</label><button class="btn">Save</button></span>
    </div>
  </form>
  <?php endforeach; ?>
  <?= pager($page, $total, fn ($number) => '/admin/parties?' . http_build_query(array_filter(['q' => $query, 'page' => $number]))) ?>
</main>
