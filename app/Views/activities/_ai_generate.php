<?php
/**
 * Diálogo «Generar con IA» (copiloto docente, documento maestro 5.1 A y 13.2). Alpine quizEditor: gen, generate().
 * Las preguntas se agregan al editor marcadas «IA» y no se guardan hasta que el docente las revisa y guarda.
 *
 * @var array $quota saldo de la persona
 */
$types = \App\Libraries\Quizzes::TYPES;
?>
<div class="modal-layer" x-show="gen.open" x-cloak @keydown.escape.window="gen.open = false">
  <div class="veil" @click="gen.loading || (gen.open = false)"></div>
  <div class="modal lg" role="dialog" aria-modal="true" aria-labelledby="gen-title">
    <div class="modal-head"><h2 id="gen-title">Generar preguntas con IA</h2><button type="button" class="btn btn-ghost btn-sm btn-icon" @click="gen.open = false" :disabled="gen.loading" aria-label="Cerrar">×</button></div>
    <div class="modal-body stack">
      <label class="field"><span class="label">Tema <span class="text-danger">*</span></span>
        <input class="input" x-model="gen.topic" maxlength="200" placeholder="Ej.: suma de fracciones con distinto denominador" @keydown.enter.prevent="generate()"></label>
      <div class="grid-2" style="gap:0.75rem">
        <label class="field"><span class="label">Cantidad</span><input class="input" type="number" min="1" max="10" x-model.number="gen.count"></label>
        <label class="field"><span class="label">Dificultad</span>
          <select class="select" x-model="gen.difficulty"><option value="">Mezclada</option><?php foreach (\App\Libraries\Quizzes::DIFFICULTIES as $dk => $dl): ?><option value="<?= $dk ?>"><?= esc($dl) ?></option><?php endforeach ?></select></label>
      </div>
      <fieldset class="field"><legend class="label">Tipos de pregunta</legend>
        <div class="chips" style="flex-wrap:wrap">
          <?php foreach ($types as $k => $l): ?>
            <button type="button" class="chip chip-toggle" :aria-pressed="gen.types.includes('<?= $k ?>').toString()" @click="gen.types = gen.types.includes('<?= $k ?>') ? gen.types.filter((t) => t !== '<?= $k ?>') : [...gen.types, '<?= $k ?>']"><?= esc($l) ?></button>
          <?php endforeach ?>
        </div></fieldset>
      <label class="field"><span class="label">Indicaciones (opcional)</span><textarea class="textarea" rows="2" maxlength="600" x-model="gen.notes" placeholder="Ej.: usa situaciones de cocina; evita números mayores que 100."></textarea></label>
      <div class="notice"><?= icon('sparkles', 16, 'soft') ?><span class="text-xs">Las preguntas llegan como propuesta marcada «IA»: revise que las respuestas sean correctas antes de guardar. Usa su cupo de IA.</span></div>
      <?= view('partials/ai_quota', ['quota' => $quota]) ?>
    </div>
    <div class="modal-foot">
      <button type="button" class="btn" @click="gen.open = false" :disabled="gen.loading">Cancelar</button>
      <button type="button" class="btn btn-primary" @click="generate()" :disabled="gen.loading || !gen.topic.trim() || !gen.types.length">
        <?= icon('sparkles', 16) ?> <span x-text="gen.loading ? 'Generando…' : 'Generar'"></span></button>
    </div>
  </div>
</div>
