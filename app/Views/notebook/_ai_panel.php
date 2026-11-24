<?php
/**
 * Asistente IA del cuaderno (copiloto docente, documento maestro 5.1-A y 6.3). Conversa sobre la página abierta: su
 * título, objetivo y bloques son el contexto. Todo lo que propone es un borrador: el docente lo copia o lo adapta;
 * publicar sigue siendo una acción suya (5.5). Real: service('assistant')->copilot() por «ia/cuaderno/{página}».
 *
 * @var array $page, $notebook, $thread (role, content, simulated), $quota
 * @var bool  $labelAi
 */
$quick = [
    ['target', 'Proponer un reto', 'Propón un reto de aplicación sobre esta página, con objetivo, pasos y entregable.'],
    ['list-checks', 'Generar preguntas', 'Propón 5 preguntas de comprensión sobre esta página, con su respuesta correcta.'],
    ['spell-check', 'Revisar claridad', 'Revisa la claridad de esta página para mis estudiantes y sugiere mejoras concretas.'],
    ['table', 'Crear una rúbrica', 'Propón una rúbrica de 4 niveles para evaluar lo que se trabaja en esta página.'],
];
?>
<aside class="ai-pane" id="ai-pane" x-show="ai" x-cloak aria-label="Asistente IA">
  <div class="contents" x-data="tutorChat(<?= esc(json_encode([
      'url'    => site_url('ia/cuaderno/' . $page['id']),
      'thread' => array_map(static fn ($m) => ['role' => $m['role'], 'text' => $m['content'], 'simulated' => (bool) $m['simulated']], $thread),
  ]), 'attr') ?>)">
    <div class="ai-head">
      <?= icon('sparkles', 16) ?>
      <h2>Asistente IA</h2>
      <span class="badge badge-accent">Modo docente</span>
      <button type="button" class="btn btn-ghost btn-sm btn-icon ml-auto" @click="toggleAi()" aria-label="Cerrar el asistente">×</button>
    </div>

    <div class="ai-body" x-ref="body" aria-live="polite">
      <div class="ai-context">
        Trabajo con <strong><?= esc($page['title']) ?></strong> (<?= esc($page['section']) ?>) de
        <strong><?= esc($notebook['title']) ?></strong>, tal como está guardada. No veo datos de estudiantes, de otros grupos ni de otras instituciones.
      </div>

      <div class="ai-quick" aria-label="Sugerencias" x-show="!thread.length">
        <?php foreach ($quick as [$ic, $label, $prompt]): ?>
          <button type="button" class="btn btn-sm" @click="text = <?= esc(json_encode($prompt), 'attr') ?>; $nextTick(() => $refs.ask.focus())"><?= icon($ic, 14) ?> <?= esc($label) ?></button>
        <?php endforeach ?>
      </div>

      <template x-for="(m, i) in thread" :key="i">
        <div>
          <div class="ai-msg user" x-show="m.role === 'user'" x-text="m.text"></div>
          <div class="ai-msg ai" x-show="m.role === 'assistant'"><?= icon('sparkles', 16) ?>
            <div>
              <?php /* Markdown → HTML seguro (mdLite escapa el texto antes de dar formato). */ ?>
              <div class="ai-md" x-html="md(m.text)"></div>
              <?php if ($labelAi): ?><span class="ai-label" x-text="m.simulated ? 'Respuesta simulada (desarrollo, sin IA real)' : 'Respuesta generada por IA'"></span><?php endif ?>
              <div class="ai-draft-foot">
                <button type="button" class="btn btn-sm" @click="insertInPage(m.text)"
                        title="Insertar la respuesta en la página como bloques" aria-label="Insertar la respuesta en la página como bloques"><?= icon('list-plus', 14) ?> Insertar en la página</button>
                <button type="button" class="btn btn-sm" @click="copy(m.text)"
                        title="Copiar la respuesta con formato" aria-label="Copiar la respuesta con formato"><?= icon('copy', 14) ?> Copiar</button>
              </div>
            </div>
          </div>
        </div>
      </template>
      <div class="ai-msg ai" x-show="sending"><?= icon('sparkles', 16) ?><div><p class="soft">Pensando…</p></div></div>
    </div>

    <div class="ai-foot">
      <?= view('partials/ai_quota', ['quota' => $quota]) ?>
      <div class="ai-input">
        <label class="sr-only" for="ai-ask">Pídele algo al asistente</label>
        <textarea id="ai-ask" x-ref="ask" class="textarea" x-model="text" maxlength="1500" rows="3" placeholder="Pídele algo sobre esta página…"
                  @keydown.enter.prevent="if (!$event.shiftKey) send(); else text += '\n'" :disabled="sending" <?= ! empty($quota['blocked']) ? 'disabled' : '' ?>></textarea>
        <button type="button" class="btn btn-primary btn-sm btn-icon" @click="send()" :disabled="sending || !text.trim()" title="Enviar al asistente" aria-label="Enviar al asistente"><?= icon('send', 14) ?></button>
      </div>
      <p class="hint mt-2">La IA propone borradores; tú decides qué se publica.</p>
    </div>
  </div>
</aside>
