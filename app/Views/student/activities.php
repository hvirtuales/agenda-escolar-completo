<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
/**
 * Mis actividades (estudiante): todos sus retos y evaluaciones por estado, con filtro por asignatura.
 *
 * @var array $acts StudentProgress::activities(), string $tab, string $subject
 */
$groups   = \App\Libraries\StudentProgress::GROUPS;
$subjects = array_values(array_unique(array_column($acts, 'subject')));
sort($subjects);
$inGroup  = static fn (string $g) => array_values(array_filter($acts, static fn ($a) => in_array($a['state'], $groups[$g][1], true)));
$list     = array_values(array_filter($inGroup($tab), static fn ($a) => $subject === '' || $a['subject'] === $subject));
usort($list, static fn ($a, $b) => $tab === 'pending' ? strcmp($a['due_at'], $b['due_at']) : strcmp($b['due_at'], $a['due_at']));
$badgeOf  = static fn (array $a) => match ($a['state']) {
    'graded'      => badge('Nota ' . number_format((float) $a['grade'], 1, ',', ''), (float) $a['grade'] < 3 ? 'danger' : ((float) $a['grade'] < 4 ? 'warn' : 'ok')),
    'submitted'   => badge('Por revisar', 'accent'),
    'returned'    => badge('Devuelta: corrígela', 'warn'),
    'in_progress' => badge('En curso'),
    'late'        => badge('Vencida: aún puedes entregar', 'warn'),
    'missed'      => badge('Vencida', 'danger'),
    default       => badge('Por hacer'),
};
$q = static fn (array $extra) => site_url('estudiante/actividades') . '?' . http_build_query(array_filter(array_merge(['ver' => $tab, 'asignatura' => $subject], $extra), 'strlen'));
$emptyMsg = ['pending' => '¡Nada pendiente! Cuando tus docentes asignen algo, aparecerá aquí.', 'sent' => 'No tienes entregas esperando revisión.',
    'graded' => 'Aún no tienes actividades calificadas.', 'missed' => 'No tienes actividades vencidas. ¡Bien!'];
?>
<div class="page-header">
  <div><h1>Mis actividades</h1><p>Todos tus retos y evaluaciones. Empieza por los que vencen primero.</p></div>
</div>

<div class="toolbar">
  <div class="chips" role="tablist" aria-label="Estado">
    <?php foreach ($groups as $key => [$label]): ?>
      <a class="chip" role="tab" aria-selected="<?= $tab === $key ? 'true' : 'false' ?>" href="<?= esc($q(['ver' => $key]), 'attr') ?>"><?= esc($label) ?> (<?= count($inGroup($key)) ?>)</a>
    <?php endforeach ?>
  </div>
  <?php if (count($subjects) > 1): ?>
    <form method="get" action="<?= site_url('estudiante/actividades') ?>">
      <input type="hidden" name="ver" value="<?= esc($tab, 'attr') ?>">
      <select class="select" name="asignatura" aria-label="Asignatura" onchange="this.form.submit()" style="width:auto">
        <option value="">Todas las asignaturas</option>
        <?php foreach ($subjects as $sj): ?><option <?= $subject === $sj ? 'selected' : '' ?>><?= esc($sj) ?></option><?php endforeach ?>
      </select>
    </form>
  <?php endif ?>
</div>

<section class="panel">
  <?php if (! $list): ?>
    <div class="empty"><?= icon($tab === 'pending' ? 'party-popper' : 'inbox', 32) ?><p><?= esc($emptyMsg[$tab]) ?></p></div>
  <?php else: ?>
    <div class="date-list">
      <?php foreach ($list as $a): ?>
        <a class="date-row" href="<?= site_url($a['url']) ?>" style="--cat:<?= esc(cat_color($a['color']), 'attr') ?>">
          <span class="act-icon"><?= icon($a['kind'] === 'quiz' ? 'clipboard-list' : 'target', 18) ?></span>
          <span style="min-width:0;flex:1">
            <span class="block font-medium"><?= esc($a['title']) ?></span>
            <span class="block text-xs soft"><?= esc($a['subject']) ?> · <?= $a['kind'] === 'quiz' ? 'Evaluación' : 'Reto' ?> · <?= ($a['due_at'] > date('Y-m-d H:i:s') ? 'Cierra ' : 'Cerró ') . esc(date_short($a['due_at'])) ?></span>
            <?php if (! empty($a['feedback'])): ?><span class="block text-xs mt-1">«<?= esc(mb_strimwidth($a['feedback'], 0, 140, '…')) ?>»</span><?php endif ?>
          </span>
          <?= $badgeOf($a) ?>
          <?= icon('chevron-right', 16, 'soft') ?>
        </a>
      <?php endforeach ?>
    </div>
  <?php endif ?>
</section>
<?= $this->endSection() ?>
