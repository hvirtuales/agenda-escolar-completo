<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
/**
 * Ficha del docente (coordinación y rectoría): sus asignaturas con indicadores, próximos cierres y entregas pendientes.
 *
 * @var array $t Teachers::profile()
 */
use App\Libraries\Teachers;

$num     = static fn ($v) => $v === null ? '—' : number_format((float) $v, 1, ',', '');
$canRev  = in_array('submissions.review', $perms ?? [], true);
$tok     = static fn (int $n): string => number_format($n, 0, ',', '.');
?>
<div class="page-header">
  <div>
    <nav class="crumbs text-xs" aria-label="Ubicación"><a class="link" href="<?= site_url('docentes') ?>">Docentes</a><?= icon('chevron-right', 12) ?><span><?= esc($t['name']) ?></span></nav>
    <div class="row mt-1"><span class="avatar" aria-hidden="true"><?= esc(initials($t['name'])) ?></span><h1><?= esc($t['name']) ?></h1></div>
    <p><?= esc(($t['role'] ?? 'Docente') . ' · ' . $t['email']) ?> · último acceso <?= $t['last_access_at'] ? esc(date_human($t['last_access_at'])) : 'nunca' ?></p>
  </div>
  <div class="actions"><a class="btn" href="mailto:<?= esc($t['email'], 'attr') ?>"><?= icon('mail', 16) ?> Escribir</a></div>
</div>

<?php if ($t['alerts']): ?>
  <div class="alert-list mb-4">
    <?php foreach ($t['alerts'] as $a): ?><div class="alert-row"><?= icon('triangle-alert', 18, 'text-warn') ?><span><?= esc($a) ?></span></div><?php endforeach ?>
  </div>
<?php endif ?>

<div class="grid-kpi">
  <div class="kpi"><div class="k-label">Asignaturas</div><div class="k-value"><?= count($t['offerings']) ?></div><div class="k-hint"><?= plural($t['students'], 'estudiante', 'estudiantes') ?></div></div>
  <div class="kpi"><div class="k-label">Actividades publicadas</div><div class="k-value"><?= (int) $t['published'] ?></div><div class="k-hint"><?= plural($t['open'], 'abierta', 'abiertas') ?> · <?= plural($t['drafts'], 'borrador', 'borradores') ?></div></div>
  <div class="kpi"><div class="k-label">Por revisar</div><div class="k-value <?= ($d = Teachers::daysSince($t['oldest'])) !== null && $d >= Teachers::LATE_REVIEW_DAYS ? 'text-warn' : '' ?>"><?= (int) $t['to_review'] ?></div><div class="k-hint"><?= $t['oldest'] ? 'La más antigua hace ' . plural((int) $d, 'día', 'días') : 'Al día' ?></div></div>
  <div class="kpi"><div class="k-label">IA este mes</div><div class="k-value"><?= $tok($t['ai_tokens']) ?></div><div class="k-hint">tokens · <?= plural($t['pages'], 'página publicada', 'páginas publicadas') ?></div></div>
</div>

<section class="panel mt-4" aria-labelledby="asig">
  <div class="panel-bar"><h2 id="asig" class="font-medium">Asignaturas del periodo</h2></div>
  <?php if (! $t['offerings']): ?>
    <div class="empty"><?= icon('book-open', 32) ?><p>No tiene asignaturas asignadas en el periodo. Asígnelas en Ajustes › Estructura académica.</p></div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Asignatura y grupo</th><th class="num">Estudiantes</th><th class="num">Actividades</th><th class="num">Por revisar</th><th class="num">Promedio</th><th>Cuaderno</th><th class="text-right">Acciones</th></tr></thead>
        <tbody>
          <?php foreach ($t['offerings'] as $o): ?>
            <tr>
              <td><span class="row" style="--cat:<?= cat_color($o['color']) ?>"><span class="cat-dot" aria-hidden="true"></span><span class="font-medium"><?= esc($o['subject']) ?></span> <span class="soft">· <?= esc($o['group_name']) ?></span></span></td>
              <td class="num"><?= (int) $o['students'] ?></td>
              <td class="num"><?= (int) ($o['published'] ?? 0) ?><span class="meta"><?= (int) ($o['open'] ?? 0) ?> abiertas</span></td>
              <td class="num"><?= (int) ($o['to_review'] ?? 0) ?: '<span class="soft">0</span>' ?></td>
              <td class="num"><?= $num($o['avg'] ?? null) ?></td>
              <td><?= ! empty($o['pages']) ? plural((int) $o['pages'], 'página', 'páginas') . '<span class="meta">última ' . esc(date_short($o['last_page'], false)) . '</span>' : '<span class="soft">Sin páginas publicadas</span>' ?></td>
              <td class="text-right"><a class="btn btn-sm" href="<?= site_url('cuadernos/o' . $o['id']) ?>"><?= icon('notebook-tabs', 14) ?> Ver cuaderno</a></td>
            </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>
  <?php endif ?>
</section>

<div class="grid-2 mt-4" style="align-items:start">
  <section class="card" aria-labelledby="pend">
    <div class="card-header"><h2 id="pend">Entregas pendientes de revisión</h2></div>
    <?php if (! $t['pending']): ?>
      <div class="card-body"><p class="soft text-sm">No tiene entregas pendientes.</p></div>
    <?php else: ?>
      <div class="date-list">
        <?php foreach ($t['pending'] as $p): $d = Teachers::daysSince($p['oldest']); ?>
          <<?= $canRev ? 'a href="' . site_url('entregas/' . $p['assignment_id']) . '"' : 'div' ?> class="date-row">
            <span style="flex:1;min-width:0"><span class="block font-medium truncate"><?= esc($p['title']) ?></span><span class="block text-xs soft"><?= esc($p['subject'] . ' · ' . $p['group_name']) ?> · la más antigua hace <?= plural((int) $d, 'día', 'días') ?></span></span>
            <?= badge(plural((int) $p['n'], 'entrega', 'entregas'), $d >= Teachers::LATE_REVIEW_DAYS ? 'warn' : 'neutral') ?>
          </<?= $canRev ? 'a' : 'div' ?>>
        <?php endforeach ?>
      </div>
    <?php endif ?>
  </section>

  <section class="card" aria-labelledby="prox">
    <div class="card-header"><h2 id="prox">Próximos cierres</h2></div>
    <?php if (! $t['upcoming']): ?>
      <div class="card-body"><p class="soft text-sm">No tiene actividades abiertas por cerrar.</p></div>
    <?php else: ?>
      <div class="date-list">
        <?php foreach ($t['upcoming'] as $u): ?>
          <div class="date-row"><span style="flex:1;min-width:0"><span class="block font-medium truncate"><?= esc($u['title']) ?></span><span class="block text-xs soft"><?= esc(($u['kind'] === 'quiz' ? 'Evaluación' : 'Reto') . ' · ' . $u['subject'] . ' · ' . $u['group_name']) ?></span></span>
            <span class="text-xs soft nowrap"><?= esc(date_short($u['due_at'])) ?></span></div>
        <?php endforeach ?>
      </div>
    <?php endif ?>
  </section>
</div>
<?= $this->endSection() ?>
