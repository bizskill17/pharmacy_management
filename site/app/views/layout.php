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
<script>try { if (localStorage.getItem('menuHidden') === '1') { document.documentElement.classList.add('menu-hidden'); } } catch (e) {}</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/app.css?v=47">
</head>
<body>
<?php
$sidebar = $user ? sidebar_for($currentPath, $user) : null;
$account = function () use ($user) { ?>
      <span class="signed-in">Signed in as <b><?= e($user['full_name']) ?></b></span>
      <form method="post" action="/logout"><?= csrf_field() ?><button class="btn sidebar-logout"><?= menu_icon('Log out') ?>Log out</button></form>
<?php };
?>
<?php if ($sidebar): ?>
<div class="app-shell menu-<?= e($sidebar['section']) ?>">
  <aside class="sidebar">
    <div class="sidebar-top">
      <a class="side-item" href="/"><?= menu_icon('Home') ?>Home</a>
      <hr class="side-line">
      <nav class="sidebar-nav" aria-label="<?= e($sidebar['title']) ?>">
        <?php foreach ($sidebar['items'] as $item): ?>
        <a class="side-item" href="<?= e($item['href']) ?>"<?= $item['current'] ? ' aria-current="page"' : '' ?>><?= menu_icon($item['label']) ?><?= e($item['label']) ?></a>
        <?php endforeach; ?>
      </nav>
    </div>
    <div class="sidebar-foot"><?php $account(); ?></div>
  </aside>
  <div class="app-main">
    <div class="menu-bar"><button type="button" class="menu-toggle" id="menu-toggle" aria-expanded="true" aria-label="Hide menu" title="Hide menu"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg></button><?php if (!empty($barTitle)): ?><h1 class="bar-title"><?= e($barTitle) ?></h1><?php endif; ?></div>
<?php elseif ($user): ?>
<header class="topbar"><span></span><div class="topbar-group"><?php $account(); ?></div></header>
<?php endif; ?>
<?php if ($flash): ?>
<div class="flash-wrap"><p class="<?= $flash['ok'] ? 'success' : 'error' ?>" role="status"><?= e($flash['message']) ?></p></div>
<?php endif; ?>
<?= $content ?>
<?php if ($sidebar): ?>
  </div>
</div>
<?php endif; ?>
<script src="/assets/app.js?v=23"></script>
</body>
</html>
