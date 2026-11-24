<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
/**
 * Editor de evaluación (datos reales, documento maestro 4.5). Las preguntas se editan en el navegador y se guardan
 * todas juntas (questions_json). «Publicar y asignar» guarda, publica y asigna al grupo con las fechas.
 *
 * @var array $c evaluación, $questions preguntas con respuestas, $state, $stats
 */
$o      = static fn (string $k, $def) => old($k) ?? $def;
$x      = $c['assignment'];
$dt     = static fn (?string $v) => $v ? date('Y-m-d\TH:i', strtotime($v)) : '';
$starts = $o('starts_at', $dt($x['starts_at'] ?? null) ?: date('Y-m-d\TH:i'));
$due    = $o('due_at', $dt($x['due_at'] ?? null) ?: date('Y-m-d\T23:59', strtotime('+7 days')));
$qs     = old('questions_json') ? json_decode(old('questions_json'), true) : array_map(static fn ($q) => [
    'type' => $q['type'], 'text' => $q['text'], 'options' => $q['options'], 'correct' => $q['correct'], 'points' => $q['points'], 'topic' => (string) $q['topic'],
], $questions);
$types  = \App\Libraries\Quizzes::TYPES;
$stateBadge = match ($state) { 'scheduled' => badge('Programada'), 'open' => badge('Abierta para ' . ($c['offering']['group_name'] ?? ''), 'accent'), 'closed' => badge('Cerrada'), default => '' };
?>
<form method="post" action="<?= site_url('actividades/' . $c['id']) ?>" novalidate
      x-data="quizEditor(<?= esc(json_encode($qs), 'attr') ?>, <?= esc(json_encode(['bankUrl' => site_url('biblioteca/bancos/{id}/preguntas'), 'aiUrl' => site_url('ia/preguntas'), 'aiContext' => ['offering_id' => (int) $c['offering_id']]]), 'attr') ?>)" @input="dirty = true">
  <?= csrf_field() ?>
  <input type="hidden" name="questions_json" :value="JSON.stringify(questions)">

  <div class="page-header">
    <div>
      <nav class="crumbs text-xs" aria-label="Ubicación"><a class="link" href="<?= site_url('actividades') ?>">Actividades</a><?= icon('chevron-right', 12) ?><span><?= esc(($c['offering']['subject'] ?? '') . ' · ' . ($c['offering']['group_name'] ?? '')) ?></span></nav>
      <div class="row mt-1"><h1><?= esc($c['title']) ?></h1><?= $c['status'] === 'published' ? badge('Publicada', 'ok') : badge('Borrador') ?> <?= $stateBadge ?></div>
    </div>
    <div class="actions">
      <?php if ($x): ?><a class="btn" href="<?= site_url('entregas/' . $x['id']) ?>"><?= icon('inbox', 16) ?> Resultados (<?= (int) ($stats['submitted'] ?? 0) ?>)</a><?php endif ?>
      <button type="submit" name="then" value="template" class="btn" data-loading="Guardando…" title="Guarda los cambios y crea una plantilla en la biblioteca"><?= icon('layout-template', 16) ?><span class="hide-mobile"> Guardar como plantilla</span></button>
      <button type="submit" name="then" value="save" class="btn" data-loading="Guardando…">Guardar</button>
      <button type="submit" name="then" value="publish" class="btn btn-primary" data-loading="Publicando…"><?= icon('send', 16) ?> <?= $c['status'] === 'published' ? 'Guardar y actualizar' : 'Publicar y asignar' ?></button>
    </div>
  </div>
  <?php if (session('errors.questions') ?? (session('errors')['questions'] ?? null)): ?>
    <div class="notice notice-danger mb-4"><?= icon('circle-alert', 16) ?><span><?= esc(session('errors')['questions']) ?></span></div>
  <?php endif ?>

  <div class="split">
    <div class="stack">
      <section class="card"><div class="card-body stack">
        <label class="field"><span class="label">Título <span class="text-danger">*</span></span><input class="input" name="title" value="<?= esc($o('title', $c['title']), 'attr') ?>" maxlength="160" required><?= field_error('title') ?></label>
        <label class="field"><span class="label">Instrucciones para el estudiante</span><textarea class="textarea" name="instructions" rows="2" maxlength="8000" placeholder="Ej.: Lee con calma; puedes revisar tus respuestas antes de terminar."><?= esc($o('instructions', $c['instructions'] ?? '')) ?></textarea></label>
      </div></section>

      <?= $this->include('activities/_questions') ?>
    </div>

    <aside class="split-side stack">
      <section class="card">
        <div class="card-header"><h2>Resumen</h2></div>
        <div class="card-body">
          <dl class="plan-limits">
            <div><dt>Preguntas</dt><dd x-text="questions.length"></dd></div>
            <div><dt>Puntaje total</dt><dd x-text="total() + ' pts'"></dd></div>
            <div><dt>Calificación automática</dt><dd x-text="questions.filter(q => q.type !== 'long').length + ' de ' + questions.length"></dd></div>
            <div><dt>Revisión docente</dt><dd x-text="questions.filter(q => q.type === 'long').length"></dd></div>
          </dl>
        </div>
      </section>

      <section class="card">
        <div class="card-header"><h2>Disponibilidad</h2></div>
        <div class="card-body stack">
          <p class="text-sm">Se asigna a <strong class="font-medium"><?= esc($c['offering']['group_name'] ?? '') ?></strong>.</p>
          <div class="grid-2" style="gap:0.75rem">
            <label class="field"><span class="label">Abre</span><input class="input" type="datetime-local" name="starts_at" value="<?= esc($starts, 'attr') ?>"></label>
            <label class="field"><span class="label">Cierra</span><input class="input" type="datetime-local" name="due_at" value="<?= esc($due, 'attr') ?>"><?= field_error('due_at') ?></label>
            <label class="field"><span class="label">Tiempo límite</span><input class="input" type="number" name="time_limit" min="0" max="600" value="<?= (int) $o('time_limit', $c['time_limit']) ?>"><span class="hint">minutos · 0 = sin límite</span></label>
            <label class="field"><span class="label">Intentos</span><input class="input" type="number" name="max_attempts" min="1" max="10" value="<?= (int) $o('max_attempts', $c['max_attempts']) ?>"><span class="hint">Cuenta el mejor</span></label>
          </div>
          <label class="toggle"><span><span class="block t-title">Orden aleatorio</span><span class="block t-desc">Las preguntas aparecen en distinto orden para cada intento.</span></span>
            <input type="checkbox" name="shuffle" value="1" <?= $o('shuffle', $c['shuffle']) ? 'checked' : '' ?> style="width:1.25rem;height:1.25rem;accent-color:var(--app-accent)"></label>
          <label class="field"><span class="label">El estudiante ve sus resultados</span>
            <select class="select" name="results_mode">
              <option value="after_attempt" <?= $o('results_mode', $c['results_mode']) === 'after_attempt' ? 'selected' : '' ?>>Al terminar cada intento</option>
              <option value="after_close" <?= $o('results_mode', $c['results_mode']) === 'after_close' ? 'selected' : '' ?>>Cuando cierre la evaluación</option>
            </select></label>
        </div>
      </section>

      <section class="card">
        <div class="card-header"><h2>IA durante la evaluación</h2></div>
        <div class="card-body"><div class="notice"><?= icon('lock', 16, 'soft') ?><span class="text-xs">Desactivada: el tutor no responde mientras el estudiante presenta una evaluación.</span></div></div>
      </section>
    </aside>
  </div>

  <?= $this->include('activities/_bank_picker') ?>
  <?php if (! empty($aiGen)): ?><?= $this->include('activities/_ai_generate') ?><?php endif ?>
</form>
<?= $this->endSection() ?>
