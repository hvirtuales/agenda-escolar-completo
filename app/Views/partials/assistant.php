<?php
/**
 * Asistente flotante de la interfaz (todas las pantallas con sesión). Se arrastra a cualquier lugar (recuerda la posición
 * en este navegador), se abre en un panel y se oculta; «Mostrar asistente» en el menú de la cuenta lo trae de vuelta.
 * Con el teclado: Alt + flechas lo mueven y Esc cierra el panel. Configuración: Consola › Configuración › Asistente.
 *
 * @var array $assistant HelpAssistant::settings() + ['image' => url]
 */
$a = $assistant;
$t = service('tenant');
$suggest = match (true) {
    $t->isStudent()             => ['¿Dónde veo mis tareas?', '¿Cómo le pregunto al tutor?', '¿Cómo cambio mi contraseña?'],
    $t->isGuardian()            => ['¿Dónde veo la agenda de mi hijo?', '¿Qué avisos recibo?', '¿Cómo cambio mi contraseña?'],
    $t->can('tenant.users.manage') => ['¿Cómo agrego una persona?', '¿Dónde veo cómo van los docentes?', '¿Cómo organizo los grupos?'],
    $t->can('content.edit')     => ['¿Cómo creo un reto?', '¿Cómo reviso entregas?', '¿Cómo publico una página?'],
    default                     => ['¿Cómo cambio mi contraseña?', '¿Dónde están los avisos?', '¿Cómo uso el buscador?'],
};
?>
<div class="assistant" x-data="helpAssistant(<?= esc(json_encode(['url' => site_url('asistente/preguntar'), 'name' => $a['name']]), 'attr') ?>)"
     x-show="!hidden" x-cloak :style="pos" :class="{ 'is-open': open, 'to-left': side === 'left', 'to-top': vside === 'top', 'is-dragging': dragging }"
     @keydown.escape.window="open = false" @assistant-toggle.window="toggleHidden()" @assistant-open.window="show()" @resize.window="clamp()">
  <button type="button" class="assistant-avatar" x-ref="avatar"
          @pointerdown="dragStart($event)" @click="click()" @keydown.alt.arrow-left.prevent="nudge(-24, 0)" @keydown.alt.arrow-right.prevent="nudge(24, 0)"
          @keydown.alt.arrow-up.prevent="nudge(0, -24)" @keydown.alt.arrow-down.prevent="nudge(0, 24)"
          :aria-expanded="open" aria-controls="assistant-panel" title="<?= esc(t('App.assistantTitle', [$a['name']]), 'attr') ?>"
          aria-label="<?= esc(t('App.assistantOpen', [$a['name']]), 'attr') ?>">
    <img src="<?= esc($a['image'], 'attr') ?>" alt="" draggable="false">
  </button>

  <section class="assistant-panel" id="assistant-panel" x-show="open" x-cloak role="dialog" aria-label="<?= esc($a['name'], 'attr') ?>" @click.outside="if (!dragging) open = false">
    <header class="assistant-head">
      <img src="<?= esc($a['image'], 'attr') ?>" alt="" class="assistant-head-img">
      <div style="min-width:0;flex:1"><h2 class="truncate"><?= esc($a['name']) ?></h2><p class="text-xs soft"><?= esc(t('App.assistantAsk')) ?></p></div>
      <button type="button" class="btn btn-sm btn-icon" @click="clear()" x-show="thread.length" title="<?= esc(t('App.assistantClear'), 'attr') ?>" aria-label="<?= esc(t('App.assistantClear'), 'attr') ?>"><?= icon('eraser', 14) ?></button>
      <button type="button" class="btn btn-sm btn-icon" @click="toggleHidden()" title="<?= esc(t('App.assistantHide'), 'attr') ?>" aria-label="<?= esc(t('App.assistantHideShort'), 'attr') ?>"><?= icon('eye-off', 14) ?></button>
      <button type="button" class="btn btn-ghost btn-sm btn-icon" @click="open = false" aria-label="<?= esc(t('App.close'), 'attr') ?>">×</button>
    </header>
    <div class="assistant-body" x-ref="body" aria-live="polite">
      <div class="ai-msg ai" x-show="!thread.length"><img src="<?= esc($a['image'], 'attr') ?>" alt="" class="assistant-mini">
        <div><p><?= esc(t('App.assistantHello', [$a['name']])) ?></p>
          <div class="ai-quick mt-2"><?php foreach ($suggest as $s): ?><button type="button" class="btn btn-sm" @click="ask(<?= esc(json_encode($s), 'attr') ?>)"><?= esc($s) ?></button><?php endforeach ?></div></div>
      </div>
      <template x-for="(m, i) in thread" :key="i">
        <div>
          <div class="ai-msg user" x-show="m.role === 'user'" x-text="m.text"></div>
          <div class="ai-msg ai" x-show="m.role === 'assistant'"><img src="<?= esc($a['image'], 'attr') ?>" alt="" class="assistant-mini">
            <div><p style="white-space:pre-wrap" x-text="m.text"></p>
              <span class="row mt-2" style="flex-wrap:wrap;gap:0.375rem">
                <a class="btn btn-sm" x-show="m.link" :href="m.link && m.link.url" x-text="m.link && m.link.text"></a>
                <a class="btn btn-sm" x-show="m.article" :href="m.article && m.article.url"><?= icon('book-open', 14) ?> <span x-text="m.article && m.article.text"></span></a>
              </span>
              <span class="ai-label" x-show="m.source" x-text="m.source === 'ai' ? (m.simulated ? 'Respuesta simulada (sin IA real)' : 'Respuesta generada por IA con base en la Ayuda') : 'Respuesta de la Ayuda de la plataforma'"></span></div>
          </div>
        </div>
      </template>
      <div class="ai-msg ai" x-show="sending"><img src="<?= esc($a['image'], 'attr') ?>" alt="" class="assistant-mini"><div><p class="soft"><?= esc(t('App.thinking')) ?></p></div></div>
    </div>
    <div class="assistant-foot">
      <label class="sr-only" for="assistant-q"><?= esc(t('App.yourQuestion')) ?></label>
      <textarea id="assistant-q" x-ref="q" class="textarea" rows="2" maxlength="600" x-model="text" placeholder="Escribe tu pregunta…"
                @keydown.enter.prevent="if (!$event.shiftKey) ask(); else text += '\n'" :disabled="sending"></textarea>
      <button type="button" class="btn btn-primary btn-sm btn-icon" @click="ask()" :disabled="sending || !text.trim()" title="<?= esc(t('App.sendQuestion'), 'attr') ?>" aria-label="<?= esc(t('App.sendQuestion'), 'attr') ?>"><?= icon('send', 14) ?></button>
    </div>
  </section>
</div>
