<?php
/**
 * Tarjetas de preguntas editables (Alpine quizEditor), compartidas por el editor de evaluaciones y el de bancos.
 * $withDifficulty = true muestra el selector de dificultad (bancos). Requiere $types (Quizzes::TYPES).
 */
$types ??= \App\Libraries\Quizzes::TYPES;
?>
      <template x-for="(q, i) in questions" :key="i">
        <article class="card q-card">
          <div class="card-header">
            <div class="row">
              <span class="step-num" aria-hidden="true" x-text="i + 1"></span>
              <span class="ai-tag" x-show="q.ai" title="Propuesta por la IA: revísela antes de guardar"><?= icon('sparkles', 12) ?> IA</span>
              <select class="select" x-model="q.type" @change="retype(q)" :aria-label="'Tipo de la pregunta ' + (i + 1)" style="width:auto">
                <?php foreach ($types as $k => $l): ?><option value="<?= $k ?>"><?= esc($l) ?></option><?php endforeach ?>
              </select>
              <input class="input" x-model="q.topic" maxlength="120" placeholder="Tema (opcional)" style="width:12rem" :aria-label="'Tema de la pregunta ' + (i + 1)" list="q-topics">
              <?php if (! empty($withDifficulty)): ?>
                <select class="select" x-model="q.difficulty" :aria-label="'Dificultad de la pregunta ' + (i + 1)" style="width:auto">
                  <?php foreach (\App\Libraries\Quizzes::DIFFICULTIES as $dk => $dl): ?><option value="<?= $dk ?>"><?= esc($dl) ?></option><?php endforeach ?>
                </select>
              <?php endif ?>
            </div>
            <div class="row">
              <label class="row text-xs soft"><input class="input q-points" type="number" min="0.5" step="0.5" x-model.number="q.points" :aria-label="'Puntos de la pregunta ' + (i + 1)"> pts</label>
              <button type="button" class="btn btn-sm btn-icon" :disabled="i === 0" @click="move(i, -1)" :title="'Subir la pregunta ' + (i + 1)" :aria-label="'Subir la pregunta ' + (i + 1)"><?= icon('arrow-up', 14) ?></button>
              <button type="button" class="btn btn-sm btn-icon" @click="questions.splice(i + 1, 0, JSON.parse(JSON.stringify(q))); dirty = true" :title="'Duplicar la pregunta ' + (i + 1)" :aria-label="'Duplicar la pregunta ' + (i + 1)"><?= icon('copy', 14) ?></button>
              <button type="button" class="btn btn-sm btn-icon btn-danger" @click="questions.splice(i, 1); dirty = true" :title="'Eliminar la pregunta ' + (i + 1)" :aria-label="'Eliminar la pregunta ' + (i + 1)"><?= icon('trash-2', 14) ?></button>
            </div>
          </div>
          <div class="card-body stack-sm">
            <textarea class="textarea" rows="2" x-model="q.text" :aria-label="'Enunciado de la pregunta ' + (i + 1)" placeholder="Escriba el enunciado…"></textarea>

            <template x-if="q.type === 'single' || q.type === 'multiple'">
              <div class="q-options">
                <template x-for="(op, j) in q.options" :key="j">
                  <div class="q-option" :class="{ 'is-correct': q.correct.includes(j) }">
                    <input :type="q.type === 'single' ? 'radio' : 'checkbox'" :name="'c' + i" :checked="q.correct.includes(j)" @change="mark(q, j, $event.target.checked)" :aria-label="'Marcar la opción ' + (j + 1) + ' como correcta'">
                    <input class="input" x-model="q.options[j]" :aria-label="'Opción ' + (j + 1)">
                    <button type="button" class="btn btn-sm btn-icon" @click="dropOption(q, j)" :title="'Quitar la opción ' + (j + 1)" :aria-label="'Quitar la opción ' + (j + 1)"><?= icon('x', 14) ?></button>
                  </div>
                </template>
                <button type="button" class="btn btn-sm" style="align-self:flex-start" @click="q.options.push(''); dirty = true"><?= icon('plus', 14) ?> Agregar opción</button>
                <p class="hint" x-text="q.type === 'single' ? 'Marque la opción correcta.' : 'Marque todas las correctas: la respuesta vale si coincide exactamente.'"></p>
              </div>
            </template>

            <template x-if="q.type === 'truefalse'">
              <div class="row">
                <label class="btn btn-sm" :class="{ 'btn-active': q.correct[0] === 0 }"><input type="radio" class="sr-only" :name="'tf' + i" @change="q.correct = [0]; dirty = true" :checked="q.correct[0] === 0"> Verdadero</label>
                <label class="btn btn-sm" :class="{ 'btn-active': q.correct[0] === 1 }"><input type="radio" class="sr-only" :name="'tf' + i" @change="q.correct = [1]; dirty = true" :checked="q.correct[0] === 1"> Falso</label>
                <span class="hint">Respuesta correcta</span>
              </div>
            </template>

            <template x-if="q.type === 'numeric'">
              <div class="grid-2" style="gap:0.75rem;max-width:24rem">
                <label class="field"><span class="label">Valor correcto</span><input class="input" x-model="q.correct.value" inputmode="decimal"></label>
                <label class="field"><span class="label">Tolerancia (±)</span><input class="input" x-model="q.correct.tolerance" inputmode="decimal"></label>
              </div>
            </template>

            <template x-if="q.type === 'short'">
              <label class="field"><span class="label">Respuestas aceptadas</span>
                <input class="input" :value="q.correct.join('; ')" @input="q.correct = $event.target.value.split(';').map(s => s.trim()).filter(Boolean)" placeholder="Ej.: 6; seis">
                <span class="hint">Separe con punto y coma. No distingue mayúsculas ni tildes.</span></label>
            </template>

            <template x-if="q.type === 'long'">
              <div class="notice"><?= icon('user-check', 16, 'soft') ?><span class="text-xs">Respuesta abierta: usted la califica al revisar. El intento queda «pendiente de revisión» hasta entonces.</span></div>
            </template>
          </div>
        </article>
      </template>

      <div class="row">
      <div class="blk-insert" @click.outside="addOpen = false" @keydown.escape.stop="addOpen = false">
        <button type="button" class="btn" @click="addOpen = !addOpen; $nextTick(() => window.lucide && lucide.createIcons())" :aria-expanded="addOpen" aria-haspopup="true"><?= icon('plus', 16) ?> Agregar pregunta</button>
        <div class="blk-menu" x-show="addOpen" x-cloak role="menu">
          <div class="menu-label">Tipo de pregunta</div>
          <?php foreach ($types as $k => $l): ?>
            <button type="button" class="menu-item" role="menuitem" @click="add('<?= $k ?>')"><?= esc($l) ?></button>
          <?php endforeach ?>
        </div>
      </div>
      <?php if (! empty($aiGen)): ?>
        <button type="button" class="btn" @click="gen.open = true"><?= icon('sparkles', 16) ?> Generar con IA</button>
      <?php endif ?>
      <?php if (isset($pickerBanks)): ?>
        <button type="button" class="btn" @click="openPicker()" <?= $pickerBanks ? '' : 'disabled title="La institución aún no tiene bancos con preguntas"' ?>><?= icon('library', 16) ?> Agregar desde un banco</button>
      <?php endif ?>
      </div>
