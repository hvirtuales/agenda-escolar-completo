<?php
/**
 * Drawer para crear o editar una noticia (rectoría y coordinación). Se abre con la variable Alpine «f».
 *
 * @var array|null $n         noticia a editar (null = nueva)
 * @var array      $audiences
 */
$aud = $n ? ($n['audience'] === 'all' ? array_keys($audiences) : explode(',', $n['audience'])) : array_keys($audiences);
?>
<div class="drawer-layer" x-show="f" x-cloak @keydown.escape.window="f = false">
  <div class="veil" @click="f = false"></div>
  <aside class="drawer wide" role="dialog" aria-modal="true" aria-labelledby="news-form-title">
    <form method="post" action="<?= site_url('noticias' . ($n ? '/' . $n['id'] : '')) ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <div class="drawer-head"><h2 id="news-form-title"><?= $n ? 'Editar noticia' : 'Nueva noticia' ?></h2><button type="button" class="btn btn-ghost btn-sm btn-icon" @click="f = false" aria-label="Cerrar">×</button></div>
      <div class="drawer-body stack">
        <label class="field"><span class="label">Título <span class="text-danger">*</span></span><input class="input" name="title" required maxlength="160" value="<?= esc(old('title') ?? ($n['title'] ?? ''), 'attr') ?>" placeholder="Ej.: Feria de la ciencia 2026"></label>
        <label class="field"><span class="label">Resumen</span><input class="input" name="summary" maxlength="300" value="<?= esc(old('summary') ?? ($n['summary'] ?? ''), 'attr') ?>" placeholder="Una o dos frases que se ven en la tarjeta y en el aviso"></label>
        <label class="field"><span class="label">Contenido <span class="text-danger">*</span></span><textarea class="textarea" name="body" rows="10" required maxlength="20000" placeholder="Escribe la noticia. Deja una línea en blanco entre párrafos."><?= esc(old('body') ?? ($n['body'] ?? '')) ?></textarea></label>
        <label class="field"><span class="label">Imagen de portada</span><input class="input" type="file" name="cover" accept="image/jpeg,image/png,image/webp">
          <span class="hint">JPG, PNG o WebP de hasta 3 MB<?= ! empty($n['cover_path']) ? '. Si eliges otra, reemplaza la actual' : '' ?>.</span></label>
        <fieldset class="field"><legend class="label">Para quién</legend>
          <div class="row" style="flex-wrap:wrap;gap:0.75rem">
            <?php foreach ($audiences as $k => $label): ?>
              <label class="row text-sm" style="gap:0.375rem"><input type="checkbox" name="audience[]" value="<?= $k ?>" <?= in_array($k, $aud, true) ? 'checked' : '' ?>> <?= esc($label) ?></label>
            <?php endforeach ?>
          </div>
          <span class="hint">Solo ellos la verán y recibirán el aviso al publicarla.</span></fieldset>
        <label class="row text-sm" style="gap:0.5rem"><input type="checkbox" name="pinned" value="1" <?= ! empty($n['pinned']) ? 'checked' : '' ?>> Destacar (sale primero)</label>
        <label class="field" style="gap:0.25rem"><span class="row text-sm" style="gap:0.5rem"><input type="checkbox" name="is_public" value="1" <?= ! empty($n['is_public']) ? 'checked' : '' ?>> <?= esc(t('Site.publicToggle')) ?></span>
          <span class="hint"><?= esc(t('Site.publicHint', ['url' => preg_replace('#^https?://#', '', site_url(service('tenant')->institution()['slug'] ?? ''))])) ?></span></label>
      </div>
      <div class="drawer-foot">
        <button type="button" class="btn" @click="f = false">Cancelar</button>
        <button type="submit" name="action" value="draft" class="btn ml-auto" data-loading="Guardando…"><?= ! $n || $n['status'] === 'draft' ? 'Guardar borrador' : 'Guardar cambios' ?></button>
        <?php if (! $n || $n['status'] === 'draft'): ?><button type="submit" name="action" value="publish" class="btn btn-primary" data-loading="Publicando…"><?= icon('send', 14) ?> Publicar</button><?php endif ?>
      </div>
    </form>
  </aside>
</div>
