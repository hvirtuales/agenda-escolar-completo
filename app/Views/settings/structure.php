<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
/**
 * Ajustes › Estructura académica (datos reales). Un Drawer por tipo de registro; los errores del servidor reabren el
 * Drawer con lo escrito (session reopen). Eliminar pide confirmación y el servicio impide borrar con dependencias.
 *
 * @var string $tab
 */
$tabs = ['oferta' => 'Oferta académica', 'cursos' => 'Cursos y grupos', 'asignaturas' => 'Categorías y asignaturas', 'periodos' => 'Periodos', 'sedes' => 'Sedes'];
$reopen = session('reopen');
$base   = site_url('ajustes/estructura');
$swatches = ['#2563EB', '#16A34A', '#DB2777', '#9333EA', '#EA580C', '#0891B2', '#CA8A04', '#475569'];
$blank = [
    'periodo' => ['name' => '', 'starts_on' => '', 'ends_on' => '', 'is_current' => false],
    'categoria' => ['name' => '', 'color' => '#2563EB', 'sort_order' => 0],
    'asignatura' => ['name' => '', 'category_id' => (string) ($categories[0]['id'] ?? ''), 'description' => ''],
    'curso' => ['name' => '', 'sort_order' => 0],
    'grupo' => ['name' => '', 'level_id' => '', 'period_id' => (string) ($period['id'] ?? ''), 'campus_id' => ''],
    'oferta' => ['subject_id' => '', 'group_id' => '', 'hours_per_week' => 0],
    'sede' => ['name' => '', 'address' => ''],
];
$titles = ['periodo' => 'periodo', 'categoria' => 'categoría', 'asignatura' => 'asignatura', 'curso' => 'curso', 'grupo' => 'grupo', 'oferta' => 'oferta', 'sede' => 'sede'];
$editJs = static fn (string $type, array $row): string => esc("edit('" . $type . "', " . json_encode($row) . ')', 'attr');
?>
<div x-data="structure({ blank: <?= esc(json_encode($blank), 'attr') ?>, reopen: <?= esc(json_encode($reopen), 'attr') ?>, base: '<?= esc($base, 'js') ?>' })"
     @keydown.escape.window="close()">

  <div class="page-header">
    <div><h1>Ajustes</h1><p><?= esc($institution['name']) ?><?= $period ? ' · ' . esc($period['name']) : '' ?></p></div>
  </div>
  <?= view('settings/_tabs', ['section' => 'estructura']) ?>

  <nav class="chips mb-4" aria-label="Partes de la estructura">
    <?php foreach ($tabs as $k => $l): ?>
      <a class="chip" href="<?= $base ?>?t=<?= $k ?>" <?= $tab === $k ? 'aria-selected="true" aria-current="page"' : 'aria-selected="false"' ?>><?= esc($l) ?></a>
    <?php endforeach ?>
  </nav>

  <?php if (! $period && $tab !== 'periodos'): ?>
    <div class="notice notice-accent mb-4"><?= icon('info', 16) ?><span>Primero cree un periodo académico (por ejemplo, «Año lectivo 2026»): los grupos y la oferta pertenecen a un periodo. <a class="link" href="<?= $base ?>?t=periodos">Ir a Periodos</a></span></div>
  <?php endif ?>

  <?php if ($tab === 'oferta'): ?>
    <section class="panel">
      <form method="get" class="panel-bar">
        <input type="hidden" name="t" value="oferta">
        <select class="select" name="curso" aria-label="Curso" onchange="this.form.submit()">
          <option value="">Todos los cursos</option>
          <?php foreach ($levels as $l): ?><option value="<?= $l['id'] ?>" <?= (int) $filters['curso'] === (int) $l['id'] ? 'selected' : '' ?>><?= esc($l['name']) ?></option><?php endforeach ?>
        </select>
        <select class="select" name="asignatura" aria-label="Asignatura" onchange="this.form.submit()">
          <option value="">Todas las asignaturas</option>
          <?php foreach ($subjects as $s): ?><option value="<?= $s['id'] ?>" <?= (int) $filters['asignatura'] === (int) $s['id'] ? 'selected' : '' ?>><?= esc($s['name']) ?></option><?php endforeach ?>
        </select>
        <span class="panel-count"><?= plural(count($offerings), 'oferta', 'ofertas') ?></span>
        <button type="button" class="btn btn-primary panel-action" @click="create('oferta')" <?= $groups && $subjects ? '' : 'disabled title="Primero cree asignaturas y grupos"' ?>><?= icon('plus', 16) ?> Nueva oferta</button>
      </form>
      <?php if (! $offerings): ?>
        <div class="empty"><?= icon('layers', 32) ?><p>Aún no hay oferta en este periodo. Cada oferta une una asignatura con un grupo; a ella se asignan docentes y actividades.</p></div>
      <?php else: ?>
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>Asignatura</th><th>Grupo</th><th>Docentes</th><th class="num">Horas/semana</th><th class="text-right">Acciones</th></tr></thead>
            <tbody>
              <?php foreach ($offerings as $o): ?>
                <tr style="--cat:<?= cat_color($o['color']) ?>">
                  <td><span class="row"><span class="cat-dot" aria-hidden="true"></span><span class="font-medium"><?= esc($o['subject']) ?></span></span></td>
                  <td><?= esc($o['group_name']) ?></td>
                  <td><?= $o['teachers'] ? esc(implode(', ', array_column($o['teachers'], 'name'))) : badge('Sin docente', 'warn') ?></td>
                  <td class="num"><?= (int) $o['hours_per_week'] ?></td>
                  <td class="text-right nowrap">
                    <button type="button" class="btn btn-sm" @click="teachersFor(<?= (int) $o['id'] ?>, <?= esc(json_encode(array_column($o['teachers'], 'id')), 'attr') ?>, '<?= esc($o['subject'] . ' · ' . $o['group_name'], 'js') ?>')"><?= icon('user-plus', 14) ?> Docentes</button>
                    <button type="button" class="btn btn-sm btn-icon" title="Editar la oferta de <?= esc($o['subject'] . ' ' . $o['group_name'], 'attr') ?>" aria-label="Editar la oferta de <?= esc($o['subject'] . ' ' . $o['group_name'], 'attr') ?>"
                            @click="<?= $editJs('oferta', ['id' => (int) $o['id'], 'subject_id' => (string) $o['subject_id'], 'group_id' => (string) $o['group_id'], 'hours_per_week' => (int) $o['hours_per_week']]) ?>"><?= icon('pencil-line', 14) ?></button>
                    <button type="button" class="btn btn-sm btn-icon btn-danger" title="Eliminar la oferta de <?= esc($o['subject'] . ' ' . $o['group_name'], 'attr') ?>" aria-label="Eliminar la oferta de <?= esc($o['subject'] . ' ' . $o['group_name'], 'attr') ?>"
                            @click="ask('oferta', <?= (int) $o['id'] ?>, '<?= esc($o['subject'] . ' · ' . $o['group_name'], 'js') ?>')"><?= icon('trash-2', 14) ?></button>
                  </td>
                </tr>
              <?php endforeach ?>
            </tbody>
          </table>
        </div>
      <?php endif ?>
    </section>

  <?php elseif ($tab === 'cursos'): ?>
    <div class="row-between mb-4">
      <p class="soft">Cada grupo pertenece a un curso y al periodo <?= esc($period['name'] ?? '') ?>; los estudiantes se matriculan en grupos.</p>
      <div class="row"><button type="button" class="btn" @click="create('curso')"><?= icon('plus', 16) ?> Nuevo curso</button><button type="button" class="btn btn-primary" @click="create('grupo')" <?= $levels && $period ? '' : 'disabled' ?>><?= icon('plus', 16) ?> Nuevo grupo</button></div>
    </div>
    <?php if (! $levels): ?>
      <div class="empty"><?= icon('graduation-cap', 32) ?><p>Aún no hay cursos. Cree el primero, por ejemplo «6.º» o «Nivel básico».</p></div>
    <?php else: ?>
      <div class="group-cards">
        <?php foreach ($levels as $l): ?>
          <div class="card card-body">
            <div class="row-between">
              <span class="font-semibold"><?= esc($l['name']) ?></span>
              <span class="row">
                <span class="text-xs soft"><?= plural(array_sum(array_column($l['groups'], 'students')), 'estudiante', 'estudiantes') ?></span>
                <button type="button" class="btn btn-sm btn-icon" title="Editar el curso <?= esc($l['name'], 'attr') ?>" aria-label="Editar el curso <?= esc($l['name'], 'attr') ?>" @click="<?= $editJs('curso', ['id' => (int) $l['id'], 'name' => $l['name'], 'sort_order' => (int) $l['sort_order']]) ?>"><?= icon('pencil-line', 14) ?></button>
                <button type="button" class="btn btn-sm btn-icon btn-danger" title="Eliminar el curso <?= esc($l['name'], 'attr') ?>" aria-label="Eliminar el curso <?= esc($l['name'], 'attr') ?>" @click="ask('curso', <?= (int) $l['id'] ?>, '<?= esc($l['name'], 'js') ?>')"><?= icon('trash-2', 14) ?></button>
              </span>
            </div>
            <div class="row wrap mt-2">
              <?php foreach ($l['groups'] as $g): ?>
                <?php $gData = ['id' => (int) $g['id'], 'name' => $g['name'], 'level_id' => (string) $g['level_id'], 'period_id' => (string) $g['period_id'], 'campus_id' => (string) ($g['campus_id'] ?? '')]; ?>
                <span class="chip-group">
                  <a class="chip" href="<?= site_url('ajustes/estructura/grupos/' . $g['id']) ?>" title="Ver y matricular estudiantes de <?= esc($g['name'], 'attr') ?>">
                    <?= esc($g['name']) ?> <span class="soft">· <?= plural((int) $g['students'], 'estudiante', 'estudiantes') ?></span>
                  </a>
                  <button type="button" class="btn btn-sm btn-icon" title="Editar el grupo <?= esc($g['name'], 'attr') ?>" aria-label="Editar el grupo <?= esc($g['name'], 'attr') ?>" @click="<?= $editJs('grupo', $gData) ?>"><?= icon('pencil-line', 14) ?></button>
                  <button type="button" class="btn btn-sm btn-icon btn-danger" title="Eliminar el grupo <?= esc($g['name'], 'attr') ?>" aria-label="Eliminar el grupo <?= esc($g['name'], 'attr') ?>" @click="ask('grupo', <?= (int) $g['id'] ?>, '<?= esc($g['name'], 'js') ?>')"><?= icon('trash-2', 14) ?></button>
                </span>
              <?php endforeach ?>
              <button type="button" class="btn btn-sm" @click="create('grupo', { level_id: '<?= (int) $l['id'] ?>' })" <?= $period ? '' : 'disabled' ?>><?= icon('plus', 14) ?> Grupo</button>
            </div>
          </div>
        <?php endforeach ?>
      </div>
    <?php endif ?>

  <?php elseif ($tab === 'asignaturas'): ?>
    <div class="grid-3" style="align-items:start">
      <section class="card">
        <div class="card-header"><h2>Categorías</h2><button type="button" class="btn btn-sm" @click="create('categoria')"><?= icon('plus', 14) ?> Nueva</button></div>
        <?php if (! $categories): ?>
          <div class="card-body"><p class="soft">Aún no hay categorías. Agrupan asignaturas, por ejemplo «Ciencias exactas».</p></div>
        <?php else: ?>
          <div class="date-list">
            <?php foreach ($categories as $c): ?>
              <div class="date-row" style="--cat:<?= cat_color($c['color']) ?>">
                <span class="cat-dot" aria-hidden="true"></span>
                <span style="flex:1;min-width:0" class="font-medium truncate"><?= esc($c['name']) ?></span>
                <button type="button" class="btn btn-sm btn-icon" title="Editar la categoría <?= esc($c['name'], 'attr') ?>" aria-label="Editar la categoría <?= esc($c['name'], 'attr') ?>" @click="<?= $editJs('categoria', ['id' => (int) $c['id'], 'name' => $c['name'], 'color' => $c['color'], 'sort_order' => (int) $c['sort_order']]) ?>"><?= icon('pencil-line', 14) ?></button>
                <button type="button" class="btn btn-sm btn-icon btn-danger" title="Eliminar la categoría <?= esc($c['name'], 'attr') ?>" aria-label="Eliminar la categoría <?= esc($c['name'], 'attr') ?>" @click="ask('categoria', <?= (int) $c['id'] ?>, '<?= esc($c['name'], 'js') ?>')"><?= icon('trash-2', 14) ?></button>
              </div>
            <?php endforeach ?>
          </div>
        <?php endif ?>
      </section>
      <section class="panel span-2">
        <div class="panel-bar"><span class="panel-count"><?= plural(count($subjects), 'asignatura', 'asignaturas') ?></span><button type="button" class="btn btn-primary panel-action" @click="create('asignatura')" <?= $categories ? '' : 'disabled title="Primero cree una categoría"' ?>><?= icon('plus', 16) ?> Nueva asignatura</button></div>
        <?php if (! $subjects): ?>
          <div class="empty"><?= icon('book-open', 32) ?><p>Aún no hay asignaturas.</p></div>
        <?php else: ?>
          <div class="table-wrap">
            <table class="table">
              <thead><tr><th>Asignatura</th><th>Categoría</th><th class="num">Ofertas</th><th class="text-right">Acciones</th></tr></thead>
              <tbody>
                <?php foreach ($subjects as $s): ?>
                  <tr style="--cat:<?= cat_color($s['color']) ?>">
                    <td><span class="row"><span class="cat-dot" aria-hidden="true"></span><span class="font-medium"><?= esc($s['name']) ?></span></span><?php if ($s['description']): ?><span class="meta"><?= esc($s['description']) ?></span><?php endif ?></td>
                    <td><?= esc($s['category']) ?></td>
                    <td class="num"><?= (int) $s['offerings'] ?></td>
                    <td class="text-right nowrap">
                      <button type="button" class="btn btn-sm btn-icon" title="Editar <?= esc($s['name'], 'attr') ?>" aria-label="Editar <?= esc($s['name'], 'attr') ?>" @click="<?= $editJs('asignatura', ['id' => (int) $s['id'], 'name' => $s['name'], 'category_id' => (string) $s['category_id'], 'description' => (string) $s['description']]) ?>"><?= icon('pencil-line', 14) ?></button>
                      <button type="button" class="btn btn-sm btn-icon btn-danger" title="Eliminar <?= esc($s['name'], 'attr') ?>" aria-label="Eliminar <?= esc($s['name'], 'attr') ?>" @click="ask('asignatura', <?= (int) $s['id'] ?>, '<?= esc($s['name'], 'js') ?>')"><?= icon('trash-2', 14) ?></button>
                    </td>
                  </tr>
                <?php endforeach ?>
              </tbody>
            </table>
          </div>
        <?php endif ?>
      </section>
    </div>

  <?php elseif ($tab === 'periodos'): ?>
    <section class="panel">
      <div class="panel-bar"><span class="panel-count">Periodos académicos</span><button type="button" class="btn btn-primary panel-action" @click="create('periodo')"><?= icon('plus', 16) ?> Nuevo periodo</button></div>
      <?php if (! $periods): ?>
        <div class="empty"><?= icon('calendar-days', 32) ?><p>Aún no hay periodos. Cree el año lectivo o semestre en curso.</p></div>
      <?php else: ?>
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>Periodo</th><th>Desde</th><th>Hasta</th><th>Estado</th><th class="text-right">Acciones</th></tr></thead>
            <tbody>
              <?php foreach ($periods as $p): ?>
                <tr>
                  <td class="font-medium"><?= esc($p['name']) ?></td>
                  <td><?= esc(date_short($p['starts_on'], false)) ?></td>
                  <td><?= esc(date_short($p['ends_on'], false)) ?></td>
                  <td><?= $p['is_current'] ? badge('En curso', 'ok') : badge($p['starts_on'] > date('Y-m-d') ? 'Programado' : 'Cerrado') ?></td>
                  <td class="text-right nowrap">
                    <button type="button" class="btn btn-sm btn-icon" title="Editar <?= esc($p['name'], 'attr') ?>" aria-label="Editar <?= esc($p['name'], 'attr') ?>" @click="<?= $editJs('periodo', ['id' => (int) $p['id'], 'name' => $p['name'], 'starts_on' => $p['starts_on'], 'ends_on' => $p['ends_on'], 'is_current' => (bool) $p['is_current']]) ?>"><?= icon('pencil-line', 14) ?></button>
                    <button type="button" class="btn btn-sm btn-icon btn-danger" title="Eliminar <?= esc($p['name'], 'attr') ?>" aria-label="Eliminar <?= esc($p['name'], 'attr') ?>" @click="ask('periodo', <?= (int) $p['id'] ?>, '<?= esc($p['name'], 'js') ?>')"><?= icon('trash-2', 14) ?></button>
                  </td>
                </tr>
              <?php endforeach ?>
            </tbody>
          </table>
        </div>
      <?php endif ?>
    </section>

  <?php else: ?>
    <section class="panel">
      <div class="panel-bar"><span class="panel-count"><?= plural(count($campuses), 'sede', 'sedes') ?></span><button type="button" class="btn btn-primary panel-action" @click="create('sede')"><?= icon('plus', 16) ?> Nueva sede</button></div>
      <?php if (! $campuses): ?>
        <div class="empty"><?= icon('map-pin', 32) ?><p>Sin sedes: la institución funciona en un solo lugar. Créelas solo si tiene varias.</p></div>
      <?php else: ?>
        <div class="date-list">
          <?php foreach ($campuses as $c): ?>
            <div class="date-row">
              <?= icon('map-pin', 18, 'soft') ?>
              <span style="flex:1"><span class="block font-medium"><?= esc($c['name']) ?></span><span class="block text-xs soft"><?= esc($c['address'] ?? '') ?></span></span>
              <button type="button" class="btn btn-sm btn-icon" title="Editar <?= esc($c['name'], 'attr') ?>" aria-label="Editar <?= esc($c['name'], 'attr') ?>" @click="<?= $editJs('sede', ['id' => (int) $c['id'], 'name' => $c['name'], 'address' => (string) $c['address']]) ?>"><?= icon('pencil-line', 14) ?></button>
              <button type="button" class="btn btn-sm btn-icon btn-danger" title="Eliminar <?= esc($c['name'], 'attr') ?>" aria-label="Eliminar <?= esc($c['name'], 'attr') ?>" @click="ask('sede', <?= (int) $c['id'] ?>, '<?= esc($c['name'], 'js') ?>')"><?= icon('trash-2', 14) ?></button>
            </div>
          <?php endforeach ?>
        </div>
      <?php endif ?>
    </section>
  <?php endif ?>

  <!-- Drawer de creación y edición -->
  <div class="drawer-layer" x-show="open" x-cloak>
    <div class="veil" @click="close()"></div>
    <aside class="drawer" role="dialog" aria-modal="true" aria-labelledby="st-title">
      <form method="post" :action="action()" novalidate>
        <?= csrf_field() ?>
        <div class="drawer-head">
          <h2 id="st-title" x-text="(mode === 'create' ? 'Nuevo ' : 'Editar ') + ({ <?php foreach ($titles as $k => $t) { echo $k . ": '" . $t . "', "; } ?> })[type]"></h2>
          <button type="button" class="btn btn-ghost btn-sm btn-icon" @click="close()" aria-label="Cerrar">×</button>
        </div>
        <div class="drawer-body stack">
          <template x-if="['periodo','categoria','asignatura','curso','grupo','sede'].includes(type)">
            <label class="field">
              <span class="label">Nombre <span class="text-danger">*</span></span>
              <input class="input" name="name" x-model="form.name" maxlength="120" data-autofocus required
                     :placeholder="({ periodo: 'Año lectivo 2027', categoria: 'Ciencias exactas', asignatura: 'Matemáticas', curso: '7.º', grupo: '7.º A', sede: 'Sede norte' })[type]">
              <?= field_error('name') ?>
            </label>
          </template>

          <template x-if="type === 'periodo'">
            <div class="stack">
              <div class="grid-2" style="gap:0.75rem">
                <label class="field"><span class="label">Desde <span class="text-danger">*</span></span><input class="input" type="date" name="starts_on" x-model="form.starts_on" required><?= field_error('starts_on') ?></label>
                <label class="field"><span class="label">Hasta <span class="text-danger">*</span></span><input class="input" type="date" name="ends_on" x-model="form.ends_on" required><?= field_error('ends_on') ?></label>
              </div>
              <label class="toggle"><span><span class="block t-title">Periodo en curso</span><span class="block t-desc">Es el que se usa por defecto en grupos, oferta y cuadernos. Solo puede haber uno.</span></span>
                <input type="checkbox" name="is_current" value="1" x-model="form.is_current" style="width:1.25rem;height:1.25rem;accent-color:var(--app-accent)"></label>
            </div>
          </template>

          <template x-if="type === 'categoria'">
            <fieldset class="fieldset">
              <legend class="label">Color</legend>
              <div class="swatches">
                <?php foreach ($swatches as $s): ?>
                  <button type="button" class="swatch" style="background:<?= $s ?>" :aria-pressed="(form.color === '<?= $s ?>').toString()" @click="form.color = '<?= $s ?>'" aria-label="Usar el color <?= $s ?>"></button>
                <?php endforeach ?>
                <input class="input input-color" type="color" name="color" x-model="form.color" aria-label="Elegir otro color">
              </div>
              <p class="hint mt-1">Se usa como acento en el panel de asignaturas y en los reportes.</p>
              <?= field_error('color') ?>
            </fieldset>
          </template>

          <template x-if="type === 'asignatura'">
            <div class="stack">
              <label class="field"><span class="label">Categoría <span class="text-danger">*</span></span>
                <select class="select" name="category_id" x-model="form.category_id" required>
                  <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>"><?= esc($c['name']) ?></option><?php endforeach ?>
                </select><?= field_error('category_id') ?></label>
              <label class="field"><span class="label">Descripción</span><input class="input" name="description" x-model="form.description" maxlength="255"></label>
            </div>
          </template>

          <template x-if="type === 'curso' || type === 'categoria'">
            <label class="field"><span class="label">Orden</span><input class="input" type="number" name="sort_order" x-model="form.sort_order" min="0" style="max-width:8rem"><span class="hint">Los de menor número aparecen primero.</span></label>
          </template>

          <template x-if="type === 'grupo'">
            <div class="stack">
              <label class="field"><span class="label">Curso <span class="text-danger">*</span></span>
                <select class="select" name="level_id" x-model="form.level_id" required><option value="">Elija…</option>
                  <?php foreach ($levels as $l): ?><option value="<?= $l['id'] ?>"><?= esc($l['name']) ?></option><?php endforeach ?>
                </select><?= field_error('level_id') ?></label>
              <label class="field"><span class="label">Periodo <span class="text-danger">*</span></span>
                <select class="select" name="period_id" x-model="form.period_id" required>
                  <?php foreach ($periods as $p): ?><option value="<?= $p['id'] ?>"><?= esc($p['name']) ?></option><?php endforeach ?>
                </select><?= field_error('period_id') ?></label>
              <?php if ($campuses): ?>
                <label class="field"><span class="label">Sede</span>
                  <select class="select" name="campus_id" x-model="form.campus_id"><option value="">Sin sede</option>
                    <?php foreach ($campuses as $c): ?><option value="<?= $c['id'] ?>"><?= esc($c['name']) ?></option><?php endforeach ?>
                  </select><?= field_error('campus_id') ?></label>
              <?php endif ?>
            </div>
          </template>

          <template x-if="type === 'oferta'">
            <div class="stack">
              <label class="field"><span class="label">Asignatura <span class="text-danger">*</span></span>
                <select class="select" name="subject_id" x-model="form.subject_id" required data-autofocus><option value="">Elija…</option>
                  <?php foreach ($subjects as $s): ?><option value="<?= $s['id'] ?>"><?= esc($s['name']) ?></option><?php endforeach ?>
                </select><?= field_error('subject_id') ?></label>
              <label class="field"><span class="label">Grupo <span class="text-danger">*</span></span>
                <select class="select" name="group_id" x-model="form.group_id" required><option value="">Elija…</option>
                  <?php foreach ($groups as $g): ?><option value="<?= $g['id'] ?>"><?= esc($g['name']) ?></option><?php endforeach ?>
                </select><?= field_error('group_id') ?></label>
              <label class="field"><span class="label">Horas por semana</span><input class="input" type="number" name="hours_per_week" x-model="form.hours_per_week" min="0" max="40" style="max-width:8rem"><?= field_error('hours_per_week') ?></label>
              <p class="hint">Los docentes se asignan después con el botón «Docentes» de la oferta.</p>
            </div>
          </template>

          <template x-if="type === 'sede'">
            <label class="field"><span class="label">Dirección</span><input class="input" name="address" x-model="form.address" maxlength="200"></label>
          </template>
        </div>
        <div class="drawer-foot">
          <button type="button" class="btn" @click="close()">Cancelar</button>
          <button type="submit" class="btn btn-primary" data-loading="Guardando…" x-text="mode === 'create' ? 'Crear' : 'Guardar'"></button>
        </div>
      </form>
    </aside>
  </div>

  <!-- Docentes de una oferta -->
  <div class="modal-layer" x-show="teach" x-cloak @keydown.escape.window="teach = null">
    <div class="veil" @click="teach = null"></div>
    <div class="modal lg" role="dialog" aria-modal="true" aria-labelledby="teach-title">
      <form method="post" :action="teach && (base + '/oferta/' + teach.id + '/docentes')">
        <?= csrf_field() ?>
        <div class="modal-head"><h2 id="teach-title" x-text="teach && ('Docentes de ' + teach.label)"></h2><button type="button" class="btn btn-ghost btn-sm btn-icon" @click="teach = null" aria-label="Cerrar">×</button></div>
        <div class="modal-body stack-sm">
          <?php if (! $teachers): ?><p class="soft">No hay docentes activos. Invítelos en Ajustes › Personas.</p><?php endif ?>
          <?php foreach ($teachers as $t): ?>
            <label class="perm-row" style="padding:0.5rem 0.25rem"><input type="checkbox" name="user_ids[]" value="<?= $t['id'] ?>" :checked="teach && teach.ids.includes(<?= (int) $t['id'] ?>)"> <span><?= esc($t['name']) ?></span></label>
          <?php endforeach ?>
        </div>
        <div class="modal-foot"><button type="button" class="btn" @click="teach = null">Cancelar</button><button type="submit" class="btn btn-primary" data-loading="Guardando…">Guardar</button></div>
      </form>
    </div>
  </div>

  <!-- Confirmación de eliminación -->
  <div class="modal-layer" x-show="confirm" x-cloak @keydown.escape.window.stop="confirm = null">
    <div class="veil" @click="confirm = null"></div>
    <div class="modal" role="alertdialog" aria-modal="true" aria-labelledby="del-title" aria-describedby="del-msg">
      <form method="post" :action="confirm && (base + '/' + confirm.type + '/' + confirm.id + '/eliminar')" class="confirm">
        <?= csrf_field() ?>
        <h2 id="del-title" x-text="confirm && ('¿Eliminar «' + confirm.label + '»?')"></h2>
        <p id="del-msg">Si tiene registros relacionados no se elimina y se le explica qué quitar primero.</p>
        <div class="actions"><button type="button" class="btn" @click="confirm = null">Cancelar</button><button type="submit" class="btn btn-danger" data-loading="Eliminando…">Eliminar</button></div>
      </form>
    </div>
  </div>
</div>
<?= $this->endSection() ?>
