<?php
$barTitle = $register['label'];
$dateText = fn (string $iso) => $iso === '' ? '' : display_date($iso);
$link = function (array $change) use ($register, $filter, $status, $dateText) {
    $values = array_merge(['q' => $filter['q'], 'from' => $dateText($filter['from']), 'to' => $dateText($filter['to']), 'area' => $filter['area'] ?: '', 'status' => $status === 'ALL' ? '' : $status, 'group' => $filter['group'] === 'date' ? '' : $filter['group']], $change);
    $query = http_build_query(array_filter($values, fn ($value) => $value !== ''));
    return '/r/' . $register['slug'] . ($query ? '?' . $query : '');
};
$textValue = function (array $d, string $field) {
    return match ($field) {
        'party' => $d['party'] ?? '',
        'date' => display_date($d['doc_date']),
        'amount' => money($d['amount']),
        'receipt' => $d['receipt_ref'] ?? '',
        'mr' => $d['mr_name'] ?? '',
        'area' => $d['area'] ?? '',
        'address' => $d['address'] ?? '',
        'items' => $d['item_count'] ?? '',
        'uploadDate' => display_date($d['upload_date']),
        'uploader' => $d['uploader'] ?? '',
        'remarks' => $d['remarks'] ?? '',
        default => '',
    };
};
?>
<main class="page">
  <form method="get" data-live-filter data-filter-form>
    <?php if ($status !== 'ALL'): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
    <div class="search-row">
      <label for="q" class="visually-hidden">Search number or party</label>
      <input id="q" name="q" class="input" type="search" placeholder="Search number or party" value="<?= e($filter['q']) ?>">
      <button type="button" class="icon-button" data-filter-open aria-label="Open filters" title="Filters" aria-expanded="false">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 5h18l-7 8v6l-4 2v-8z"/></svg>
        <span class="filter-count" data-filter-count hidden></span>
      </button>
    </div>
    <aside class="filter-panel" data-filter-panel aria-label="Filters" hidden>
      <div class="row between center">
        <h2>Filters</h2>
        <button type="button" class="icon-button" data-filter-close aria-label="Close filters" title="Close"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button>
      </div>
      <div class="field"><label for="from">From date</label><input id="from" name="from" class="input" data-date value="<?= e($dateText($filter['from'])) ?>"></div>
      <div class="field"><label for="to">To date</label><input id="to" name="to" class="input" data-date value="<?= e($dateText($filter['to'])) ?>"></div>
      <div class="field">
        <label for="area">Area</label>
        <select id="area" name="area" class="input">
          <option value="">All areas</option>
          <?php foreach ($areas as $area): ?><option value="<?= (int) $area['id'] ?>"<?= (int) $area['id'] === $filter['area'] ? ' selected' : '' ?>><?= e($area['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="field" role="radiogroup" aria-labelledby="group-label">
        <span id="group-label" class="choice-title">Group By</span>
        <div class="choice-row">
          <?php foreach (['date' => 'Date', 'area' => 'Area', 'mr' => 'MR'] as $value => $label): ?>
          <label class="choice"><input class="sr-input" type="radio" name="group" value="<?= e($value) ?>" data-default="date"<?= $filter['group'] === $value ? ' checked' : '' ?>><span><?= e($label) ?></span></label>
          <?php endforeach; ?>
        </div>
      </div>
      <button type="button" class="btn" data-filter-clear>Clear filters</button>
    </aside>
  </form>

  <div id="live-results">
  <?php if (!$documents): ?>
  <p class="muted">No documents found.</p>
  <?php else: ?>
  <div class="table-box">
    <table class="data">
      <thead><tr>
        <?php foreach ($register['columns'] as $column): ?><th<?= !empty($column['right']) ? ' class="right"' : '' ?>><?= e($column['label']) ?></th><?php endforeach; ?>
      </tr></thead>
      <tbody>
      <?php
      $groupLast = null;
      $groupPosition = array_flip(array_keys($groupTotals));
      foreach ($documents as $d):
          $state = $d['deleted_at'] ? 'DELETED' : $d['status'];
          $groupKey = (string) ($filter['group'] === 'date' ? $d['doc_date'] : ($filter['group'] === 'area' ? ($d['area'] ?? '') : ($d['mr_name'] ?? '')));
          if ($groupKey !== $groupLast):
              $groupLast = $groupKey;
              $groupLabel = $groupKey === '' ? ($filter['group'] === 'area' ? 'No Area' : 'No MR') : ($filter['group'] === 'date' ? display_date($groupKey) : $groupKey);
              $groupInfo = $groupTotals[$groupKey] ?? ['documents' => 0];
              $groupColor = ($groupPosition[$groupKey] ?? 0) % 10;
      ?>
        <tr class="group-row g<?= $groupColor ?>"><td colspan="<?= count($register['columns']) ?>"><span><strong><?= e($groupLabel) ?></strong> &middot; <?= number_format((int) $groupInfo['documents']) ?></span></td></tr>
      <?php endif; ?>
        <tr class="row-link g<?= $groupColor ?>" data-href="/r/<?= e($register['slug']) ?>/<?= (int) $d['id'] ?>">
        <?php foreach ($register['columns'] as $column):
            if ($column['type'] === 'text'): ?>
          <td<?= $column['right'] ? ' class="right"' : '' ?>><?= e($textValue($d, $column['field'])) ?></td>
          <?php elseif ($column['type'] === 'link'): ?>
          <td><a class="doc-link" href="/r/<?= e($register['slug']) ?>/<?= (int) $d['id'] ?>"><?= e($d['doc_no']) ?></a></td>
          <?php elseif ($column['type'] === 'status'): ?>
          <td><span class="pill <?= e($state) ?>"><?= e(ucfirst(strtolower($state))) ?></span></td>
          <?php else:
              $photo = $photos["{$d['id']}:{$column['kind']}:{$column['index']}"] ?? null; ?>
          <td><?php if ($photo): ?><a href="/api/files/<?= $photo ?>" data-photo="<?= e($column['label']) ?>" aria-label="Open <?= e($column['label']) ?>"><img class="thumb" src="/api/files/<?= $photo ?>" alt="<?= e($column['label']) ?> preview" loading="lazy"></a><?php endif; ?></td>
          <?php endif; ?>
        <?php endforeach; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <?= pager($filter['page'], $total, fn ($number) => $link(['page' => $number])) ?>
  </div>
  <p class="muted"><?= number_format($counts['PENDING']) ?> pending, <?= number_format($counts['UPLOADED']) ?> uploaded</p>
</main>
