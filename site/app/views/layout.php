<?php
$currentPath = '/' . trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf" content="<?= e(csrf_token()) ?>">
<title><?= e($title) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/app.css?v=9">
</head>
<body>
<?php if ($user): ?>
<header class="topbar">
  <nav class="topbar-group" aria-label="Main">
    <?php if ($currentPath !== '/'):
        $bulkType = $currentPath === '/bulk' ? '/r/' . ($_GET['type'] ?? 'sale') : '';
        foreach (section_links($user) as $section):
            $isCurrent = $currentPath === $section['match'] || str_starts_with($currentPath, $section['match'] . '/') || $bulkType === $section['match'];
    ?>
    <a class="section-btn <?= e($section['slug']) ?>" href="<?= e($section['href']) ?>"<?= $isCurrent ? ' aria-current="page"' : '' ?>>
      <span class="section-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= section_icon($section['slug']) ?></svg></span>
      <?= e($section['label']) ?>
    </a>
    <?php endforeach; endif; ?>
  </nav>
  <div class="topbar-group">
    <span class="signed-in">Signed in as <?= e($user['full_name']) ?></span>
    <form method="post" action="/logout"><?= csrf_field() ?><button class="btn">Log out</button></form>
  </div>
</header>
<?php endif; ?>
<?php if ($flash): ?>
<div class="flash-wrap"><p class="<?= $flash['ok'] ? 'success' : 'error' ?>" role="status"><?= e($flash['message']) ?></p></div>
<?php endif; ?>
<?= $content ?>
<script src="/assets/app.js?v=6"></script>
</body>
</html>
