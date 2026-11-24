<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
/** Actividades del docente (datos reales): retos de sus ofertas con estado, fecha y entregas. */
$stateBadge = static fn (?string $s) => match ($s) { 'scheduled' => badge('Programado'), 'open' => badge('Abierto', 'accent'), 'closed' => badge('Cerrado'), default => '' };
?>
<div x-data="{ open: false, kind: 'challenge' }">
  <div class="page-header">
    <div><h1>Actividades</h1><p>Retos y evaluaciones de sus asignaturas.</p></div>
    <div class="actions">
      <button type="button" class="btn" @click="kind = 'quiz'; open = true" <?= $offerings ? '' : 'disabled' ?>><?= icon('clipboard-list', 16) ?> Nueva evaluación</button>
      <button type="button" class="btn btn-primary" @click="kind = 'challenge'; open = true" <?= $offerings ? '' : 'disabled title="No tiene asignaturas en el periodo en curso"' ?>><?= icon('plus', 16) ?> Nuevo reto</button>
    </div>
  </div>

  <section class="panel">
    <?php $filtered = (bool) array_filter($filters); if ($offerings): ?>
      <form method="get" class="panel-bar" role="search" aria-label="Filtrar actividades">
        <?php if (count($subjects) > 1): ?>
          <select class="select" name="asignatura" aria-label="Asignatura" onchange="this.form.submit()" style="width:auto">
            <option value="">Todas las asignaturas</option>
            <?php foreach ($subjects as $id => $name): ?><option value="<?= (int) $id ?>" <?= $filters['asignatura'] === (int) $id ? 'selected' : '' ?>><?= esc($name) ?></option><?php endforeach ?>
          </select>
        <?php endif ?>
        <?php if (count($groups) > 1): ?>
          <select class="select" name="grupo" aria-label="Grado o grupo" onchange="this.form.submit()" style="width:auto">
            <option value="">Todos los grupos</option>
            <?php foreach ($groups as $id => $name): ?><option value="<?= (int) $id ?>" <?= $filters['grupo'] === (int) $id ? 'selected' : '' ?>><?= esc($name) ?></option><?php endforeach ?>
          </select>
        <?php endif ?>
        <select class="select" name="estado" aria-label="Estado" onchange="this.form.submit()" style="width:auto">
          <option value="">Todos los estados</option>
          <?php foreach ($states as $k => $label): ?><option value="<?= esc($k, 'attr') ?>" <?= $filters['estado'] === $k ? 'selected' : '' ?>><?= esc($label) ?></option><?php endforeach ?>
        </select>
        <noscript><button type="submit" class="btn btn-sm">Filtrar</button></noscript>
        <?php if ($filtered): ?>
          <a class="btn btn-ghost btn-sm btn-icon" href="<?= site_url('actividades') ?>" aria-label="Limpiar filtros" title="Limpiar filtros"><?= icon('x', 16) ?></a>
        <?php endif ?>
        <span class="panel-count ml-auto"><?= plural(count($rows), 'actividad', 'actividades') ?></span>
      </form>
    <?php endif ?>
    <?php if (! $rows && $filtered): ?>
      <div class="empty"><?= icon('filter', 32) ?><p>Ninguna actividad coincide con ese filtro. Pruebe otra asignatura o grupo, o limpie los filtros.</p></div>
    <?php elseif (! $rows): ?>
      <div class="empty"><?= icon('target', 32) ?><p>Aún no hay actividades. Cree un reto (una situación real que se resuelve en pasos, con rúbrica) o una evaluación con preguntas.</p></div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Actividad</th><th>Asignatura</th><th>Estado</th><th>Entrega</th><th class="num">Entregas</th><th class="text-right">Acciones</th></tr></thead>
          <tbody>
            <?php foreach ($rows as $r): ?>
              <tr style="--cat:<?= cat_color($r['color']) ?>">
                <td><span class="row"><span class="act-icon" style="width:1.75rem;height:1.75rem"><?= icon($r['kind'] === 'quiz' ? 'clipboard-list' : 'target', 14) ?></span><span><span class="font-medium"><?= esc($r['title']) ?></span><span class="meta"><?= $r['kind'] === 'quiz' ? 'Evaluación' : 'Reto' ?></span></span></span></td>
                <td><span class="row"><span class="cat-dot" aria-hidden="true"></span><?= esc($r['label']) ?></span></td>
                <td><span class="row"><?= $r['status'] === 'published' ? badge('Publicado', 'ok') : badge('Borrador') ?> <?= $stateBadge($r['state']) ?></span></td>
                <td class="nowrap"><?= $r['due_at'] ? esc(date_human($r['due_at'])) : '<span class="soft">Sin asignar</span>' ?></td>
                <td class="num"><?= $r['assignment_id'] ? (int) $r['submitted'] . ' de ' . (int) $r['students'] : '—' ?><?= (int) $r['to_review'] > 0 ? ' · ' . badge((int) $r['to_review'] . ' por revisar', 'warn') : '' ?></td>
                <td class="text-right nowrap">
                  <?php if ($r['assignment_id']): ?>
                    <a class="btn btn-sm" href="<?= site_url('entregas/' . $r['assignment_id']) ?>"><?= icon('inbox', 14) ?> Entregas</a>
                  <?php endif ?>
                  <a class="btn btn-sm btn-icon" href="<?= site_url('actividades/' . $r['id']) ?>" title="Editar «<?= esc($r['title'], 'attr') ?>»" aria-label="Editar «<?= esc($r['title'], 'attr') ?>»"><?= icon('pencil-line', 14) ?></a>
                </td>
              </tr>
            <?php endforeach ?>
          </tbody>
        </table>
      </div>
    <?php endif ?>
  </section>

  <div class="modal-layer" x-show="open" x-cloak @keydown.escape.window="open = false">
    <div class="veil" @click="open = false"></div>
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="nr-title">
      <form method="post" action="<?= site_url('actividades') ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="kind" :value="kind">
        <div class="modal-head"><h2 id="nr-title" x-text="kind === 'quiz' ? 'Nueva evaluación' : 'Nuevo reto'"></h2><button type="button" class="btn btn-ghost btn-sm btn-icon" @click="open = false" aria-label="Cerrar">×</button></div>
        <div class="modal-body stack">
          <label class="field"><span class="label">Asignatura <span class="text-danger">*</span></span>
            <select class="select" name="offering_id" required>
              <?php $pre = false; foreach ($offerings as $o): /* con filtro activo, se propone la primera oferta que coincide */
                  $match = $filtered && ! $pre && (! $filters['asignatura'] || $o['subject_id'] === $filters['asignatura']) && (! $filters['grupo'] || $o['group_id'] === $filters['grupo']);
                  $pre   = $pre || $match; ?>
                <option value="<?= (int) $o['id'] ?>" <?= $match ? 'selected' : '' ?>><?= esc($o['subject'] . ' · ' . $o['group']) ?></option>
              <?php endforeach ?>
            </select></label>
          <label class="field"><span class="label">Título <span class="text-danger">*</span></span><input class="input" name="title" maxlength="160" required :placeholder="kind === 'quiz' ? 'Ej.: Evaluación: fracciones' : 'Ej.: Reto: la receta para 30'"></label>
          <p class="hint" x-text="kind === 'quiz' ? 'Se crea como borrador; luego agrega las preguntas.' : 'Se crea como borrador con una rúbrica base que puede ajustar.'"></p>
        </div>
        <div class="modal-foot"><button type="button" class="btn" @click="open = false">Cancelar</button><button type="submit" class="btn btn-primary" data-loading="Creando…" x-text="kind === 'quiz' ? 'Crear evaluación' : 'Crear reto'"></button></div>
      </form>
    </div>
  </div>
</div>
<?= $this->endSection() ?>
