<?php
/**
 * Formulario de un evento de la institución (Drawer), dentro de «Mi agenda». Alpine: «evf» = datos del evento (null cerrado).
 * Lo usa quien gestiona los eventos (tenant.users.manage).
 *
 * @var array $categories Events::CATEGORIES, $audiences Events::AUDIENCES
 */
?>
<div class="drawer-layer" x-show="evf" x-cloak @keydown.escape.window="evf = null">
  <div class="veil" @click="evf = null"></div>
  <aside class="drawer wide" role="dialog" aria-modal="true" aria-labelledby="ev-form-title">
    <form method="post" :action="evf && evf.id ? '<?= site_url('eventos') ?>/' + evf.id : '<?= site_url('eventos') ?>'">
      <?= csrf_field() ?>
      <div class="drawer-head"><h2 id="ev-form-title" x-text="evf && evf.id ? 'Editar evento' : 'Nuevo evento'"></h2><button type="button" class="btn btn-ghost btn-sm btn-icon" @click="evf = null" aria-label="Cerrar">×</button></div>
      <div class="drawer-body stack" x-show="evf">
        <label class="field"><span class="label">Título <span class="text-danger">*</span></span><input class="input" name="title" required maxlength="160" :value="evf && evf.title" placeholder="Ej.: Reunión de padres de familia"></label>
        <div class="grid-2">
          <label class="field"><span class="label">Categoría</span>
            <select class="select" name="category"><?php foreach ($categories as $k => [$label]): ?><option value="<?= $k ?>" :selected="evf && evf.category === '<?= $k ?>'"><?= esc($label) ?></option><?php endforeach ?></select></label>
          <label class="field"><span class="label">Lugar</span><input class="input" name="location" maxlength="160" :value="evf && evf.location" placeholder="Ej.: Auditorio"></label>
          <label class="field"><span class="label">Fecha <span class="text-danger">*</span></span><input class="input" type="date" name="date" required :value="evf && evf.date"></label>
          <label class="field"><span class="label">Hasta (opcional)</span><input class="input" type="date" name="end_date" :value="evf && evf.end_date"><span class="hint">Para eventos de varios días.</span></label>
        </div>
        <label class="row text-sm" style="gap:0.5rem"><input type="checkbox" name="all_day" value="1" :checked="evf && evf.all_day" @change="evf.all_day = $event.target.checked"> Todo el día</label>
        <div class="grid-2" x-show="evf && !evf.all_day">
          <label class="field"><span class="label">Hora de inicio</span><input class="input" type="time" name="start_time" :value="evf && evf.start_time" :required="evf && !evf.all_day"></label>
          <label class="field"><span class="label">Hora de fin</span><input class="input" type="time" name="end_time" :value="evf && evf.end_time"></label>
        </div>
        <label class="field"><span class="label">Descripción</span><textarea class="textarea" name="description" rows="4" maxlength="2000" x-text="evf && evf.description" placeholder="Qué es, qué deben traer, a quién va dirigido…"></textarea></label>
        <fieldset class="field"><legend class="label">Para quién</legend>
          <div class="row" style="flex-wrap:wrap;gap:0.75rem">
            <?php foreach ($audiences as $k => $label): ?>
              <label class="row text-sm" style="gap:0.375rem"><input type="checkbox" name="audience[]" value="<?= $k ?>" :checked="evf && evf.audience.includes('<?= $k ?>')"> <?= esc($label) ?></label>
            <?php endforeach ?>
          </div>
          <span class="hint">Lo verán en su agenda; al crearlo reciben un aviso.</span></fieldset>
        <label class="field" style="gap:0.25rem"><span class="row text-sm" style="gap:0.5rem"><input type="checkbox" name="is_public" value="1" :checked="evf && evf.is_public"> <?= esc(t('Site.publicToggle')) ?></span>
          <span class="hint"><?= esc(t('Site.publicHint', ['url' => preg_replace('#^https?://#', '', site_url(service('tenant')->institution()['slug'] ?? ''))])) ?></span></label>
      </div>
      <div class="drawer-foot"><button type="button" class="btn" @click="evf = null">Cancelar</button><button type="submit" class="btn btn-primary" data-loading="Guardando…" x-text="evf && evf.id ? 'Guardar' : 'Crear evento'"></button></div>
    </form>
  </aside>
</div>
