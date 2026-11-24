<?= $this->extend('layouts/auth') ?>
<?= $this->section('content') ?>
<?php /** Elegir red cuando la persona administra varias (o es superadministrador). */ ?>
<div class="auth-panel">
  <img class="auth-logo mb-4" src="<?= base_url('assets/img/logo-mark.png') ?>" alt="<?= esc(config('AgendaEscolar')->appName, 'attr') ?>" width="48" height="48">
  <h1>Elija una red</h1>
  <p class="soft mt-1">Verá el resumen de sus colegios. Puede cambiar de red luego desde el menú superior.</p>
  <div class="stack-sm mt-4">
    <?php foreach ($networks as $n): $sw = brand_valid($n['accent_color']); ?>
      <form method="post" action="<?= site_url('red/entrar') ?>">
        <?= csrf_field() ?><input type="hidden" name="network_id" value="<?= (int) $n['id'] ?>">
        <button type="submit" class="entity-option" style="--swatch:<?= $sw ?>;--swatch-ink:<?= brand_best_ink($sw) ?>">
          <span class="brand-mark" aria-hidden="true"><?= icon('network', 18) ?></span>
          <span style="min-width:0"><span class="block font-medium truncate"><?= esc($n['name']) ?></span>
            <span class="block text-xs soft"><?= esc(plural((int) $n['schools'], 'colegio', 'colegios') . ' · ' . ($n['role'] === 'admin' ? 'Administrador' : 'Analista')) ?></span></span>
          <?= icon('chevron-right', 16, 'soft ml-auto') ?>
        </button>
      </form>
    <?php endforeach ?>
  </div>
  <div class="row-between mt-4">
    <?php if (! empty($isSuperadmin)): ?>
      <a class="btn btn-sm" href="<?= site_url('plataforma') ?>"><?= icon('arrow-left', 14) ?> Volver a la plataforma</a>
    <?php else: ?>
      <a class="btn btn-sm" href="<?= site_url('instituciones') ?>"><?= icon('school', 14) ?> Ir a un colegio</a>
    <?php endif ?>
    <form method="post" action="<?= site_url('logout') ?>"><?= csrf_field() ?><button type="submit" class="btn btn-sm"><?= icon('log-out', 14) ?> Cerrar sesión</button></form>
  </div>
</div>
<?= $this->endSection() ?>
