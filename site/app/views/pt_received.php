<?php
$barTitle = 'Mark Received';
$value = fn (string $key, string $default) => e(isset($old[$key]) ? (string) $old[$key] : $default);
$fields = [
    ['Party Name', $entry['party_name']], ['Follow Up Date', display_date($entry['followup_date'])], ['Follow Up By', $entry['followup_by_name']],
    ['Amount Promised', $entry['amount_promised'] === null ? '' : money($entry['amount_promised'])], ['Expected Payment Date', display_date($entry['expected_date'])], ['Remarks', (string) $entry['remarks']],
];
?>
<main class="page">
  <div class="doc-bar">
    <div class="doc-head">
      <a class="icon-back" href="<?= e($back) ?>" aria-label="Back" title="Back"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M11 6l-6 6 6 6"/></svg></a>
      <div class="doc-name"><h1><?= e(preg_replace('/\s+/', ' ', $entry['party_name'])) ?></h1></div>
    </div>
  </div>
  <?php if ($error): ?><p class="error" role="alert"><?= e($error) ?></p><?php endif; ?>
  <section class="card flush">
    <?php foreach ($fields as [$label, $text]): if ($text === '') { continue; } ?><div class="kv"><span><?= e($label) ?></span><span><?= e($text) ?></span></div><?php endforeach; ?>
  </section>
  <form method="post" action="/payment-tracker/followups/<?= (int) $entry['id'] ?>/received" class="card">
    <?= csrf_field() ?>
    <input type="hidden" name="back" value="<?= e($back) ?>">
    <div class="pt-form">
      <div class="field"><label for="rc-amount">Received Amount</label><input id="rc-amount" name="received_amount" class="input" required inputmode="decimal" value="<?= $value('received_amount', $entry['received_amount'] === null ? (string) ($entry['amount_promised'] ?? '') : (string) $entry['received_amount']) ?>"></div>
      <div class="field"><label for="rc-date">Amount Received Date</label><input id="rc-date" name="received_date" class="input" required data-date value="<?= $value('received_date', $entry['received_date'] ? display_date($entry['received_date']) : date('d/m/Y')) ?>"></div>
    </div>
    <div class="form-foot"><button class="btn primary">Save</button></div>
  </form>
</main>
