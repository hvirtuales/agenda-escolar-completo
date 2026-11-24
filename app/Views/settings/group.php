<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
/**
 * Ajustes › Estructura académica › Grupo: estudiantes matriculados, matrícula (solo estudiantes sin grupo en el
 * periodo) y oferta del grupo. Retirar a alguien del grupo no borra su cuenta ni su historial.
 */
?>
<div x-data="{ add: false, confirm: null }">
  <div class="page-header">
    <div>
      <nav class="crumbs text-xs" aria-label="Ubicación">
        <a class="link" href="<?= site_url('ajustes/estructura?t=cursos') ?>">Cursos y grupos</a><?= icon('chevron-right', 12) ?><span><?= esc($group['level']) ?></span>
      </nav>
      <h1 class="mt-1">Grupo <?= esc($group['name']) ?></h1>
      <p><?= esc($group['period']) ?><?= $group['campus'] ? ' · ' . esc($group['campus']) : '' ?> · <?= plural(count($students), 'estudiante', 'estudiantes') ?></p>
    </div>
    <div class="actions">
      <button type="button" class="btn btn-primary" @click="add = true" <?= $available ? '' : 'disabled title="No hay estudiantes sin grupo en este periodo"' ?>><?= icon('user-plus', 16) ?> Matricular estudiantes</button>
    </div>
  </div>

  <div class="grid-3" style="align-items:start">
    <section class="panel span-2">
      <?php if (! $students): ?>
        <div class="empty"><?= icon('users', 32) ?><p>Aún no hay estudiantes en este grupo.<?= $available ? ' Use «Matricular estudiantes».' : ' Primero créelos en Ajustes › Personas con el rol Estudiante.' ?></p></div>
      <?php else: ?>
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>Estudiante</th><th class="text-right">Acciones</th></tr></thead>
            <tbody>
              <?php foreach ($students as $s): ?>
                <tr>
                  <td><span class="row"><span class="avatar" aria-hidden="true"><?= esc(initials($s['name'])) ?></span><span><span class="font-medium"><?= esc($s['name']) ?></span><span class="meta"><?= esc(person_id($s)) ?></span></span></span></td>
                  <td class="text-right">
                    <button type="button" class="btn btn-sm btn-danger" @click="confirm = { id: <?= (int) $s['id'] ?>, name: '<?= esc($s['name'], 'js') ?>' }"><?= icon('user-minus', 14) ?> Retirar</button>
                  </td>
                </tr>
              <?php endforeach ?>
            </tbody>
          </table>
        </div>
      <?php endif ?>
    </section>

    <section class="card">
      <div class="card-header"><h2>Oferta del grupo</h2><a class="btn btn-sm" href="<?= site_url('ajustes/estructura?t=oferta') ?>"><?= icon('layers', 14) ?> Gestionar</a></div>
      <?php if (! $offerings): ?>
        <div class="card-body"><p class="soft">Este grupo aún no tiene asignaturas ofertadas.</p></div>
      <?php else: ?>
        <div class="date-list">
          <?php foreach ($offerings as $o): ?>
            <div class="date-row" style="--cat:<?= cat_color($o['color']) ?>">
              <span class="cat-dot" aria-hidden="true"></span>
              <span style="flex:1;min-width:0"><span class="block font-medium"><?= esc($o['subject']) ?></span><span class="block text-xs soft"><?= $o['teachers'] ? esc(implode(', ', array_column($o['teachers'], 'name'))) : 'Sin docente' ?></span></span>
            </div>
          <?php endforeach ?>
        </div>
      <?php endif ?>
    </section>
  </div>

  <!-- Matricular -->
  <div class="modal-layer" x-show="add" x-cloak @keydown.escape.window="add = false">
    <div class="veil" @click="add = false"></div>
    <div class="modal lg" role="dialog" aria-modal="true" aria-labelledby="add-title">
      <form method="post" action="<?= site_url('ajustes/estructura/grupos/' . $group['id'] . '/matricular') ?>" x-data="{ q: '' }">
        <?= csrf_field() ?>
        <div class="modal-head"><h2 id="add-title">Matricular en <?= esc($group['name']) ?></h2><button type="button" class="btn btn-ghost btn-sm btn-icon" @click="add = false" aria-label="Cerrar">×</button></div>
        <div class="modal-body stack-sm">
          <p class="hint">Solo aparecen estudiantes activos que aún no tienen grupo en <?= esc($group['period']) ?>.</p>
          <label class="input-icon"><span class="sr-only">Filtrar</span><?= icon('search', 16) ?><input class="input" type="search" x-model="q" placeholder="Filtrar por nombre"></label>
          <div style="max-height:18rem;overflow-y:auto">
            <?php foreach ($available as $a): ?>
              <label class="perm-row" style="padding:0.5rem 0.25rem" x-show="!q || '<?= esc(mb_strtolower($a['name'] . ' ' . person_id($a)), 'js') ?>'.includes(q.toLowerCase())">
                <input type="checkbox" name="user_ids[]" value="<?= (int) $a['id'] ?>">
                <span><span class="block"><?= esc($a['name']) ?></span><span class="block text-xs soft"><?= esc(person_id($a)) ?></span></span>
              </label>
            <?php endforeach ?>
          </div>
        </div>
        <div class="modal-foot"><button type="button" class="btn" @click="add = false">Cancelar</button><button type="submit" class="btn btn-primary" data-loading="Matriculando…">Matricular</button></div>
      </form>
    </div>
  </div>

  <!-- Confirmar retiro -->
  <div class="modal-layer" x-show="confirm" x-cloak @keydown.escape.window.stop="confirm = null">
    <div class="veil" @click="confirm = null"></div>
    <div class="modal" role="alertdialog" aria-modal="true" aria-labelledby="rm-title" aria-describedby="rm-msg">
      <form method="post" :action="confirm && ('<?= site_url('ajustes/estructura/grupos/' . $group['id'] . '/retirar') ?>/' + confirm.id)" class="confirm">
        <?= csrf_field() ?>
        <h2 id="rm-title" x-text="confirm && ('¿Retirar a ' + confirm.name + ' del grupo?')"></h2>
        <p id="rm-msg">Deja de ver los cuadernos y actividades del grupo. Su cuenta y su historial se conservan.</p>
        <div class="actions"><button type="button" class="btn" @click="confirm = null">Cancelar</button><button type="submit" class="btn btn-danger" data-loading="Retirando…">Retirar</button></div>
      </form>
    </div>
  </div>
</div>
<?= $this->endSection() ?>
