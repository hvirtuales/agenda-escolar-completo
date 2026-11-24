<?php
/**
 * Diálogo «Evaluación aleatoria»: crea una evaluación en borrador con N preguntas al azar del banco.
 * Se abre con rq = { id, name, n } en el x-data del contenedor. $topics opcional (página del banco).
 *
 * @var array $offerings ofertas donde la persona puede crear evaluaciones
 */
$topics ??= null;
?>
<div class="modal-layer" x-show="rq" x-cloak @keydown.escape.window="rq = null">
  <div class="veil" @click="rq = null"></div>
  <div class="modal lg" role="dialog" aria-modal="true" aria-labelledby="rq-title">
    <form method="post" :action="'<?= site_url('biblioteca/bancos') ?>/' + (rq ? rq.id : 0) + '/evaluacion'">
      <?= csrf_field() ?>
      <div class="modal-head"><h2 id="rq-title">Evaluación aleatoria</h2><button type="button" class="btn btn-ghost btn-sm btn-icon" @click="rq = null" aria-label="Cerrar">×</button></div>
      <div class="modal-body stack">
        <p class="text-sm">Del banco <strong class="font-medium" x-text="rq && rq.name"></strong> (<span x-text="rq && rq.n"></span> preguntas).</p>
        <label class="field"><span class="label">Asignatura y grupo <span class="text-danger">*</span></span>
          <select class="select" name="offering_id" required>
            <?php foreach ($offerings as $o): ?><option value="<?= (int) $o['id'] ?>"><?= esc($o['subject'] . ' · ' . $o['group']) ?></option><?php endforeach ?>
          </select></label>
        <label class="field"><span class="label">Título</span><input class="input" name="title" maxlength="160" :placeholder="rq ? 'Evaluación: ' + rq.name : ''"></label>
        <div class="grid-3" style="gap:0.75rem">
          <label class="field"><span class="label">Preguntas <span class="text-danger">*</span></span><input class="input" type="number" name="count" min="1" max="100" required :value="rq ? Math.min(10, rq.n) : 10"></label>
          <?php if ($topics !== null): ?>
            <label class="field"><span class="label">Tema</span>
              <select class="select" name="topic"><option value="">Todos</option><option value="-">Sin tema</option><?php foreach ($topics as $tp): ?><option value="<?= esc($tp, 'attr') ?>"><?= esc($tp) ?></option><?php endforeach ?></select></label>
          <?php endif ?>
          <label class="field"><span class="label">Dificultad</span>
            <select class="select" name="difficulty"><option value="">Todas</option><?php foreach (\App\Libraries\Quizzes::DIFFICULTIES as $dk => $dl): ?><option value="<?= $dk ?>"><?= esc($dl) ?></option><?php endforeach ?></select></label>
        </div>
        <p class="hint">Se crea como borrador con las preguntas copiadas: puede ajustarlas, cambiar tiempos e intentos y publicarla.</p>
      </div>
      <div class="modal-foot"><button type="button" class="btn" @click="rq = null">Cancelar</button><button type="submit" class="btn btn-primary" data-loading="Creando…"><?= icon('shuffle', 16) ?> Crear evaluación</button></div>
    </form>
  </div>
</div>
