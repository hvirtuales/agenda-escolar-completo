<?= $this->extend('layouts/auth') ?>
<?= $this->section('content') ?>
<?php /** Segundo paso del inicio de sesión: código de la aplicación autenticadora o un código de respaldo. */ ?>
<div class="auth-panel" x-data="{ backup: false }">
  <div class="row mb-4">
    <img class="auth-logo" src="<?= base_url('assets/img/logo-mark.png') ?>" alt="" width="64" height="64">
    <div>
      <h1><?= esc(t('Auth.mfaTitle')) ?></h1>
      <p class="soft"><?= esc($email) ?></p>
    </div>
  </div>

  <form method="post" action="<?= site_url('login/verificacion') ?>" class="stack" novalidate>
    <?= csrf_field() ?>
    <label class="field" x-show="!backup">
      <span class="label"><?= esc(t('Auth.mfaAppCode')) ?></span>
      <input class="input" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="7" pattern="[0-9 ]*" placeholder="000000" autofocus :disabled="backup">
      <span class="hint"><?= esc(t('Auth.mfaAppHint', [config('AgendaEscolar')->appName])) ?></span>
    </label>
    <label class="field" x-show="backup" x-cloak>
      <span class="label"><?= esc(t('Auth.mfaBackupCode')) ?></span>
      <input class="input" name="code" autocomplete="off" maxlength="11" placeholder="xxxxx-xxxxx" :disabled="!backup">
      <span class="hint"><?= esc(t('Auth.mfaBackupHint')) ?></span>
    </label>
    <button type="submit" class="btn btn-primary btn-lg" data-loading="<?= esc(t('Auth.mfaVerifying'), 'attr') ?>"><?= esc(t('Auth.mfaVerify')) ?></button>
  </form>

  <div class="row mt-4" style="justify-content:space-between">
    <button type="button" class="btn btn-sm" @click="backup = !backup" x-text="backup ? <?= esc(json_encode(t('Auth.mfaUseApp')), 'attr') ?> : <?= esc(json_encode(t('Auth.mfaUseBackup')), 'attr') ?>"></button>
    <form method="post" action="<?= site_url('login/verificacion/cancelar') ?>"><?= csrf_field() ?><button type="submit" class="btn btn-sm"><?= esc(t('Auth.cancel')) ?></button></form>
  </div>
  <p class="text-xs soft mt-4" style="text-align:center"><?= esc(t('Auth.mfaLost')) ?></p>
</div>
<?= $this->endSection() ?>
