<?= $this->extend('layouts/auth') ?>
<?= $this->section('content') ?>
<div class="auth-panel">
  <img class="auth-logo mb-4" src="<?= base_url('assets/img/logo-mark.png') ?>" alt="<?= esc(config('AgendaEscolar')->appName, 'attr') ?>" width="48" height="48">
  <h1>Elija una institución</h1>
  <p class="soft mt-1">Hola, <?= esc(explode(' ', $user['name'])[0]) ?>. Trabajará con los datos de la institución que elija; puede cambiarla luego desde el menú superior.</p>

  <?php if (! empty($networks)): ?>
    <div class="stack-sm mt-4">
      <div class="menu-label" style="padding:0">Redes que administra</div>
      <?php foreach ($networks as $n): $sw = brand_valid($n['accent_color']); ?>
        <form method="post" action="<?= site_url('red/entrar') ?>"><?= csrf_field() ?><input type="hidden" name="network_id" value="<?= (int) $n['id'] ?>">
          <button type="submit" class="entity-option" style="--swatch:<?= $sw ?>;--swatch-ink:<?= brand_best_ink($sw) ?>">
            <span class="brand-mark" aria-hidden="true"><?= icon('network', 18) ?></span>
            <span style="min-width:0"><span class="block font-medium truncate"><?= esc($n['name']) ?></span>
              <span class="block text-xs soft"><?= esc(plural((int) $n['schools'], 'colegio', 'colegios') . ' · Consola de la red') ?></span></span>
            <?= icon('chevron-right', 16, 'soft ml-auto') ?>
          </button></form>
      <?php endforeach ?>
      <div class="menu-label mt-2" style="padding:0">Colegios</div>
    </div>
  <?php endif ?>

  <div class="stack-sm mt-4">
    <?php foreach ($institutions as $i): ?>
      <form method="post" action="<?= site_url('instituciones/cambiar') ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="institution_id" value="<?= (int) $i['id'] ?>">
        <?php $sw = brand_valid($i['accent_color']); ?>
        <button type="submit" class="entity-option" style="--swatch:<?= $sw ?>;--swatch-ink:<?= brand_best_ink($sw) ?>">
          <span class="brand-mark" aria-hidden="true"><?= esc(initials($i['name'])) ?></span>
          <span style="min-width:0">
            <span class="block font-medium truncate"><?= esc($i['name']) ?></span>
            <span class="block text-xs soft"><?= esc($i['city'] . ' · ' . $i['role_name']) ?></span>
          </span>
          <?= icon('chevron-right', 16, 'soft ml-auto') ?>
        </button>
      </form>
    <?php endforeach ?>
  </div>

  <div class="row-between mt-4">
    <?php if (! empty($isSuperadmin)): ?>
      <a class="btn btn-sm" href="<?= site_url('plataforma') ?>"><?= icon('arrow-left', 14) ?> Volver a la plataforma</a>
    <?php else: ?><span></span><?php endif ?>
    <form method="post" action="<?= site_url('logout') ?>">
      <?= csrf_field() ?>
      <button type="submit" class="btn btn-sm"><?= icon('log-out', 14) ?> Cerrar sesión</button>
    </form>
  </div>
</div>
<?= $this->endSection() ?>
