<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
/**
 * Consola de plataforma › Planes (datos reales, tabla plans). 0 = sin límite o a convenir. El cupo de IA rige al instante
 * (Ai\Quota lo lee en cada consulta).
 *
 * @var array $plans con institutions
 */
$edit = static fn (?array $p) => esc(json_encode($p ? ['id' => (int) $p['id'], 'name' => $p['name'], 'price' => $p['price'] === null ? '' : (string) (float) $p['price'], 'max_users' => (int) $p['max_users'],
    'storage_gb' => (int) $p['storage_gb'], 'monthly_tokens' => (int) $p['monthly_tokens'], 'advanced_model' => (bool) $p['advanced_model'], 'sort_order' => (int) $p['sort_order']]
    : ['id' => null, 'name' => '', 'price' => '', 'max_users' => 0, 'storage_gb' => 0, 'monthly_tokens' => 0, 'advanced_model' => false, 'sort_order' => count($plans) + 1]), 'attr');
?>
<div x-data="{ f: null }">
  <div class="page-header">
    <div><h1>Planes</h1><p>Lo que incluye cada plan. El cupo de IA rige desde la próxima consulta de cada institución.</p></div>
    <div class="actions"><button type="button" class="btn btn-primary" @click="f = <?= $edit(null) ?>"><?= icon('plus', 16) ?> Nuevo plan</button></div>
  </div>

  <div class="plan-grid">
    <?php foreach ($plans as $p): ?>
      <article class="card plan-card">
        <div class="card-body stack-sm">
          <div class="row-between"><h2 class="text-base font-semibold"><?= esc($p['name']) ?></h2><span class="badge"><?= esc(plural($p['institutions'], 'institución', 'instituciones')) ?></span></div>
          <p class="text-2xl font-semibold"><?= $p['price'] === null ? 'A convenir' : ((float) $p['price'] === 0.0 ? 'Gratis' : number_format((float) $p['price'], 0, ',', '.') . ' <span class="text-sm soft">USD/mes</span>') ?></p>
          <dl class="plan-limits">
            <div><dt>Usuarios</dt><dd><?= (int) $p['max_users'] ? number_format((int) $p['max_users'], 0, ',', '.') : 'Sin límite' ?></dd></div>
            <div><dt>Almacenamiento</dt><dd><?= (int) $p['storage_gb'] ? (int) $p['storage_gb'] . ' GB' : 'Sin límite' ?></dd></div>
            <div><dt>IA al mes</dt><dd><?= (int) $p['monthly_tokens'] ? number_format((int) $p['monthly_tokens'], 0, ',', '.') . '<span class="block text-xs soft">≈ ' . number_format(intdiv((int) $p['monthly_tokens'], config('Ai')->tokensPerQuery), 0, ',', '.') . ' consultas</span>' : 'A convenir' ?></dd></div>
          </dl>
        </div>
        <div class="drawer-foot">
          <?php if (! $p['institutions']): ?>
            <form method="post" action="<?= site_url('plataforma/planes/' . $p['id'] . '/eliminar') ?>" onsubmit="return confirm('¿Eliminar el plan «<?= esc($p['name'], 'js') ?>»?')" style="margin-right:auto">
              <?= csrf_field() ?><button type="submit" class="btn btn-sm btn-danger btn-icon" title="Eliminar el plan <?= esc($p['name'], 'attr') ?>" aria-label="Eliminar el plan <?= esc($p['name'], 'attr') ?>"><?= icon('trash-2', 14) ?></button></form>
          <?php endif ?>
          <button type="button" class="btn btn-sm" @click="f = <?= $edit($p) ?>"><?= icon('pencil-line', 14) ?> Editar plan</button>
        </div>
      </article>
    <?php endforeach ?>
  </div>

  <div class="drawer-layer" x-show="f" x-cloak @keydown.escape.window="f = null">
    <div class="veil" @click="f = null"></div>
    <aside class="drawer" role="dialog" aria-modal="true" aria-labelledby="plan-title">
      <form method="post" :action="f && f.id ? '<?= site_url('plataforma/planes') ?>/' + f.id : '<?= site_url('plataforma/planes') ?>'">
        <?= csrf_field() ?>
        <div class="drawer-head"><h2 id="plan-title" x-text="f && f.id ? 'Editar plan' : 'Nuevo plan'"></h2><button type="button" class="btn btn-ghost btn-sm btn-icon" @click="f = null" aria-label="Cerrar">×</button></div>
        <div class="drawer-body stack">
          <label class="field"><span class="label">Nombre <span class="text-danger">*</span></span><input class="input" name="name" maxlength="40" required :value="f && f.name"><span class="hint" x-show="f && f.id">Si lo cambia, las instituciones de este plan quedan con el nombre nuevo.</span></label>
          <div class="grid-2" style="gap:0.75rem">
            <label class="field"><span class="label">Precio (USD/mes)</span><input class="input" name="price" inputmode="decimal" :value="f && f.price" placeholder="Vacío = a convenir"></label>
            <label class="field"><span class="label">Orden</span><input class="input" type="number" name="sort_order" :value="f && f.sort_order"></label>
            <label class="field"><span class="label">Usuarios máximos</span><input class="input" type="number" min="0" name="max_users" :value="f && f.max_users"><span class="hint">0 = sin límite</span></label>
            <label class="field"><span class="label">Almacenamiento (GB)</span><input class="input" type="number" min="0" name="storage_gb" :value="f && f.storage_gb"><span class="hint">0 = sin límite</span></label>
          </div>
          <label class="field"><span class="label">IA al mes</span><input class="input" type="number" min="0" step="100000" name="monthly_tokens" :value="f && f.monthly_tokens" @input="f && (f.monthly_tokens = +$event.target.value)"><span class="hint">0 = a convenir (sin tope de plan). Una consulta usa unos <?= number_format(config('Ai')->tokensPerQuery, 0, ',', '.') ?> tokens: <span x-text="f && f.monthly_tokens ? '≈ ' + Math.floor(f.monthly_tokens / <?= config('Ai')->tokensPerQuery ?>).toLocaleString('es-CO') + ' consultas al mes' : ''"></span></span></label>
        </div>
        <div class="drawer-foot"><button type="button" class="btn" @click="f = null">Cancelar</button><button type="submit" class="btn btn-primary" data-loading="Guardando…">Guardar</button></div>
      </form>
    </aside>
  </div>
</div>
<?= $this->endSection() ?>
