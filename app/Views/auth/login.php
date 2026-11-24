<?= $this->extend('layouts/auth') ?>
<?= $this->section('content') ?>
<?php
/**
 * Inicio de sesión. En desarrollo o con el modo demo (agendaescolar.demoMode) lista las cuentas del colegio de demostración
 * para llenar el formulario (App\Libraries\Demo::accounts): nunca el superadministrador.
 */
$demo = service('demo')->accounts();
?>
<div class="auth-panel" x-data="{ email: '<?= esc(old('email') ?? '', 'js') ?>', password: '' }">
  <div class="row mb-4">
    <img class="auth-logo" src="<?= base_url('assets/img/logo-mark.png') ?>" alt="" width="64" height="64">
    <div>
      <h1><?= esc(config('AgendaEscolar')->appName) ?></h1>
      <p class="soft"><?= esc(t('Auth.tagline')) ?> <a class="link" href="<?= site_url('/') ?>"><?= esc(t('Auth.discover')) ?></a></p>
    </div>
  </div>

  <form method="post" action="<?= site_url('login') ?>" class="stack" novalidate>
    <?= csrf_field() ?>
    <label class="field">
      <span class="label"><?= esc(t('Auth.identity')) ?></span>
      <input class="input" type="text" name="email" x-model="email" autocomplete="username" autocapitalize="off" spellcheck="false" required autofocus>
      <?= field_error('email') ?>
    </label>
    <label class="field">
      <span class="label"><?= esc(t('Auth.password')) ?></span>
      <input class="input" type="password" name="password" x-model="password" autocomplete="current-password" required>
      <?= field_error('password') ?>
    </label>
    <button type="submit" class="btn btn-primary btn-lg btn-block" data-loading="<?= esc(t('Auth.signingIn'), 'attr') ?>"><?= esc(t('Auth.signIn')) ?></button>
  </form>
  <p class="text-xs soft mt-4" style="text-align:center"><?= esc(t('Auth.forgot')) ?></p>

  <?php if ($demo): ?>
    <div class="menu-sep mt-4"></div>
    <p class="text-xs soft mt-4"><?= esc(t('Auth.demoAccounts')) ?> <strong class="font-medium"><?= esc(\App\Database\Seeds\DemoSeeder::DEMO_PASSWORD) ?></strong></p>
    <div class="stack-sm mt-2">
      <?php foreach ($demo as [$mail, $roleKey, $where]): $role = t('Auth.' . $roleKey); ?>
        <button type="button" class="entity-option" style="padding:0.5rem 0.75rem" @click="email = '<?= esc($mail, 'js') ?>'; password = '<?= esc(\App\Database\Seeds\DemoSeeder::DEMO_PASSWORD, 'js') ?>'; $nextTick(() => $root.querySelector('form').requestSubmit())">
          <span style="min-width:0">
            <span class="block font-medium text-sm truncate"><?= esc($role) ?></span>
            <span class="block text-xs soft truncate"><?= esc($mail) ?> · <?= esc($where) ?></span>
          </span>
          <?= icon('log-in', 16, 'soft ml-auto') ?>
        </button>
      <?php endforeach ?>
    </div>
  <?php endif ?>
</div>
<?= $this->endSection() ?>
