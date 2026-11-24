<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
/**
 * Resultados de una evaluación (docente): estudiantes con su intento (mejor calificado o pendiente de revisión),
 * respuestas con su puntaje automático y puntos de las preguntas abiertas.
 */
$x    = $activity['assignment'];
$base = site_url('entregas/' . $current['assignment_id']);
$fmt  = static fn ($n) => rtrim(rtrim(number_format((float) $n, 2, ',', ''), '0'), ',');
$st   = ['pending_review' => ['Por revisar', 'warn'], 'graded' => ['Calificado', 'ok']];
?>
<div class="page-header">
  <div>
    <h1>Resultados · <?= esc($activity['title']) ?></h1>
    <p><?= esc($current['label']) ?> · cierra <?= esc(date_human($x['due_at'])) ?> · <?= (int) $current['submitted'] ?> de <?= (int) $current['students'] ?> presentaron</p>
  </div>
  <div class="actions">
    <?php if (count($list) > 1): ?>
      <select class="select" aria-label="Actividad" style="width:auto" onchange="location.href='<?= site_url('entregas') ?>/' + this.value">
        <?php foreach ($list as $l): ?><option value="<?= (int) $l['assignment_id'] ?>" <?= (int) $l['assignment_id'] === (int) $current['assignment_id'] ? 'selected' : '' ?>><?= esc($l['title'] . ' · ' . $l['label']) ?></option><?php endforeach ?>
      </select>
    <?php endif ?>
    <a class="btn" href="<?= site_url('actividades/' . $activity['id']) ?>"><?= icon('pencil-line', 16) ?> Editar evaluación</a>
  </div>
</div>

<div class="review">
  <aside class="review-list card" aria-label="Estudiantes">
    <div class="card-header"><h2>Estudiantes</h2><span class="text-xs soft"><?= count($students) ?></span></div>
    <div class="date-list">
      <?php foreach ($students as $s): $t = $s['attempt']; ?>
        <<?= $t ? 'a href="' . $base . '?e=' . (int) $t['id'] . '"' : 'div' ?> class="date-row review-item" <?= $attempt && $t && (int) $t['id'] === (int) $attempt['id'] ? 'aria-current="true"' : '' ?>>
          <span class="avatar" aria-hidden="true"><?= esc(initials($s['name'])) ?></span>
          <span style="min-width:0;flex:1">
            <span class="block font-medium truncate"><?= esc($s['name']) ?></span>
            <span class="block text-xs soft"><?= $t ? plural((int) $s['count'], 'intento', 'intentos') . ' · ' . esc(date_human($t['finished_at'])) : ($s['working'] ? 'Presentando ahora' : 'Sin presentar') ?></span>
          </span>
          <?= $t ? ($t['grade'] !== null ? badge(str_replace('.', ',', (string) $t['grade']), 'ok') : badge(...$st[$t['status']])) : badge('—') ?>
        </<?= $t ? 'a' : 'div' ?>>
      <?php endforeach ?>
      <?php if (! $students): ?><div class="card-body"><p class="soft">El grupo no tiene estudiantes matriculados.</p></div><?php endif ?>
    </div>
  </aside>

  <?php if (! $attempt): ?>
    <section class="card review-main"><div class="card-body"><div class="empty" style="border:0"><?= icon('clipboard-list', 32) ?><p>No hay intentos por revisar. Elija un estudiante que ya haya presentado.</p></div></div></section>
  <?php else: ?>
    <form method="post" action="<?= $base . '/intentos/' . (int) $attempt['id'] ?>" class="review-main" style="display:contents">
      <?= csrf_field() ?>
      <section class="card review-main" aria-labelledby="int-actual">
        <div class="card-header">
          <div>
            <h2 id="int-actual"><?= esc($attempt['name']) ?> · intento <?= (int) $attempt['number'] ?></h2>
            <p class="text-xs soft">Terminado <?= esc(date_human($attempt['finished_at'])) ?><?= $attempt['over_time'] ? ' · <span class="text-warn">fuera de tiempo</span>' : '' ?> · automático: <?= $fmt($attempt['auto_score']) ?> de <?= $fmt($attempt['max_score']) ?> pts</p>
          </div>
          <?= badge(...$st[$attempt['status']]) ?>
        </div>
        <div class="date-list">
          <?php foreach ($questions as $i => $q):
              $ans = $attempt['answers'][$q['id']] ?? null;
              $pts = \App\Libraries\Quizzes::scoreOf($q, $ans); ?>
            <div class="card-body" style="border-top:1px solid var(--app-line)">
              <div class="row-between"><span class="font-medium">Pregunta <?= $i + 1 ?></span>
                <?php if ($pts !== null): ?><?= badge($fmt($pts) . ' de ' . $fmt($q['points']) . ' pts', $pts >= $q['points'] ? 'ok' : 'danger') ?>
                <?php else: ?>
                  <label class="row text-xs"><span class="soft">Puntos</span><input class="input q-points" type="number" step="0.5" min="0" max="<?= $q['points'] ?>" name="manual[<?= (int) $q['id'] ?>]" value="<?= esc((string) ($attempt['manual'][$q['id']] ?? ''), 'attr') ?>" aria-label="Puntos de la pregunta <?= $i + 1 ?>"><span class="soft">de <?= $fmt($q['points']) ?></span></label>
                <?php endif ?>
              </div>
              <p class="text-sm mt-1" style="white-space:pre-wrap"><?= esc($q['text']) ?></p>
              <p class="text-sm mt-1"><span class="soft">Respuesta:</span>
                <?php if ($q['options']): ?>
                  <?= $ans === null || $ans === [] ? '<span class="soft">sin responder</span>' : esc(implode(', ', array_map(static fn ($k) => $q['options'][(int) $k] ?? '?', (array) $ans))) ?>
                <?php else: ?>
                  <?= $ans !== null && $ans !== '' ? nl2br(esc((string) $ans)) : '<span class="soft">sin responder</span>' ?>
                <?php endif ?>
              </p>
            </div>
          <?php endforeach ?>
        </div>
      </section>

      <aside class="review-side">
        <section class="card">
          <div class="card-header"><h2>Retroalimentación</h2></div>
          <div class="card-body stack-sm">
            <textarea name="feedback" class="textarea" rows="5" placeholder="Comentario para el estudiante (opcional)…" aria-label="Retroalimentación"><?= esc((string) $attempt['feedback']) ?></textarea>
            <p class="hint">La nota se calcula sola: 1 + 4 × puntaje ÷ puntaje máximo. Cuenta el mejor intento calificado.</p>
          </div>
          <div class="drawer-foot"><button type="submit" class="btn btn-primary" data-loading="Guardando…"><?= $attempt['status'] === 'pending_review' ? 'Calificar' : 'Guardar cambios' ?></button></div>
        </section>
      </aside>
    </form>
  <?php endif ?>
</div>
<?= $this->endSection() ?>
