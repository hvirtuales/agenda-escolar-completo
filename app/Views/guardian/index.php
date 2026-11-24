<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php /** Portal del acudiente: sus estudiantes vinculados con un resumen de cada uno. @var array $kids */ ?>
<div class="page-header">
  <div><h1>Mis estudiantes</h1><p>Consulte cómo van en sus actividades y qué les dicen sus docentes.</p></div>
</div>
<?php if (! $kids): ?>
  <div class="empty"><?= icon('users', 32) ?><p>Aún no tiene estudiantes vinculados. Pida a la institución que vincule su cuenta con la de su estudiante.</p></div>
<?php else: ?>
  <div class="group-cards">
    <?php foreach ($kids as $k): $c = $k['counts']; ?>
      <a class="group-card" href="<?= site_url('acudiente/' . $k['id']) ?>">
        <div class="row-between"><span class="row"><span class="avatar" aria-hidden="true"><?= esc(initials($k['name'])) ?></span><span class="g-title"><?= esc($k['name']) ?></span></span>
          <?= $k['avg'] === null ? badge('Sin notas') : badge('Promedio ' . number_format((float) $k['avg'], 1, ',', ''), $k['avg'] < 3 ? 'danger' : ($k['avg'] < 4 ? 'warn' : 'ok')) ?></div>
        <div class="g-meta"><?= esc(($k['group_name'] ?? 'Sin grupo') . ' · ' . $k['relationship']) ?></div>
        <div class="g-meta"><?= plural($k['pending'], 'actividad por hacer', 'actividades por hacer') ?><?= $c['missed'] ? ' · ' . plural($c['missed'], 'vencida sin entregar', 'vencidas sin entregar') : '' ?></div>
      </a>
    <?php endforeach ?>
  </div>
<?php endif ?>
<?= $this->endSection() ?>
