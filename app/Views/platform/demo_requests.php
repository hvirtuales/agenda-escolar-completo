<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
/**
 * Consola › Solicitudes de demostración (página pública). Seguimiento por estado, notas internas y conversión en institución.
 *
 * @var array $list, $filters, $statuses, $counts
 * @var int   $total
 */
$filtered = $filters['estado'] !== '' || $filters['q'] !== '';
$qs       = static fn (array $q): string => ($p = http_build_query(array_filter($q + ['q' => $filters['q']]))) ? '?' . $p : '';
?>
<div x-data="{ r: null }">
  <div class="page-header">
    <div><h1>Solicitudes de demostración</h1><p>Las que llegan desde la página pública. Cada nueva solicitud avisa en la campana.</p></div>
    <div class="actions"><a class="btn" href="<?= site_url('plataforma/solicitudes/exportar') . $qs(['estado' => $filters['estado']]) ?>"><?= icon('download', 16) ?> Exportar CSV</a></div>
  </div>

  <nav class="chips mb-4" aria-label="Filtrar por estado">
    <a class="chip" href="<?= site_url('plataforma/solicitudes') . $qs([]) ?>" aria-selected="<?= $filters['estado'] === '' ? 'true' : 'false' ?>">Todas (<?= $total ?>)</a>
    <?php foreach ($statuses as $k => [$label]): ?>
      <a class="chip" href="<?= site_url('plataforma/solicitudes') . $qs(['estado' => $k]) ?>" aria-selected="<?= $filters['estado'] === $k ? 'true' : 'false' ?>"><?= esc($label) ?> (<?= (int) ($counts[$k] ?? 0) ?>)</a>
    <?php endforeach ?>
  </nav>

  <div class="panel">
    <form class="panel-bar" method="get" action="<?= site_url('plataforma/solicitudes') ?>">
      <?php if ($filters['estado'] !== ''): ?><input type="hidden" name="estado" value="<?= esc($filters['estado'], 'attr') ?>"><?php endif ?>
      <label class="input-icon"><span class="sr-only">Buscar solicitudes</span><?= icon('search', 16) ?><input class="input" type="search" name="q" value="<?= esc($filters['q'], 'attr') ?>" placeholder="Institución, contacto, correo o ciudad"></label>
      <?php if ($filtered): ?><a class="btn btn-ghost btn-sm" href="<?= site_url('plataforma/solicitudes') ?>">Limpiar filtros</a><?php endif ?>
      <span class="panel-count"><?= plural(count($list), 'solicitud', 'solicitudes') ?></span>
    </form>
    <?php if (! $list): ?>
      <div class="empty"><?= icon('inbox', 32) ?><p><?= $filtered ? 'Ninguna solicitud coincide con los filtros.' : 'Aún no hay solicitudes. Llegan desde el formulario «Solicitar demostración» de la página pública.' ?></p></div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Fecha</th><th>Institución</th><th>Contacto</th><th>Estado</th><th class="text-right">Acciones</th></tr></thead>
          <tbody>
            <?php foreach ($list as $d): [$label, $tone] = $statuses[$d['status']] ?? [$d['status'], 'neutral'];
                $json = ['id' => (int) $d['id'], 'institution' => $d['institution'], 'name' => $d['name'], 'email' => $d['email'], 'phone' => (string) $d['phone'],
                    'role' => (string) $d['role'], 'city' => (string) $d['city'], 'students' => (string) $d['students'], 'message' => (string) $d['message'],
                    'status' => $d['status'], 'notes' => (string) $d['notes'], 'created' => date_human($d['created_at']),
                    'handled' => $d['handler'] ? $d['handler'] . ' · ' . date_human($d['updated_at']) : '', 'converted' => (string) ($d['institution_name'] ?? '')]; ?>
              <tr>
                <td class="nowrap"><?= esc(date_human($d['created_at'])) ?></td>
                <td><span class="font-medium"><?= esc($d['institution']) ?></span>
                  <span class="meta"><?= esc(trim(($d['city'] ?? '') . ($d['students'] ? ' · ' . $d['students'] . ' estudiantes' : ''), ' ·')) ?: '—' ?></span></td>
                <td><span><?= esc($d['name']) ?><?= $d['role'] ? ' <span class="soft">· ' . esc($d['role']) . '</span>' : '' ?></span>
                  <span class="meta"><a class="link" href="mailto:<?= esc($d['email'], 'attr') ?>"><?= esc($d['email']) ?></a><?= $d['phone'] ? ' · ' . esc($d['phone']) : '' ?></span></td>
                <td><?= badge($label, $tone) ?><?php if ($d['institution_name']): ?><span class="meta"><?= esc($d['institution_name']) ?></span><?php endif ?></td>
                <td class="text-right nowrap">
                  <button type="button" class="btn btn-sm" @click="r = <?= esc(json_encode($json), 'attr') ?>"><?= icon('clipboard-list', 14) ?> Gestionar</button>
                  <?php if ($d['status'] !== 'won'): ?>
                    <a class="btn btn-sm btn-icon" href="<?= site_url('plataforma/instituciones?solicitud=' . $d['id']) ?>" title="Crear la institución «<?= esc($d['institution'], 'attr') ?>»" aria-label="Crear la institución «<?= esc($d['institution'], 'attr') ?>»"><?= icon('school', 14) ?></a>
                  <?php endif ?>
                </td>
              </tr>
            <?php endforeach ?>
          </tbody>
        </table>
      </div>
    <?php endif ?>
  </div>

  <div class="drawer-layer" x-show="r" x-cloak @keydown.escape.window="r = null">
    <div class="veil" @click="r = null"></div>
    <aside class="drawer" role="dialog" aria-modal="true" aria-labelledby="dr-title">
      <form method="post" :action="r ? '<?= site_url('plataforma/solicitudes') ?>/' + r.id : '#'">
        <?= csrf_field() ?>
        <input type="hidden" name="back" value="<?= esc($filters['estado'], 'attr') ?>">
        <div class="drawer-head"><h2 id="dr-title" x-text="r && r.institution"></h2><button type="button" class="btn btn-ghost btn-sm btn-icon" @click="r = null" aria-label="Cerrar">×</button></div>
        <div class="drawer-body stack">
          <dl class="plan-limits">
            <div><dt>Recibida</dt><dd x-text="r && r.created"></dd></div>
            <div><dt>Contacto</dt><dd x-text="r && (r.name + (r.role ? ' · ' + r.role : ''))"></dd></div>
            <div><dt>Correo</dt><dd><a class="link" :href="r && ('mailto:' + r.email)" x-text="r && r.email"></a></dd></div>
            <div x-show="r && r.phone"><dt>Teléfono</dt><dd><a class="link" :href="r && ('tel:' + r.phone)" x-text="r && r.phone"></a></dd></div>
            <div x-show="r && r.city"><dt>Ciudad</dt><dd x-text="r && r.city"></dd></div>
            <div x-show="r && r.students"><dt>Estudiantes</dt><dd x-text="r && r.students"></dd></div>
            <div x-show="r && r.converted"><dt>Institución creada</dt><dd x-text="r && r.converted"></dd></div>
          </dl>
          <div class="field" x-show="r && r.message"><span class="label">Lo que quiere ver</span><p class="text-sm" style="white-space:pre-wrap" x-text="r && r.message"></p></div>
          <fieldset class="field"><legend class="label">Estado</legend>
            <div class="level-row" role="radiogroup">
              <?php foreach ($statuses as $k => [$label]): ?>
                <label class="btn btn-sm" :class="{ 'btn-active': r && r.status === '<?= $k ?>' }"><input type="radio" class="sr-only" name="status" value="<?= $k ?>" :checked="r && r.status === '<?= $k ?>'" @change="r.status = '<?= $k ?>'"><?= esc($label) ?></label>
              <?php endforeach ?>
            </div>
          </fieldset>
          <label class="field"><span class="label">Notas internas</span><textarea class="textarea" name="notes" rows="4" maxlength="1000" x-text="r && r.notes" placeholder="Llamadas, acuerdos, fecha de la demostración…"></textarea>
            <span class="hint" x-show="r && r.handled" x-text="r && ('Última gestión: ' + r.handled)"></span></label>
        </div>
        <div class="drawer-foot">
          <a class="btn" x-show="r && r.status !== 'won'" :href="r ? '<?= site_url('plataforma/instituciones?solicitud=') ?>' + r.id : '#'"><?= icon('school', 14) ?> Crear institución</a>
          <button type="button" class="btn ml-auto" @click="r = null">Cancelar</button>
          <button type="submit" class="btn btn-primary" data-loading="Guardando…">Guardar</button>
        </div>
      </form>
    </aside>
  </div>
</div>
<?= $this->endSection() ?>
