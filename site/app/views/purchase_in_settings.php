<?php $barTitle = 'Purchase In'; ?>
<main class="page">
  <form method="post" action="/purchase-in/settings" class="card pin-settings">
    <?= csrf_field() ?>
    <h2>Settings</h2>
    <div class="field"><label for="last-sl">Last Sl. No.</label><input id="last-sl" name="last_sl" class="input" required inputmode="numeric" pattern="\d{1,9}" value="<?= (int) $lastSl ?>"></div>
    <p class="muted">The next entry will use Sl. No. <?= (int) $lastSl + 1 ?>.</p>
    <div><button class="btn primary">Save</button></div>
  </form>
</main>
