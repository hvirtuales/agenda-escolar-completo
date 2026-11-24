<?= $this->extend('layouts/app') ?>
<?= $this->section('bare') ?>1<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php
/**
 * Cuaderno del docente con contenido real (documento maestro 3.3 y 4.3). Guardado automático por JSON; publicar
 * copia la página para los estudiantes. Variables: $catalog, $notebook (con 'sections'), $page (o null), $thread, $quota.
 */
$color   = cat_color($notebook['color']);
$base    = site_url('cuadernos/' . $notebook['slug']);
$canPub  = in_array('content.publish', $perms, true);
$sections = $notebook['sections'];
// Tipos que ya se guardan de verdad, y los que llegan con otros módulos.
$ready = [['text', t('Notebook.bText'), 'pilcrow'], ['heading', t('Notebook.bHeading'), 'heading'], ['callout', t('Notebook.bCallout'), 'lightbulb'], ['formula', t('Notebook.bFormula'), 'sigma'],
    ['image', t('Notebook.bImage'), 'image'], ['file', t('Notebook.bFile'), 'paperclip'], ['video', t('Notebook.bVideo'), 'video'], ['table', t('Notebook.bTable'), 'table'],
    ['question', t('Notebook.bQuestion'), 'message-circle-question'], ['challenge', t('Notebook.bChallenge'), 'target'], ['quiz', t('Notebook.bQuiz'), 'clipboard-list'], ['divider', t('Notebook.bDivider'), 'minus']];
$soon  = []; // todos los bloques planeados ya están disponibles
$statusLabel = static fn (array $p): string => $p['status'] === 'published' ? ($p['dirty'] ? 'Publicada · con cambios sin publicar' : 'Publicada') : 'Borrador';
?>
<div class="nb" x-data="notebook({ openAi: <?= $openAi ? 'true' : 'false' ?>, saveUrl: '<?= $page ? esc($base . '/paginas/' . $page['id'] . '/guardar', 'js') : '' ?>', dirty: <?= $page && $page['dirty'] ? 'true' : 'false' ?> })"
     @keydown.escape.window="closeOverlays(); newPage = false; newSection = false"
     @ai-insert="insertBlocks($event.detail.blocks)" @paste="pastePlain($event)" @fmt-changed="edited()">

  <?php if ($page): /* Barra de formato flotante (partials/fmt_bar) */ ?><?= view('partials/fmt_bar') ?><?php endif ?>

  <div class="nb-veil" x-show="narrow && (paneA || paneB)" x-cloak @click="closeOverlays()"></div>

  <!-- Panel A: asignaturas (ofertas reales) -->
  <aside class="nb-pane nb-pane-a" x-show="paneA" x-cloak aria-label="Asignaturas">
    <div class="nb-pane-head">
      <div class="row-between">
        <h2>Asignaturas</h2>
        <button type="button" class="btn btn-ghost btn-sm btn-icon" x-show="narrow" @click="paneA = false" aria-label="Cerrar panel de asignaturas">×</button>
      </div>
    </div>
    <div class="nb-pane-body">
      <?php foreach ($catalog as $cat): ?>
        <div class="nb-cat" style="--cat:<?= cat_color($cat['color']) ?>">
          <div class="nb-cat-title"><span class="cat-dot" aria-hidden="true"></span><?= esc($cat['name']) ?></div>
          <?php foreach ($cat['items'] as $it): ?>
            <a class="nb-item" href="<?= site_url('cuadernos/' . $it['slug']) ?>" <?= $it['slug'] === $notebook['slug'] ? 'aria-current="page"' : '' ?>>
              <span style="min-width:0"><span class="block truncate"><?= esc($it['subject']) ?></span><span class="meta"><?= esc($it['group']) ?></span></span>
            </a>
          <?php endforeach ?>
        </div>
      <?php endforeach ?>
    </div>
  </aside>

  <!-- Panel B: secciones y páginas -->
  <aside class="nb-pane nb-pane-b" x-show="paneB" x-cloak aria-label="Secciones y páginas">
    <div class="nb-pane-head" style="--cat:<?= $color ?>">
      <div class="row-between">
        <div class="nb-cat-title" style="padding:0"><span class="cat-dot" aria-hidden="true"></span><?= esc($notebook['category']) ?></div>
        <button type="button" class="btn btn-ghost btn-sm btn-icon" x-show="narrow" @click="paneB = false" aria-label="Cerrar panel de páginas">×</button>
      </div>
      <h2 class="text-base"><?= esc($notebook['title']) ?></h2>
      <div class="row">
        <button type="button" class="btn btn-sm" style="flex:1" @click="newPage = true"><?= icon('plus', 14) ?> Nueva página</button>
        <button type="button" class="btn btn-sm btn-icon" @click="newSection = true" title="Nueva sección" aria-label="Nueva sección"><?= icon('folder-plus', 14) ?></button>
      </div>
    </div>
    <div class="nb-pane-body">
      <?php foreach ($sections as $si => $sec): ?>
        <div class="nb-section" x-data="{ rename: false, name: '<?= esc($sec['name'], 'js') ?>' }">
          <div class="row" style="gap:0.125rem">
            <button type="button" class="nb-section-btn" @click="toggleSection(<?= (int) $sec['id'] ?>)" :aria-expanded="sectionOpen(<?= (int) $sec['id'] ?>)" aria-controls="sec-<?= $si ?>" x-show="!rename">
              <?= icon('chevron-right', 14, 'chev') ?><span class="truncate"><?= esc($sec['name']) ?></span>
            </button>
            <form method="post" action="<?= $base ?>/secciones/<?= (int) $sec['id'] ?>" class="row" style="flex:1" x-show="rename" x-cloak>
              <?= csrf_field() ?>
              <input class="input" name="name" x-model="name" maxlength="120" style="height:2rem" aria-label="Nombre de la sección" x-ref="secName">
              <button type="submit" class="btn btn-sm btn-icon" title="Guardar nombre" aria-label="Guardar el nombre de la sección"><?= icon('check', 14) ?></button>
            </form>
            <div class="menu" x-data="dropdown" @click.outside="close()" @keydown.escape="close()" x-show="!rename">
              <button type="button" class="btn btn-sm btn-icon" @click="toggle()" :aria-expanded="open" aria-haspopup="true" title="Acciones de la sección <?= esc($sec['name'], 'attr') ?>" aria-label="Acciones de la sección <?= esc($sec['name'], 'attr') ?>"><?= icon('ellipsis', 14) ?></button>
              <div class="menu-panel" x-show="open" x-cloak>
                <button type="button" class="menu-item" @click="close(); rename = true; $nextTick(() => $refs.secName.focus())"><?= icon('pencil-line', 16) ?> Renombrar</button>
                <form method="post" action="<?= $base ?>/secciones/<?= (int) $sec['id'] ?>/eliminar">
                  <?= csrf_field() ?>
                  <button type="submit" class="menu-item text-danger" <?= $sec['pages'] ? 'disabled title="Tiene páginas"' : '' ?>><?= icon('trash-2', 16) ?> Eliminar sección</button>
                </form>
              </div>
            </div>
          </div>
          <div class="nb-pages" id="sec-<?= $si ?>" x-show="sectionOpen(<?= (int) $sec['id'] ?>)">
            <?php foreach ($sec['pages'] as $pg): $k = page_kind($pg['kind']); ?>
              <a class="nb-page" href="<?= $base . '/' . $pg['id'] ?>" <?= $page && (int) $pg['id'] === (int) $page['id'] ? 'aria-current="page"' : '' ?>>
                <?= icon($k['icon'], 15) ?>
                <span class="truncate"><?= esc($pg['title']) ?></span>
                <?php if ($pg['status'] !== 'published'): ?><span class="draft">Borrador</span><?php endif ?>
              </a>
            <?php endforeach ?>
            <?php if (! $sec['pages']): ?><p class="hint" style="padding:0.25rem 0.5rem">Sin páginas.</p><?php endif ?>
          </div>
        </div>
      <?php endforeach ?>
    </div>
  </aside>

  <!-- Lienzo -->
  <section class="nb-canvas" aria-label="Página">
    <div class="nb-toolbar">
      <button type="button" class="btn btn-sm btn-icon" @click="togglePane('paneA')" :class="{ 'btn-active': paneA && !narrow }" :aria-pressed="paneA" title="Mostrar u ocultar asignaturas" aria-label="Mostrar u ocultar el panel de asignaturas"><?= icon('panel-left', 16) ?></button>
      <button type="button" class="btn btn-sm btn-icon" @click="togglePane('paneB')" :class="{ 'btn-active': paneB && !narrow }" :aria-pressed="paneB" title="Mostrar u ocultar páginas" aria-label="Mostrar u ocultar el panel de secciones y páginas"><?= icon('book-open', 16) ?></button>
      <?php if ($page): ?>
        <nav class="crumbs" aria-label="Ubicación">
          <span class="hide-mobile"><?= esc($page['section']) ?></span><span class="hide-mobile"><?= icon('chevron-right', 12) ?></span>
          <span class="here" aria-current="page"><?= esc($page['title']) ?></span>
        </nav>
        <span class="save-state" role="status" aria-live="polite">
          <span class="dot" :class="saving === 'error' ? 'dot-danger' : (saving === 'saved' ? 'dot-ok' : 'dot-warn')" aria-hidden="true"></span>
          <span x-text="saveLabel()">Guardado</span>
        </span>
        <div class="row ml-auto">
          <a class="btn btn-sm" href="<?= site_url('estudiante/asignaturas/' . $notebook['slug']) . '?grupo=' . (int) ($notebook['group_id'] ?? 0) ?>" title="Ver este cuaderno como lo ve un estudiante del grupo"><?= icon('eye', 14) ?><span class="hide-mobile">Vista de estudiante</span></a>
          <form method="post" action="<?= site_url('biblioteca/plantillas') ?>"><?= csrf_field() ?><input type="hidden" name="page_id" value="<?= (int) $page['id'] ?>">
            <button type="submit" class="btn btn-sm btn-icon" :disabled="saving === 'saving'" title="Guardar esta página como plantilla de la biblioteca" aria-label="Guardar la página «<?= esc($page['title'], 'attr') ?>» como plantilla"><?= icon('layout-template', 14) ?></button></form>
          <button type="button" class="btn btn-sm" :class="{ 'btn-active': ai }" @click="toggleAi()" :aria-pressed="ai" aria-controls="ai-pane" title="Asistente IA en esta página"><?= icon('sparkles', 14) ?><span class="hide-mobile">Asistente IA</span></button>
          <?php if ($canPub): ?>
            <form method="post" action="<?= $base . '/paginas/' . $page['id'] . '/publicar' ?>" x-ref="publishForm">
              <?= csrf_field() ?>
              <button type="button" class="btn btn-primary btn-sm" :disabled="!dirty || saving === 'saving'" @click="publishNow()"><?= $page['status'] === 'published' ? 'Publicar cambios' : 'Publicar' ?></button>
            </form>
          <?php endif ?>
        </div>
      <?php else: ?>
        <span class="crumbs"><span class="here"><?= esc($notebook['title']) ?></span></span>
      <?php endif ?>
    </div>

    <div class="nb-scroll">
      <?php if (! $page): ?>
        <div class="nb-doc">
          <div class="empty">
            <?= icon('file-plus', 32) ?>
            <p class="font-medium" style="color:var(--app-ink)">Este cuaderno aún no tiene páginas.</p>
            <p class="mt-1">Cree la primera página de <?= esc($notebook['title']) ?>: una explicación, una guía o los recursos de la unidad.</p>
            <button type="button" class="btn btn-primary mt-4" @click="newPage = true"><?= icon('plus', 16) ?> Nueva página</button>
          </div>
        </div>
      <?php else: ?>
        <article class="nb-doc" x-ref="doc">
          <h1 class="nb-title" contenteditable="true" spellcheck="true" data-title data-placeholder="Título de la página" @input="edited()"><?= esc($page['title']) ?></h1>
          <div class="nb-meta">
            <?= $page['status'] === 'published' ? badge($statusLabel($page), $page['dirty'] ? 'warn' : 'ok') : badge('Borrador') ?>
            <span>Editada <?= esc(date_human($page['updated_at'])) ?><?= $page['editor'] ? ' por ' . esc($page['editor']) : '' ?></span>
            <span>· <?= $page['status'] === 'published' ? 'Visible para ' . esc($notebook['group']) : 'Solo la ve quien edita el cuaderno' ?></span>
          </div>
          <div class="notice notice-accent nb-objective">
            <?= icon('flag', 16) ?>
            <span style="flex:1"><strong class="font-medium">Objetivo de aprendizaje:</strong>
              <span contenteditable="true" spellcheck="true" data-objective data-placeholder="¿Qué aprenderá el estudiante con esta página?" @input="edited()"><?= esc($page['objective'] ?? '') ?></span></span>
          </div>

          <div class="blocks" x-ref="blocks">
            <?php foreach ($page['blocks'] as $b): ?>
              <?= view('notebook/_rblock', ['b' => $b, 'edit' => true, 'acts' => $acts ?? [], 'files' => $files ?? [], 'uploadUrl' => $uploadUrl ?? null]) ?>
            <?php endforeach ?>
          </div>

          <?php
          // Opciones del menú de bloques: se usan al final de la página y en el «+» de cada bloque (insertar debajo).
          $blockMenu = static function () use ($ready, $soon): string {
              ob_start(); ?>
              <div class="menu-label"><?= esc(t('Notebook.menuContent')) ?></div>
              <?php foreach ($ready as [$type, $label, $ic]): ?>
                <button type="button" class="menu-item" role="menuitem" @click="insert('<?= $type ?>')"><?= icon($ic, 16) ?> <?= esc($label) ?></button>
              <?php endforeach ?>
              <?php if ($soon): ?><div class="menu-label"><?= esc(t('Notebook.menuSoon')) ?></div><?php endif ?>
              <?php foreach ($soon as [$label, $ic]): ?>
                <button type="button" class="menu-item" role="menuitem" disabled style="opacity:.5;cursor:not-allowed" title="Disponible próximamente"><?= icon($ic, 16) ?> <?= esc($label) ?></button>
              <?php endforeach ?>
              <div class="menu-sep"></div>
              <button type="button" class="menu-item ai" role="menuitem" @click="insertOpen = false; closeFloat(); if (!ai) toggleAi()"><?= icon('sparkles', 16) ?> <?= esc(t('Notebook.menuAi')) ?></button>
              <?php return (string) ob_get_clean();
          }; ?>
          <div class="blk-insert" @click.outside="insertOpen = false" @keydown.escape.stop="insertOpen = false">
            <button type="button" class="btn btn-sm" @click="closeFloat(); insertOpen = !insertOpen; $nextTick(() => window.lucide && lucide.createIcons())" :aria-expanded="insertOpen" aria-haspopup="true"><?= icon('plus', 14) ?> Agregar bloque</button>
            <div class="blk-menu" x-show="insertOpen" x-cloak role="menu"><?= $blockMenu() ?></div>
          </div>
          <?php /* Menú flotante del «+» de un bloque: el bloque nuevo se inserta debajo de ese. */ ?>
          <div class="blk-menu blk-menu-float" x-ref="floatMenu" x-show="floatOpen" x-cloak role="menu" aria-label="Agregar un bloque aquí"
               :style="`left:${floatX}px;top:${floatY}px`" @click.outside="closeFloat()" @keydown.escape.stop="closeFloat()"><?= $blockMenu() ?></div>

          <div class="row-between mt-6">
            <span class="hint">Los cambios se guardan solos. Los estudiantes ven la última versión publicada.</span>
            <form method="post" action="<?= $base . '/paginas/' . $page['id'] . '/eliminar' ?>" onsubmit="return confirm('¿Eliminar esta página? Deja de estar disponible para los estudiantes.')">
              <?= csrf_field() ?>
              <button type="submit" class="btn btn-sm btn-danger"><?= icon('trash-2', 14) ?> Eliminar página</button>
            </form>
          </div>
        </article>

        <!-- Plantillas de bloques nuevos (se clonan al insertar) -->
        <?php foreach ($ready as [$type]): ?>
          <template id="tpl-<?= $type ?>"><?= view('notebook/_rblock', ['b' => ['type' => $type, 'data' => []], 'edit' => true, 'acts' => $acts ?? [], 'uploadUrl' => $uploadUrl ?? null]) ?></template>
        <?php endforeach ?>
      <?php endif ?>
    </div>
  </section>

  <!-- Asistente IA del cuaderno (copiloto docente) -->
  <div class="ai-veil" x-show="ai && aiOverlay" x-cloak @click="toggleAi()"></div>
  <?php if ($page): ?>
    <?= view('notebook/_ai_panel', ['page' => $page + ['section' => $page['section']], 'notebook' => $notebook, 'thread' => $thread, 'quota' => $quota, 'labelAi' => $labelAi]) ?>
  <?php endif ?>

  <!-- Nueva página -->
  <div class="modal-layer" x-show="newPage" x-cloak>
    <div class="veil" @click="newPage = false"></div>
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="np-title">
      <form method="post" action="<?= $base ?>/paginas">
        <?= csrf_field() ?>
        <div class="modal-head"><h2 id="np-title">Nueva página</h2><button type="button" class="btn btn-ghost btn-sm btn-icon" @click="newPage = false" aria-label="Cerrar">×</button></div>
        <div class="modal-body stack">
          <label class="field"><span class="label">Título <span class="text-danger">*</span></span><input class="input" name="title" maxlength="160" required placeholder="Ej.: Fracciones equivalentes"></label>
          <label class="field"><span class="label">Sección</span>
            <select class="select" name="section_id">
              <?php foreach ($sections as $sec): ?><option value="<?= (int) $sec['id'] ?>" <?= $page && (int) $page['section_id'] === (int) $sec['id'] ? 'selected' : '' ?>><?= esc($sec['name']) ?></option><?php endforeach ?>
            </select></label>
          <p class="hint">Se crea como borrador: los estudiantes no la ven hasta que la publique.</p>
        </div>
        <div class="modal-foot"><button type="button" class="btn" @click="newPage = false">Cancelar</button><button type="submit" class="btn btn-primary" data-loading="Creando…">Crear página</button></div>
      </form>
    </div>
  </div>

  <!-- Nueva sección -->
  <div class="modal-layer" x-show="newSection" x-cloak>
    <div class="veil" @click="newSection = false"></div>
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="ns-title">
      <form method="post" action="<?= $base ?>/secciones">
        <?= csrf_field() ?>
        <div class="modal-head"><h2 id="ns-title">Nueva sección</h2><button type="button" class="btn btn-ghost btn-sm btn-icon" @click="newSection = false" aria-label="Cerrar">×</button></div>
        <div class="modal-body"><label class="field"><span class="label">Nombre <span class="text-danger">*</span></span><input class="input" name="name" maxlength="120" required placeholder="Ej.: Unidad 3 · Proporciones"></label></div>
        <div class="modal-foot"><button type="button" class="btn" @click="newSection = false">Cancelar</button><button type="submit" class="btn btn-primary" data-loading="Creando…">Crear sección</button></div>
      </form>
    </div>
  </div>
</div>
<?= $this->endSection() ?>
