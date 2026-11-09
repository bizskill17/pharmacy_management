<?php
$statusLabels = ['ALL' => 'All', 'PENDING' => 'Pending', 'UPLOADED' => 'Uploaded', 'MINE' => 'My uploads', 'DELETED' => 'Deleted'];
$lastPage = max(1, (int) ceil($total / PAGE_SIZE));
$dateText = fn (string $iso) => $iso === '' ? '' : display_date($iso);
$link = function (array $change) use ($register, $filter, $status, $dateText) {
    $values = array_merge(['q' => $filter['q'], 'from' => $dateText($filter['from']), 'to' => $dateText($filter['to']), 'area' => $filter['area'] ?: '', 'status' => $status === 'ALL' ? '' : $status], $change);
    $query = http_build_query(array_filter($values, fn ($value) => $value !== ''));
    return '/r/' . $register['slug'] . ($query ? '?' . $query : '');
};
$textValue = function (array $d, string $field) {
    return match ($field) {
        'party' => $d['party'] ?? '-',
        'date' => display_date($d['doc_date']),
        'amount' => money($d['amount']),
        'receipt' => $d['receipt_ref'] ?? '-',
        'mr' => $d['mr_name'] ?? '-',
        'area' => $d['area'] ?? '-',
        'address' => $d['address'] ?? '-',
        'items' => $d['item_count'] ?? '-',
        'uploadDate' => display_date($d['upload_date']),
        'uploader' => $d['uploader'] ?? '-',
        'remarks' => $d['remarks'] ?? '-',
        default => '-',
    };
};
?>
<main class="page">
  <div class="row between">
    <div>
      <h1><?= e($register['label']) ?></h1>
      <p class="muted"><?= number_format($counts['PENDING']) ?> pending, <?= number_format($counts['UPLOADED']) ?> uploaded</p>
    </div>
  </div>

  <form class="filters" method="get">
    <?php if ($status !== 'ALL'): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
    <div class="field"><label for="q">Search number or party</label><input id="q" name="q" class="input" type="search" value="<?= e($filter['q']) ?>"></div>
    <div class="field"><label for="from">From date</label><input id="from" name="from" class="input" data-date value="<?= e($dateText($filter['from'])) ?>"></div>
    <div class="field"><label for="to">To date</label><input id="to" name="to" class="input" data-date value="<?= e($dateText($filter['to'])) ?>"></div>
    <div class="field">
      <label for="area">Area</label>
      <select id="area" name="area" class="input">
        <option value="">All areas</option>
        <?php foreach ($areas as $area): ?><option value="<?= (int) $area['id'] ?>"<?= (int) $area['id'] === $filter['area'] ? ' selected' : '' ?>><?= e($area['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <button class="btn primary">Apply</button>
  </form>

  <div class="row">
    <?php foreach (array_intersect($statuses, ['MINE', 'DELETED']) as $value): ?>
    <a class="toggle<?= $value === $status ? ' on' : '' ?>" href="<?= e($link(['status' => $value === 'ALL' ? '' : $value])) ?>"><?= e($statusLabels[$value]) ?></a>
    <?php endforeach; ?>
  </div>

  <?php if (!$documents): ?>
  <p class="muted">No documents found.</p>
  <?php else: ?>
  <div class="table-box">
    <table class="data">
      <thead><tr>
        <?php foreach ($register['columns'] as $column): ?><th<?= !empty($column['right']) ? ' class="right"' : '' ?>><?= e($column['label']) ?></th><?php endforeach; ?>
      </tr></thead>
      <tbody>
      <?php foreach ($documents as $d):
          $state = $d['deleted_at'] ? 'DELETED' : $d['status'];
      ?>
        <tr>
        <?php foreach ($register['columns'] as $column):
            if ($column['type'] === 'text'): ?>
          <td<?= $column['right'] ? ' class="right"' : '' ?>><?= e($textValue($d, $column['field'])) ?></td>
          <?php elseif ($column['type'] === 'link'): ?>
          <td><a class="doc-link" href="/r/<?= e($register['slug']) ?>/<?= (int) $d['id'] ?>"><?= e($d['doc_no']) ?></a></td>
          <?php elseif ($column['type'] === 'status'): ?>
          <td><span class="pill <?= e($state) ?>"><?= e(ucfirst(strtolower($state))) ?></span></td>
          <?php else:
              $photo = $photos["{$d['id']}:{$column['kind']}:{$column['index']}"] ?? null; ?>
          <td><?php if ($photo): ?><a href="/api/files/<?= $photo ?>" data-photo="<?= e($column['label']) ?>" aria-label="Open <?= e($column['label']) ?>"><img class="thumb" src="/api/files/<?= $photo ?>" alt="<?= e($column['label']) ?> preview" loading="lazy"></a><?php else: ?>-<?php endif; ?></td>
          <?php endif; ?>
        <?php endforeach; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <?php if ($lastPage > 1): ?>
  <nav class="row between" aria-label="Pages">
    <span class="muted">Page <?= $filter['page'] ?> of <?= $lastPage ?></span>
    <span class="row">
      <?php if ($filter['page'] > 1): ?><a class="btn" href="<?= e($link(['page' => $filter['page'] - 1])) ?>">Previous</a><?php endif; ?>
      <?php if ($filter['page'] < $lastPage): ?><a class="btn" href="<?= e($link(['page' => $filter['page'] + 1])) ?>">Next</a><?php endif; ?>
    </span>
  </nav>
  <?php endif; ?>
</main>
