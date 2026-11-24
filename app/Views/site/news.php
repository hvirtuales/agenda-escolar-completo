<?php
/**
 * Noticia pública de un colegio (agendaescolar.co/{slug}/noticias/{id}). Solo las marcadas como públicas y publicadas.
 *
 * @var array $inst
 * @var array $site
 * @var array $n
 */
$slug = $inst['slug'];
?>
<!doctype html>
<html lang="<?= esc(service('locale')->current(), 'attr') ?>" style="<?= esc(brand_vars($inst['accent_color'] ?? null), 'attr') ?>">
<head>
  <?= $this->include('partials/head') ?>
  <meta name="description" content="<?= esc(mb_substr($n['summary'] ?: $n['body'], 0, 160), 'attr') ?>">
  <meta property="og:title" content="<?= esc($n['title'], 'attr') ?>">
  <?php if ($n['cover_path']): ?><meta property="og:image" content="<?= site_url($slug . '/noticias/' . $n['id'] . '/imagen') ?>"><?php endif ?>
</head>
<body class="site">
<header class="site-top">
  <div class="site-wrap site-top-in">
    <a class="site-brand" href="<?= site_url($slug) ?>"><span class="site-mark" aria-hidden="true"><?= esc(mb_strtoupper(mb_substr($inst['name'], 0, 1))) ?></span><span><?= esc($inst['name']) ?></span></a>
    <a class="btn btn-sm" href="<?= site_url('login') ?>"><?= icon('log-in', 15) ?> <?= esc(t('Site.login')) ?></a>
  </div>
</header>
<main id="contenido" class="site-sec">
  <article class="site-wrap site-article">
    <a class="btn btn-sm" href="<?= site_url($slug) ?>#noticias"><?= icon('arrow-left', 14) ?> <?= esc(t('Site.backToSite')) ?></a>
    <p class="text-sm soft" style="margin:1.5rem 0 0.5rem"><?= esc(date_long($n['published_at'])) ?></p>
    <h1 class="site-article-title"><?= esc($n['title']) ?></h1>
    <?php if ($n['summary']): ?><p class="site-article-lead"><?= esc($n['summary']) ?></p><?php endif ?>
    <?php if ($n['cover_path']): ?><img class="site-article-img" src="<?= site_url($slug . '/noticias/' . $n['id'] . '/imagen') ?>" alt=""><?php endif ?>
    <div class="site-prose site-article-body"><?= esc($n['body']) ?></div>
  </article>
</main>
<footer class="site-foot">
  <div class="site-wrap site-foot-in">
    <span>© <?= date('Y') ?> <?= esc($inst['name']) ?></span>
    <a href="<?= site_url('/') ?>" class="site-powered"><img src="<?= base_url('assets/img/logo-mark.png') ?>" alt="" width="18" height="18"> <?= esc(t('Site.poweredBy', ['app' => config('AgendaEscolar')->appName])) ?></a>
  </div>
</footer>
</body>
</html>
