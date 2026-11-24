<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php /** Bandeja de notificaciones. @var array $items, bool $only (solo sin leer) */ ?>
<div class="page-header">
  <div><h1>Notificaciones</h1><p>Avisos de sus actividades, entregas, notas y cupos. Al abrir uno queda como leído.</p></div>
  <div class="actions">
    <form method="post" action="<?= site_url('notificaciones/leidas') ?>"><?= csrf_field() ?><button type="submit" class="btn"><?= icon('check-check', 16) ?> Marcar todas como leídas</button></form>
  </div>
</div>
<div class="chips mb-4" role="tablist" aria-label="Filtro">
  <a class="chip" role="tab" aria-selected="<?= $only ? 'false' : 'true' ?>" href="<?= site_url('notificaciones') ?>">Todas</a>
  <a class="chip" role="tab" aria-selected="<?= $only ? 'true' : 'false' ?>" href="<?= site_url('notificaciones?ver=sin-leer') ?>">Sin leer</a>
</div>
<section class="panel">
  <?php if (! $items): ?>
    <div class="empty"><?= icon('bell', 32) ?><p><?= $only ? 'No tiene notificaciones sin leer.' : 'Aún no tiene notificaciones.' ?></p></div>
  <?php else: ?>
    <div class="date-list">
      <?php foreach ($items as $n): ?>
        <a class="notif-item<?= $n['read_at'] ? '' : ' is-unread' ?>" href="<?= site_url('notificaciones/' . $n['id']) ?>">
          <span class="act-icon"><?= icon(notif_icon($n['kind']), 16) ?></span>
          <span style="min-width:0;flex:1">
            <span class="block font-medium"><?= esc($n['title']) ?></span>
            <span class="block text-xs soft"><?= esc(trim(($n['body'] ?? '') . ($n['institution'] ? ' · ' . $n['institution'] : '') . ' · ' . date_short($n['created_at']), ' ·')) ?></span>
          </span>
          <?php if (! $n['read_at']): ?><span class="notif-dot" aria-label="Sin leer"></span><?php endif ?>
        </a>
      <?php endforeach ?>
    </div>
  <?php endif ?>
</section>
<?= $this->endSection() ?>
