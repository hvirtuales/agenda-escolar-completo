<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
/**
 * Intento en curso (estudiante): una pregunta a la vez, reloj hasta la hora límite, navegador de preguntas y
 * guardado automático. Las respuestas correctas no llegan al navegador.
 */
$a  = $q['activity'];
$x  = $q['assignment'];
$t  = $q['attempt'];
$qs = $q['questions'];
$n  = count($qs);
$base = site_url('estudiante/evaluaciones/' . $x['id'] . '/intento/' . $t['id']);
$hasLimit = (int) $a['time_limit'] > 0 || strtotime($x['due_at']) - time() < 86400;
$preview  = ! empty($preview); // vista de estudiante: no guarda ni envía
?>
<div x-data="quizTake({ total: <?= $n ?>, ids: <?= esc(json_encode(array_map('intval', array_column($qs, 'id'))), 'attr') ?>, answers: <?= esc(json_encode((object) $t['answers']), 'attr') ?>,
     deadline: '<?= esc($q['deadline'], 'js') ?>', serverNow: '<?= date('Y-m-d H:i:s') ?>', hasLimit: <?= $hasLimit ? 'true' : 'false' ?>, saveUrl: '<?= $preview ? '' : esc($base . '/guardar', 'js') ?>' })">

  <div class="page-header">
    <div>
      <nav class="crumbs text-xs" aria-label="Ubicación"><span><?= esc($a['offering']['subject'] ?? '') ?></span></nav>
      <h1 class="mt-1"><?= esc($a['title']) ?></h1>
    </div>
    <div class="actions">
      <?php if ($hasLimit): ?>
        <span class="quiz-clock" role="timer" :class="{ 'is-low': left < 300 }" :aria-label="'Tiempo restante ' + clock()"><?= icon('timer', 16) ?> <span x-text="clock()"></span></span>
      <?php endif ?>
      <?php if ($preview): ?>
        <a class="btn" href="<?= site_url('estudiante/evaluaciones/' . $x['id']) ?>"><?= icon('arrow-left', 14) ?> Salir de la vista previa</a>
      <?php else: ?>
        <button type="button" class="btn btn-primary" @click="confirm = true">Terminar intento</button>
      <?php endif ?>
    </div>
  </div>

  <?php if ($preview): ?>
  <div class="notice mb-4"><?= icon('eye', 16, 'soft') ?><span class="text-xs">Vista previa: así presenta el estudiante la evaluación. Puedes responder para probarla, pero nada se guarda y no se muestran las respuestas correctas.</span></div>
  <?php else: ?>
  <div class="notice mb-4"><?= icon('lock', 16, 'soft') ?><span class="text-xs">Intento <?= (int) $t['number'] ?> de <?= (int) $a['max_attempts'] ?>. Tus respuestas se guardan solas<span x-show="saving"> (guardando…)</span>. El tutor de IA no está disponible.</span></div>
  <?php endif ?>

  <?php if (! $qs): ?>
    <div class="empty"><p>Esta evaluación no tiene preguntas.</p></div>
  <?php else: ?>
    <div class="split">
      <div>
        <?php foreach ($qs as $i => $item): $id = (int) $item['id']; ?>
          <section class="card" x-show="cur === <?= $i ?>" <?= $i ? 'x-cloak' : '' ?> aria-labelledby="sq<?= $i ?>">
            <div class="card-header">
              <h2 id="sq<?= $i ?>">Pregunta <?= $i + 1 ?> de <?= $n ?></h2>
              <span class="text-xs soft"><?= rtrim(rtrim(number_format($item['points'], 2, ',', ''), '0'), ',') ?> puntos</span>
            </div>
            <div class="card-body stack">
              <p class="text-base" style="line-height:1.65;white-space:pre-wrap"><?= esc($item['text']) ?></p>
              <?php if (in_array($item['type'], ['single', 'truefalse'], true)): ?>
                <div class="radio-cards">
                  <?php foreach ($item['options'] as $oi => $op): ?>
                    <label class="radio-card"><input type="radio" name="q<?= $id ?>" value="<?= $oi ?>" :checked="String(answers[<?= $id ?>]) === '<?= $oi ?>'" @change="set(<?= $id ?>, '<?= $oi ?>')"><span class="text-base"><?= esc($op) ?></span></label>
                  <?php endforeach ?>
                </div>
              <?php elseif ($item['type'] === 'multiple'): ?>
                <div class="radio-cards">
                  <?php foreach ($item['options'] as $oi => $op): ?>
                    <label class="radio-card"><input type="checkbox" value="<?= $oi ?>" :checked="(answers[<?= $id ?>] || []).includes('<?= $oi ?>')" @change="toggle(<?= $id ?>, '<?= $oi ?>', $event.target.checked)"><span class="text-base"><?= esc($op) ?></span></label>
                  <?php endforeach ?>
                </div>
                <p class="hint">Puede haber más de una respuesta correcta.</p>
              <?php elseif ($item['type'] === 'long'): ?>
                <textarea class="textarea" rows="6" :value="answers[<?= $id ?>] || ''" @input="set(<?= $id ?>, $event.target.value)" placeholder="Escribe tu respuesta…" aria-label="Tu respuesta"></textarea>
              <?php else: ?>
                <input class="input input-lg" style="max-width:16rem" <?= $item['type'] === 'numeric' ? 'inputmode="decimal"' : '' ?> :value="answers[<?= $id ?>] || ''" @input="set(<?= $id ?>, $event.target.value)" aria-label="Tu respuesta">
              <?php endif ?>
            </div>
            <div class="drawer-foot" style="justify-content:space-between">
              <button type="button" class="btn" :disabled="cur === 0" @click="cur--"><?= icon('chevron-left', 14) ?> Anterior</button>
              <button type="button" class="btn" @click="flagged.includes(cur) ? flagged.splice(flagged.indexOf(cur), 1) : flagged.push(cur)" :class="{ 'btn-active': flagged.includes(cur) }"><?= icon('flag', 14) ?> Revisar luego</button>
              <button type="button" class="btn" :disabled="cur === <?= $n - 1 ?>" @click="cur++">Siguiente <?= icon('chevron-right', 14) ?></button>
            </div>
          </section>
        <?php endforeach ?>
      </div>

      <aside class="split-side card">
        <div class="card-header"><h2>Preguntas</h2><span class="text-xs soft"><span x-text="countAnswered()"></span> de <?= $n ?> respondidas</span></div>
        <div class="card-body">
          <div class="q-nav">
            <?php foreach ($qs as $i => $item): ?>
              <button type="button" class="btn" :class="{ 'btn-active': cur === <?= $i ?>, 'is-answered': answered(<?= (int) $item['id'] ?>), 'is-flagged': flagged.includes(<?= $i ?>) }" @click="cur = <?= $i ?>" :aria-current="cur === <?= $i ?>" aria-label="Ir a la pregunta <?= $i + 1 ?>"><?= $i + 1 ?></button>
            <?php endforeach ?>
          </div>
          <div class="q-legend mt-4"><span><span class="dot dot-ok"></span> Respondida</span><span><span class="dot dot-warn"></span> Para revisar</span><span><span class="dot"></span> Sin responder</span></div>
        </div>
      </aside>
    </div>
  <?php endif ?>

  <form method="post" action="<?= $base ?>/terminar" x-ref="finishForm">
    <?= csrf_field() ?>
    <input type="hidden" name="answers_json" x-ref="answersJson">
  </form>

  <div class="modal-layer" x-show="confirm" x-cloak @keydown.escape.window="confirm = false">
    <div class="veil" @click="confirm = false"></div>
    <div class="modal" role="alertdialog" aria-modal="true" aria-labelledby="end-title" aria-describedby="end-msg">
      <div class="confirm">
        <h2 id="end-title">¿Terminar el intento?</h2>
        <p id="end-msg">Respondiste <span x-text="countAnswered()"></span> de <?= $n ?> preguntas. Después de terminar no podrás cambiar tus respuestas.</p>
        <div class="actions"><button type="button" class="btn" @click="confirm = false">Seguir respondiendo</button><button type="button" class="btn btn-primary" @click="finishNow()">Terminar</button></div>
      </div>
    </div>
  </div>
</div>
<?= $this->endSection() ?>
