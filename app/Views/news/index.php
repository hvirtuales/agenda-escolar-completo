<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
/**
 * Noticias y boletines: tarjetas con portada (destacadas primero). Rectoría y coordinación ven también los borradores
 * y crean noticias en un Drawer (news/_form).
 *
 * @var array $list, $audiences
 * @var bool  $canManage
 */
?>
<div x-data="{ f: <?= old('title') !== null ? 'true' : 'false' ?> }">
  <div class="page-header">
    <div><h1>Comunicaciones</h1><p>Mensajes y noticias de la institución.</p></div>
    <?php if ($canManage): ?><div class="actions"><button type="button" class="btn btn-primary" @click="f = true"><?= icon('plus', 16) ?> Nueva noticia</button></div><?php endif ?>
  </div>
  <?= view('partials/tabs', ['label' => 'Comunicaciones', 'current' => 'noticias', 'tabs' => ['mensajes' => ['Mensajes', 'mensajes'], 'noticias' => ['Noticias', 'noticias']]]) ?>

  <?php if (! $list): ?>
    <div class="panel"><div class="empty"><?= icon('newspaper', 32) ?><p><?= $canManage ? 'Aún no hay noticias. Publica la primera con «Nueva noticia».' : 'Aún no hay noticias para ti.' ?></p></div></div>
  <?php else: ?>
    <div class="news-grid">
      <?php foreach ($list as $i => $n): ?>
        <a class="news-card<?= $i === 0 && $n['pinned'] && $n['status'] === 'published' ? ' is-lead' : '' ?>" href="<?= site_url('noticias/' . $n['id']) ?>">
          <span class="news-cover"><?php if ($n['cover_path']): ?><img src="<?= site_url('noticias/' . $n['id'] . '/imagen') ?>" alt="" loading="lazy"><?php else: ?><?= icon('newspaper', 32) ?><?php endif ?></span>
          <span class="news-body">
            <span class="row" style="gap:0.375rem;flex-wrap:wrap">
              <?php if ($n['status'] === 'draft'): ?><?= badge('Borrador') ?><?php endif ?>
              <?php if ($n['pinned']): ?><?= badge('Destacada', 'accent') ?><?php endif ?>
              <?php if (! empty($n['network_name'])): ?><span class="net-badge"><?= icon('network', 12) ?> <?= esc($n['network_name']) ?></span><?php endif ?>
              <span class="text-xs soft"><?= esc(date_short($n['published_at'] ?? $n['updated_at'], false)) ?></span>
            </span>
            <span class="news-title"><?= esc($n['title']) ?></span>
            <?php if ($n['summary']): ?><span class="news-summary"><?= esc($n['summary']) ?></span><?php endif ?>
            <?php if ($canManage): ?><span class="text-xs soft">Para: <?= esc($n['audience'] === 'all' ? 'toda la comunidad' : implode(', ', array_map(static fn ($k) => $audiences[$k] ?? $k, explode(',', $n['audience'])))) ?></span><?php endif ?>
          </span>
        </a>
      <?php endforeach ?>
    </div>
  <?php endif ?>

  <?php if ($canManage): ?><?= view('news/_form', ['n' => null, 'audiences' => $audiences]) ?><?php endif ?>
</div>
<?= $this->endSection() ?>
