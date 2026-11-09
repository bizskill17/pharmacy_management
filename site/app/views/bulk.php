<?php
$info = array_map(fn ($register) => ['slug' => $register['slug'], 'label' => $register['label'], 'columns' => array_map(fn ($c) => $c[0], $register['template']), 'required' => array_map(fn ($c) => $c[2], $register['template'])], array_values(registers()));
?>
<main class="page" id="bulk" data-initial="<?= e($initial) ?>" data-registers="<?= e(json_encode($info)) ?>">
  <div>
    <h1>Bulk upload</h1>
    <p class="muted">Download a template, fill it in, then check the rows before saving.</p>
  </div>
  <section class="card">
    <h2 id="bulk-title"></h2>
    <div class="chips" id="bulk-chips"></div>
    <div class="row">
      <a class="btn" id="bulk-template" href="#">Download template</a>
      <label class="btn primary" id="bulk-pick">
        <span id="bulk-pick-text">Upload filled file</span>
        <input class="sr-input" id="bulk-file" type="file" accept=".xlsx,.csv">
      </label>
      <span class="muted" id="bulk-name"></span>
    </div>
  </section>
  <p class="error" id="bulk-error" role="alert" hidden></p>
  <p class="success" id="bulk-done" role="status" hidden></p>
  <section class="card" id="bulk-result" hidden>
    <div class="row between">
      <div><h2 id="bulk-result-title"></h2><p class="muted" id="bulk-summary"></p></div>
      <div class="row">
        <button type="button" class="btn" id="bulk-download" hidden>Download rows to fix</button>
        <button type="button" class="btn primary" id="bulk-save">Save valid rows</button>
      </div>
    </div>
    <div class="table-box">
      <table class="data fill">
        <thead><tr><th>Row</th><th>Number</th><th>Date</th><th>Party</th><th class="right">Amount</th><th>Result</th></tr></thead>
        <tbody id="bulk-rows"></tbody>
      </table>
    </div>
    <p class="muted" id="bulk-more" hidden></p>
  </section>
</main>
