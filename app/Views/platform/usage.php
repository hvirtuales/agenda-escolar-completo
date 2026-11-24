<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
/**
 * Consola de plataforma › IA: clave del proveedor, modelos y precios, y consumo del mes (ai_usage) por institución, modelo y función.
 *
 * @var array $u ConsoleController::usageData(), $ai estado del proveedor
 */
$t     = $u['totals'];
$tok   = static fn (int $n): string => number_format($n, 0, ',', '.');
$usd   = static fn (float $v): string => number_format($v, 2, ',', '.');
$labels = array_map(static fn ($f) => $f['label'], config('Ai')->features);
?>
<div class="page-header">
  <div><h1>Inteligencia artificial</h1><p>Consumo de <?= esc($u['month']) ?> por institución, modelo y función, y el proveedor que la plataforma usa.</p></div>
  <div class="actions"><a class="btn" href="<?= site_url('plataforma/consumo/exportar') ?>"><?= icon('download', 16) ?> Exportar CSV</a></div>
</div>

<div x-data="{ tab: 'consumo', pick() { this.tab = ['proveedor', 'proveedor-ia'].includes(location.hash.slice(1)) ? 'proveedor' : 'consumo'; } }" x-init="pick()" @hashchange.window="pick()">
<nav class="tabs" role="tablist" aria-label="Secciones de IA">
  <a class="tab" href="#consumo" role="tab" :aria-selected="(tab === 'consumo').toString()" @click.prevent="tab = 'consumo'; history.replaceState(null, '', '#consumo')"><?= icon('chart-column', 15) ?> Consumo del mes</a>
  <a class="tab" href="#proveedor" role="tab" :aria-selected="(tab === 'proveedor').toString()" @click.prevent="tab = 'proveedor'; history.replaceState(null, '', '#proveedor')"><?= icon('key-round', 15) ?> Proveedor y modelos <?= ! $ai['available'] ? badge('Sin configurar', 'danger') : '' ?></a>
</nav>

<?php $cfg = $ai['cfg']; ?>
<div class="grid-2 mb-4" style="align-items:start" x-show="tab === 'proveedor'" x-cloak role="tabpanel">
  <?php $k = $ai['key']; ?>
  <section class="card" id="proveedor-ia">
    <div class="card-header"><h2>Proveedor de IA</h2><?= ! $ai['available'] ? badge('Sin configurar', 'danger') : ($ai['simulated'] ? badge('Simulado (desarrollo)', 'warn') : badge('Conectado', 'ok')) ?></div>
    <div class="card-body stack-sm">
      <dl class="plan-limits">
        <div><dt>Proveedor</dt><dd>Claude (Anthropic)</dd></div>
        <div><dt>Clave de API</dt><dd><?= $k['source'] === 'console' ? 'Guardada en la consola · <code>' . esc($k['hint']) . '</code>'
            : ($k['source'] === 'env' ? 'Del archivo .env · <code>' . esc($k['hint']) . '</code>' : 'Falta') ?></dd></div>
        <?php if ($k['source'] === 'console' && $k['updatedAt']): ?><div><dt>Actualizada</dt><dd><?= esc($k['updatedAt']) ?></dd></div><?php endif ?>
        <div><dt>Versión de la API</dt><dd><?= esc($cfg->apiVersion) ?></dd></div>
      </dl>
      <?php if ($k['unreadable']): ?>
        <p class="hint" style="color:var(--danger)">Hay una clave guardada que no se puede descifrar (¿cambió <code>encryption.key</code>?). Vuelva a escribirla.</p>
      <?php endif ?>
    </div>
    <form class="card-body stack" style="padding-top:0" method="post" action="<?= site_url('plataforma/configuracion/ia/clave') ?>" autocomplete="off">
      <?= csrf_field() ?>
      <label class="field"><span class="label"><?= $k['source'] ? 'Reemplazar la clave' : 'Clave de API' ?></span>
        <input class="input" type="password" name="api_key" required maxlength="260" placeholder="sk-ant-…" autocomplete="off" spellcheck="false">
        <span class="hint">Se prueba con Anthropic antes de guardarla, se guarda cifrada y no se vuelve a mostrar (solo los últimos 4 caracteres). Tiene prioridad sobre la del <code>.env</code>.</span></label>
      <label class="field"><span class="label">Su contraseña</span>
        <input class="input" type="password" name="password" required autocomplete="current-password">
        <span class="hint">Para confirmar que es usted.</span></label>
      <div class="row" style="gap:0.5rem"><button type="submit" class="btn btn-primary btn-sm" data-loading="Verificando…"><?= icon('key-round', 14) ?> Probar y guardar</button></div>
    </form>
    <?php if ($k['source'] === 'console'): ?>
      <form class="card-body row" style="padding-top:0;gap:0.5rem;align-items:end" method="post" action="<?= site_url('plataforma/configuracion/ia/clave/quitar') ?>" x-data
            @submit="if (!confirm('¿Quitar la clave guardada en la consola?<?= $k['envPresent'] ? ' Se volverá a usar la del .env.' : ' La IA quedará sin configurar.' ?>')) $event.preventDefault()">
        <?= csrf_field() ?>
        <label class="field" style="flex:1"><span class="label">Contraseña para quitarla</span><input class="input" type="password" name="password" required autocomplete="current-password"></label>
        <button type="submit" class="btn btn-sm btn-danger"><?= icon('trash-2', 14) ?> Quitar la clave</button>
      </form>
    <?php endif ?>
  </section>
  <section class="card col-full">
    <div class="card-header"><h2>Modelos de IA y precios</h2><span class="text-xs soft">USD por millón de tokens · se usan para calcular el costo de cada llamada</span></div>
    <div class="table-wrap" style="border:0;border-radius:0">
      <table class="table">
        <thead><tr><th>Nivel</th><th>Modelo</th><th>Esfuerzo</th><th class="num">Entrada</th><th class="num">Salida</th><th>Se usa para</th></tr></thead>
        <tbody>
          <?php foreach ($cfg->models as $key => $m): $uses = array_column(array_filter($cfg->features, static fn ($f) => $f['tier'] === $key), 'label'); ?>
            <tr>
              <td class="font-medium"><?= esc($m['label']) ?></td><td><code><?= esc($m['id']) ?></code></td><td><?= esc($m['effort']) ?></td>
              <td class="num"><?= number_format($m['in'], 2, ',', '.') ?></td><td class="num"><?= number_format($m['out'], 2, ',', '.') ?></td>
              <td><?= esc(implode(', ', $uses) ?: '—') ?></td>
            </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>
    <div class="card-body" style="padding-top:0"><p class="hint">La plataforma usa siempre Claude Haiku 5.5; los niveles solo cambian el esfuerzo. Si una llamada supera 100 000 tokens de entrada, esa llamada se cobra a 0,50 / 2,50. El modelo, los esfuerzos y el límite por solicitud de cada función están en <code>app/Config/Ai.php</code>.</p></div>
  </section>
</div>

<div x-show="tab === 'consumo'" role="tabpanel">
<?php if (! $ai['available']): ?>
  <div class="notice notice-danger mb-4"><?= icon('circle-alert', 16) ?><span>La IA no está configurada: guarde la clave en la pestaña «Proveedor y modelos». No hay consumo nuevo hasta configurarla.</span></div>
<?php elseif ($ai['simulated']): ?>
  <div class="notice mb-4"><?= icon('flask-conical', 16, 'soft') ?><span class="text-xs">Desarrollo sin clave: las respuestas son simuladas y su costo es 0. Los tokens sí se miden.</span></div>
<?php endif ?>

<div class="grid-kpi">
  <div class="kpi"><div class="k-label">Tokens</div><div class="k-value"><?= $tok($t['tokens']) ?></div><div class="k-hint">Entrada y salida · ≈ <?= number_format(intdiv((int) $t['tokens'], config('Ai')->tokensPerQuery), 0, ',', '.') ?> consultas</div></div>
  <div class="kpi"><div class="k-label">Llamadas</div><div class="k-value"><?= $tok($t['calls']) ?></div><div class="k-hint"><?= $t['failed'] ? plural($t['failed'], 'fallida', 'fallidas') . ' sin consumo' : 'Ninguna fallida' ?></div></div>
  <div class="kpi"><div class="k-label">Costo</div><div class="k-value"><?= $usd($t['cost']) ?> <span class="text-sm soft">USD</span></div><div class="k-hint">Precio del proveedor por modelo</div></div>
  <div class="kpi"><div class="k-label">Instituciones con consumo</div><div class="k-value"><?= $t['active'] ?></div><div class="k-hint">De <?= $t['all'] ?> registradas</div></div>
</div>

<div class="grid-3 mt-4" style="align-items:start">
  <section class="card span-2">
    <div class="card-header"><h2>Por institución</h2></div>
    <div class="table-wrap" style="border:0;border-radius:0">
      <table class="table">
        <thead><tr><th>Institución</th><th>Plan</th><th class="num">Tokens</th><th class="num">Costo</th><th style="width:32%">Cupo del mes</th></tr></thead>
        <tbody>
          <?php foreach ($u['rows'] as $r): ?>
            <tr>
              <td class="font-medium"><?= esc($r['name']) ?><?= $r['status'] === 'suspended' ? ' ' . badge('Suspendida', 'danger') : '' ?></td>
              <td><?= esc($r['plan']) ?></td>
              <td class="num"><?= $tok($r['tokens']) ?><span class="meta"><?= plural($r['calls'], 'llamada', 'llamadas') ?></span></td>
              <td class="num"><?= $usd($r['cost']) ?></td>
              <td><?php if ($r['pct'] === null): ?><span class="meta">Plan a convenir</span><?php else: ?>
                <span class="share" role="img" aria-label="<?= $r['pct'] ?> %"><span style="width:<?= min(100, $r['pct']) ?>%<?= $r['pct'] >= 80 ? ';background:var(--app-warn)' : '' ?>"></span></span>
                <span class="meta"><?= $r['pct'] ?> % usado<?= $r['pct'] >= 80 ? ' · se avisó a su administración' : '' ?></span><?php endif ?></td>
            </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>
  </section>
  <section class="stack">
    <section class="card">
      <div class="card-header"><h2>Por modelo</h2></div>
      <div class="card-body stack">
        <?php if (! $u['models']): ?><p class="hint">Sin consumo este mes.</p><?php endif ?>
        <?php foreach ($u['models'] as $md): $sh = $t['tokens'] ? (int) round($md['tokens'] * 100 / $t['tokens']) : 0; ?>
          <div>
            <div class="row-between"><span class="font-medium"><?= esc($md['model']) ?></span><span class="text-xs soft"><?= $usd((float) $md['cost']) ?> USD</span></div>
            <span class="share" role="img" aria-label="<?= $sh ?> % de los tokens"><span style="width:<?= $sh ?>%"></span></span>
            <span class="hint"><?= $tok((int) $md['tokens']) ?> tokens · <?= plural((int) $md['calls'], 'llamada', 'llamadas') ?></span>
          </div>
        <?php endforeach ?>
      </div>
    </section>
    <section class="card">
      <div class="card-header"><h2>Por función</h2></div>
      <div class="card-body stack">
        <?php if (! $u['features']): ?><p class="hint">Sin consumo este mes.</p><?php endif ?>
        <?php foreach ($u['features'] as $fe): $sh = $t['tokens'] ? (int) round($fe['tokens'] * 100 / $t['tokens']) : 0; ?>
          <div>
            <div class="row-between"><span><?= esc($labels[$fe['feature']] ?? $fe['feature']) ?></span><span class="text-xs soft"><?= $sh ?> %</span></div>
            <span class="share" role="img" aria-label="<?= $sh ?> %"><span style="width:<?= $sh ?>%"></span></span>
          </div>
        <?php endforeach ?>
      </div>
    </section>
  </section>
</div>
</div>
</div>
<?= $this->endSection() ?>
