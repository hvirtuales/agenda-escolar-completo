<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
/**
 * Revisión de entregas (datos reales, documento maestro 4.7): estudiantes del grupo con su estado, la entrega elegida y
 * la calificación por rúbrica. La nota se calcula con los pesos de la rúbrica (1,0 a 5,0); devolver permite reentregar.
 */
$criteria = $activity['rubric']['criteria'];
$levels   = \App\Libraries\Activities::LEVELS;
$x        = $activity['assignment'];
$base     = site_url('entregas/' . $current['assignment_id']);
$label    = ['submitted' => ['Por revisar', 'warn'], 'reviewed' => ['Revisada', 'ok'], 'returned' => ['Devuelta', 'neutral'], 'in_progress' => ['En progreso', 'neutral']];
$cur      = $submission ? array_map('intval', array_pad($submission['levels'], count($criteria), 0)) : [];
$readonly = $submission && $submission['status'] === 'returned';
?>
<div class="page-header">
  <div>
    <h1>Entregas · <?= esc($activity['title']) ?></h1>
    <p><?= esc($current['label']) ?> · vence <?= esc(date_human($x['due_at'])) ?> · <?= (int) $current['submitted'] ?> de <?= (int) $current['students'] ?> entregaron</p>
  </div>
  <div class="actions">
    <?php if (count($list) > 1): ?>
      <select class="select" aria-label="Actividad" style="width:auto" onchange="location.href='<?= site_url('entregas') ?>/' + this.value">
        <?php foreach ($list as $a): ?><option value="<?= (int) $a['assignment_id'] ?>" <?= (int) $a['assignment_id'] === (int) $current['assignment_id'] ? 'selected' : '' ?>><?= esc($a['title'] . ' · ' . $a['label']) ?></option><?php endforeach ?>
      </select>
    <?php endif ?>
    <a class="btn" href="<?= site_url('actividades/' . $activity['id']) ?>"><?= icon('pencil-line', 16) ?> Editar reto</a>
  </div>
</div>

<div class="review" x-data="{ levels: <?= esc(json_encode($cur), 'attr') ?>, weights: <?= esc(json_encode(array_column($criteria, 'weight')), 'attr') ?>,
     score() { let t = 0; this.levels.forEach((l, i) => { t += (this.weights[i] || 0) * (3 - l) / 3; }); return (1 + t * 4 / 100).toFixed(1).replace('.', ','); } }">

  <aside class="review-list card" aria-label="Estudiantes">
    <div class="card-header"><h2>Estudiantes</h2><span class="text-xs soft"><?= count($students) ?></span></div>
    <div class="date-list">
      <?php foreach ($students as $s):
          [$lbl, $tone] = $s['status'] ? $label[$s['status']] : ['Sin entregar', 'neutral'];
          $clickable = $s['id'] && $s['status'] !== 'in_progress'; ?>
        <<?= $clickable ? 'a href="' . $base . '?e=' . (int) $s['id'] . '"' : 'div' ?> class="date-row review-item" <?= $submission && (int) $s['id'] === (int) $submission['id'] ? 'aria-current="true"' : '' ?>>
          <span class="avatar" aria-hidden="true"><?= esc(initials($s['name'])) ?></span>
          <span style="min-width:0;flex:1">
            <span class="block font-medium truncate"><?= esc($s['name']) ?></span>
            <span class="block text-xs soft"><?= $s['submitted_at'] ? esc(date_human($s['submitted_at'])) . ($s['is_late'] ? ' · tarde' : '') : ($s['status'] === 'in_progress' ? 'Trabajando en él' : 'Aún no empieza') ?></span>
          </span>
          <?= $s['grade'] !== null ? badge(str_replace('.', ',', (string) $s['grade']), 'ok') : badge($lbl, $tone) ?>
        </<?= $clickable ? 'a' : 'div' ?>>
      <?php endforeach ?>
      <?php if (! $students): ?><div class="card-body"><p class="soft">El grupo no tiene estudiantes matriculados.</p></div><?php endif ?>
    </div>
  </aside>

  <?php if (! $submission): ?>
    <section class="card review-main"><div class="card-body"><div class="empty" style="border:0"><?= icon('inbox', 32) ?><p>No hay entregas por revisar. Elija un estudiante con entrega en la lista.</p></div></div></section>
  <?php else: ?>
    <section class="card review-main" aria-labelledby="entrega-actual">
      <div class="card-header">
        <div>
          <h2 id="entrega-actual"><?= esc($submission['name']) ?></h2>
          <p class="text-xs soft">Intento <?= (int) $submission['attempt'] ?> · entregado <?= esc(date_human($submission['submitted_at'])) ?><?= $submission['is_late'] ? ' · <span class="text-warn">tarde</span>' : ' · a tiempo' ?></p>
        </div>
        <?= badge(...$label[$submission['status']]) ?>
      </div>
      <div class="card-body stack">
        <div class="answer"><?= nl2br(esc((string) $submission['answer'])) ?></div>
        <?php if ($activity['steps']): ?>
          <div>
            <p class="label">Pasos marcados como hechos</p>
            <ul class="check-list">
              <?php foreach ($activity['steps'] as $i => $st): ?>
                <li><?= ! empty($submission['steps_done'][$i]) ? icon('circle-check', 16, 'text-ok') : icon('circle', 16, 'soft') ?> <?= esc($st['title']) ?></li>
              <?php endforeach ?>
            </ul>
          </div>
        <?php endif ?>
      </div>
    </section>

    <aside class="review-side">
      <form method="post" action="<?= $base . '/' . (int) $submission['id'] ?>" class="stack">
        <?= csrf_field() ?>
        <section class="card">
          <div class="card-header"><h2>Rúbrica</h2><span class="text-xs soft">Nota: <strong class="text-base" style="color:var(--app-ink)" x-text="score()"></strong> / 5</span></div>
          <div class="card-body stack">
            <?php foreach ($criteria as $ci => $cr): ?>
              <fieldset class="fieldset" <?= $readonly ? 'disabled' : '' ?>>
                <legend class="label"><?= esc($cr['name']) ?> <span class="soft text-xs">· <?= (int) $cr['weight'] ?> %</span></legend>
                <div class="level-row">
                  <?php foreach ($levels as $li => $lv): ?>
                    <label class="btn btn-sm" :class="{ 'btn-active': levels[<?= $ci ?>] === <?= $li ?> }" title="<?= esc($cr['desc'][$li] ?? '', 'attr') ?>">
                      <input type="radio" class="sr-only" name="levels[<?= $ci ?>]" value="<?= $li ?>" @change="levels[<?= $ci ?>] = <?= $li ?>" <?= ($cur[$ci] ?? 0) === $li ? 'checked' : '' ?>><?= esc($lv) ?>
                    </label>
                  <?php endforeach ?>
                </div>
                <p class="hint mt-1" x-text="<?= esc(json_encode($cr['desc']), 'attr') ?>[levels[<?= $ci ?>]]"></p>
              </fieldset>
            <?php endforeach ?>
          </div>
        </section>
        <section class="card">
          <div class="card-header"><h2>Retroalimentación</h2>
            <?php if (! $readonly && in_array('ai.teacher.use', $perms ?? [], true)): ?>
              <button type="button" class="btn btn-sm" x-data="{ busy: false }" :disabled="busy" data-ai-feedback
                      @click="if (document.getElementById('feedback').value.trim() && !confirm('¿Reemplazar lo que escribiste con una propuesta de la IA?')) return;
                               busy = true;
                               window.postJson('<?= esc(site_url('ia/retroalimentacion/' . $submission['assignment_id'] . '/' . $submission['id']), 'js') ?>', { levels: levels })
                                 .then((out) => { if (out.ok) { const fb = document.getElementById('feedback'), note = document.getElementById('feedback-ai'); fb.value = out.reply; note.hidden = false; note.textContent = out.simulated ? 'Propuesta simulada (desarrollo, sin IA real): revísala antes de guardar.' : 'Propuesta generada por IA: revísala y ajústala antes de guardar.'; fb.focus(); } else { window.toast(out.error || 'No se pudo generar la propuesta.', 'error'); } })
                                 .catch(() => window.toast('Sin conexión: intenta de nuevo.', 'error'))
                                 .finally(() => busy = false)"
                      title="Proponer la retroalimentación con IA según los niveles elegidos"><?= icon('sparkles', 14) ?> <span x-text="busy ? 'Proponiendo…' : 'Proponer con IA'">Proponer con IA</span></button>
            <?php endif ?>
          </div>
          <div class="card-body">
            <label class="sr-only" for="feedback">Retroalimentación</label>
            <textarea id="feedback" name="feedback" class="textarea" rows="5" placeholder="Qué hizo bien y qué mejorar…" <?= $readonly ? 'disabled' : '' ?>><?= esc((string) $submission['feedback']) ?></textarea>
            <p class="ai-label mt-1" id="feedback-ai" hidden></p>
          </div>
          <?php if ($readonly): ?>
            <div class="card-body" style="padding-top:0"><p class="hint">Devuelta para corrección: espere la nueva entrega del estudiante.</p></div>
          <?php else: ?>
            <div class="drawer-foot" style="justify-content:space-between">
              <button type="submit" name="action" value="return" class="btn" data-loading="Devolviendo…"><?= icon('undo-2', 14) ?> Devolver para corrección</button>
              <button type="submit" name="action" value="grade" class="btn btn-primary" data-loading="Guardando…">Guardar calificación</button>
            </div>
          <?php endif ?>
        </section>
      </form>
    </aside>
  <?php endif ?>
</div>
<?= $this->endSection() ?>
