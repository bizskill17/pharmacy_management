<main class="login">
  <form method="post" action="/login" class="card">
    <?= csrf_field() ?>
    <h1>Sign in</h1>
    <?php if ($error): ?><p class="error" role="alert"><?= e($error) ?></p><?php endif; ?>
    <div class="field">
      <label for="phone">Phone number</label>
      <input id="phone" name="phone" class="input" inputmode="tel" autocomplete="username" required>
    </div>
    <div class="field">
      <label for="password">Password</label>
      <input id="password" name="password" class="input" type="password" autocomplete="current-password" required>
    </div>
    <button class="btn primary">Sign in</button>
  </form>
</main>
