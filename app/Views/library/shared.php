<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
/**
 * Banco que otro colegio de la red comparte: solo lectura. «Copiar a mi institución» crea una copia propia editable.
 *
 * @var array $bank con school, subject, author y questions
 */
$types = \App\Libraries\Quizzes::TYPES;
$diffs = \App\Libraries\Quizzes::DIFFICULTIES;
$qs    = $bank['questions'];
?>
<div class="page-header">
  <div>
    <nav class="crumbs text-xs" aria-label="Ubicación"><a class="link" href="<?= site_url('biblioteca') ?>">Biblioteca</a><?= icon('chevron-right', 12) ?><span>Compartidos por la red</span></nav>
    <div class="row mt-1"><h1><?= esc($bank['name']) ?></h1><?= badge($bank['subject'] ?? 'General') ?></div>
    <p>De <?= esc($bank['school']) ?><?= $bank['author'] ? ' · ' . esc($bank['author']) : '' ?><?= $bank['description'] ? ' · ' . esc($bank['description']) : '' ?></p>
  </div>
  <div class="actions">
    <?php if (service('tenant')->can('activities.create')): ?>
      <form method="post" action="<?= site_url('biblioteca/red/' . $bank['id'] . '/copiar') ?>">
        <?= csrf_field() ?><button type="submit" class="btn btn-primary" data-loading="Copiando…"><?= icon('copy', 16) ?> Copiar a mi institución</button>
      </form>
    <?php endif ?>
  </div>
</div>
<div class="split">
  <section class="panel">
    <?php if (! $qs): ?><div class="empty"><?= icon('library', 32) ?><p>Este banco aún no tiene preguntas.</p></div><?php endif ?>
    <?php foreach ($qs as $i => $q): ?>
      <div class="bank-q">
        <span class="step-num" aria-hidden="true"><?= $i + 1 ?></span>
        <div style="min-width:0;flex:1">
          <div><?= nl2br(esc($q['text'])) ?></div>
          <?php if (in_array($q['type'], ['single', 'multiple', 'truefalse'], true)): ?>
            <ol type="a"><?php foreach ($q['options'] as $j => $op): $ok = in_array($j, (array) $q['correct'], true); ?><li class="<?= $ok ? 'is-correct' : '' ?>"><?= esc($op) ?><?= $ok ? ' <span class="sr-only">(correcta)</span>' : '' ?></li><?php endforeach ?></ol>
          <?php elseif ($q['type'] === 'numeric'): ?>
            <p class="text-xs soft mt-1">Respuesta: <?= esc(str_replace('.', ',', (string) $q['correct']['value'])) ?><?= (float) $q['correct']['tolerance'] ? ' ± ' . esc(str_replace('.', ',', (string) $q['correct']['tolerance'])) : '' ?></p>
          <?php elseif ($q['type'] === 'short'): ?>
            <p class="text-xs soft mt-1">Respuestas aceptadas: <?= esc(implode('; ', (array) $q['correct'])) ?></p>
          <?php endif ?>
          <p class="text-xs soft mt-1"><?= esc($types[$q['type']] ?? $q['type']) ?> · <?= esc($q['topic'] ?: 'Sin tema') ?> · <?= esc($diffs[$q['difficulty']] ?? 'Media') ?> · <?= esc(str_replace('.', ',', (string) $q['points'])) ?> pts</p>
        </div>
      </div>
    <?php endforeach ?>
  </section>
  <aside class="split-side stack">
    <section class="card">
      <div class="card-header"><h2>Resumen</h2></div>
      <div class="card-body">
        <dl class="plan-limits">
          <div><dt>Preguntas</dt><dd><?= count($qs) ?></dd></div>
          <?php foreach ($diffs as $dk => $dl): ?><div><dt><?= esc($dl) ?></dt><dd><?= count(array_filter($qs, static fn ($q) => $q['difficulty'] === $dk)) ?></dd></div><?php endforeach ?>
        </dl>
        <p class="hint mt-2">Al copiarlo, el banco pasa a su institución y puede editarlo. Los cambios del colegio de origen no se sincronizan.</p>
      </div>
    </section>
  </aside>
</div>
<?= $this->endSection() ?>
