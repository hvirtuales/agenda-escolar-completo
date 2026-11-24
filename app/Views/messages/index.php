<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
/**
 * Mensajes: bandeja de recibidos y, para quien envía, enviados (con cuántos lo leyeron).
 *
 * @var string $box (inbox | sent), $q
 * @var array  $list
 * @var bool   $canSend
 * @var int    $unread
 */
$base = site_url('mensajes');
?>
<div class="page-header">
  <div><h1>Comunicaciones</h1><p><?= $canSend ? 'Mensajes para tus grupos, sus familias y tus colegas, y las noticias de la institución.' : 'Mensajes de tus docentes y noticias de la institución.' ?></p></div>
  <?php if ($canSend): ?><div class="actions"><a class="btn btn-primary" href="<?= $base ?>/nuevo"><?= icon('send', 16) ?> Nuevo mensaje</a></div><?php endif ?>
</div>
<?= view('partials/tabs', ['label' => 'Comunicaciones', 'current' => 'mensajes', 'tabs' => ['mensajes' => ['Mensajes', 'mensajes'], 'noticias' => ['Noticias', 'noticias']]]) ?>

<?php if ($canSend): ?>
  <nav class="chips mb-4" aria-label="Bandeja">
    <a class="chip" href="<?= $base ?>" aria-selected="<?= $box === 'inbox' ? 'true' : 'false' ?>">Recibidos<?= $unread ? ' (' . $unread . ' sin leer)' : '' ?></a>
    <a class="chip" href="<?= $base ?>?ver=enviados" aria-selected="<?= $box === 'sent' ? 'true' : 'false' ?>">Enviados</a>
  </nav>
<?php endif ?>

<section class="panel">
  <form class="panel-bar" method="get" action="<?= $base ?>">
    <?php if ($box === 'sent'): ?><input type="hidden" name="ver" value="enviados"><?php endif ?>
    <label class="input-icon"><span class="sr-only">Buscar mensajes</span><?= icon('search', 16) ?><input class="input" type="search" name="q" value="<?= esc($q, 'attr') ?>" placeholder="Buscar por asunto o texto"></label>
    <?php if ($q !== ''): ?><a class="btn btn-ghost btn-sm" href="<?= $base . ($box === 'sent' ? '?ver=enviados' : '') ?>">Limpiar filtros</a><?php endif ?>
    <span class="panel-count"><?= plural(count($list), 'mensaje', 'mensajes') ?></span>
  </form>
  <?php if (! $list): ?>
    <div class="empty"><?= icon('mail', 32) ?><p><?= $q !== '' ? 'Ningún mensaje coincide con la búsqueda.' : ($box === 'sent' ? 'Aún no has enviado mensajes.' : 'No tienes mensajes.') ?></p></div>
  <?php else: ?>
    <div class="msg-list">
      <?php foreach ($list as $m): $unreadRow = $box === 'inbox' && ! $m['read_at']; ?>
        <a class="msg-row<?= $unreadRow ? ' is-unread' : '' ?>" href="<?= $base . '/' . $m['id'] ?>">
          <?php if ($box === 'inbox'): ?><span class="avatar" aria-hidden="true"><?= esc(initials($m['sender'] ?? '?')) ?></span><?php else: ?><span class="msg-ico" aria-hidden="true"><?= icon('send', 16) ?></span><?php endif ?>
          <span class="msg-main">
            <span class="msg-top"><span class="msg-who truncate"><?= esc($box === 'inbox' ? ($m['sender'] ?? '—') : 'Para: ' . $m['audience']) ?></span><?php if ($box === 'inbox' && ! empty($m['network_name'])): ?><span class="net-badge"><?= icon('network', 12) ?> Red</span><?php endif ?>
              <span class="msg-date"><?= esc(date_short($m['created_at'])) ?></span></span>
            <span class="msg-subject truncate"><?php if ($m['priority'] === 'important'): ?><?= badge('Importante', 'danger') ?> <?php endif ?><?= esc($m['subject']) ?></span>
            <span class="msg-snippet truncate"><?= esc(mb_substr(preg_replace('/\s+/u', ' ', $m['body']), 0, 160)) ?></span>
          </span>
          <?php if ($box === 'sent'): ?>
            <span class="msg-stats text-xs soft nowrap"><?= (int) $m['read_count'] ?> de <?= (int) $m['recipients'] ?> leído</span>
          <?php elseif ($unreadRow): ?><span class="notif-dot" aria-label="Sin leer"></span><?php endif ?>
        </a>
      <?php endforeach ?>
    </div>
  <?php endif ?>
</section>
<?= $this->endSection() ?>
