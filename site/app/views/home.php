<main class="home">
  <div class="home-bg" aria-hidden="true"></div>
  <div class="home-rows">
    <?php foreach (registers() as $register): ?>
    <a href="/r/<?= e($register['slug']) ?>" class="home-row <?= e($register['slug']) ?>">
      <span class="home-icon"><svg width="50" height="50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= section_icon($register['slug']) ?></svg></span>
      <span class="home-label"><?= e($register['label']) ?></span>
      <svg width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 5l7 7-7 7"/></svg>
    </a>
    <?php endforeach; ?>
    <a href="/sales-challan/add" class="home-row sales-challan">
      <span class="home-icon"><svg width="50" height="50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= section_icon('sales-challan') ?></svg></span>
      <span class="home-label">Sales Challan</span>
      <svg width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 5l7 7-7 7"/></svg>
    </a>
    <a href="/purchase-in/add" class="home-row purchase-in">
      <span class="home-icon"><svg width="50" height="50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= section_icon('purchase-in') ?></svg></span>
      <span class="home-label">Purchase In</span>
      <svg width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 5l7 7-7 7"/></svg>
    </a>
    <a href="/payment-tracker/due" class="home-row payment-tracker">
      <span class="home-icon"><svg width="50" height="50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= section_icon('payment-tracker') ?></svg></span>
      <span class="home-label">Payment Tracker</span>
      <svg width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 5l7 7-7 7"/></svg>
    </a>
    <?php if (current_user()['role'] === 'ADMIN'): ?>
    <a href="/admin/users" class="home-row masters">
      <span class="home-icon"><svg width="50" height="50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= section_icon('masters') ?></svg></span>
      <span class="home-label">Masters</span>
      <svg width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 5l7 7-7 7"/></svg>
    </a>
    <?php endif; ?>
  </div>
</main>
