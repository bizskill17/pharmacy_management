<main class="page">
  <?php require __DIR__ . '/admin_nav.php'; ?>
  <form method="get" class="row" data-live-filter>
    <div class="field grow"><label for="party-search">Search party</label><input id="party-search" name="q" class="input" type="search" value="<?= e($query) ?>"></div>
  </form>
  <div class="row between"><p class="muted">Parties are added automatically by bulk upload.</p><a class="btn" href="/admin/parties/bulk">Bulk upload</a></div>
  <div id="live-results" class="stack">
  <?php if (!$parties): ?><p class="muted">No parties found.</p><?php else: ?>
  <div class="table-box">
    <table class="data fill master-table">
      <thead><tr><th>Upload name</th><th>Area</th><th>Active</th><th>Save</th></tr></thead>
      <tbody>
      <?php foreach ($parties as $party): $partyId = (int) $party['id']; $formId = 'party-' . $partyId; ?>
        <tr>
          <td><input form="<?= $formId ?>" type="hidden" name="name" value="<?= e($party['name']) ?>"><?= e($party['source_name']) ?></td>
          <td><select form="<?= $formId ?>" name="area" class="input" aria-label="Area"><option value="">None</option><?php foreach ($areas as $area): ?><option value="<?= (int) $area['id'] ?>"<?= (int) $area['id'] === (int) $party['area_id'] ? ' selected' : '' ?>><?= e($area['name']) ?></option><?php endforeach; ?></select></td>
          <td class="cell-center"><input form="<?= $formId ?>" type="checkbox" name="active" aria-label="Active"<?= $party['is_active'] ? ' checked' : '' ?>></td>
          <td class="cell-center"><form method="post" action="/admin/parties/update" id="<?= $formId ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $partyId ?>"><button class="btn">Save</button></form></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
  <?= pager($page, $total, fn ($number) => '/admin/parties?' . http_build_query(array_filter(['q' => $query, 'page' => $number]))) ?>
  </div>
</main>
