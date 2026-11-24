<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
/**
 * Reportes de una oferta (documento maestro 4.8), calculados con entregas de retos e intentos de evaluaciones.
 * Las alertas de acompañamiento muestran su razón y las decide el docente: ningún indicador automático es un diagnóstico.
 */
$k      = $r['kpis'];
$o      = $r['offering'];
$num    = static fn ($v) => $v === null ? '—' : number_format((float) $v, 1, ',', '');
$tone   = static fn ($v) => $v === null ? 'neutral' : ($v < \App\Libraries\Reports::PASSING ? 'danger' : ($v < 4 ? 'warn' : 'ok'));
$canCsv = service('tenant')->can('grades.export');
$canRev = service('tenant')->can('submissions.review');
?>
<div class="page-header">
  <div>
    <h1>Seguimiento</h1>
    <p>Cómo va el grupo, en qué temas y en qué preguntas. Se actualiza con cada entrega y cada evaluación.</p>
  </div>
  <div class="actions">
    <form method="get" action="<?= site_url('reportes') ?>">
      <select class="select" name="o" aria-label="Asignatura y grupo" style="width:auto" onchange="this.form.submit()">
        <?php foreach ($offerings as $op): ?>
          <option value="<?= $op['id'] ?>" <?= $op['id'] === $o['id'] ? 'selected' : '' ?>><?= esc($op['subject'] . ' · ' . $op['group']) ?></option>
        <?php endforeach ?>
      </select>
      <noscript><button type="submit" class="btn">Ver</button></noscript>
    </form>
    <?php if ($canCsv): ?>
      <a class="btn" href="<?= site_url('reportes/' . $o['id'] . '/exportar') ?>" <?= $r['students'] ? '' : 'aria-disabled="true"' ?>><?= icon('download', 16) ?> Exportar CSV</a>
    <?php endif ?>
  </div>
</div>
<?= view('partials/tabs', ['label' => 'Vistas de seguimiento', 'current' => 'asignatura', 'tabs' => ['grupo' => ['Por grupo', 'estudiantes'], 'asignatura' => ['Por asignatura', 'reportes']]]) ?>

<div class="grid-kpi">
  <div class="kpi"><div class="k-label">Participación</div><div class="k-value"><?= $k['participation'] === null ? '—' : $k['participation'] . ' %' ?></div><div class="k-hint"><?= $k['active'] ?> de <?= $k['total'] ?> con al menos una entrega</div></div>
  <div class="kpi"><div class="k-label">Promedio</div><div class="k-value"><?= $num($k['avg']) ?></div><div class="k-hint"><?= $k['graded'] ? 'Escala de 1 a 5 · ' . plural($k['graded'], 'nota', 'notas') : 'Aún no hay notas' ?></div></div>
  <div class="kpi"><div class="k-label">Actividades cerradas</div><div class="k-value"><?= $k['closed'] ?></div><div class="k-hint">De <?= plural($k['activities'], 'asignada', 'asignadas') ?></div></div>
  <div class="kpi"><div class="k-label">Por revisar</div><div class="k-value"><?= $k['to_review'] ?></div><div class="k-hint"><?= $k['to_review'] ? 'Entregas y respuestas abiertas sin calificar' : 'Todo está calificado' ?></div></div>
</div>

<?php if (! $r['activities']): ?>
  <div class="empty mt-4">
    <?= icon('chart-column', 32) ?>
    <p>Este grupo todavía no tiene actividades publicadas y asignadas. Cuando publique un reto o una evaluación, aquí verá cómo le va.</p>
    <a class="btn btn-primary mt-4" href="<?= site_url('actividades') ?>"><?= icon('target', 16) ?> Ir a Actividades</a>
  </div>
<?php else: ?>
<div class="grid-3 mt-4" style="align-items:start">
  <section class="card span-2">
    <div class="card-header"><h2>Por actividad</h2><span class="text-xs soft">Promedio de 1 a 5 y entregas</span></div>
    <div class="card-body stack">
      <?php foreach ($r['activities'] as $a): ?>
        <div>
          <div class="row-between">
            <span class="row" style="min-width:0"><?= icon($a['kind'] === 'quiz' ? 'clipboard-list' : 'target', 14) ?><span class="truncate"><?= esc($a['title']) ?></span><?= $a['closed'] ? badge('Cerrada', 'neutral') : badge('Abierta', 'ok') ?></span>
            <span class="text-xs soft nowrap"><?= $a['submitted'] ?>/<?= $k['total'] ?> entregas · Prom. <?= $num($a['avg']) ?></span>
          </div>
          <span class="share" role="img" aria-label="<?= esc($a['title'], 'attr') ?>: promedio <?= $num($a['avg']) ?> de 5"><span style="width:<?= $a['avg'] === null ? 0 : (int) round($a['avg'] * 20) ?>%"></span></span>
        </div>
      <?php endforeach ?>
    </div>
  </section>

  <section class="card">
    <div class="card-header"><h2>Por tema</h2></div>
    <div class="card-body stack">
      <?php if (! $r['topics']): ?>
        <p class="hint">Aparece cuando los estudiantes terminen evaluaciones con preguntas de respuesta cerrada.</p>
      <?php endif ?>
      <?php foreach ($r['topics'] as $t): ?>
        <div>
          <div class="row-between"><span><?= esc($t['topic']) ?></span><span class="text-xs soft"><?= $t['pct'] ?> %</span></div>
          <span class="share" role="img" aria-label="<?= esc($t['topic'], 'attr') ?>: <?= $t['pct'] ?> % de acierto"><span style="width:<?= $t['pct'] ?>%"></span></span>
        </div>
      <?php endforeach ?>
      <?php if ($r['topics']): ?><p class="hint">Porcentaje de respuestas correctas según el tema de cada pregunta.</p><?php endif ?>
    </div>
  </section>

  <section class="card span-2">
    <div class="card-header"><h2>Requieren acompañamiento</h2><span class="text-xs soft">Revíselas: son señales, no diagnósticos</span></div>
    <?php if (! $r['alerts']): ?>
      <div class="card-body"><p class="hint">Nadie tiene actividades vencidas sin entregar ni promedio por debajo de <?= $num(\App\Libraries\Reports::PASSING) ?>.</p></div>
    <?php else: ?>
      <div class="date-list">
        <?php foreach ($r['alerts'] as $s): ?>
          <div class="date-row">
            <span class="avatar" aria-hidden="true"><?= esc(initials($s['name'])) ?></span>
            <span style="min-width:0;flex:1"><span class="block font-medium"><?= esc($s['name']) ?></span><span class="block text-xs soft"><?= esc($s['reason']) ?></span></span>
            <span class="text-xs soft nowrap">Prom. <?= $num($s['avg']) ?></span>
          </div>
        <?php endforeach ?>
      </div>
    <?php endif ?>
  </section>

  <section class="card">
    <div class="card-header"><h2>Preguntas con más error</h2></div>
    <?php if (! $r['questions']): ?>
      <div class="card-body"><p class="hint">Aún no hay respuestas incorrectas en las evaluaciones.</p></div>
    <?php else: ?>
      <div class="date-list">
        <?php foreach ($r['questions'] as $q): ?>
          <div class="date-row">
            <span style="min-width:0;flex:1"><span class="block font-medium"><?= esc($q['text']) ?></span><span class="block text-xs soft"><?= esc($q['where']) ?> · <?= plural($q['n'], 'respuesta', 'respuestas') ?></span></span>
            <?= badge($q['pct'] . ' % error', $q['pct'] >= 60 ? 'danger' : 'warn') ?>
          </div>
        <?php endforeach ?>
      </div>
    <?php endif ?>
  </section>
</div>

<section class="card mt-4">
  <div class="card-header"><h2>Notas por estudiante</h2><span class="text-xs soft">Retos: nota de la revisión · Evaluaciones: mejor intento</span></div>
  <?php if (! $r['students']): ?>
    <div class="card-body"><p class="hint">El grupo no tiene estudiantes matriculados.</p></div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th scope="col">Estudiante</th>
            <?php foreach ($r['activities'] as $a): ?>
              <th scope="col" class="num">
                <?php if ($canRev): ?><a href="<?= site_url('entregas/' . $a['assignment_id']) ?>" title="Ver entregas de <?= esc($a['title'], 'attr') ?>"><?= esc($a['title']) ?></a><?php else: ?><?= esc($a['title']) ?><?php endif ?>
              </th>
            <?php endforeach ?>
            <th scope="col" class="num">Promedio</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($r['students'] as $s): ?>
            <tr>
              <td class="font-medium"><?= esc($s['name']) ?></td>
              <?php foreach ($r['activities'] as $a): $g = $s['grades'][$a['assignment_id']] ?? null; ?>
                <td class="num"><?= $g === null ? '<span class="soft">—</span>' : $num($g) ?></td>
              <?php endforeach ?>
              <td class="num"><?= $s['avg'] === null ? '<span class="soft">—</span>' : badge($num($s['avg']), $tone($s['avg'])) ?></td>
            </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>
  <?php endif ?>
</section>
<?php endif ?>
<?= $this->endSection() ?>
