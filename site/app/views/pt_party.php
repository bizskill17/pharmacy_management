<?php
$barTitle = 'Payment Tracker';
$old = fn (string $key) => (string) ($old[$key] ?? '');
$plain = preg_replace('/\s+/', ' ', $party['name']);
$self = '/payment-tracker/party?' . http_build_query(['name' => $party['name'], 'back' => $back]);
?>
<main class="page">
  <div class="doc-bar">
    <div class="doc-head">
      <a class="icon-back" href="<?= e($back) ?>" aria-label="Back" title="Back"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M11 6l-6 6 6 6"/></svg></a>
      <div class="doc-name"><h1><?= e($plain) ?></h1></div>
    </div>
    <div class="pt-actions"><?= pt_contact($party['phone'], $plain, pt_party_message($plain, $party['balance'])) ?></div>
  </div>
  <?php if ($error): ?><p class="error" role="alert"><?= e($error) ?></p><?php endif; ?>
  <div class="pt-summary">
    <div class="pt-stat"><span>Balance</span><b><?= signed_money($party['balance']) ?></b></div>
    <div class="pt-stat amber"><span>Area</span><b><?= e($party['area'] ?: '-') ?></b></div>
    <div class="pt-stat green"><span>MR</span><b><?= e($party['mr'] ?: '-') ?></b><?php if ($mrPhone !== ''): ?><span class="pt-actions"><?= pt_contact($mrPhone, $party['mr'], "Please collect the payment of " . money($party['balance']) . " from $plain.") ?></span><?php endif; ?></div>
    <div class="pt-stat rose"><span>Phone</span><b><?= e($party['phone'] ?: '-') ?></b></div>
  </div>
  <form method="post" action="/payment-tracker/party" class="card">
    <?= csrf_field() ?>
    <input type="hidden" name="name" value="<?= e($party['name']) ?>">
    <input type="hidden" name="back" value="<?= e($back) ?>">
    <h2>Add Follow Up</h2>
    <div class="pt-form">
      <div class="field"><label for="fu-date">Follow Up Date</label><input id="fu-date" class="input" readonly value="<?= e(date('d/m/Y')) ?>"></div>
      <div class="field"><label for="fu-by">Follow Up By</label><input id="fu-by" class="input" readonly value="<?= e(current_user()['full_name']) ?>"></div>
      <div class="field"><label for="fu-due">Amount Due</label><input id="fu-due" class="input" readonly value="<?= e(money($party['balance'])) ?>"></div>
      <div class="field"><label for="fu-promised">Amount Promised</label><input id="fu-promised" name="amount_promised" class="input" inputmode="decimal" value="<?= e($old('amount_promised')) ?>"></div>
      <div class="field"><label for="fu-expected">Expected Payment Date</label><input id="fu-expected" name="expected_date" class="input" data-date placeholder="DD/MM/YYYY" value="<?= e($old('expected_date')) ?>"></div>
      <div class="field pt-wide"><label for="fu-remarks">Remarks</label><textarea id="fu-remarks" name="remarks" class="input" rows="2" maxlength="1000"><?= e($old('remarks')) ?></textarea></div>
    </div>
    <div class="form-foot"><button class="btn primary">Save</button></div>
  </form>

  <section class="card flush">
    <h2>Follow Ups</h2>
    <?php if (!$party['history']): ?><p class="muted">No follow ups yet.</p><?php else: ?>
    <div class="table-box"><table class="data">
      <thead><tr><th>Follow Up Date</th><th>Time</th><th>Follow Up By</th><th class="right">Amount Due</th><th class="right">Amount Promised</th><th>Expected Payment Date</th><th>Remarks</th><th class="right">Received Amount</th><th>Amount Received Date</th><th></th></tr></thead>
      <tbody><?php foreach ($party['history'] as $entry): ?>
        <tr>
          <td><?= e(display_date($entry['followup_date'])) ?></td>
          <td><?= e($entry['time_slot']) ?></td>
          <td><?= e($entry['followup_by_name']) ?></td>
          <td class="right"><?= $entry['amount_due'] === null ? '' : signed_money($entry['amount_due']) ?></td>
          <td class="right"><?= $entry['amount_promised'] === null ? '' : e(money($entry['amount_promised'])) ?></td>
          <td><?= e(display_date($entry['expected_date'])) ?></td>
          <td><?= e($entry['remarks']) ?></td>
          <td class="right"><?= $entry['received_amount'] === null ? '' : e(money($entry['received_amount'])) ?></td>
          <td><?= e(display_date($entry['received_date'])) ?></td>
          <td><a class="pt-icon ok" href="/payment-tracker/followups/<?= (int) $entry['id'] ?>/received?<?= e(http_build_query(['back' => $self])) ?>" aria-label="Mark received" title="Mark received"><?= pt_icon('ok') ?></a></td>
        </tr>
      <?php endforeach; ?></tbody>
    </table></div>
    <?php endif; ?>
  </section>

  <section class="card flush">
    <h2>Invoices &middot; <?= number_format(count($party['invoices'])) ?></h2>
    <?php if (!$party['invoices']): ?><p class="muted">This party is not in the last uploaded outstanding.</p><?php else: ?>
    <div class="table-box"><table class="data">
      <thead><tr><th>Party Name</th><th>Invoice No</th><th>Invoice Date</th><th class="right">Invoice Amount</th><th class="right">Balance</th></tr></thead>
      <tbody><?php foreach ($party['invoices'] as $invoice): ?>
        <tr><td><?= e($invoice['party_name']) ?></td><td><?= e($invoice['invoice_no']) ?></td><td><?= e(display_date($invoice['invoice_date'])) ?></td><td class="right"><?= $invoice['invoice_amount'] === null ? '' : signed_money($invoice['invoice_amount']) ?></td><td class="right"><?= signed_money($invoice['balance']) ?></td></tr>
      <?php endforeach; ?></tbody>
    </table></div>
    <?php endif; ?>
  </section>
</main>
