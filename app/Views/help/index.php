<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
/**
 * Ayuda integrada (base de conocimiento del asistente flotante): índice por categoría con buscador a la izquierda y el
 * artículo (o los resultados de búsqueda) a la derecha. Artículos en app/Config/HelpCenter.php.
 *
 * @var array       $index [categoría => [slug => título]]
 * @var array|null  $article, $results ([slug, puntaje, artículo]), $neighbors [anterior, siguiente]
 * @var string      $q
 */
$current = $article['slug'] ?? null;
$from    = (string) service('request')->getGet('desde');
$url     = static fn (string $slug): string => site_url('ayuda/' . $slug) . ($from !== '' ? '?desde=' . rawurlencode($from) : '');
?>
<div class="help-layout">
  <aside class="help-index">
    <form method="get" action="<?= site_url('ayuda') ?>" class="mb-4">
      <?php if ($from !== ''): ?><input type="hidden" name="desde" value="<?= esc($from, 'attr') ?>"><?php endif ?>
      <label class="input-icon"><span class="sr-only">Buscar en la ayuda</span><?= icon('search', 16) ?><input class="input" type="search" name="q" value="<?= esc($q, 'attr') ?>" placeholder="Buscar en la ayuda"></label>
    </form>
    <?php foreach ($index as $group => $items): ?>
      <div class="nav-group">
        <div class="nb-cat-title"><?= esc($group) ?></div>
        <div class="nav-list">
          <?php foreach ($items as $slug => $titleTxt): ?>
            <a class="nav-item" href="<?= $url($slug) ?>" <?= $slug === $current ? 'aria-current="page"' : '' ?>><?= esc($titleTxt) ?></a>
          <?php endforeach ?>
        </div>
      </div>
    <?php endforeach ?>
  </aside>

  <article class="card help-article">
    <?php if ($results !== null): ?>
      <p class="text-xs soft">Búsqueda</p>
      <h1 class="text-2xl font-semibold mt-1">Resultados para «<?= esc($q) ?>»</h1>
      <?php if (! $results): ?>
        <p class="mt-4 soft">No encontramos artículos con esas palabras. Prueba con otras o pregúntale al asistente.</p>
      <?php else: ?>
        <div class="date-list mt-4">
          <?php foreach ($results as [$slug, , $a]): ?>
            <a class="date-row" href="<?= $url($slug) ?>"><span style="flex:1;min-width:0"><span class="block font-medium"><?= esc($a['title']) ?></span><span class="block text-sm soft"><?= esc($a['summary']) ?></span></span><span class="text-xs soft nowrap"><?= esc($a['category']) ?></span></a>
          <?php endforeach ?>
        </div>
      <?php endif ?>
    <?php elseif ($article): ?>
      <p class="text-xs soft"><?= esc($article['category']) ?></p>
      <h1 class="text-2xl font-semibold mt-1"><?= esc($article['title']) ?></h1>
      <p class="mt-4 text-base"><?= esc($article['summary']) ?></p>
      <?php if (! empty($article['steps'])): ?>
        <h2 class="text-lg font-semibold mt-6">Paso a paso</h2>
        <ol class="help-steps"><?php foreach ($article['steps'] as $st): ?><li><?= esc($st) ?></li><?php endforeach ?></ol>
      <?php endif ?>
      <?php foreach ($article['body'] ?? [] as $para): ?><p class="mt-4"><?= esc($para) ?></p><?php endforeach ?>
      <?php if (! empty($article['tip'])): ?>
        <div class="notice notice-accent mt-4"><?= icon('lightbulb', 16) ?><span><?= esc($article['tip']) ?></span></div>
      <?php endif ?>
      <?php if (! empty($article['link'])): ?>
        <div class="mt-4"><a class="btn btn-primary" href="<?= site_url($article['link'][1]) ?>"><?= esc($article['link'][0]) ?> <?= icon('arrow-right', 14) ?></a></div>
      <?php endif ?>
      <?php [$prev, $next] = $neighbors; ?>
      <div class="row-between mt-6" style="flex-wrap:wrap;gap:0.5rem">
        <?php if ($prev): ?><a class="btn" href="<?= $url($prev['slug']) ?>"><?= icon('chevron-left', 14) ?> <?= esc($prev['title']) ?></a><?php else: ?><span></span><?php endif ?>
        <?php if ($next): ?><a class="btn" href="<?= $url($next['slug']) ?>"><?= esc($next['title']) ?> <?= icon('chevron-right', 14) ?></a><?php endif ?>
      </div>
    <?php endif ?>
    <div class="notice mt-6"><?= icon('message-circle-question', 16, 'soft') ?>
      <span>¿No encontraste lo que buscabas? El asistente responde con base en esta Ayuda.</span>
      <button type="button" class="btn btn-sm ml-auto" @click="$dispatch('assistant-open')"><?= icon('bot', 14) ?> Pregúntale al asistente</button>
    </div>
  </article>
</div>
<?= $this->endSection() ?>
