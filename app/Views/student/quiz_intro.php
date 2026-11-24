<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
/** Presentación de la evaluación (estudiante): datos, intentos usados y botón para iniciar o retomar. */
$a    = $v['activity'];
$x    = $v['assignment'];
$open = $v['state'] === 'open';
$inProgress = array_values(array_filter($attempts, static fn ($t) => $t['status'] === 'in_progress'))[0] ?? null;
$left = max(0, (int) $a['max_attempts'] - count($attempts));
$st   = ['in_progress' => ['En curso', 'accent'], 'pending_review' => ['Por revisar', 'warn'], 'graded' => ['Calificado', 'ok']];
?>
<div class="page-header">
  <div>
    <nav class="crumbs text-xs" aria-label="Ubicación"><a class="link" href="<?= site_url('estudiante/actividades') ?>">Mis actividades</a><?= icon('chevron-right', 12) ?><span><?= esc($a['offering']['subject'] ?? '') ?></span></nav>
    <h1 class="mt-1"><?= esc($a['title']) ?></h1>
  </div>
</div>

<div class="grid-3" style="align-items:start">
  <section class="card span-2">
    <div class="card-body stack">
      <div class="act-meta" style="margin-top:0">
        <span><?= icon('list-checks', 14) ?> <?= plural($questions, 'pregunta', 'preguntas') ?></span>
        <span><?= icon('timer', 14) ?> <?= (int) $a['time_limit'] ? (int) $a['time_limit'] . ' minutos' : 'Sin tiempo límite' ?></span>
        <span><?= icon('rotate-ccw', 14) ?> <?= plural((int) $a['max_attempts'], 'intento', 'intentos') ?> · cuenta el mejor</span>
        <span><?= icon('calendar-days', 14) ?> Cierra <?= esc(date_human($x['due_at'])) ?></span>
      </div>
      <?php if ($a['instructions']): ?><p style="white-space:pre-wrap"><?= esc($a['instructions']) ?></p><?php endif ?>
      <div class="notice"><?= icon('lock', 16, 'soft') ?><span class="text-xs">El tutor de IA no está disponible durante las evaluaciones. Tus respuestas se guardan solas mientras presentas<?= (int) $a['time_limit'] ? ', y al acabarse el tiempo el intento se envía automáticamente' : '' ?>.</span></div>
      <div class="row">
        <?php if (! empty($preview)): ?>
          <a class="btn btn-primary btn-lg" href="<?= site_url('estudiante/evaluaciones/' . $x['id'] . '/vista-previa') ?>"><?= icon('eye', 16) ?> Ver las preguntas como estudiante</a>
          <span class="soft text-sm">Vista previa: no se crea ningún intento.</span>
        <?php elseif ($inProgress): ?>
          <a class="btn btn-primary btn-lg" href="<?= site_url('estudiante/evaluaciones/' . $x['id'] . '/intento/' . $inProgress['id']) ?>"><?= icon('play', 16) ?> Continuar el intento <?= (int) $inProgress['number'] ?></a>
        <?php elseif ($open && $left > 0): ?>
          <form method="post" action="<?= site_url('estudiante/evaluaciones/' . $x['id'] . '/iniciar') ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-primary btn-lg" data-loading="Iniciando…" onclick="return confirm('¿Iniciar el intento? <?= (int) $a['time_limit'] ? 'El reloj empieza a correr.' : '' ?>')"><?= icon('play', 16) ?> <?= $attempts ? 'Iniciar un nuevo intento' : 'Iniciar la evaluación' ?></button>
          </form>
        <?php else: ?>
          <span class="soft"><?= $v['state'] === 'scheduled' ? 'La evaluación aún no abre.' : ($left === 0 ? 'Ya usaste todos tus intentos.' : 'La evaluación cerró.') ?></span>
        <?php endif ?>
      </div>
    </div>
  </section>

  <section class="card">
    <div class="card-header"><h2>Tus intentos</h2><span class="text-xs soft"><?= count($attempts) ?> de <?= (int) $a['max_attempts'] ?></span></div>
    <?php if (! $attempts): ?>
      <div class="card-body"><p class="soft"><?= ! empty($preview) ? 'Aquí el estudiante verá sus intentos y sus notas.' : 'Aún no has presentado esta evaluación.' ?></p></div>
    <?php else: ?>
      <div class="date-list">
        <?php foreach ($attempts as $t): [$lbl, $tone] = $st[$t['status']]; ?>
          <a class="date-row" href="<?= site_url('estudiante/evaluaciones/' . $x['id'] . '/intento/' . $t['id']) ?>">
            <span style="flex:1"><span class="block font-medium">Intento <?= (int) $t['number'] ?></span><span class="block text-xs soft"><?= esc(date_human($t['finished_at'] ?? $t['started_at'])) ?></span></span>
            <?= $t['status'] === 'graded' && ($a['results_mode'] === 'after_attempt' || $v['state'] === 'closed') ? badge('Nota ' . str_replace('.', ',', (string) $t['grade']), 'ok') : badge($lbl, $tone) ?>
          </a>
        <?php endforeach ?>
      </div>
    <?php endif ?>
  </section>
</div>
<?= $this->endSection() ?>
