<!doctype html>
<html lang="<?= esc(service('locale')->current(), 'attr') ?>" style="<?= esc(brand_vars($accent ?? null), 'attr') ?>">
<head>
  <?= $this->include('partials/head') ?>
</head>
<body>
<main class="auth-wrap">
  <?php $cur = service('locale')->current(); /* Selector de idioma (ES / EN) antes de iniciar sesión. */ ?>
  <nav class="auth-lang" aria-label="<?= esc(t('App.languageChange'), 'attr') ?>">
    <?php foreach (\App\Libraries\Locale::SUPPORTED as $code => $name): ?>
      <form method="post" action="<?= site_url('idioma') ?>"><?= csrf_field() ?><input type="hidden" name="locale" value="<?= $code ?>"><input type="hidden" name="back" value="<?= esc(current_url(), 'attr') ?>">
        <button type="submit" class="btn btn-sm<?= $cur === $code ? ' btn-active' : '' ?>" lang="<?= $code ?>" <?= $cur === $code ? 'aria-current="true"' : '' ?>><?= icon('languages', 14) ?> <?= esc($name) ?></button></form>
    <?php endforeach ?>
  </nav>
  <?= $this->renderSection('content') ?>
</main>
<?= $this->include('partials/toasts') ?>
<?= $this->include('partials/contact') ?>
</body>
</html>
