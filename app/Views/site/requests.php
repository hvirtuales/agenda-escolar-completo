<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
/**
 * Ajustes › Página pública › Solicitudes de admisión (formulario «Quiero información» de la página del colegio).
 *
 * @var array $list, $statuses, $counts; string $status
 */
$all  = array_sum(array_map('intval', $counts));
?>
<div class="page-header">
  <div>
    <h1>Ajustes</h1><p><?= esc($institution['name']) ?></p>
  </div>
</div>
<?= view('settings/_tabs', ['section' => 'pagina-publica']) ?>

<div class="row-between mb-4" style="flex-wrap:wrap;gap:0.75rem">
  <div>
    <h2 class="section-title" style="margin:0">Solicitudes de admisión</h2>
    <p class="soft text-sm" style="margin:0.25rem 0 0">Las envían las familias desde el formulario «Quiero información» de la página pública.</p>
  </div>
  <div class="row" style="gap:0.5rem;flex-wrap:wrap">
    <a class="btn" href="<?= site_url($institution['slug']) ?>#admisiones" target="_blank" rel="noopener"><?= icon('external-link', 16) ?> Ver el formulario</a>
    <a class="btn" href="<?= site_url('pagina-publica') ?>"><?= icon('pencil', 16) ?> Editar la página</a>
  </div>
</div>

<nav class="chips mb-4" aria-label="Estado">
  <a class="chip" href="<?= site_url('pagina-publica/solicitudes') ?>" aria-selected="<?= $status === '' ? 'true' : 'false' ?>">Todas (<?= $all ?>)</a>
  <?php foreach ($statuses as $k => $l): ?>
    <a class="chip" href="<?= site_url('pagina-publica/solicitudes?estado=' . $k) ?>" aria-selected="<?= $status === $k ? 'true' : 'false' ?>"><?= esc($l) ?> (<?= (int) ($counts[$k] ?? 0) ?>)</a>
  <?php endforeach ?>
</nav>

<div class="panel">
  <?php if (! $list): ?>
    <div class="empty"><?= icon('inbox', 32) ?><p><?= $all ? 'No hay solicitudes con ese estado.' : 'Aún no hay solicitudes. Llegan cuando una familia llena «Quiero información» en la página del colegio.' ?></p></div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Fecha</th><th>Quién</th><th>Grado</th><th>Mensaje</th><th>Estado</th></tr></thead>
        <tbody>
          <?php foreach ($list as $r): ?>
            <tr>
              <td class="nowrap"><?= esc(date_human($r['created_at'])) ?></td>
              <td><span class="font-medium"><?= esc($r['name']) ?></span>
                <span class="meta"><?php if ($r['phone']): ?><a class="link" href="tel:<?= esc(preg_replace('/[^0-9+]/', '', $r['phone']), 'attr') ?>"><?= esc($r['phone']) ?></a><?php endif ?><?= $r['phone'] && $r['email'] ? ' · ' : '' ?><?php if ($r['email']): ?><a class="link" href="mailto:<?= esc($r['email'], 'attr') ?>"><?= esc($r['email']) ?></a><?php endif ?></span></td>
              <td class="nowrap"><?= esc($r['grade'] ?: '—') ?></td>
              <td class="soft" style="max-width:24rem"><?= esc($r['message'] ?: '—') ?></td>
              <td>
                <form method="post" action="<?= site_url('pagina-publica/solicitudes/' . (int) $r['id']) ?>" class="row" style="gap:0.375rem">
                  <?= csrf_field() ?>
                  <select class="select" name="status" aria-label="Estado de la solicitud de <?= esc($r['name'], 'attr') ?>" onchange="this.form.submit()" style="width:auto">
                    <?php foreach ($statuses as $k => $l): ?><option value="<?= $k ?>" <?= $r['status'] === $k ? 'selected' : '' ?>><?= esc($l) ?></option><?php endforeach ?>
                  </select>
                  <noscript><button type="submit" class="btn btn-sm">Guardar</button></noscript>
                </form>
              </td>
            </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>
  <?php endif ?>
</div>
<?= $this->endSection() ?>
