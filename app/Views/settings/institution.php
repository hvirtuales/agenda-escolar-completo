<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
/** Ajustes › Institución (datos reales): datos, identidad visual con vista previa y preferencias regionales. */
$v = static fn (string $k) => old($k) ?? ($institution[$k] ?? '');
$accent   = brand_valid($v('accent_color'));
$swatches = ['#1D4ED8', '#0F766E', '#7C3AED', '#B91C1C', '#C2410C', '#0369A1', '#4D7C0F', '#334155'];
$zones    = \App\Models\InstitutionModel::TIMEZONES;
$ro       = $canEdit ? '' : 'disabled';
?>
<form method="post" action="<?= site_url('ajustes/institucion') ?>" x-data="{ color: '<?= $accent ?>', dirty: false }" @input="dirty = true" novalidate>
  <?= csrf_field() ?>
  <div class="page-header">
    <div><h1>Ajustes</h1><p><?= esc($institution['name']) ?></p></div>
    <?php if ($canEdit): ?>
      <div class="actions"><button type="submit" class="btn btn-primary" :disabled="!dirty" data-loading="Guardando…">Guardar cambios</button></div>
    <?php endif ?>
  </div>
  <?= view('settings/_tabs', ['section' => 'institucion']) ?>

  <?php if (! $canEdit): ?>
    <div class="notice mb-4"><?= icon('lock', 16, 'soft') ?><span class="text-xs">Puede consultar estos datos. Para cambiarlos se necesita el permiso «Identidad visual».</span></div>
  <?php endif ?>

  <div class="grid-2" style="align-items:start">
    <section class="card">
      <div class="card-header"><h2>Datos de la institución</h2></div>
      <div class="card-body stack">
        <label class="field"><span class="label">Nombre <span class="text-danger">*</span></span><input class="input" name="name" value="<?= esc($v('name'), 'attr') ?>" maxlength="160" required <?= $ro ?>><?= field_error('name') ?></label>
        <div class="grid-2" style="gap:0.75rem">
          <label class="field"><span class="label">NIT</span><input class="input" name="tax_id" value="<?= esc($v('tax_id'), 'attr') ?>" placeholder="860012345-1" <?= $ro ?>><?= field_error('tax_id') ?></label>
          <label class="field"><span class="label">Código DANE</span><input class="input" name="dane_code" value="<?= esc($v('dane_code'), 'attr') ?>" placeholder="111001012345" <?= $ro ?>><?= field_error('dane_code') ?></label>
          <label class="field"><span class="label">Ciudad</span><input class="input" name="city" value="<?= esc($v('city'), 'attr') ?>" <?= $ro ?>><?= field_error('city') ?></label>
          <label class="field"><span class="label">Teléfono</span><input class="input" name="phone" value="<?= esc($v('phone'), 'attr') ?>" <?= $ro ?>><?= field_error('phone') ?></label>
        </div>
        <label class="field"><span class="label">Correo de contacto</span><input class="input" type="email" name="email" value="<?= esc($v('email'), 'attr') ?>" <?= $ro ?>><?= field_error('email') ?></label>
      </div>
    </section>

    <section class="card">
      <div class="card-header"><h2>Identidad visual</h2></div>
      <div class="card-body stack">
        <fieldset class="fieldset">
          <legend class="label">Color de acento</legend>
          <div class="swatches">
            <?php foreach ($swatches as $s): ?>
              <button type="button" class="swatch" style="background:<?= $s ?>" :aria-pressed="(color === '<?= $s ?>').toString()" @click="color = '<?= $s ?>'; dirty = true" aria-label="Usar el color <?= $s ?>" <?= $ro ?>></button>
            <?php endforeach ?>
            <input class="input input-color" type="color" name="accent_color" x-model="color" aria-label="Elegir otro color" <?= $ro ?>>
          </div>
          <p class="hint mt-1">Botones principales, selección y enlaces. El texto sobre el color se elige solo para que se lea bien.</p>
          <?= field_error('accent_color') ?>
        </fieldset>
        <div class="brand-preview" :style="'--p:' + color">
          <span class="pv-btn">Botón principal</span><span class="pv-soft">Elemento seleccionado</span><span class="pv-link">Un enlace</span>
        </div>
      </div>
    </section>

    <section class="card">
      <div class="card-header"><h2>Preferencias regionales</h2></div>
      <div class="card-body grid-2" style="gap:0.75rem">
        <label class="field"><span class="label">Zona horaria</span>
          <select class="select" name="timezone" <?= $ro ?>><?php foreach ($zones as $k => $l): ?><option value="<?= $k ?>" <?= $v('timezone') === $k ? 'selected' : '' ?>><?= esc($l) ?></option><?php endforeach ?></select><?= field_error('timezone') ?></label>
        <label class="field"><span class="label">Idioma</span>
          <select class="select" name="locale" <?= $ro ?>><option value="es" <?= $v('locale') === 'es' ? 'selected' : '' ?>>Español</option><option value="en" <?= $v('locale') === 'en' ? 'selected' : '' ?>>English</option></select><?= field_error('locale') ?></label>
      </div>
    </section>

    <section class="card">
      <div class="card-header"><h2>Sedes</h2><a class="btn btn-sm" href="<?= site_url('ajustes/estructura?t=sedes') ?>"><?= icon('pencil-line', 14) ?> Gestionar sedes</a></div>
      <?php if (! $campuses): ?>
        <div class="card-body"><p class="soft">Sin sedes: la institución funciona en un solo lugar.</p></div>
      <?php else: ?>
        <div class="date-list">
          <?php foreach ($campuses as $c): ?>
            <div class="date-row"><?= icon('map-pin', 18, 'soft') ?><span><span class="block font-medium"><?= esc($c['name']) ?></span><span class="block text-xs soft"><?= esc($c['address'] ?? '') ?></span></span></div>
          <?php endforeach ?>
        </div>
      <?php endif ?>
    </section>
  </div>
</form>
<?= $this->endSection() ?>
