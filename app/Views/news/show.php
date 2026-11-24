<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
/**
 * Una noticia: portada, título, resumen y contenido en párrafos. Rectoría y coordinación la editan o eliminan.
 *
 * @var array $n, $audiences
 * @var bool  $canManage
 */
$paras = array_filter(array_map('trim', preg_split("/\n\s*\n/", $n['body'])));
?>
<div x-data="{ f: false }">
  <div class="page-header">
    <div><nav class="crumbs text-xs" aria-label="Ubicación"><a class="link" href="<?= site_url('noticias') ?>">Noticias</a><?= icon('chevron-right', 12) ?><span><?= $n['status'] === 'draft' ? 'Borrador' : esc(date_short($n['published_at'], false)) ?></span></nav></div>
    <?php if ($canManage && empty($n['network_post_id'])): ?>
      <div class="actions">
        <button type="button" class="btn" @click="f = true"><?= icon('pencil', 16) ?> Editar</button>
        <form method="post" action="<?= site_url('noticias/' . $n['id'] . '/eliminar') ?>" onsubmit="return confirm('¿Eliminar la noticia «<?= esc($n['title'], 'js') ?>»?')"><?= csrf_field() ?>
          <button type="submit" class="btn btn-danger"><?= icon('trash-2', 16) ?> Eliminar</button></form>
      </div>
    <?php endif ?>
  </div>

  <article class="card news-article">
    <?php if ($n['cover_path']): ?><img class="news-article-cover" src="<?= site_url('noticias/' . $n['id'] . '/imagen') ?>" alt=""><?php endif ?>
    <div class="card-body">
      <div class="row" style="gap:0.375rem;flex-wrap:wrap">
        <?= $n['status'] === 'draft' ? badge('Borrador') : '' ?><?= $n['pinned'] ? badge('Destacada', 'accent') : '' ?>
        <?php if (! empty($n['network_name'])): ?><span class="net-badge"><?= icon('network', 12) ?> <?= esc($n['network_name']) ?></span><?php endif ?>
        <span class="text-xs soft"><?= $n['published_at'] ? 'Publicada ' . esc(date_human($n['published_at'])) : 'Sin publicar' ?><?= $n['author'] ? ' · ' . esc($n['author']) : '' ?></span>
      </div>
      <h1 class="news-article-title"><?= esc($n['title']) ?></h1>
      <?php if ($n['summary']): ?><p class="news-article-lead"><?= esc($n['summary']) ?></p><?php endif ?>
      <?php foreach ($paras as $p): ?><p class="mt-4"><?= nl2br(esc($p)) ?></p><?php endforeach ?>
      <?php if ($canManage): ?><p class="hint mt-6">Para: <?= esc($n['audience'] === 'all' ? 'toda la comunidad' : implode(', ', array_map(static fn ($k) => $audiences[$k] ?? $k, explode(',', $n['audience'])))) ?></p><?php endif ?>
    </div>
  </article>

  <?php if ($canManage && empty($n['network_post_id'])): ?><?= view('news/_form', ['n' => $n, 'audiences' => $audiences]) ?><?php endif ?>
</div>
<?= $this->endSection() ?>
