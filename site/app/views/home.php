<?php
$icons = [
    'sale' => '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3z"/><path d="M9 8h6M9 12h6"/>',
    'cf-sales' => '<path d="M2 6h12v11H2z"/><path d="M14 10h4l4 4v3h-8z"/><circle cx="7" cy="18.5" r="1.8"/><circle cx="18" cy="18.5" r="1.8"/>',
    'purchase' => '<circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M2 3h3l2.6 12.2a2 2 0 0 0 2 1.6h8.2a2 2 0 0 0 2-1.6L21.5 7H6"/>',
];
?>
<main class="home">
  <div class="home-bg" aria-hidden="true"></div>
  <div class="home-rows">
    <?php foreach (registers() as $register): ?>
    <a href="/r/<?= e($register['slug']) ?>" class="home-row <?= e($register['slug']) ?>">
      <span class="home-icon"><svg width="50" height="50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $icons[$register['slug']] ?></svg></span>
      <span class="home-label"><?= e($register['label']) ?></span>
      <svg width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 5l7 7-7 7"/></svg>
    </a>
    <?php endforeach; ?>
    <?php if (current_user()['role'] === 'ADMIN'): ?>
    <a href="/admin/users" class="home-row masters">
      <span class="home-icon"><svg width="50" height="50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.6-3.6 3.2-5.7 6.5-5.7s5.9 2.1 6.5 5.7"/><path d="M16 4.8a3.5 3.5 0 0 1 0 6.4M18 14.8c1.9.8 3.1 2.6 3.5 5.2"/></svg></span>
      <span class="home-label">Masters</span>
      <svg width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 5l7 7-7 7"/></svg>
    </a>
    <?php endif; ?>
  </div>
</main>
