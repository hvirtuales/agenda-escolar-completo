<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
/**
 * Consola › Auditoría: acciones sensibles de toda la plataforma (documento maestro 2.1, 7.2 y 10). Solo lectura.
 *
 * @var array $log, $filters, $types, $insts
 * @var int   $total, $page, $pages, $per
 */
$filtered = array_filter($filters) !== [];
$qs       = static fn (array $extra = []): string => ($p = http_build_query(array_filter($extra + $filters))) ? '?' . $p : '';
$from     = $total ? ($page - 1) * $per + 1 : 0;
$to       = min($total, $page * $per);
?>
<div class="page-header">
  <div><h1>Auditoría</h1><p>Acciones sensibles de toda la plataforma. El registro no se puede editar ni borrar.</p></div>
  <div class="actions"><a class="btn" href="<?= site_url('plataforma/auditoria/exportar') . $qs() ?>"><?= icon('download', 16) ?> Exportar CSV</a></div>
</div>

<div class="panel">
  <form class="panel-bar" method="get" action="<?= site_url('plataforma/auditoria') ?>">
    <label class="input-icon"><span class="sr-only">Buscar en la auditoría</span><?= icon('search', 16) ?><input class="input" type="search" name="q" value="<?= esc($filters['q'], 'attr') ?>" placeholder="Persona, correo, detalle o IP"></label>
    <select class="select" name="institucion" aria-label="Institución" onchange="this.form.submit()" style="width:auto">
      <option value="">Todas las instituciones</option>
      <option value="plataforma" <?= $filters['institucion'] === 'plataforma' ? 'selected' : '' ?>>Solo la consola de plataforma</option>
      <?php foreach ($insts as $i): ?><option value="<?= (int) $i['id'] ?>" <?= $filters['institucion'] === (string) $i['id'] ? 'selected' : '' ?>><?= esc($i['name']) ?></option><?php endforeach ?>
    </select>
    <select class="select" name="tipo" aria-label="Tipo de acción" onchange="this.form.submit()" style="width:auto">
      <option value="">Todas las acciones</option>
      <?php foreach ($types as $k => $l): ?><option value="<?= $k ?>" <?= $filters['tipo'] === $k ? 'selected' : '' ?>><?= esc($l) ?></option><?php endforeach ?>
    </select>
    <span class="panel-range row">
      <input class="input" type="date" name="desde" value="<?= esc($filters['desde'], 'attr') ?>" aria-label="Desde" onchange="this.form.submit()">
      <span class="soft">a</span>
      <input class="input" type="date" name="hasta" value="<?= esc($filters['hasta'], 'attr') ?>" aria-label="Hasta" onchange="this.form.submit()">
    </span>
    <?php if ($filtered): ?><a class="btn btn-ghost btn-sm" href="<?= site_url('plataforma/auditoria') ?>">Limpiar filtros</a><?php endif ?>
    <span class="panel-count"><?= plural($total, 'evento', 'eventos') ?></span>
  </form>
  <?php if (! $log): ?>
    <div class="empty"><?= icon('scroll-text', 32) ?><p><?= $filtered ? 'Ningún evento coincide con los filtros.' : 'Aún no hay eventos en la auditoría.' ?></p></div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Fecha</th><th>Quién y dónde</th><th>Acción</th><th>Detalle</th></tr></thead>
        <tbody>
          <?php foreach ($log as $e): ?>
            <tr>
              <td class="nowrap"><?= esc(date_human($e['when'])) ?></td>
              <td class="nowrap"><span class="font-medium"><?= esc($e['who']) ?></span><span class="meta"><?= esc(trim(($e['role'] !== '—' ? $e['role'] . ' · ' : '') . $e['where'], ' ·')) ?></span></td>
              <td class="nowrap"><?= badge($e['action'], $e['tone']) ?></td>
              <td class="soft"><?= esc(trim($e['detail'] . ($e['ip'] !== '' ? ' · IP ' . $e['ip'] : ''), ' ·')) ?: '—' ?></td>
            </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>
  <?php endif ?>
</div>

<?php if ($pages > 1): ?>
  <nav class="pager" aria-label="Páginas de la auditoría">
    <span class="count">Eventos <?= number_format($from, 0, ',', '.') ?> a <?= number_format($to, 0, ',', '.') ?> de <?= number_format($total, 0, ',', '.') ?></span>
    <span class="row">
      <?php if ($page > 1): ?><a class="btn btn-sm" href="<?= site_url('plataforma/auditoria') . $qs(['pagina' => $page - 1]) ?>" rel="prev"><?= icon('chevron-left', 14) ?> Anteriores</a><?php endif ?>
      <span class="soft text-sm">Página <?= $page ?> de <?= $pages ?></span>
      <?php if ($page < $pages): ?><a class="btn btn-sm" href="<?= site_url('plataforma/auditoria') . $qs(['pagina' => $page + 1]) ?>" rel="next">Siguientes <?= icon('chevron-right', 14) ?></a><?php endif ?>
    </span>
  </nav>
<?php elseif ($log): ?>
  <p class="count"><?= plural($total, 'evento', 'eventos') ?>, del más reciente al más antiguo.</p>
<?php endif ?>
<?= $this->endSection() ?>
