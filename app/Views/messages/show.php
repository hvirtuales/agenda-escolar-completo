<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
/**
 * Un mensaje. Quien lo recibe ve el contenido; quien lo envió ve además a quién llegó y quién lo leyó.
 *
 * @var array $m Messages::open()
 */
?>
<div class="page-header">
  <div><nav class="crumbs text-xs" aria-label="Ubicación"><a class="link" href="<?= site_url('mensajes' . ($m['mine'] ? '?ver=enviados' : '')) ?>">Mensajes</a><?= icon('chevron-right', 12) ?><span><?= $m['mine'] ? 'Enviado' : 'Recibido' ?></span></nav>
    <div class="row mt-1" style="flex-wrap:wrap"><h1><?= esc($m['subject']) ?></h1><?= $m['priority'] === 'important' ? badge('Importante', 'danger') : '' ?></div></div>
</div>

<div class="grid-3" style="align-items:start">
  <article class="card span-2">
    <div class="card-body">
      <div class="row" style="gap:0.75rem">
        <span class="avatar" aria-hidden="true"><?= esc(initials($m['sender'] ?? '?')) ?></span>
        <div><div class="font-medium"><?= esc($m['sender'] ?? '—') ?> <?php if (! empty($m['network_name'])): ?><span class="net-badge"><?= icon('network', 12) ?> <?= esc($m['network_name']) ?></span><?php endif ?></div><div class="text-xs soft"><?= esc(date_human($m['created_at'])) ?> · Para: <?= esc($m['audience']) ?></div></div>
      </div>
      <div class="msg-body mt-4"><?= nl2br(esc($m['body'])) ?></div>
    </div>
  </article>

  <?php if ($m['mine']): ?>
    <section class="card">
      <div class="card-header"><h2>Lectura</h2><span class="text-xs soft"><?= (int) $m['read_count'] ?> de <?= (int) $m['recipients'] ?></span></div>
      <div class="card-body" style="padding-bottom:0">
        <span class="share" role="img" aria-label="<?= (int) $m['read_count'] ?> de <?= (int) $m['recipients'] ?> lo leyeron"><span style="width:<?= $m['recipients'] ? (int) round($m['read_count'] * 100 / $m['recipients']) : 0 ?>%"></span></span>
      </div>
      <ul class="msg-recips">
        <?php foreach ($m['recipients_list'] as $r): ?>
          <li><span class="truncate"><?= esc($r['name']) ?></span><?= $r['read_at'] ? '<span class="text-xs text-ok nowrap">' . icon('check-check', 12) . ' ' . esc(date_short($r['read_at'])) . '</span>' : '<span class="text-xs soft">Sin leer</span>' ?></li>
        <?php endforeach ?>
      </ul>
    </section>
  <?php endif ?>
</div>
<?= $this->endSection() ?>
