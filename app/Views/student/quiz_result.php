<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
/** Resultado de un intento (estudiante). Las respuestas correctas solo se muestran si la configuración lo permite. */
$a  = $q['activity'];
$x  = $q['assignment'];
$t  = $q['attempt'];
$show = $q['showResults'];
$fmt  = static fn ($n) => rtrim(rtrim(number_format((float) $n, 2, ',', ''), '0'), ',');
?>
<div class="page-header">
  <div>
    <nav class="crumbs text-xs" aria-label="Ubicación"><a class="link" href="<?= site_url('estudiante/evaluaciones/' . $x['id']) ?>"><?= esc($a['title']) ?></a><?= icon('chevron-right', 12) ?><span>Intento <?= (int) $t['number'] ?></span></nav>
    <h1 class="mt-1">Intento <?= (int) $t['number'] ?></h1>
    <p>Terminado <?= esc(date_human($t['finished_at'])) ?><?= $t['over_time'] ? ' · fuera de tiempo' : '' ?></p>
  </div>
  <div class="actions"><a class="btn" href="<?= site_url('estudiante/evaluaciones/' . $x['id']) ?>"><?= icon('arrow-left', 14) ?> Volver a la evaluación</a></div>
</div>

<?php if ($t['status'] === 'pending_review'): ?>
  <div class="notice mb-4"><?= icon('clock', 16, 'soft') ?><span>Tu docente debe revisar las respuestas abiertas. Verás la nota cuando termine.</span></div>
<?php elseif (! $show): ?>
  <div class="notice mb-4"><?= icon('lock', 16, 'soft') ?><span>Los resultados se publican cuando cierre la evaluación (<?= esc(date_human($x['due_at'])) ?>).</span></div>
<?php else: ?>
  <div class="grid-kpi mb-4">
    <div class="kpi"><div class="k-label">Nota</div><div class="k-value"><?= esc(str_replace('.', ',', (string) $t['grade'])) ?> <span class="text-sm soft">/ 5</span></div></div>
    <div class="kpi"><div class="k-label">Puntaje</div><div class="k-value"><?= $fmt($t['score']) ?> <span class="text-sm soft">de <?= $fmt($t['max_score']) ?></span></div></div>
  </div>
  <?php if ($t['feedback']): ?><div class="notice notice-accent mb-4"><?= icon('message-square-text', 16) ?><span><?= esc($t['feedback']) ?></span></div><?php endif ?>
<?php endif ?>

<?php if ($show && $t['status'] === 'graded'): ?>
  <div class="stack">
    <?php foreach ($q['questions'] as $i => $item):
        $ans  = $t['answers'][$item['id']] ?? null;
        $pts  = \App\Libraries\Quizzes::scoreOf($item, $ans) ?? (float) ($t['manual'][$item['id']] ?? 0);
        $ok   = $pts >= $item['points'] - 1e-9; ?>
      <section class="card">
        <div class="card-header"><h2>Pregunta <?= $i + 1 ?></h2><?= badge($fmt($pts) . ' de ' . $fmt($item['points']) . ' pts', $ok ? 'ok' : ($pts > 0 ? 'warn' : 'danger')) ?></div>
        <div class="card-body stack-sm">
          <p style="white-space:pre-wrap"><?= esc($item['text']) ?></p>
          <?php if ($item['options']): ?>
            <ul class="check-list">
              <?php foreach ($item['options'] as $oi => $op):
                  $chosen  = in_array((string) $oi, array_map('strval', (array) $ans), true);
                  $correct = in_array($oi, (array) $item['correct'], true); ?>
                <li><?= $correct ? icon('circle-check', 16, 'text-ok') : ($chosen ? icon('circle-x', 16, 'text-danger') : icon('circle', 16, 'soft')) ?> <span class="<?= $chosen ? 'font-medium' : '' ?>"><?= esc($op) ?></span><?= $chosen ? ' <span class="soft text-xs">(tu respuesta)</span>' : '' ?></li>
              <?php endforeach ?>
            </ul>
          <?php else: ?>
            <p class="text-sm"><span class="soft">Tu respuesta:</span> <?= $ans !== null && $ans !== '' ? nl2br(esc((string) $ans)) : '<span class="soft">Sin responder</span>' ?></p>
            <?php if ($item['type'] === 'numeric'): ?><p class="text-sm soft">Respuesta correcta: <?= esc((string) $item['correct']['value']) ?></p><?php endif ?>
            <?php if ($item['type'] === 'short'): ?><p class="text-sm soft">Respuestas aceptadas: <?= esc(implode(', ', $item['correct'])) ?></p><?php endif ?>
          <?php endif ?>
        </div>
      </section>
    <?php endforeach ?>
  </div>
<?php endif ?>
<?= $this->endSection() ?>
