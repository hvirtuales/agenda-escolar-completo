<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
/**
 * Reto del estudiante (datos reales, documento maestro 13.3): instrucciones, pasos, pistas, respuesta, borrador y
 * entrega; después, estado, nota y retroalimentación. El tutor de IA es real (service('assistant')->tutor) y respeta el modo del reto.
 *
 * @var array $v ['assignment', 'activity', 'submission', 'state']
 */
$a     = $v['activity'];
$x     = $v['assignment'];
$s     = $v['submission'];
$st    = $s['status'] ?? null;
$preview = ! empty($preview);
$locked = $preview || in_array($st, ['submitted', 'reviewed'], true);
$late   = date('Y-m-d H:i:s') > $x['due_at'];
$closed = $late && ! $x['allow_late'];
$done   = array_map('boolval', array_pad($s['steps_done'] ?? [], count($a['steps']), false));
$statusBadge = match ($st) { 'submitted' => badge('Entregado', 'ok'), 'reviewed' => badge('Revisado', 'ok'), 'returned' => badge('Devuelto para corrección', 'warn'), 'in_progress' => badge('En progreso', 'accent'), default => badge('Pendiente') };
$diff  = ['low' => 'baja', 'medium' => 'media', 'high' => 'alta'][$a['difficulty']] ?? 'media';
?>
<form method="post" action="<?= site_url('estudiante/retos/' . $x['id']) ?>"<?= $preview ? ' onsubmit="return false"' : '' ?> x-data="{ done: <?= esc(json_encode($done), 'attr') ?>, hints: 1,
      progress() { return this.done.length ? Math.round(this.done.filter(Boolean).length * 100 / this.done.length) : 0; } }">
  <?= csrf_field() ?>
  <div class="page-header">
    <div>
      <nav class="crumbs text-xs" aria-label="Ubicación"><a class="link" href="<?= site_url('estudiante/actividades') ?>">Mis actividades</a><?= icon('chevron-right', 12) ?><span><?= esc($a['offering']['subject'] ?? '') ?></span></nav>
      <div class="row mt-1"><h1><?= esc($a['title']) ?></h1><?= $statusBadge ?></div>
    </div>
  </div>

  <?php if ($preview): ?>
    <div class="notice mb-4"><?= icon('eye', 16, 'soft') ?><span>Vista previa: así verá el reto un estudiante del grupo. Aquí no se puede entregar.</span></div>
  <?php elseif ($st === 'reviewed'): ?>
    <div class="notice notice-accent mb-4"><?= icon('circle-check', 16) ?>
      <span><strong class="font-medium">Nota: <?= esc(str_replace('.', ',', (string) $s['grade'])) ?> / 5.</strong> <?= $s['feedback'] ? esc($s['feedback']) : 'Tu docente revisó tu entrega.' ?></span></div>
  <?php elseif ($st === 'returned'): ?>
    <div class="notice mb-4"><?= icon('undo-2', 16, 'text-warn') ?>
      <span><strong class="font-medium">Tu docente te devolvió el reto para corregir.</strong> <?= $s['feedback'] ? esc($s['feedback']) : '' ?></span></div>
  <?php elseif ($st === 'submitted'): ?>
    <div class="notice mb-4"><?= icon('clock', 16, 'soft') ?><span>Entregaste el <?= esc(date_human($s['submitted_at'])) ?><?= $s['is_late'] ? ' (tarde)' : '' ?>. Tu docente lo revisará.</span></div>
  <?php elseif ($closed): ?>
    <div class="notice notice-danger mb-4"><?= icon('lock', 16) ?><span>La fecha de entrega pasó y este reto no acepta entregas tardías.</span></div>
  <?php endif ?>

  <div class="split split-tutor">
    <div class="stack">
      <section class="card card-body">
        <div class="act-meta" style="margin-top:0">
          <span><?= icon('calendar-days', 14) ?> Entrega: <?= esc(date_human($x['due_at'])) ?><?= $late && ! $locked ? ' · <span class="text-danger">vencida</span>' : '' ?></span>
          <span><?= icon('gauge', 14) ?> Dificultad <?= $diff ?></span>
          <span><?= icon('award', 14) ?> <?= (int) $a['points'] ?> puntos</span>
        </div>
        <?php if ($a['instructions']): ?><p class="mt-4 text-base" style="line-height:1.65;white-space:pre-wrap"><?= esc($a['instructions']) ?></p><?php endif ?>
        <?php if ($a['objective']): ?><div class="notice notice-accent mt-4"><?= icon('flag', 16) ?><span><strong class="font-medium">Lo que vas a aprender:</strong> <?= esc($a['objective']) ?></span></div><?php endif ?>
      </section>

      <?php if ($a['steps']): ?>
        <section class="card">
          <div class="card-header"><h2>Pasos</h2><span class="text-xs soft"><span x-text="progress()"></span> % completado</span></div>
          <ol class="step-list">
            <?php foreach ($a['steps'] as $i => $step): ?>
              <li class="step-row">
                <label class="step-check"><input type="checkbox" name="steps[<?= $i ?>]" value="1" x-model="done[<?= $i ?>]" <?= $locked ? 'disabled' : '' ?> aria-label="Marcar el paso <?= $i + 1 ?> como hecho"></label>
                <div><p class="font-medium" :class="{ 'soft': done[<?= $i ?>] }"><?= $i + 1 ?>. <?= esc($step['title']) ?></p><p class="soft text-sm"><?= esc($step['text']) ?></p></div>
              </li>
            <?php endforeach ?>
          </ol>
        </section>
      <?php endif ?>

      <?php if ($a['hints']): ?>
        <section class="card">
          <div class="card-header"><h2>Pistas</h2><span class="text-xs soft"><span x-text="hints"></span> de <?= count($a['hints']) ?> abiertas</span></div>
          <div class="card-body stack-sm">
            <?php foreach ($a['hints'] as $i => $h): ?>
              <div class="hint-row" x-show="hints > <?= $i ?>" <?= $i ? 'x-cloak' : '' ?>><span class="badge badge-accent">Pista <?= $i + 1 ?></span><span><?= esc($h['text']) ?></span></div>
            <?php endforeach ?>
            <button type="button" class="btn btn-sm" x-show="hints < <?= count($a['hints']) ?>" @click="hints++"><?= icon('lightbulb', 14) ?> Ver la siguiente pista</button>
          </div>
        </section>
      <?php endif ?>

      <section class="card">
        <div class="card-header"><h2>Tu entrega</h2><?php if ($s && $s['attempt'] > 1): ?><span class="text-xs soft">Intento <?= (int) $s['attempt'] ?></span><?php endif ?></div>
        <div class="card-body stack">
          <?php if ($a['deliverable']): ?><p class="text-sm soft"><?= esc($a['deliverable']) ?></p><?php endif ?>
          <label class="sr-only" for="answer">Respuesta</label>
          <textarea id="answer" name="answer" class="textarea" rows="8" placeholder="Escribe aquí tu respuesta y tu explicación…" <?= $locked || $closed ? 'readonly' : '' ?>><?= esc(old('answer') ?? (string) ($s['answer'] ?? '')) ?></textarea>
          <p class="hint">Adjuntar fotos o archivos llega con el almacenamiento de archivos.</p>
        </div>
        <?php if (! $locked && ! $closed): ?>
          <div class="drawer-foot" style="justify-content:space-between">
            <button type="submit" name="action" value="draft" class="btn" data-loading="Guardando…"><?= icon('save', 14) ?> Guardar borrador</button>
            <button type="submit" name="action" value="submit" class="btn btn-primary" data-loading="Entregando…" onclick="return confirm('¿Entregar el reto? Después no podrás cambiarlo, salvo que tu docente te lo devuelva.')"><?= $st === 'returned' ? 'Volver a entregar' : 'Entregar' ?></button>
          </div>
        <?php endif ?>
      </section>
    </div>

    <aside class="split-side card tutor" aria-label="Tutor de IA">
      <div class="ai-head"><?= icon('sparkles', 16) ?><h2>Tutor</h2><span class="badge badge-accent"><?= ['off' => 'Sin IA', 'guide' => 'Modo guía', 'explain' => 'Modo explicación'][$a['ai_mode']] ?? 'Modo guía' ?></span></div>
      <?php if ($a['ai_mode'] === 'off'): ?>
        <div class="ai-body"><div class="ai-context">Tu docente desactivó el tutor en este reto.</div></div>
      <?php elseif ($preview): ?>
        <div class="ai-body"><div class="ai-context">El estudiante podrá pedirle guía al tutor aquí. En la vista previa el tutor no responde, para no gastar el cupo de IA de la institución.</div></div>
      <?php else: ?>
        <div class="contents" x-data="tutorChat(<?= esc(json_encode([
            'url'    => site_url('estudiante/retos/' . $x['id'] . '/tutor'),
            'thread' => array_map(static fn ($m) => ['role' => $m['role'], 'text' => $m['content'], 'simulated' => (bool) $m['simulated']], $thread),
        ]), 'attr') ?>)">
          <div class="ai-body" x-ref="body" aria-live="polite">
            <div class="ai-context"><?= $a['ai_mode'] === 'explain'
                ? 'Puedo explicarte los conceptos del reto con ejemplos, pero la solución la construyes tú.'
                : 'Te ayudaré a pensar el reto con preguntas y pistas, sin darte la respuesta final.' ?></div>
            <template x-for="(m, i) in thread" :key="i">
              <div>
                <div class="ai-msg user" x-show="m.role === 'user'" x-text="m.text"></div>
                <div class="ai-msg ai" x-show="m.role === 'assistant'"><?= icon('sparkles', 16) ?>
                  <div><p style="white-space:pre-wrap" x-text="m.text"></p><?php if ($labelAi): ?><span class="ai-label" x-text="m.simulated ? 'Respuesta simulada (desarrollo, sin IA real)' : 'Respuesta generada por IA'"></span><?php endif ?></div>
                </div>
              </div>
            </template>
            <div class="ai-msg ai" x-show="sending"><?= icon('sparkles', 16) ?><div><p class="soft">Pensando…</p></div></div>
          </div>
          <div class="ai-foot">
            <?= view('partials/ai_quota', ['quota' => $quota, 'asQueries' => true]) ?>
            <div class="ai-input">
              <label class="sr-only" for="tutor-q">Pregunta al tutor</label>
              <textarea id="tutor-q" class="textarea" x-model="text" maxlength="800" placeholder="Escribe tu duda sobre el reto…"
                        @keydown.enter.prevent="if (!$event.shiftKey) send(); else text += '\n'" :disabled="sending" <?= ! empty($quota['blocked']) ? 'disabled' : '' ?>></textarea>
              <button type="button" class="btn btn-primary btn-sm btn-icon" @click="send()" :disabled="sending || !text.trim()" title="Enviar la pregunta al tutor" aria-label="Enviar la pregunta al tutor"><?= icon('send', 14) ?></button>
            </div>
          </div>
        </div>
      <?php endif ?>
    </aside>
  </div>
</form>
<?= $this->endSection() ?>
