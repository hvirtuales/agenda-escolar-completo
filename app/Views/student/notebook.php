<?= $this->extend('layouts/app') ?>
<?= $this->section('bare') ?>1<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php
/**
 * Mis asignaturas: el cuaderno de cada asignatura, en solo lectura: mismas zonas que el del docente, solo con lo publicado, sin edición y con
 * el tutor en el modo que permita la página (aquí, explicación). Reutiliza el componente Alpine `notebook`.
 */
?>
<div class="nb" x-data="notebook({ openAi: false })" @keydown.escape.window="closeOverlays()">
  <div class="nb-veil" x-show="narrow && (paneA || paneB)" x-cloak @click="closeOverlays()"></div>

  <aside class="nb-pane nb-pane-a" x-show="paneA" x-cloak aria-label="Mis asignaturas">
    <div class="nb-pane-head">
      <div class="row-between"><h2>Mis asignaturas</h2><button type="button" class="btn btn-ghost btn-sm btn-icon" x-show="narrow" @click="paneA = false" aria-label="Cerrar panel de asignaturas">×</button></div>
    </div>
    <div class="nb-pane-body">
      <?php
      // Lista plana, en orden alfabético (con tildes y ñ): el estudiante tiene pocas asignaturas y no necesita el nivel de
      // categoría del cuaderno docente. La categoría queda solo como la línea de color a la izquierda de cada asignatura.
      // El catálogo ya viene filtrado al grupo del estudiante (o al de la vista previa).
      $items = \App\Libraries\Academic::flatten($catalog);
      $coll  = class_exists(\Collator::class) ? new \Collator('es') : null;
      usort($items, static fn ($a, $b) => $coll ? $coll->compare($a['subject'], $b['subject']) : strcasecmp($a['subject'], $b['subject']));
      foreach ($items as $it): ?>
        <a class="nb-item nb-item--line" style="--cat:<?= cat_color($it['color']) ?>" href="<?= site_url('estudiante/asignaturas/' . $it['slug']) ?>"
           title="<?= esc($it['subject'] . ' · ' . $it['category'], 'attr') ?>" <?= $it['slug'] === $notebook['slug'] ? 'aria-current="page"' : '' ?>>
          <span class="block truncate"><?= esc($it['subject']) ?></span>
        </a>
      <?php endforeach ?>
    </div>
  </aside>

  <aside class="nb-pane nb-pane-b" x-show="paneB" x-cloak aria-label="Secciones y páginas">
    <div class="nb-pane-head" style="--cat:<?= cat_color($notebook['color']) ?>">
      <div class="row-between">
        <div class="nb-cat-title" style="padding:0"><span class="cat-dot" aria-hidden="true"></span><?= esc($notebook['category']) ?></div>
        <button type="button" class="btn btn-ghost btn-sm btn-icon" x-show="narrow" @click="paneB = false" aria-label="Cerrar panel de páginas">×</button>
      </div>
      <h2 class="text-base"><?= esc($notebook['title']) ?></h2>
    </div>
    <div class="nb-pane-body">
      <?php foreach ($notebook['sections'] as $si => $sec): ?>
        <div class="nb-section">
          <button type="button" class="nb-section-btn" @click="toggleSection(<?= $si ?>)" :aria-expanded="sectionOpen(<?= $si ?>)" aria-controls="ssec-<?= $si ?>">
            <?= icon('chevron-right', 14, 'chev') ?><span class="truncate"><?= esc($sec['name']) ?></span>
          </button>
          <div class="nb-pages" id="ssec-<?= $si ?>" x-show="sectionOpen(<?= $si ?>)">
            <?php foreach ($sec['pages'] as $pg): $k = page_kind($pg['kind']);
                $key = (string) $pg['id'];
                $url = match ($pg['kind']) { 'challenge' => 'estudiante/retos/' . $key, 'quiz' => 'estudiante/evaluaciones/' . $key, default => 'estudiante/asignaturas/' . $notebook['slug'] . '/' . $key }; ?>
              <a class="nb-page" href="<?= site_url($url) ?>" <?= $key === $pageSlug ? 'aria-current="page"' : '' ?> title="<?= esc($k['label'], 'attr') ?>">
                <?= icon($k['icon'], 15) ?><span class="truncate"><?= esc($pg['title']) ?></span>
                <?php if ($pg['status'] === 'closed'): ?><span class="draft">Cerrada</span><?php endif ?>
              </a>
            <?php endforeach ?>
          </div>
        </div>
      <?php endforeach ?>
    </div>
  </aside>

  <section class="nb-canvas" aria-label="Página">
    <div class="nb-toolbar">
      <button type="button" class="btn btn-sm btn-icon" @click="togglePane('paneA')" :class="{ 'btn-active': paneA && !narrow }" :aria-pressed="paneA" title="Mostrar u ocultar asignaturas" aria-label="Mostrar u ocultar el panel de asignaturas"><?= icon('panel-left', 16) ?></button>
      <button type="button" class="btn btn-sm btn-icon" @click="togglePane('paneB')" :class="{ 'btn-active': paneB && !narrow }" :aria-pressed="paneB" title="Mostrar u ocultar páginas" aria-label="Mostrar u ocultar el panel de secciones y páginas"><?= icon('book-open', 16) ?></button>
      <?php if ($page): ?>
        <nav class="crumbs" aria-label="Ubicación">
          <span class="hide-mobile"><?= esc($page['section']) ?></span><span class="hide-mobile"><?= icon('chevron-right', 12) ?></span>
          <span class="here" aria-current="page"><?= esc($page['title_published'] ?? $page['title']) ?></span>
        </nav>
      <?php endif ?>
      <div class="row ml-auto">
        <button type="button" class="btn btn-sm" :class="{ 'btn-active': ai }" @click="toggleAi()" :aria-pressed="ai" aria-controls="tutor-pane"><?= icon('sparkles', 14) ?><span class="hide-mobile">Preguntar al tutor</span></button>
      </div>
    </div>
    <div class="nb-scroll">
      <?php if (! $page): ?>
        <div class="nb-doc"><div class="empty"><?= icon('notebook-tabs', 32) ?><p>Tu docente aún no ha publicado páginas en <?= esc($notebook['title']) ?>.</p></div></div>
      <?php else: ?>
        <article class="nb-doc">
          <h1 class="nb-title"><?= esc($page['title_published'] ?? $page['title']) ?></h1>
          <div class="nb-meta"><span>Publicada <?= esc(date_short($page['published_at'] ?? $page['updated_at'], false)) ?><?= isset($page['author']) ? ' por ' . esc($page['author']) : '' ?></span></div>
          <?php if (! empty($page['objective'])): ?>
            <div class="notice notice-accent nb-objective"><?= icon('flag', 16) ?><span><strong class="font-medium">Lo que vas a aprender:</strong> <?= esc($page['objective']) ?></span></div>
          <?php endif ?>
          <div class="blocks">
            <?php foreach ($page['blocks'] as $b): ?>
              <?= view('notebook/_rblock', ['b' => $b, 'edit' => false, 'acts' => $acts ?? [], 'files' => $files ?? []]) ?>
            <?php endforeach ?>
          </div>
        </article>
      <?php endif ?>
    </div>
  </section>

  <div class="ai-veil" x-show="ai && aiOverlay" x-cloak @click="toggleAi()"></div>
  <aside class="ai-pane" id="tutor-pane" x-show="ai" x-cloak aria-label="Tutor">
    <?php
    $quick = [
        ['message-circle-question', 'Explícamelo fácil', 'Explícame esta página con palabras más fáciles.'],
        ['list-checks', 'Dame un ejemplo', 'Dame un ejemplo parecido a lo que explica esta página.'],
        ['target', 'Quiero practicar', 'Propónme un ejercicio para practicar lo de esta página, sin darme la respuesta todavía.'],
        ['book-open', 'Resúmelo', 'Resúmeme esta página en pocas líneas.'],
    ];
    ?>
    <div class="contents" x-data="tutorChat(<?= esc(json_encode([
        'url'    => $page ? site_url('estudiante/paginas/' . $page['id'] . '/tutor') : '',
        'thread' => array_map(static fn ($m) => ['role' => $m['role'], 'text' => $m['content'], 'simulated' => (bool) $m['simulated']], $thread ?? []),
    ]), 'attr') ?>)">
      <div class="ai-head">
        <?= icon('sparkles', 16) ?><h2>Tutor</h2><span class="badge badge-accent">Modo explicación</span>
        <button type="button" class="btn btn-ghost btn-sm btn-icon ml-auto" @click="toggleAi()" aria-label="Cerrar el tutor">×</button>
      </div>
      <div class="ai-body" x-ref="body" aria-live="polite">
        <div class="ai-context">Puedo explicarte esta página con otras palabras, darte ejemplos y ejercicios para practicar. Solo uso lo que tu docente publicó en <strong><?= esc($notebook['title']) ?></strong>.</div>
        <?php if (! $page): ?>
          <p class="soft text-sm">Abre una página para preguntarle al tutor.</p>
        <?php elseif (empty($canTutor)): ?>
          <p class="soft text-sm"><?= ! empty($studentPreview) ? 'En la vista previa el tutor no responde, para no gastar el cupo de IA de la institución.' : 'Tu institución no tiene activado el tutor para tu rol.' ?></p>
        <?php else: ?>
          <div class="ai-quick" aria-label="Sugerencias" x-show="!thread.length">
            <?php foreach ($quick as [$ic, $label, $prompt]): ?>
              <button type="button" class="btn btn-sm" @click="text = <?= esc(json_encode($prompt), 'attr') ?>; send()"><?= icon($ic, 14) ?> <?= esc($label) ?></button>
            <?php endforeach ?>
          </div>
          <template x-for="(m, i) in thread" :key="i">
            <div>
              <div class="ai-msg user" x-show="m.role === 'user'" x-text="m.text"></div>
              <div class="ai-msg ai" x-show="m.role === 'assistant'"><?= icon('sparkles', 16) ?>
                <div><p style="white-space:pre-wrap" x-text="m.text"></p><?php if (! empty($labelAi)): ?><span class="ai-label" x-text="m.simulated ? 'Respuesta simulada (desarrollo, sin IA real)' : 'Respuesta generada por IA'"></span><?php endif ?></div>
              </div>
            </div>
          </template>
          <div class="ai-msg ai" x-show="sending"><?= icon('sparkles', 16) ?><div><p class="soft">Pensando…</p></div></div>
        <?php endif ?>
      </div>
      <?php if ($page && ! empty($canTutor)): ?>
        <div class="ai-foot">
          <?= view('partials/ai_quota', ['quota' => $quota, 'asQueries' => true]) ?>
          <div class="ai-input">
            <label class="sr-only" for="st-ask">Pregúntale al tutor</label>
            <textarea id="st-ask" class="textarea" rows="3" x-model="text" maxlength="800" placeholder="¿Qué no entendiste de esta página?"
                      @keydown.enter.prevent="if (!$event.shiftKey) send(); else text += '\n'" :disabled="sending" <?= ! empty($quota['blocked']) ? 'disabled' : '' ?>></textarea>
            <button type="button" class="btn btn-primary btn-sm btn-icon" @click="send()" :disabled="sending || !text.trim()" title="Enviar al tutor" aria-label="Enviar al tutor"><?= icon('send', 14) ?></button>
          </div>
        </div>
      <?php endif ?>
    </div>
  </aside>
</div>
<?= $this->endSection() ?>
