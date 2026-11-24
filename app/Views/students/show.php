<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
/**
 * Ficha del estudiante: resumen, asignaturas (actividades con estado, nota, retroalimentación y uso del tutor) y
 * observaciones del docente. El docente ve solo sus asignaturas; la coordinación, todas.
 *
 * @var array $p Students::profile()
 */
$s     = $p['student'];
$num   = static fn ($v) => $v === null ? '—' : number_format((float) $v, 1, ',', '');
$tone  = static fn ($v) => $v === null ? 'neutral' : ($v < 3 ? 'danger' : ($v < 4 ? 'warn' : 'ok'));
$state = static fn (array $i) => match (true) {
    $i['status'] === 'reviewed'    => badge('Calificada', 'ok'),
    $i['status'] === 'submitted'   => badge('Por revisar', 'accent'),
    $i['status'] === 'returned'    => badge('Devuelta', 'warn'),
    $i['status'] === 'in_progress' => badge('En curso'),
    $i['late']                     => badge('Vencida sin entregar', 'danger'),
    default                        => badge('Pendiente'),
};
$group = $p['groups'][0] ?? null;
?>
<div class="page-header">
  <div>
    <nav class="crumbs text-xs" aria-label="Ubicación"><a class="link" href="<?= site_url('estudiantes' . ($group ? '?g=' . $group['id'] : '')) ?>">Estudiantes</a><?= icon('chevron-right', 12) ?><span><?= esc($group['name'] ?? '') ?></span></nav>
    <div class="row mt-1"><span class="avatar" aria-hidden="true"><?= esc(initials($s['name'])) ?></span><h1><?= esc($s['name']) ?></h1></div>
    <p><?= esc(implode(', ', array_column($p['groups'], 'name'))) ?> · <?= esc(person_id($s)) ?> · Último acceso: <?= $s['last_access_at'] ? esc(date_short($s['last_access_at'])) : 'nunca' ?></p>
  </div>
</div>

<div class="grid-kpi">
  <div class="kpi"><div class="k-label">Promedio</div><div class="k-value"><?= $num($p['avg']) ?></div><div class="k-hint"><?= $p['all'] ? 'Todas las asignaturas' : 'En sus asignaturas' ?></div></div>
  <div class="kpi"><div class="k-label">Vencidas sin entregar</div><div class="k-value <?= $p['missing'] ? 'text-danger' : '' ?>"><?= $p['missing'] ?></div><div class="k-hint">Retos y evaluaciones cerrados</div></div>
  <div class="kpi"><div class="k-label">Consultas al tutor</div><div class="k-value"><?= $p['tutor'] ?></div><div class="k-hint">Preguntas hechas al tutor de IA en sus retos</div></div>
  <div class="kpi"><div class="k-label">Observaciones</div><div class="k-value"><?= count($p['notes']) ?></div><div class="k-hint">De sus docentes</div></div>
</div>

<div class="split mt-4">
  <div class="stack">
    <?php foreach ($p['subjects'] as $sub): $o = $sub['offering']; ?>
      <section class="card">
        <div class="card-header">
          <div class="row"><span class="brand-dot" style="background:<?= esc($o['color'] ?? '#64748b', 'attr') ?>" aria-hidden="true"></span><h2><?= esc($o['subject']) ?></h2><span class="text-xs soft"><?= esc($o['group']) ?><?= $sub['teacher'] ? ' · ' . esc($sub['teacher']) : '' ?></span></div>
          <span class="row text-xs soft">Promedio <?= $sub['avg'] === null ? '—' : badge($num($sub['avg']), $tone($sub['avg'])) ?></span>
        </div>
        <?php if (! $sub['items']): ?>
          <div class="card-body"><p class="hint">Aún no hay actividades asignadas en esta asignatura.</p></div>
        <?php else: ?>
          <div class="table-wrap" style="border:0;border-radius:0">
            <table class="table">
              <thead><tr><th>Actividad</th><th>Estado</th><th class="num">Nota</th><th>Entrega</th><th class="num">Tutor</th></tr></thead>
              <tbody>
                <?php foreach ($sub['items'] as $i): ?>
                  <tr>
                    <td><span class="row"><?= icon($i['kind'] === 'quiz' ? 'clipboard-list' : 'target', 14, 'soft') ?><span class="font-medium"><?= esc($i['title']) ?></span></span>
                      <?php if ($i['feedback']): ?><span class="meta" title="Retroalimentación">«<?= esc(mb_strimwidth($i['feedback'], 0, 110, '…')) ?>»</span><?php endif ?></td>
                    <td><?= $state($i) ?><?= $i['detail'] ? '<span class="meta">' . esc($i['detail']) . '</span>' : '' ?></td>
                    <td class="num"><?= $i['grade'] === null ? '<span class="soft">—</span>' : $num($i['grade']) ?></td>
                    <td class="nowrap"><span class="text-xs">Cierra <?= esc(date_short($i['due_at'])) ?></span></td>
                    <td class="num"><?= $i['kind'] === 'quiz' ? '<span class="soft" title="El tutor está apagado en evaluaciones">—</span>' : ($i['tutor'] ? $i['tutor'] : '<span class="soft">0</span>') ?></td>
                  </tr>
                <?php endforeach ?>
              </tbody>
            </table>
          </div>
        <?php endif ?>
      </section>
    <?php endforeach ?>
  </div>

  <aside class="split-side stack" id="observaciones">
    <section class="card">
      <div class="card-header"><h2>Observaciones</h2></div>
      <div class="card-body stack">
        <form method="post" action="<?= site_url('estudiantes/' . $s['id'] . '/notas') ?>" class="stack-sm">
          <?= csrf_field() ?>
          <label class="field"><span class="sr-only">Nueva observación</span><textarea class="textarea" name="body" rows="3" maxlength="2000" required placeholder="Ej.: Participa mejor cuando trabaja en pareja."><?= esc(old('body') ?? '') ?></textarea></label>
          <div class="row-between">
            <select class="select" name="offering_id" aria-label="Asignatura de la observación" style="width:auto">
              <option value="">General</option>
              <?php foreach ($p['subjects'] as $sub): ?><option value="<?= (int) $sub['offering']['id'] ?>"><?= esc($sub['offering']['subject']) ?></option><?php endforeach ?>
            </select>
            <button type="submit" class="btn btn-sm" data-loading="Guardando…"><?= icon('plus', 14) ?> Agregar</button>
          </div>
          <p class="hint">La ven los docentes del estudiante y la coordinación. El estudiante no la ve.</p>
        </form>
        <?php foreach ($p['notes'] as $n): $mine = (int) $n['author_id'] === (int) service('auth')->id(); ?>
          <div class="note-item">
            <p style="white-space:pre-wrap"><?= esc($n['body']) ?></p>
            <div class="row-between text-xs soft mt-1">
              <span><?= esc(($n['author'] ?? '—') . ($n['subject'] ? ' · ' . $n['subject'] : '') . ' · ' . date_short($n['created_at'])) ?></span>
              <?php if ($mine || $p['all']): ?>
                <form method="post" action="<?= site_url('estudiantes/' . $s['id'] . '/notas/' . $n['id'] . '/eliminar') ?>" onsubmit="return confirm('¿Eliminar esta observación?')">
                  <?= csrf_field() ?><button type="submit" class="btn btn-sm btn-danger btn-icon" title="Eliminar la observación" aria-label="Eliminar la observación"><?= icon('trash-2', 14) ?></button></form>
              <?php endif ?>
            </div>
          </div>
        <?php endforeach ?>
      </div>
    </section>

    <?php if ($guardians !== null): /* Solo la administración (tenant.users.manage) gestiona los acudientes. */ ?>
      <section class="card" id="acudientes">
        <div class="card-header"><h2>Acudientes</h2></div>
        <div class="card-body stack">
          <?php if (! $guardians): ?><p class="hint">Sin acudientes vinculados. El acudiente ve el progreso, las actividades y la retroalimentación del estudiante; no ve las observaciones.</p><?php endif ?>
          <?php foreach ($guardians as $g): ?>
            <div class="row-between">
              <span><span class="block font-medium"><?= esc($g['name']) ?></span><span class="block text-xs soft"><?= esc($g['relationship'] . ' · ' . person_id($g)) ?></span></span>
              <form method="post" action="<?= site_url('estudiantes/' . $s['id'] . '/acudientes/' . $g['id'] . '/quitar') ?>" onsubmit="return confirm('¿Desvincular a <?= esc($g['name'], 'js') ?>? Dejará de ver a este estudiante.')">
                <?= csrf_field() ?><button type="submit" class="btn btn-sm btn-danger btn-icon" title="Desvincular a <?= esc($g['name'], 'attr') ?>" aria-label="Desvincular a <?= esc($g['name'], 'attr') ?>"><?= icon('unlink', 14) ?></button></form>
            </div>
          <?php endforeach ?>
          <form method="post" action="<?= site_url('estudiantes/' . $s['id'] . '/acudientes') ?>" class="stack-sm">
            <?= csrf_field() ?>
            <label class="field"><span class="label">Correo del acudiente</span><input class="input" type="email" name="email" required maxlength="160" placeholder="acudiente@correo.com"></label>
            <div class="row-between">
              <select class="select" name="relationship" aria-label="Parentesco" style="width:auto">
                <?php foreach (\App\Libraries\Guardians::RELATIONSHIPS as $rel): ?><option <?= $rel === 'Acudiente' ? 'selected' : '' ?>><?= esc($rel) ?></option><?php endforeach ?>
              </select>
              <button type="submit" class="btn btn-sm" data-loading="Vinculando…"><?= icon('link', 14) ?> Vincular</button>
            </div>
            <p class="hint">Debe tener cuenta en la institución con el rol Acudiente (se invita en Ajustes › Usuarios).</p>
          </form>
        </div>
      </section>
    <?php endif ?>
  </aside>
</div>
<?= $this->endSection() ?>
