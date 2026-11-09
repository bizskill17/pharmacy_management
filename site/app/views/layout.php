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
<link rel="stylesheet" href="/assets/app.css?v=5">
</head>
<body>
<?php if ($user): ?>
<header class="topbar">
  <nav class="topbar-group" aria-label="Main">
    <?php if ($currentPath !== '/'): ?><a class="btn" href="/">Home</a><?php endif; ?>
    <?php if ($user['role'] === 'ADMIN'): ?><a class="btn" href="/admin/users">Masters</a><?php endif; ?>
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
<script src="/assets/app.js?v=4"></script>
</body>
</html>
