<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
/**
 * Editor de reto (datos reales, documento maestro 4.4). Un formulario: contenido, pasos, pistas y rúbrica a la
 * izquierda; asignación, evaluación y tutor en la columna lateral. «Publicar y asignar» guarda, publica y asigna al
 * grupo de la oferta con las fechas indicadas.
 *
 * @var array $c reto (get()), $state estado de la asignación, $stats conteo de entregas
 */
$o      = static fn (string $k, $def) => old($k) ?? $def;
$x      = $c['assignment'];
$dt     = static fn (?string $v) => $v ? date('Y-m-d\TH:i', strtotime($v)) : '';
$starts = $o('starts_at', $dt($x['starts_at'] ?? null) ?: date('Y-m-d\TH:i'));
$due    = $o('due_at', $dt($x['due_at'] ?? null) ?: date('Y-m-d\T23:59', strtotime('+7 days')));
$modes  = ['individual' => ['Individual', 'Cada estudiante entrega su propio trabajo.'], 'pairs' => ['En parejas', 'Por ahora cada estudiante entrega; la entrega por pareja llega después.'], 'groups' => ['En grupos', 'Por ahora cada estudiante entrega; la entrega por grupo llega después.']];
$aiModes = ['off' => ['Sin IA', 'El tutor no está disponible en este reto.'], 'guide' => ['Modo guía', 'Pregunta y da pistas, nunca la respuesta final. Recomendado.'], 'explain' => ['Modo explicación', 'Puede explicar conceptos con ejemplos parecidos.']];
$levels = \App\Libraries\Activities::LEVELS;
$stateBadge = match ($state) { 'scheduled' => badge('Programado'), 'open' => badge('Abierto para ' . ($c['offering']['group_name'] ?? ''), 'accent'), 'closed' => badge('Cerrado'), default => '' };
?>
<form method="post" action="<?= site_url('actividades/' . $c['id']) ?>" novalidate
      x-data="{ tab: 'content', dirty: false,
        steps: <?= esc(json_encode(old('steps_json') ? json_decode(old('steps_json'), true) : $c['steps']), 'attr') ?>,
        hints: <?= esc(json_encode(old('hints_json') ? json_decode(old('hints_json'), true) : $c['hints']), 'attr') ?>,
        rubric: <?= esc(json_encode(old('rubric_json') ? json_decode(old('rubric_json'), true) : $c['rubric']), 'attr') ?>,
        total() { return this.rubric.criteria.reduce((a, c) => a + Number(c.weight || 0), 0); } }"
      @input="dirty = true">
  <?= csrf_field() ?>
  <input type="hidden" name="steps_json" :value="JSON.stringify(steps)">
  <input type="hidden" name="hints_json" :value="JSON.stringify(hints)">
  <input type="hidden" name="rubric_json" :value="JSON.stringify(rubric)">

  <div class="page-header">
    <div>
      <nav class="crumbs text-xs" aria-label="Ubicación">
        <a class="link" href="<?= site_url('actividades') ?>">Actividades</a><?= icon('chevron-right', 12) ?><span><?= esc(($c['offering']['subject'] ?? '') . ' · ' . ($c['offering']['group_name'] ?? '')) ?></span>
      </nav>
      <div class="row mt-1"><h1><?= esc($c['title']) ?></h1><?= $c['status'] === 'published' ? badge('Publicado', 'ok') : badge('Borrador') ?> <?= $stateBadge ?></div>
    </div>
    <div class="actions">
      <?php if ($x): ?>
        <a class="btn" href="<?= site_url('entregas/' . $x['id']) ?>"><?= icon('inbox', 16) ?> Entregas (<?= (int) ($stats['submitted'] ?? 0) ?>)</a>
      <?php endif ?>
      <button type="submit" name="then" value="template" class="btn" data-loading="Guardando…" title="Guarda los cambios y crea una plantilla en la biblioteca"><?= icon('layout-template', 16) ?><span class="hide-mobile"> Guardar como plantilla</span></button>
      <button type="submit" name="then" value="save" class="btn" data-loading="Guardando…">Guardar</button>
      <button type="submit" name="then" value="publish" class="btn btn-primary" data-loading="Publicando…"><?= icon('send', 16) ?> <?= $c['status'] === 'published' ? 'Guardar y actualizar' : 'Publicar y asignar' ?></button>
    </div>
  </div>

  <div class="tabs" role="tablist">
    <button type="button" class="tab" role="tab" :aria-selected="tab === 'content'" @click="tab = 'content'">Contenido</button>
    <button type="button" class="tab" role="tab" :aria-selected="tab === 'rubric'" @click="tab = 'rubric'">Rúbrica <span class="soft text-xs" x-text="'(' + total() + ' %)'"></span></button>
  </div>

  <div class="split">
    <div>
      <div class="stack" x-show="tab === 'content'">
        <section class="card"><div class="card-body stack">
          <label class="field"><span class="label">Título <span class="text-danger">*</span></span><input class="input" name="title" value="<?= esc($o('title', $c['title']), 'attr') ?>" maxlength="160" required><?= field_error('title') ?></label>
          <label class="field"><span class="label">Objetivo de aprendizaje</span><input class="input" name="objective" value="<?= esc($o('objective', $c['objective'] ?? ''), 'attr') ?>" maxlength="255" placeholder="Ej.: Aplicar fracciones equivalentes para escalar cantidades"><span class="hint">Lo usará el tutor de IA para guiar sin salirse del tema.</span><?= field_error('objective') ?></label>
          <label class="field"><span class="label">Situación e instrucciones</span><textarea class="textarea" name="instructions" rows="5" maxlength="8000" placeholder="Describa la situación real y lo que deben hacer."><?= esc($o('instructions', $c['instructions'] ?? '')) ?></textarea><?= field_error('instructions') ?></label>
        </div></section>

        <section class="card">
          <div class="card-header"><h2>Pasos del reto</h2><button type="button" class="btn btn-sm" @click="steps.push({ title: '', text: '' }); dirty = true"><?= icon('plus', 14) ?> Agregar paso</button></div>
          <template x-if="steps.length === 0"><div class="card-body"><p class="soft">Sin pasos. Divida el reto en 3 o 4 pasos para guiar a los estudiantes.</p></div></template>
          <ol class="step-list">
            <template x-for="(s, i) in steps" :key="i">
              <li class="step-row">
                <span class="step-num" aria-hidden="true" x-text="i + 1"></span>
                <div class="step-fields">
                  <input class="input" x-model="s.title" maxlength="120" :aria-label="'Título del paso ' + (i + 1)" placeholder="Ej.: Encuentra el factor">
                  <textarea class="textarea" rows="2" x-model="s.text" maxlength="1000" :aria-label="'Descripción del paso ' + (i + 1)"></textarea>
                </div>
                <div class="row" style="align-self:flex-start">
                  <button type="button" class="btn btn-sm btn-icon" :disabled="i === 0" @click="[steps[i - 1], steps[i]] = [steps[i], steps[i - 1]]; dirty = true" :title="'Subir el paso ' + (i + 1)" :aria-label="'Subir el paso ' + (i + 1)"><?= icon('arrow-up', 14) ?></button>
                  <button type="button" class="btn btn-sm btn-icon btn-danger" @click="steps.splice(i, 1); dirty = true" :title="'Quitar el paso ' + (i + 1)" :aria-label="'Quitar el paso ' + (i + 1)"><?= icon('trash-2', 14) ?></button>
                </div>
              </li>
            </template>
          </ol>
        </section>

        <section class="card"><div class="card-body">
          <label class="field"><span class="label">Entregable esperado</span><textarea class="textarea" name="deliverable" rows="2" maxlength="2000" placeholder="Ej.: Tabla con las cantidades y un párrafo de explicación."><?= esc($o('deliverable', $c['deliverable'] ?? '')) ?></textarea><?= field_error('deliverable') ?></label>
        </div></section>

        <section class="card">
          <div class="card-header"><h2>Pistas progresivas</h2><button type="button" class="btn btn-sm" @click="hints.push({ text: '' }); dirty = true"><?= icon('plus', 14) ?> Agregar pista</button></div>
          <div class="card-body stack-sm">
            <template x-for="(h, i) in hints" :key="i">
              <div class="hint-row">
                <span class="badge" x-text="'Pista ' + (i + 1)"></span>
                <input class="input" style="flex:1" x-model="h.text" maxlength="500" :aria-label="'Texto de la pista ' + (i + 1)">
                <button type="button" class="btn btn-sm btn-icon btn-danger" @click="hints.splice(i, 1); dirty = true" :title="'Quitar la pista ' + (i + 1)" :aria-label="'Quitar la pista ' + (i + 1)"><?= icon('trash-2', 14) ?></button>
              </div>
            </template>
            <p class="hint">El estudiante las abre una a una, en orden.</p>
          </div>
        </section>
      </div>

      <section class="card" x-show="tab === 'rubric'" x-cloak>
        <div class="card-header">
          <h2>Rúbrica</h2>
          <div class="row"><span class="text-xs" :class="total() === 100 ? 'soft' : 'text-danger'" x-text="'Pesos: ' + total() + ' % (deben sumar 100 %)'"></span>
            <button type="button" class="btn btn-sm" @click="rubric.criteria.push({ name: '', weight: 0, desc: ['', '', '', ''] }); dirty = true"><?= icon('plus', 14) ?> Agregar criterio</button></div>
        </div>
        <?= field_error('rubric') ?>
        <div class="table-wrap" style="border:0;border-radius:0">
          <table class="table rubric-table">
            <thead><tr><th>Criterio</th><th style="width:5.5rem">Peso</th><?php foreach ($levels as $lv): ?><th><?= esc($lv) ?></th><?php endforeach ?><th></th></tr></thead>
            <tbody>
              <template x-for="(cr, i) in rubric.criteria" :key="i">
                <tr>
                  <td><input class="input" x-model="cr.name" maxlength="120" :aria-label="'Nombre del criterio ' + (i + 1)"></td>
                  <td><input class="input" type="number" min="0" max="100" x-model.number="cr.weight" :aria-label="'Peso del criterio ' + (i + 1)"></td>
                  <?php foreach ($levels as $li => $lv): ?>
                    <td><textarea class="textarea" rows="2" style="min-height:3.5rem" x-model="cr.desc[<?= $li ?>]" :aria-label="'<?= esc($lv, 'js') ?> en el criterio ' + (i + 1)"></textarea></td>
                  <?php endforeach ?>
                  <td><button type="button" class="btn btn-sm btn-icon btn-danger" @click="rubric.criteria.splice(i, 1); dirty = true" :title="'Quitar el criterio ' + (i + 1)" :aria-label="'Quitar el criterio ' + (i + 1)"><?= icon('trash-2', 14) ?></button></td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </section>
    </div>

    <aside class="split-side stack">
      <section class="card">
        <div class="card-header"><h2>Asignación</h2></div>
        <div class="card-body stack">
          <p class="text-sm">Se asigna a <strong class="font-medium"><?= esc($c['offering']['group_name'] ?? '') ?></strong>, el grupo de esta asignatura.</p>
          <div class="grid-2" style="gap:0.75rem">
            <label class="field"><span class="label">Inicio</span><input class="input" type="datetime-local" name="starts_at" value="<?= esc($starts, 'attr') ?>"></label>
            <label class="field"><span class="label">Entrega</span><input class="input" type="datetime-local" name="due_at" value="<?= esc($due, 'attr') ?>"><?= field_error('due_at') ?></label>
          </div>
          <label class="toggle"><span><span class="block t-title">Aceptar entregas tardías</span><span class="block t-desc">Se marcan como tardías; usted decide si afectan la nota.</span></span>
            <input type="checkbox" name="allow_late" value="1" <?= $o('allow_late', $x['allow_late'] ?? 1) ? 'checked' : '' ?> style="width:1.25rem;height:1.25rem;accent-color:var(--app-accent)"></label>
          <fieldset class="fieldset">
            <legend class="label">Modalidad</legend>
            <div class="radio-cards">
              <?php foreach ($modes as $k => [$t, $d]): ?>
                <label class="radio-card"><input type="radio" name="mode" value="<?= $k ?>" <?= $o('mode', $c['mode']) === $k ? 'checked' : '' ?>><span><span class="block font-medium"><?= esc($t) ?></span><span class="block text-xs soft"><?= esc($d) ?></span></span></label>
              <?php endforeach ?>
            </div>
          </fieldset>
        </div>
      </section>

      <section class="card">
        <div class="card-header"><h2>Evaluación</h2></div>
        <div class="card-body grid-2" style="gap:0.75rem">
          <label class="field"><span class="label">Dificultad</span><select class="select" name="difficulty"><?php foreach (['low' => 'Baja', 'medium' => 'Media', 'high' => 'Alta'] as $k => $l): ?><option value="<?= $k ?>" <?= $o('difficulty', $c['difficulty']) === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach ?></select></label>
          <label class="field"><span class="label">Puntaje</span><input class="input" type="number" name="points" min="0" max="1000" value="<?= (int) $o('points', $c['points']) ?>"></label>
          <label class="field col-full"><span class="label">Reintentos</span><input class="input" type="number" name="retries" min="0" max="10" value="<?= (int) $o('retries', $c['retries']) ?>"><span class="hint">Veces que puede volver a entregar si usted le devuelve el trabajo.</span></label>
        </div>
      </section>

      <section class="card">
        <div class="card-header"><h2>Tutor de IA en este reto</h2></div>
        <div class="card-body">
          <div class="radio-cards">
            <?php foreach ($aiModes as $k => [$t, $d]): ?>
              <label class="radio-card"><input type="radio" name="ai_mode" value="<?= $k ?>" <?= $o('ai_mode', $c['ai_mode']) === $k ? 'checked' : '' ?>><span><span class="block font-medium"><?= esc($t) ?></span><span class="block text-xs soft"><?= esc($d) ?></span></span></label>
            <?php endforeach ?>
          </div>
          <p class="hint mt-2">Define cómo ayuda el tutor de IA al estudiante en este reto.</p>
        </div>
      </section>
    </aside>
  </div>
</form>
<?= $this->endSection() ?>
