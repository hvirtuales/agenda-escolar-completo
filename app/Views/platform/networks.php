<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
/**
 * Consola de plataforma › Redes educativas: grupos de colegios con administradores de red y cupo común de IA opcional.
 *
 * @var array $rows redes con schools, members_list, ai_month; $schools todas las instituciones
 */
$tok  = static fn (int $n): string => $n >= 1000000 ? str_replace('.', ',', rtrim(rtrim(number_format($n / 1000000, 2, '.', ''), '0'), '.')) . ' M' : number_format($n, 0, ',', '.');
$edit = static fn (?array $n) => esc(json_encode($n ? ['id' => (int) $n['id'], 'name' => $n['name'], 'city' => (string) $n['city'], 'accent_color' => $n['accent_color'],
    'monthly_tokens' => (int) $n['monthly_tokens'], 'schools' => array_map(static fn ($s) => (int) $s['id'], $n['schools'])]
    : ['id' => null, 'name' => '', 'city' => '', 'accent_color' => '#1D4ED8', 'monthly_tokens' => 0, 'schools' => []]), 'attr');
?>
<div x-data="{ f: null, member: null }">
  <div class="page-header">
    <div><h1>Redes educativas</h1><p>Grupos de colegios. Cada colegio sigue aislado; la red agrupa, consolida reportes y comparte bancos.</p></div>
    <div class="actions"><button type="button" class="btn btn-primary" @click="f = <?= $edit(null) ?>"><?= icon('plus', 16) ?> Nueva red</button></div>
  </div>

  <?php if (! $rows): ?>
    <div class="empty"><?= icon('network', 32) ?><p>Aún no hay redes. Cree una para agrupar colegios que comparten administración.</p></div>
  <?php endif ?>

  <div class="stack">
    <?php foreach ($rows as $n): ?>
      <section class="card">
        <div class="card-header">
          <div class="row"><span class="brand-dot" style="background:<?= esc(brand_valid($n['accent_color']), 'attr') ?>" aria-hidden="true"></span><h2><?= esc($n['name']) ?></h2>
            <span class="text-xs soft"><?= esc(trim(($n['city'] ?? '') . ' · ' . plural(count($n['schools']), 'colegio', 'colegios'), ' ·')) ?></span></div>
          <div class="row">
            <form method="post" action="<?= site_url('red/entrar') ?>"><?= csrf_field() ?><input type="hidden" name="network_id" value="<?= (int) $n['id'] ?>">
              <button type="submit" class="btn btn-sm"><?= icon('log-in', 14) ?> Entrar a la red</button></form>
            <button type="button" class="btn btn-sm" @click="f = <?= $edit($n) ?>"><?= icon('pencil', 14) ?> Editar</button>
            <form method="post" action="<?= site_url('plataforma/redes/' . $n['id'] . '/eliminar') ?>" onsubmit="return confirm('¿Eliminar la red «<?= esc($n['name'], 'js') ?>»? Sus colegios quedan independientes, con todos sus datos.')">
              <?= csrf_field() ?><button type="submit" class="btn btn-sm btn-danger btn-icon" title="Eliminar la red <?= esc($n['name'], 'attr') ?>" aria-label="Eliminar la red <?= esc($n['name'], 'attr') ?>"><?= icon('trash-2', 14) ?></button></form>
          </div>
        </div>
        <div class="card-body grid-3" style="gap:1rem;align-items:start">
          <div>
            <div class="label">Colegios</div>
            <?php if (! $n['schools']): ?><p class="hint">Sin colegios.</p><?php endif ?>
            <ul class="plain-list"><?php foreach ($n['schools'] as $s): ?><li><?= esc($s['name']) ?><?= $s['status'] === 'suspended' ? ' ' . badge('Suspendido', 'danger') : '' ?></li><?php endforeach ?></ul>
          </div>
          <div>
            <div class="label">Cupo común de IA</div>
            <?php if ((int) $n['monthly_tokens']): $p = (int) round($n['ai_month'] * 100 / max(1, (int) $n['monthly_tokens'])); ?>
              <p class="text-sm"><?= $tok($n['ai_month']) ?> de <?= $tok((int) $n['monthly_tokens']) ?> este mes</p>
              <span class="share" role="img" aria-label="<?= $p ?> % del cupo común"><span style="width:<?= min(100, $p) ?>%"></span></span>
            <?php else: ?>
              <p class="text-sm">Sin cupo común · <?= $tok($n['ai_month']) ?> este mes</p><p class="hint">Cada colegio usa el cupo de su plan.</p>
            <?php endif ?>
          </div>
          <div>
            <div class="row-between"><span class="label">Administradores</span>
              <button type="button" class="btn btn-sm" @click="member = <?= (int) $n['id'] ?>"><?= icon('user-plus', 14) ?> Agregar</button></div>
            <?php if (! $n['members_list']): ?><p class="hint">Nadie administra esta red todavía.</p><?php endif ?>
            <ul class="plain-list">
              <?php foreach ($n['members_list'] as $m): ?>
                <li class="row-between"><span><span class="block"><?= esc($m['name']) ?></span><span class="block text-xs soft"><?= esc($m['email']) ?> · <?= $m['role'] === 'admin' ? 'Administrador' : 'Analista' ?></span></span>
                  <form method="post" action="<?= site_url('plataforma/redes/' . $n['id'] . '/miembros/' . $m['id'] . '/quitar') ?>" onsubmit="return confirm('¿Quitar a <?= esc($m['name'], 'js') ?> de la red?')">
                    <?= csrf_field() ?><button type="submit" class="btn btn-sm btn-danger btn-icon" title="Quitar a <?= esc($m['name'], 'attr') ?> de la red" aria-label="Quitar a <?= esc($m['name'], 'attr') ?> de la red"><?= icon('user-minus', 14) ?></button></form></li>
              <?php endforeach ?>
            </ul>
          </div>
        </div>
      </section>
    <?php endforeach ?>
  </div>

  <div class="drawer-layer" x-show="f" x-cloak @keydown.escape.window="f = null">
    <div class="veil" @click="f = null"></div>
    <aside class="drawer" role="dialog" aria-modal="true" aria-labelledby="net-title">
      <form method="post" :action="f && f.id ? '<?= site_url('plataforma/redes') ?>/' + f.id : '<?= site_url('plataforma/redes') ?>'">
        <?= csrf_field() ?>
        <div class="drawer-head"><h2 id="net-title" x-text="f && f.id ? 'Editar red' : 'Nueva red'"></h2><button type="button" class="btn btn-ghost btn-sm btn-icon" @click="f = null" aria-label="Cerrar">×</button></div>
        <div class="drawer-body stack">
          <label class="field"><span class="label">Nombre <span class="text-danger">*</span></span><input class="input" name="name" maxlength="160" required :value="f && f.name" placeholder="Ej.: Red Educativa Andina"></label>
          <div class="grid-2" style="gap:0.75rem">
            <label class="field"><span class="label">Ciudad</span><input class="input" name="city" maxlength="80" :value="f && f.city"></label>
            <label class="field"><span class="label">Color</span><input class="input" type="color" name="accent_color" :value="f && f.accent_color" style="height:2.25rem;padding:0.25rem"></label>
          </div>
          <label class="field"><span class="label">Cupo común de IA (tokens al mes)</span><input class="input" type="number" name="monthly_tokens" min="0" step="100000" :value="f && f.monthly_tokens">
            <span class="hint">0 = sin cupo común: cada colegio usa el de su plan. Con cupo, la IA se pausa en toda la red al agotarlo.</span></label>
          <fieldset class="field"><legend class="label">Colegios de la red</legend>
            <div class="stack-sm">
              <?php foreach ($schools as $s): ?>
                <label class="row text-sm"><input type="checkbox" name="schools[]" value="<?= (int) $s['id'] ?>" :checked="f && f.schools.includes(<?= (int) $s['id'] ?>)" style="width:1.125rem;height:1.125rem;accent-color:var(--app-accent)">
                  <span><?= esc($s['name']) ?></span>
                  <?php if ($s['network_id']): ?><span class="text-xs soft" x-show="f && !f.schools.includes(<?= (int) $s['id'] ?>)">(está en otra red: pasará a esta)</span><?php endif ?></label>
              <?php endforeach ?>
            </div></fieldset>
        </div>
        <div class="drawer-foot"><button type="button" class="btn" @click="f = null">Cancelar</button><button type="submit" class="btn btn-primary" data-loading="Guardando…">Guardar</button></div>
      </form>
    </aside>
  </div>

  <div class="modal-layer" x-show="member" x-cloak @keydown.escape.window="member = null">
    <div class="veil" @click="member = null"></div>
    <div class="modal lg" role="dialog" aria-modal="true" aria-labelledby="mem-title">
      <form method="post" :action="'<?= site_url('plataforma/redes') ?>/' + member + '/miembros'">
        <?= csrf_field() ?>
        <div class="modal-head"><h2 id="mem-title">Agregar administrador de red</h2><button type="button" class="btn btn-ghost btn-sm btn-icon" @click="member = null" aria-label="Cerrar">×</button></div>
        <div class="modal-body stack">
          <label class="field"><span class="label">Correo <span class="text-danger">*</span></span><input class="input" type="email" name="email" required maxlength="160"><span class="hint">Debe tener una cuenta activa.</span></label>
          <label class="field"><span class="label">Rol</span><select class="select" name="role"><option value="admin">Administrador: resumen y entrar a los colegios</option><option value="analyst">Analista: solo el resumen</option></select></label>
        </div>
        <div class="modal-foot"><button type="button" class="btn" @click="member = null">Cancelar</button><button type="submit" class="btn btn-primary">Agregar</button></div>
      </form>
    </div>
  </div>
</div>
<?= $this->endSection() ?>
