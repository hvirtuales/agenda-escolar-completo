<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php /** Inicio del superadministrador dentro de una institución (datos reales): estado de la institución, no el tablero de un docente. */ ?>
<div class="notice notice-accent mb-4">
  <?= icon('shield-check', 16) ?>
  <span>Estás dentro de <strong class="font-medium"><?= esc($institution['name']) ?></strong> como superadministrador. Tu entrada quedó en la auditoría y ves todo lo de la institución.</span>
</div>

<div class="page-header">
  <div><h1>Resumen de la institución</h1><p><?= esc($institution['name']) ?> · plan <?= esc($o['plan']) ?> · <?= esc($institution['city'] ?? '') ?></p></div>
  <div class="actions">
    <a class="btn" href="<?= site_url('ajustes/usuarios') ?>"><?= icon('users', 16) ?> Usuarios</a>
    <a class="btn" href="<?= site_url('ajustes/ia') ?>"><?= icon('sparkles', 16) ?> Cupos de IA</a>
    <a class="btn" href="<?= site_url('ajustes/institucion') ?>"><?= icon('settings', 16) ?> Ajustes</a>
  </div>
</div>

<div class="grid-kpi">
  <?php foreach ($o['kpis'] as [$l, $v, $h]): ?>
    <div class="kpi"><div class="k-label"><?= esc($l) ?></div><div class="k-value"><?= esc($v) ?></div><div class="k-hint"><?= esc($h) ?></div></div>
  <?php endforeach ?>
</div>

<div class="grid-2 mt-4" style="align-items:start">
  <section class="card">
    <div class="card-header"><h2>Administradores</h2></div>
    <div class="date-list">
      <?php if (! $o['admins']): ?><div class="card-body"><p class="hint">La institución no tiene administradores ni coordinadores activos.</p></div><?php endif ?>
      <?php foreach ($o['admins'] as [$n, $r, $last]): ?>
        <div class="date-row">
          <span class="avatar" aria-hidden="true"><?= esc(initials($n)) ?></span>
          <span style="flex:1;min-width:0"><span class="block font-medium"><?= esc($n) ?></span><span class="block text-xs soft"><?= esc($r) ?> · <?= $last ? 'último acceso ' . esc(date_human($last)) : 'aún no ha entrado' ?></span></span>
        </div>
      <?php endforeach ?>
    </div>
  </section>

  <section class="card">
    <div class="card-header"><h2>Actividad administrativa reciente</h2><a class="btn btn-sm" href="<?= site_url('plataforma/auditoria') ?>"><?= icon('scroll-text', 14) ?> Ver auditoría</a></div>
    <div class="date-list">
      <?php if (! $o['events']): ?><div class="card-body"><p class="hint">Sin actividad administrativa registrada.</p></div><?php endif ?>
      <?php foreach ($o['events'] as [$when, $what]): ?>
        <div class="date-row"><span style="flex:1;min-width:0"><span class="block"><?= esc($what) ?></span><span class="block text-xs soft"><?= esc(date_human($when)) ?></span></span></div>
      <?php endforeach ?>
    </div>
  </section>
</div>
<?= $this->endSection() ?>
