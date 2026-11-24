<?php
/**
 * Diálogo «Agregar desde un banco» del editor de evaluaciones (Alpine quizEditor: pick, loadBank, addPicked).
 * Las preguntas se copian a la evaluación; cambiar el banco después no la modifica.
 *
 * @var array $pickerBanks bancos con preguntas
 */
$types = \App\Libraries\Quizzes::TYPES;
$diffs = \App\Libraries\Quizzes::DIFFICULTIES;
?>
<div class="modal-layer" x-show="pick.open" x-cloak @keydown.escape.window="pick.open = false"
     x-data="{ types: <?= esc(json_encode($types), 'attr') ?>, diffs: <?= esc(json_encode($diffs), 'attr') ?> }">
  <div class="veil" @click="pick.open = false"></div>
  <div class="modal xl" role="dialog" aria-modal="true" aria-labelledby="pk-title">
    <div class="modal-head"><h2 id="pk-title">Agregar desde un banco</h2><button type="button" class="btn btn-ghost btn-sm btn-icon" @click="pick.open = false" aria-label="Cerrar">×</button></div>
    <div class="modal-body stack">
      <div class="grid-3" style="gap:0.75rem">
        <label class="field"><span class="label">Banco</span>
          <select class="select" x-model="pick.bank" @change="pick.topic = ''; loadBank()">
            <option value="">Elija un banco…</option>
            <?php foreach ($pickerBanks as $b): ?>
              <option value="<?= (int) $b['id'] ?>"><?= esc($b['name'] . ($b['subject'] ? ' · ' . $b['subject'] : '') . ' (' . $b['questions'] . ')') ?></option>
            <?php endforeach ?>
          </select></label>
        <label class="field"><span class="label">Tema</span>
          <select class="select" x-model="pick.topic" @change="loadBank()" :disabled="!pick.bank">
            <option value="">Todos</option>
            <option value="-">Sin tema</option>
            <template x-for="t in pick.topics" :key="t"><option :value="t" x-text="t"></option></template>
          </select></label>
        <label class="field"><span class="label">Dificultad</span>
          <select class="select" x-model="pick.difficulty" @change="loadBank()" :disabled="!pick.bank">
            <option value="">Todas</option>
            <?php foreach ($diffs as $dk => $dl): ?><option value="<?= $dk ?>"><?= esc($dl) ?></option><?php endforeach ?>
          </select></label>
      </div>
      <p class="hint" x-show="!pick.bank">Las preguntas se copian a esta evaluación: si después cambia el banco, la evaluación no cambia.</p>
      <p class="hint" x-show="pick.loading" aria-live="polite">Cargando preguntas…</p>
      <p class="hint" x-show="pick.bank && !pick.loading && !pick.items.length">No hay preguntas con ese tema y dificultad.</p>
      <div class="pick-list" x-show="pick.items.length">
        <template x-for="q in pick.items" :key="q.id">
          <label class="pick-row">
            <input type="checkbox" :checked="pick.chosen.includes(q.id)" @change="togglePick(q.id, $event.target.checked)">
            <span style="min-width:0;flex:1"><span class="block" x-text="q.text"></span>
              <span class="block text-xs soft" x-text="[types[q.type], q.topic || 'Sin tema', diffs[q.difficulty], String(q.points).replace('.', ',') + ' pts'].join(' · ')"></span></span>
          </label>
        </template>
      </div>
    </div>
    <div class="modal-foot">
      <button type="button" class="btn btn-sm" x-show="pick.items.length" style="margin-right:auto"
              @click="pick.chosen = pick.chosen.length === pick.items.length ? [] : pick.items.map((q) => q.id)"
              x-text="pick.chosen.length === pick.items.length ? 'Quitar selección' : 'Seleccionar todas'"></button>
      <button type="button" class="btn" @click="pick.open = false">Cancelar</button>
      <button type="button" class="btn btn-primary" :disabled="!pick.chosen.length" @click="addPicked()" x-text="pick.chosen.length ? 'Agregar ' + pick.chosen.length : 'Agregar'"></button>
    </div>
  </div>
</div>
