<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
/**
 * Docentes (coordinación y rectoría): carga y retroalimentación pendiente del periodo. Acompañamiento, no vigilancia:
 * sin rankings ni registro minuto a minuto.
 *
 * @var array  $list (Teachers::list() filtrado), $kpi
 * @var string $q, $filter
 */
use App\Libraries\Teachers;

$days = static fn (?string $dt) => Teachers::daysSince($dt);
?>
<div class="page-header">
  <div><h1>Docentes</h1><p>Carga de trabajo y retroalimentación pendiente del periodo, para saber a quién acompañar.</p></div>
</div>

<div class="grid-kpi">
  <div class="kpi"><div class="k-label">Docentes</div><div class="k-value"><?= $kpi['teachers'] ?></div><div class="k-hint"><?= plural($kpi['published'], 'actividad publicada', 'actividades publicadas') ?></div></div>
  <div class="kpi"><div class="k-label">Entregas por revisar</div><div class="k-value"><?= $kpi['to_review'] ?></div><div class="k-hint">Retos y evaluaciones con respuestas abiertas</div></div>
  <div class="kpi"><div class="k-label">Con revisión atrasada</div><div class="k-value <?= $kpi['late'] ? 'text-warn' : '' ?>"><?= $kpi['late'] ?></div><div class="k-hint">Entregas sin revisar hace <?= Teachers::LATE_REVIEW_DAYS ?> días o más</div></div>
  <div class="kpi"><div class="k-label">Para acompañar</div><div class="k-value <?= $kpi['alerts'] ? 'text-warn' : '' ?>"><?= $kpi['alerts'] ?></div><div class="k-hint">Con alguna señal para revisar</div></div>
</div>

<section class="panel mt-4">
  <form class="panel-bar" method="get" action="<?= site_url('docentes') ?>">
    <label class="input-icon"><span class="sr-only">Buscar docente</span><?= icon('search', 16) ?><input class="input" type="search" name="q" value="<?= esc($q, 'attr') ?>" placeholder="Docente, asignatura o grupo"></label>
    <select class="select" name="ver" aria-label="Mostrar" onchange="this.form.submit()" style="width:auto">
      <option value="">Todos los docentes</option>
      <option value="alertas" <?= $filter === 'alertas' ? 'selected' : '' ?>>Solo los que tienen señales</option>
    </select>
    <?php if ($q !== '' || $filter !== ''): ?><a class="btn btn-ghost btn-sm" href="<?= site_url('docentes') ?>">Limpiar filtros</a><?php endif ?>
    <span class="panel-count"><?= plural(count($list), 'docente', 'docentes') ?></span>
  </form>
  <?php if (! $list): ?>
    <div class="empty"><?= icon('presentation', 32) ?><p><?= $q !== '' || $filter !== '' ? 'Ningún docente coincide con los filtros.' : 'Aún no hay docentes con asignaturas en el periodo.' ?></p></div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Docente</th><th>Asignaturas</th><th class="num">Actividades</th><th>Por revisar</th><th>Cuadernos</th><th>Último acceso</th><th>Señales</th><th class="text-right">Acciones</th></tr></thead>
        <tbody>
          <?php foreach ($list as $t): $old = $days($t['oldest']); ?>
            <tr>
              <td><a class="row link" href="<?= site_url('docentes/' . $t['id']) ?>"><span class="avatar" aria-hidden="true"><?= esc(initials($t['name'])) ?></span>
                <span><span class="font-medium"><?= esc($t['name']) ?></span><span class="meta"><?= esc($t['role'] ?? 'Docente') ?></span></span></a></td>
              <td><?php if (! $t['offerings']): ?><span class="soft">—</span><?php else: ?>
                <?php $bySubject = []; foreach ($t['offerings'] as $o) { $bySubject[$o['subject']][] = $o; } ?>
                <div class="subj-list">
                  <?php foreach ($bySubject as $subject => $offs): ?>
                    <details class="subj-groups" style="--cat:<?= cat_color($offs[0]['color']) ?>">
                      <summary><span class="cat-dot" aria-hidden="true"></span><span class="font-medium"><?= esc($subject) ?></span>
                        <span class="soft">· <?= plural(count($offs), 'grupo', 'grupos') ?></span><?= icon('chevron-down', 12, 'subj-chev') ?></summary>
                      <ul><?php foreach ($offs as $o): ?><li><?= esc($o['group_name']) ?> <span class="soft">· <?= plural((int) $o['students'], 'estudiante', 'estudiantes') ?></span></li><?php endforeach ?></ul>
                    </details>
                  <?php endforeach ?>
                </div>
                <span class="meta"><?= plural($t['students'], 'estudiante', 'estudiantes') ?></span><?php endif ?></td>
              <td class="num"><?= (int) $t['published'] ?><span class="meta"><?= $t['open'] ? plural($t['open'], 'abierta', 'abiertas') : 'ninguna abierta' ?><?= $t['drafts'] ? ' · ' . plural($t['drafts'], 'borrador', 'borradores') : '' ?></span></td>
              <td><?php if (! $t['to_review']): ?><span class="soft">Al día</span><?php else: ?>
                <?= badge((string) $t['to_review'], $old !== null && $old >= Teachers::LATE_REVIEW_DAYS ? 'warn' : 'neutral') ?>
                <span class="meta">la más antigua hace <?= plural((int) $old, 'día', 'días') ?></span><?php endif ?></td>
              <td><?= $t['pages'] ? plural($t['pages'], 'página', 'páginas') : '<span class="soft">Sin páginas</span>' ?><?= $t['last_page'] ? '<span class="meta">última ' . esc(date_short($t['last_page'], false)) . '</span>' : '' ?></td>
              <td class="nowrap"><?= $t['last_access_at'] ? esc(date_short($t['last_access_at'])) : '<span class="soft">Nunca</span>' ?></td>
              <td><?php if ($t['alerts']): ?><span class="row text-xs text-warn" title="<?= esc(implode(' · ', $t['alerts']), 'attr') ?>"><?= icon('triangle-alert', 14) ?> <?= esc($t['alerts'][0]) ?><?= count($t['alerts']) > 1 ? ' (+' . (count($t['alerts']) - 1) . ')' : '' ?></span><?php else: ?><span class="soft text-xs">—</span><?php endif ?></td>
              <td class="text-right"><a class="btn btn-sm" href="<?= site_url('docentes/' . $t['id']) ?>"><?= icon('id-card', 14) ?> Ver ficha</a></td>
            </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>
  <?php endif ?>
</section>
<p class="hint mt-2">Las señales son para conversar y acompañar, no una evaluación del desempeño. «Por revisar» cuenta retos entregados y evaluaciones con respuestas abiertas pendientes. El consumo de IA de cada docente está en su ficha.</p>
<?= $this->endSection() ?>
