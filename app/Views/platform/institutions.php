<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
/**
 * Consola de plataforma › Instituciones (datos reales). «Entrar» abre la institución como superadministrador (auditado).
 *
 * @var array $list, $filters, $plans (por nombre), $statuses, $alerts, $kpi
 */
$st      = ['active' => ['Activa', 'ok'], 'trial' => ['En prueba', 'accent'], 'suspended' => ['Suspendida', 'danger']];
$tok     = static fn (int $n): string => $n >= 1000000 ? str_replace('.', ',', rtrim(rtrim(number_format($n / 1000000, 2, '.', ''), '0'), '.')) . ' M' : number_format($n, 0, ',', '.');
$zones   = \App\Models\InstitutionModel::TIMEZONES;
$created = session('created');
$edit    = static fn (?array $i) => esc(json_encode($i ? ['id' => (int) $i['id'], 'name' => $i['name'], 'tax_id' => (string) $i['tax_id'], 'city' => (string) $i['city'], 'timezone' => $i['timezone'], 'plan' => $i['plan']]
    : ['id' => null, 'name' => '', 'tax_id' => '', 'city' => '', 'timezone' => 'America/Bogota', 'plan' => 'Prueba']), 'attr');
$filtered = $filters['q'] !== '' || $filters['estado'] !== '' || $filters['plan'] !== '';
?>
<div x-data="{ f: <?= ! empty($prefill) ? esc(json_encode($prefill), 'attr') : 'null' ?> }">
  <div class="page-header">
    <div><h1>Instituciones</h1><p>Alta, plan, consumo y estado de cada institución de la plataforma.</p></div>
    <div class="actions"><button type="button" class="btn btn-primary" @click="f = <?= $edit(null) ?>"><?= icon('plus', 16) ?> Nueva institución</button></div>
  </div>

  <?php if ($created): ?>
    <div class="notice notice-accent mb-4"><?= icon('circle-check', 16) ?>
      <span>Se creó <strong class="font-medium"><?= esc($created['name']) ?></strong> con su administrador <?= esc($created['email']) ?>.
        <?php if ($created['temp']): ?>Contraseña temporal (cópiela ahora: no se volverá a mostrar): <code class="font-medium"><?= esc($created['temp']) ?></code>. El envío de la invitación por correo llegará cuando se configure el correo saliente.
        <?php else: ?>La persona ya tenía cuenta: entra con su contraseña de siempre.<?php endif ?></span></div>
  <?php endif ?>

  <div class="grid-kpi">
    <div class="kpi"><div class="k-label">Instituciones activas</div><div class="k-value"><?= $kpi['active'] ?></div><div class="k-hint"><?= $kpi['trial'] ?> en prueba · <?= plural($kpi['suspended'], 'suspendida', 'suspendidas') ?></div></div>
    <div class="kpi"><div class="k-label">Usuarios</div><div class="k-value"><?= number_format($kpi['users'], 0, ',', '.') ?></div><div class="k-hint">Personas con membresía activa</div></div>
    <div class="kpi"><div class="k-label">Tokens de IA este mes</div><div class="k-value"><?= $tok((int) $kpi['tokens']) ?></div><div class="k-hint"><?= plural((int) $kpi['calls'], 'llamada', 'llamadas') ?></div></div>
    <div class="kpi"><div class="k-label">Costo de IA del mes</div><div class="k-value"><?= number_format((float) $kpi['cost'], 2, ',', '.') ?> <span class="text-sm soft">USD</span></div><div class="k-hint">Suma de todas las instituciones</div></div>
  </div>

  <?php if ($alerts): ?>
    <div class="alert-list mt-4">
      <?php foreach ($alerts as $a): ?>
        <a class="alert-row" href="<?= site_url('plataforma/consumo') ?>"><?= icon('triangle-alert', 18, 'text-warn') ?><span><?= esc($a) ?></span><span class="go">Revisar <?= icon('chevron-right', 14) ?></span></a>
      <?php endforeach ?>
    </div>
  <?php endif ?>

  <div class="panel mt-4">
    <form class="panel-bar" method="get" action="<?= site_url('plataforma/instituciones') ?>">
      <label class="input-icon"><span class="sr-only">Buscar institución</span><?= icon('search', 16) ?><input class="input" type="search" name="q" value="<?= esc($filters['q'], 'attr') ?>" placeholder="Buscar por nombre o ciudad"></label>
      <select class="select" name="estado" aria-label="Estado" onchange="this.form.submit()" style="width:auto"><option value="">Todos los estados</option><?php foreach ($statuses as $k => $l): ?><option value="<?= $k ?>" <?= $filters['estado'] === $k ? 'selected' : '' ?>><?= esc($l) ?></option><?php endforeach ?></select>
      <select class="select" name="plan" aria-label="Plan" onchange="this.form.submit()" style="width:auto"><option value="">Todos los planes</option><?php foreach ($plans as $pn => $p): ?><option <?= $filters['plan'] === $pn ? 'selected' : '' ?>><?= esc($pn) ?></option><?php endforeach ?></select>
      <?php if ($filtered): ?><a class="btn btn-ghost btn-sm" href="<?= site_url('plataforma/instituciones') ?>">Limpiar filtros</a><?php endif ?>
      <span class="panel-count"><?= plural(count($list), 'institución', 'instituciones') ?></span>
    </form>
    <?php if (! $list): ?>
      <div class="empty"><?= icon('school', 32) ?><p><?= $filtered ? 'Ninguna institución coincide con los filtros.' : 'Aún no hay instituciones.' ?></p></div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Institución</th><th>Plan</th><th class="num">Usuarios</th><th style="width:16%">IA del mes</th><th>Estado</th><th class="text-right">Acciones</th></tr></thead>
          <tbody>
            <?php foreach ($list as $i): [$lbl, $tone] = $st[$i['status']] ?? [$i['status'], 'neutral']; $sw = brand_valid($i['accent_color']); ?>
              <tr>
                <td><span class="row">
                  <span class="inst-tab" style="background:<?= $sw ?>" aria-hidden="true"></span>
                  <span><span class="font-medium"><?= esc($i['name']) ?></span><span class="meta"><?= esc(trim(($i['city'] ?? '') . ($i['network'] ? ' · Red: ' . $i['network'] : '') . ' · desde ' . date_short($i['created_at'], false), ' ·')) ?></span></span>
                </span></td>
                <td><?= esc($i['plan']) ?></td>
                <td class="num"><?= number_format((int) $i['users'], 0, ',', '.') ?><?= $i['max_users'] ? '<span class="meta">de ' . number_format($i['max_users'], 0, ',', '.') . '</span>' : '' ?></td>
                <td><?php if ($i['ai_pct'] === null): ?><span class="meta"><?= $tok($i['ai_used']) ?> · plan a convenir</span><?php else: ?>
                  <span class="share" role="img" aria-label="<?= $i['ai_pct'] ?> % del cupo"><span style="width:<?= min(100, $i['ai_pct']) ?>%<?= $i['ai_pct'] >= 80 ? ';background:var(--app-warn)' : '' ?>"></span></span>
                  <span class="meta"><?= $i['ai_pct'] ?> % · <?= $tok($i['ai_used']) ?> de <?= $tok($i['ai_limit']) ?></span><?php endif ?></td>
                <td><?= badge($lbl, $tone) ?></td>
                <td class="text-right nowrap">
                  <?php if ($i['status'] !== 'suspended'): ?>
                    <form method="post" action="<?= site_url('instituciones/cambiar') ?>" style="display:inline"><?= csrf_field() ?><input type="hidden" name="institution_id" value="<?= (int) $i['id'] ?>">
                      <button type="submit" class="btn btn-sm" title="Entrar a <?= esc($i['name'], 'attr') ?> como superadministrador"><?= icon('log-in', 14) ?> Entrar</button></form>
                  <?php endif ?>
                  <button type="button" class="btn btn-sm btn-icon" title="Editar <?= esc($i['name'], 'attr') ?>" aria-label="Editar <?= esc($i['name'], 'attr') ?>" @click="f = <?= $edit($i) ?>"><?= icon('pencil-line', 14) ?></button>
                  <form method="post" action="<?= site_url('plataforma/instituciones/' . $i['id'] . '/estado') ?>" style="display:inline"
                        onsubmit="return confirm('<?= $i['status'] === 'suspended' ? '¿Reactivar ' . esc($i['name'], 'js') . '?' : '¿Suspender ' . esc($i['name'], 'js') . '? Nadie podrá entrar hasta reactivarla; sus datos se conservan.' ?>')">
                    <?= csrf_field() ?>
                    <?php if ($i['status'] === 'suspended'): ?>
                      <input type="hidden" name="status" value="active"><button type="submit" class="btn btn-sm" title="Reactivar <?= esc($i['name'], 'attr') ?>"><?= icon('rotate-ccw', 14) ?> Reactivar</button>
                    <?php else: ?>
                      <input type="hidden" name="status" value="suspended"><button type="submit" class="btn btn-sm btn-icon btn-danger" title="Suspender <?= esc($i['name'], 'attr') ?>" aria-label="Suspender <?= esc($i['name'], 'attr') ?>"><?= icon('ban', 14) ?></button>
                    <?php endif ?>
                  </form>
                </td>
              </tr>
            <?php endforeach ?>
          </tbody>
        </table>
      </div>
    <?php endif ?>
  </div>

  <div class="drawer-layer" x-show="f" x-cloak @keydown.escape.window="f = null">
    <div class="veil" @click="f = null"></div>
    <aside class="drawer wide" role="dialog" aria-modal="true" aria-labelledby="inst-title">
      <form method="post" :action="f && f.id ? '<?= site_url('plataforma/instituciones') ?>/' + f.id : '<?= site_url('plataforma/instituciones') ?>'">
        <?= csrf_field() ?>
        <input type="hidden" name="request_id" :value="f && f.request_id ? f.request_id : ''">
        <div class="drawer-head"><h2 id="inst-title" x-text="f && f.id ? 'Editar institución' : 'Nueva institución'"></h2><button type="button" class="btn btn-ghost btn-sm btn-icon" @click="f = null" aria-label="Cerrar">×</button></div>
        <div class="drawer-body stack">
          <fieldset class="form-block"><legend>Identificación</legend>
            <div class="grid-2">
              <label class="field col-full"><span class="label">Nombre <span class="text-danger">*</span></span><input class="input" name="name" required maxlength="160" :value="f && f.name" placeholder="Ej.: Colegio Santa Ana"></label>
              <template x-if="f && !f.id"><div class="grid-2 col-full" style="gap:inherit">
              <label class="field"><span class="label">NIT</span><input class="input" name="tax_id" maxlength="40" :value="f && f.tax_id" placeholder="900123456-7"></label>
              <label class="field"><span class="label">Ciudad</span><input class="input" name="city" maxlength="80" :value="f && f.city" placeholder="Bogotá"></label>
              <label class="field col-full"><span class="label">Zona horaria</span>
                <select class="select" name="timezone"><?php foreach ($zones as $z => $zl): ?><option value="<?= $z ?>" :selected="f && f.timezone === '<?= $z ?>'"><?= esc($zl) ?></option><?php endforeach ?></select></label>
              </div></template>
              <p class="hint col-full" x-show="f && f.id">NIT, ciudad, zona horaria, contacto y color los edita el propio colegio en Ajustes › Institución.</p>
            </div></fieldset>
          <fieldset class="form-block"><legend>Plan</legend>
            <label class="field"><span class="label">Plan <span class="text-danger">*</span></span>
              <select class="select" name="plan"><?php foreach ($plans as $pn => $p): ?><option value="<?= esc($pn, 'attr') ?>" :selected="f && f.plan === <?= esc(json_encode($pn), 'attr') ?>"><?= esc($pn . ' · ' . ($p['monthly_tokens'] ? number_format((int) $p['monthly_tokens'], 0, ',', '.') . ' tokens/mes' : 'IA a convenir')) ?></option><?php endforeach ?></select>
              <span class="hint">El plan fija el cupo mensual de IA, el máximo de usuarios y el almacenamiento.</span></label></fieldset>
          <fieldset class="form-block" x-show="f && !f.id"><legend>Administrador de la institución</legend>
            <p class="hint">Si ya tiene cuenta en la plataforma, se le da acceso; si no, se crea con una contraseña temporal.</p>
            <div class="grid-2">
              <label class="field"><span class="label">Nombre <span class="text-danger">*</span></span><input class="input" name="admin_name" maxlength="120" :required="f && !f.id" :value="f && f.admin_name || ''"></label>
              <label class="field"><span class="label">Correo <span class="text-danger">*</span></span><input class="input" type="email" name="admin_email" maxlength="160" :required="f && !f.id" :value="f && f.admin_email || ''"></label>
            </div></fieldset>
        </div>
        <div class="drawer-foot"><button type="button" class="btn" @click="f = null">Cancelar</button><button type="submit" class="btn btn-primary" data-loading="Guardando…" x-text="f && f.id ? 'Guardar' : 'Crear institución'"></button></div>
      </form>
    </aside>
  </div>
</div>
<?= $this->endSection() ?>
