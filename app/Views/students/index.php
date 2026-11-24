<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
/**
 * Estudiantes › lista del grupo: promedio, entregas, último acceso y alertas (señales con su razón, no diagnósticos).
 *
 * @var array $groups grupos visibles, $r Students::roster(), string $q búsqueda
 */
$num  = static fn ($v) => $v === null ? '—' : number_format((float) $v, 1, ',', '');
$tone = static fn ($v) => $v === null ? 'neutral' : ($v < 3 ? 'danger' : ($v < 4 ? 'warn' : 'ok'));
$list = $q === '' ? $r['students'] : array_values(array_filter($r['students'], static fn ($s) => mb_stripos($s['name'], $q) !== false));
$withAlerts = count(array_filter($r['students'], static fn ($s) => $s['alerts']));
?>
<div class="page-header">
  <div><h1>Seguimiento</h1><p><?= $r['all'] ? 'Todos los grupos del periodo.' : 'Los grupos donde usted dicta.' ?> Abra un estudiante para ver su ficha completa.</p></div>
</div>
<?= view('partials/tabs', ['label' => 'Vistas de seguimiento', 'current' => 'grupo', 'tabs' => ['grupo' => ['Por grupo', 'estudiantes'], 'asignatura' => ['Por asignatura', 'reportes']]]) ?>

<form class="toolbar" method="get" action="<?= site_url('estudiantes') ?>">
  <select class="select" name="g" aria-label="Grupo" onchange="this.form.submit()" style="width:auto">
    <?php foreach ($groups as $g): ?><option value="<?= (int) $g['id'] ?>" <?= (int) $g['id'] === (int) $r['group']['id'] ? 'selected' : '' ?>><?= esc($g['name'] . ' · ' . plural((int) $g['students'], 'estudiante', 'estudiantes')) ?></option><?php endforeach ?>
  </select>
  <label class="input-icon"><span class="sr-only">Buscar estudiante</span><?= icon('search', 16) ?><input class="input" type="search" name="q" value="<?= esc($q, 'attr') ?>" placeholder="Buscar por nombre"></label>
  <?php if ($q !== ''): ?><a class="btn btn-ghost btn-sm" href="<?= site_url('estudiantes?g=' . $r['group']['id']) ?>">Limpiar filtros</a><?php endif ?>
</form>

<div class="grid-kpi">
  <div class="kpi"><div class="k-label">Estudiantes</div><div class="k-value"><?= count($r['students']) ?></div><div class="k-hint"><?= esc($r['group']['name']) ?></div></div>
  <div class="kpi"><div class="k-label">Asignaturas</div><div class="k-value"><?= count($r['subjects']) ?></div><div class="k-hint truncate"><?= esc(implode(', ', $r['subjects'])) ?></div></div>
  <div class="kpi"><div class="k-label">Requieren acompañamiento</div><div class="k-value <?= $withAlerts ? 'text-warn' : '' ?>"><?= $withAlerts ?></div><div class="k-hint">Vencidas sin entregar o promedio &lt; 3,0</div></div>
</div>

<section class="panel mt-4">
  <?php if (! $list): ?>
    <div class="empty"><?= icon('users', 32) ?><p><?= $q !== '' ? 'Ningún estudiante coincide con la búsqueda.' : 'Este grupo no tiene estudiantes matriculados.' ?></p></div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Estudiante</th><th class="num">Promedio</th><th style="width:18%">Entregas</th><th>Último acceso</th><th>Alertas</th><th class="text-right">Acciones</th></tr></thead>
        <tbody>
          <?php foreach ($list as $s): $pct = $s['total'] ? (int) round($s['done'] * 100 / $s['total']) : null; ?>
            <tr>
              <td><a class="row link" href="<?= site_url('estudiantes/' . $s['id']) ?>"><span class="avatar" aria-hidden="true"><?= esc(initials($s['name'])) ?></span><span class="font-medium"><?= esc($s['name']) ?></span></a></td>
              <td class="num"><?= $s['avg'] === null ? '<span class="soft">—</span>' : badge($num($s['avg']), $tone($s['avg'])) ?></td>
              <td><?php if ($pct === null): ?><span class="soft">Sin actividades</span><?php else: ?>
                <span class="share" role="img" aria-label="<?= $s['done'] ?> de <?= $s['total'] ?> entregadas"><span style="width:<?= $pct ?>%"></span></span>
                <span class="meta"><?= $s['done'] ?> de <?= $s['total'] ?><?= $s['missing'] ? ' · ' . plural($s['missing'], 'vencida', 'vencidas') : '' ?></span><?php endif ?></td>
              <td class="nowrap"><?= $s['last_access'] ? esc(date_short($s['last_access'])) : '<span class="soft">Nunca</span>' ?></td>
              <td><?php if ($s['alerts']): ?><span class="row text-xs text-warn" title="<?= esc(implode(' · ', $s['alerts']), 'attr') ?>"><?= icon('triangle-alert', 14) ?> <?= esc($s['alerts'][0]) ?><?= count($s['alerts']) > 1 ? ' (+' . (count($s['alerts']) - 1) . ')' : '' ?></span><?php else: ?><span class="soft text-xs">—</span><?php endif ?></td>
              <td class="text-right"><a class="btn btn-sm" href="<?= site_url('estudiantes/' . $s['id']) ?>"><?= icon('id-card', 14) ?> Ver ficha</a></td>
            </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>
  <?php endif ?>
</section>
<p class="hint mt-2">Promedio y entregas cuentan <?= $r['all'] ? 'todas las asignaturas del grupo' : 'solo las asignaturas que usted dicta en este grupo' ?>. Las alertas son señales para revisar, no diagnósticos.</p>
<?= $this->endSection() ?>
