<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
/**
 * Banco de preguntas (documento maestro 4.3). Quien lo creó y la coordinación lo editan con el mismo editor de las
 * evaluaciones (más la dificultad); los demás docentes lo consultan y lo usan.
 *
 * @var array $bank, $questions, $topics, $subjects, $offerings, $quizzes; bool $canEdit
 */
$types  = \App\Libraries\Quizzes::TYPES;
$diffs  = \App\Libraries\Quizzes::DIFFICULTIES;
$o      = static fn (string $k, $def) => old($k) ?? $def;
$qs     = old('questions_json') ? json_decode(old('questions_json'), true) : array_map(static fn ($q) => [
    'type' => $q['type'], 'text' => $q['text'], 'options' => $q['options'], 'correct' => $q['correct'], 'points' => $q['points'],
    'topic' => (string) $q['topic'], 'difficulty' => $q['difficulty'],
], $questions);
$count  = static fn (string $d) => count(array_filter($questions, static fn ($q) => $q['difficulty'] === $d));
$rqData = esc(json_encode(['id' => (int) $bank['id'], 'name' => $bank['name'], 'n' => count($questions)]), 'attr');
$crumbs = '<nav class="crumbs text-xs" aria-label="Ubicación"><a class="link" href="' . site_url('biblioteca') . '">Biblioteca</a>' . icon('chevron-right', 12) . '<span>Bancos de preguntas</span></nav>';
$errQ   = session('errors')['questions'] ?? null;
?>
<div x-data="{ rq: null }">
<?php if ($canEdit): ?>
<form method="post" action="<?= site_url('biblioteca/bancos/' . $bank['id']) ?>" novalidate
      x-data="quizEditor(<?= esc(json_encode($qs), 'attr') ?>, <?= esc(json_encode(['aiUrl' => site_url('ia/preguntas'), 'aiContext' => ['subject_id' => (int) $bank['subject_id']]]), 'attr') ?>)" @input="dirty = true">
  <?= csrf_field() ?>
  <input type="hidden" name="questions_json" :value="JSON.stringify(questions)">

  <div class="page-header">
    <div><?= $crumbs ?><div class="row mt-1"><h1><?= esc($bank['name']) ?></h1><?= badge($bank['subject'] ?? 'General') ?></div></div>
    <div class="actions">
      <button type="button" class="btn" <?= $questions && $offerings ? '' : 'disabled' ?> @click="rq = <?= $rqData ?>"><?= icon('shuffle', 16) ?> Evaluación aleatoria</button>
      <button type="submit" class="btn btn-primary" data-loading="Guardando…">Guardar</button>
    </div>
  </div>
  <?php if ($errQ): ?><div class="notice notice-danger mb-4"><?= icon('circle-alert', 16) ?><span><?= esc($errQ) ?></span></div><?php endif ?>

  <div class="split">
    <div class="stack">
      <section class="card"><div class="card-body stack">
        <div class="grid-2" style="gap:0.75rem">
          <label class="field"><span class="label">Nombre <span class="text-danger">*</span></span><input class="input" name="name" maxlength="120" required value="<?= esc($o('name', $bank['name']), 'attr') ?>"><?= field_error('name') ?></label>
          <label class="field"><span class="label">Asignatura</span>
            <select class="select" name="subject_id">
              <option value="">General (cualquier asignatura)</option>
              <?php foreach ($subjects as $s): ?><option value="<?= (int) $s['id'] ?>" <?= (string) $o('subject_id', $bank['subject_id']) === (string) $s['id'] ? 'selected' : '' ?>><?= esc($s['name']) ?></option><?php endforeach ?>
            </select></label>
        </div>
        <label class="field"><span class="label">Descripción</span><textarea class="textarea" name="description" rows="2" maxlength="2000"><?= esc($o('description', $bank['description'] ?? '')) ?></textarea></label>
      </div></section>

      <datalist id="q-topics"><?php foreach ($topics as $tp): ?><option value="<?= esc($tp, 'attr') ?>"><?php endforeach ?></datalist>
      <?= $this->include('activities/_questions') ?>
      <p class="hint" x-show="!questions.length">Agregue preguntas con su tema y dificultad, o importe las de una evaluación suya.</p>
    </div>

    <aside class="split-side stack">
      <section class="card">
        <div class="card-header"><h2>Resumen</h2></div>
        <div class="card-body">
          <dl class="plan-limits">
            <div><dt>Preguntas</dt><dd x-text="questions.length"></dd></div>
            <?php foreach ($diffs as $dk => $dl): ?>
              <div><dt><?= esc($dl) ?></dt><dd x-text="questions.filter(q => q.difficulty === '<?= $dk ?>').length"></dd></div>
            <?php endforeach ?>
            <div><dt>Temas</dt><dd x-text="new Set(questions.map(q => (q.topic || '').trim()).filter(Boolean)).size"></dd></div>
          </dl>
        </div>
      </section>

      <?php if ($inNetwork): ?>
        <section class="card">
          <div class="card-header"><h2>Red educativa</h2><?= $bank['network_shared'] ? badge('Compartido', 'ok') : '' ?></div>
          <div class="card-body stack">
            <p class="text-sm"><?= $bank['network_shared'] ? 'Los demás colegios de la red pueden ver este banco y copiarlo a su institución.' : 'Ofrezca este banco a los demás colegios de la red: lo podrán ver y copiar (no editar el suyo).' ?></p>
            <button type="submit" form="bank-share" class="btn" @click="if (dirty && !confirm('Tiene cambios sin guardar; se perderán. ¿Continuar?')) $event.preventDefault()"><?= icon($bank['network_shared'] ? 'eye-off' : 'share-2', 16) ?> <?= $bank['network_shared'] ? 'Dejar de compartir' : 'Compartir con la red' ?></button>
          </div>
        </section>
      <?php endif ?>

      <section class="card">
        <div class="card-header"><h2>Importar de una evaluación</h2></div>
        <div class="card-body stack">
          <?php if (! $quizzes): ?>
            <p class="hint">Aún no tiene evaluaciones para importar.</p>
          <?php else: ?>
            <select class="select" name="activity_id" form="bank-import" @input.stop aria-label="Evaluación de la que se importan preguntas">
              <?php foreach ($quizzes as $q): ?><option value="<?= (int) $q['id'] ?>"><?= esc($q['title'] . ' · ' . $q['label']) ?></option><?php endforeach ?>
            </select>
            <button type="submit" form="bank-import" class="btn" data-loading="Importando…" @click="if (dirty && !confirm('Tiene cambios sin guardar en el banco; al importar se pierden. ¿Continuar?')) $event.preventDefault()"><?= icon('download', 16) ?> Importar preguntas</button>
            <p class="hint">Se agregan al final las que no estén ya en el banco, con dificultad «Media».</p>
          <?php endif ?>
        </div>
      </section>

      <section class="card"><div class="card-body">
        <button type="submit" form="bank-delete" class="btn btn-danger"><?= icon('trash-2', 16) ?> Eliminar banco</button>
      </div></section>
    </aside>
  </div>
  <?php if (! empty($aiGen)): ?><?= $this->include('activities/_ai_generate') ?><?php endif ?>
</form>

<form id="bank-share" method="post" action="<?= site_url('biblioteca/bancos/' . $bank['id'] . '/compartir') ?>"><?= csrf_field() ?><input type="hidden" name="on" value="<?= $bank['network_shared'] ? '' : '1' ?>"></form>
<form id="bank-import" method="post" action="<?= site_url('biblioteca/bancos/' . $bank['id'] . '/importar') ?>"><?= csrf_field() ?></form>
<form id="bank-delete" method="post" action="<?= site_url('biblioteca/bancos/' . $bank['id'] . '/eliminar') ?>" onsubmit="return confirm('¿Eliminar el banco «<?= esc($bank['name'], 'js') ?>»? Las evaluaciones que usaron sus preguntas no cambian.')"><?= csrf_field() ?></form>
<?php else: ?>
  <div class="page-header">
    <div><?= $crumbs ?><div class="row mt-1"><h1><?= esc($bank['name']) ?></h1><?= badge($bank['subject'] ?? 'General') ?></div>
      <?php if ($bank['description']): ?><p><?= esc($bank['description']) ?></p><?php endif ?></div>
    <div class="actions">
      <button type="button" class="btn btn-primary" <?= $questions && $offerings ? '' : 'disabled' ?> @click="rq = <?= $rqData ?>"><?= icon('shuffle', 16) ?> Evaluación aleatoria</button>
    </div>
  </div>
  <div class="split">
    <section class="panel">
      <?php if (! $questions): ?>
        <div class="empty"><?= icon('library', 32) ?><p>Este banco aún no tiene preguntas.</p></div>
      <?php endif ?>
      <?php foreach ($questions as $i => $q): ?>
        <div class="bank-q">
          <span class="step-num" aria-hidden="true"><?= $i + 1 ?></span>
          <div style="min-width:0;flex:1">
            <div><?= nl2br(esc($q['text'])) ?></div>
            <?php if (in_array($q['type'], ['single', 'multiple', 'truefalse'], true)): ?>
              <ol type="a"><?php foreach ($q['options'] as $j => $op): ?><li class="<?= in_array($j, (array) $q['correct'], true) ? 'is-correct' : '' ?>"><?= esc($op) ?><?= in_array($j, (array) $q['correct'], true) ? ' <span class="sr-only">(correcta)</span>' : '' ?></li><?php endforeach ?></ol>
            <?php elseif ($q['type'] === 'numeric'): ?>
              <p class="text-xs soft mt-1">Respuesta: <?= esc(str_replace('.', ',', (string) $q['correct']['value'])) ?><?= (float) $q['correct']['tolerance'] ? ' ± ' . esc(str_replace('.', ',', (string) $q['correct']['tolerance'])) : '' ?></p>
            <?php elseif ($q['type'] === 'short'): ?>
              <p class="text-xs soft mt-1">Respuestas aceptadas: <?= esc(implode('; ', (array) $q['correct'])) ?></p>
            <?php endif ?>
            <p class="text-xs soft mt-1"><?= esc($types[$q['type']]) ?> · <?= esc($q['topic'] ?: 'Sin tema') ?> · <?= esc($diffs[$q['difficulty']] ?? 'Media') ?> · <?= esc(str_replace('.', ',', (string) $q['points'])) ?> pts</p>
          </div>
        </div>
      <?php endforeach ?>
    </section>
    <aside class="split-side stack">
      <section class="card">
        <div class="card-header"><h2>Resumen</h2></div>
        <div class="card-body">
          <dl class="plan-limits">
            <div><dt>Preguntas</dt><dd><?= count($questions) ?></dd></div>
            <?php foreach ($diffs as $dk => $dl): ?><div><dt><?= esc($dl) ?></dt><dd><?= $count($dk) ?></dd></div><?php endforeach ?>
            <div><dt>Temas</dt><dd><?= count($topics) ?></dd></div>
          </dl>
          <p class="hint mt-2">Lo edita quien lo creó o la coordinación. Use «Agregar desde un banco» en sus evaluaciones para copiar preguntas.</p>
        </div>
      </section>
    </aside>
  </div>
<?php endif ?>

<?php if ($offerings): ?><?= $this->include('library/_random_modal') ?><?php endif ?>
</div>
<?= $this->endSection() ?>
