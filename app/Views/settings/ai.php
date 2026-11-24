<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
/**
 * Ajustes › Inteligencia artificial (documento maestro 5.2 y 5.8), con datos reales: consumo del mes, reparto del cupo
 * por rol, tope diario por persona, interruptores, consumo por función, personas con mayor consumo y cupos adicionales.
 *
 * @var array $r Quota::report(), $members personas que pueden recibir cupo, $ai estado del proveedor
 */
$s      = $r['settings'];
$fmt    = static fn (int $n): string => $n >= 1000000 ? str_replace('.', ',', rtrim(rtrim(number_format($n / 1000000, 2, '.', ''), '0'), '.')) . ' M' : number_format($n, 0, ',', '.');
$pctUse = $r['limit'] ? (int) round($r['used'] * 100 / $r['limit']) : null;
$months = ['', 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
$renew  = $r['renews']->format('j') . ' ' . $months[(int) $r['renews']->format('n')];
$qTok   = config('Ai')->studentQueryTokens;
$check  = static fn (string $name, bool $on, string $title, string $desc) => '<label class="toggle"><span><span class="block t-title">' . esc($title) . '</span><span class="block t-desc">' . esc($desc) . '</span></span>'
    . '<input type="checkbox" name="' . $name . '" value="1" ' . ($on ? 'checked' : '') . ' style="width:1.25rem;height:1.25rem;accent-color:var(--app-accent)"></label>';
?>
<div x-data="{ shares: <?= esc(json_encode(array_column($r['buckets'], 'share')), 'attr') ?>, monthly: <?= (int) $r['limit'] ?>, grant: false, target: '',
     total() { return this.shares.reduce((a, b) => a + Number(b || 0), 0); },
     tokens(i) { return Math.round(this.monthly * Number(this.shares[i] || 0) / 100).toLocaleString('es-CO'); } }">

  <div class="page-header">
    <div><h1>Ajustes</h1><p><?= esc($institution['name']) ?> · Plan <?= esc($r['plan']['name']) ?></p></div>
    <div class="actions"><button type="submit" form="ai-settings" class="btn btn-primary" :disabled="total() !== 100" data-loading="Guardando…">Guardar cambios</button></div>
  </div>

  <?= view('settings/_tabs', ['section' => 'ia']) ?>

  <?php if (! $ai['available']): ?>
    <div class="notice notice-danger mb-4"><?= icon('circle-alert', 16) ?><span>La IA no está configurada en la plataforma (falta la clave del proveedor). Nadie puede usarla hasta que el superadministrador la configure.</span></div>
  <?php elseif ($ai['simulated']): ?>
    <div class="notice mb-4"><?= icon('flask-conical', 16, 'soft') ?><span class="text-xs">Entorno de desarrollo sin clave del proveedor: las respuestas son <strong class="font-medium">simuladas</strong>, pero cupos, reservas y consumo funcionan igual que en producción.</span></div>
  <?php endif ?>
  <?php foreach ($r['alerts'] as $al): ?>
    <div class="notice notice-warn mb-2"><?= icon('triangle-alert', 16) ?><span><?= esc($al) ?></span></div>
  <?php endforeach ?>

  <div class="grid-kpi">
    <div class="kpi">
      <div class="k-label">Tokens usados este mes</div>
      <div class="k-value"><?= $fmt($r['used']) ?> <span class="text-sm soft">de <?= $r['limit'] ? $fmt($r['limit']) : 'sin tope' ?></span></div>
      <?php if ($pctUse !== null): ?><span class="share" role="img" aria-label="<?= $pctUse ?> % del cupo mensual"><span style="width:<?= min(100, $pctUse) ?>%"></span></span><?php endif ?>
      <div class="k-hint"><?= $pctUse !== null ? $pctUse . ' % del cupo del plan' : 'Plan a convenir' ?></div>
    </div>
    <div class="kpi">
      <div class="k-label">Costo estimado del mes</div>
      <div class="k-value"><?= number_format($r['cost'], 2, ',', '.') ?> <span class="text-sm soft">USD</span></div>
      <div class="k-hint">Según el precio del modelo en cada llamada</div>
    </div>
    <div class="kpi">
      <div class="k-label">Proyección al cierre</div>
      <div class="k-value <?= ($r['projection'] ?? 0) >= 100 ? 'text-danger' : (($r['projection'] ?? 0) >= 80 ? 'text-warn' : '') ?>"><?= $r['projection'] === null ? '—' : $r['projection'] . ' %' ?></div>
      <div class="k-hint">Al ritmo actual; día <?= $r['day'] ?> de <?= $r['days'] ?></div>
    </div>
    <div class="kpi">
      <div class="k-label">Se renueva</div>
      <div class="k-value"><?= esc($renew) ?></div>
      <div class="k-hint">Topes diarios: cada día a las 00:00</div>
    </div>
  </div>

  <div class="notice mt-4"><?= icon('info', 16, 'soft') ?>
    <span class="text-xs">Al llegar al 100 % de un cupo, la IA se pausa solo en ese nivel (institución, rol o persona) y dice cuándo se renueva. El resto de la plataforma sigue funcionando. Durante las evaluaciones la IA siempre está apagada.</span></div>

  <form id="ai-settings" method="post" action="<?= site_url('ajustes/ia') ?>" class="grid-2 mt-4" style="align-items:start">
    <?= csrf_field() ?>
    <section class="card">
      <div class="card-header"><h2>Reparto del cupo mensual por rol</h2></div>
      <div class="card-body stack">
        <?php foreach ($r['buckets'] as $i => $b): $usedPct = $b['limit'] ? (int) round($b['used'] * 100 / max(1, $b['limit'])) : 0; ?>
          <div class="quota-row">
            <div style="min-width:0;flex:1">
              <div class="row-between"><span class="font-medium"><?= esc($b['name']) ?></span><span class="text-xs soft"><?= esc(plural($b['people'], 'persona', 'personas')) ?></span></div>
              <span class="share" role="img" aria-label="<?= esc($b['name'], 'attr') ?>: usado <?= $usedPct ?> % de su parte"><span style="width:<?= min(100, $usedPct) ?>%"></span></span>
              <span class="hint">Usado <?= number_format($b['used'], 0, ',', '.') ?> (<?= $usedPct ?> %) · <span x-text="tokens(<?= $i ?>)"></span> tokens al mes</span>
            </div>
            <label class="quota-input"><span class="sr-only">Porcentaje para <?= esc($b['name']) ?></span>
              <input class="input" type="number" name="share_<?= $b['key'] ?>" min="0" max="100" x-model.number="shares[<?= $i ?>]"><span class="soft">%</span></label>
          </div>
        <?php endforeach ?>
        <p class="field-error" x-show="total() !== 100" x-cloak>El reparto suma <span x-text="total()"></span> %; debe sumar 100 %.</p>
      </div>
    </section>

    <section class="card">
      <div class="card-header"><h2>Tope diario por persona</h2></div>
      <div class="card-body stack">
        <?php foreach ($r['buckets'] as $b): ?>
          <label class="field"><span class="label"><?= esc($b['name']) ?></span>
            <div class="row"><input class="input" type="number" name="daily_<?= $b['key'] ?>" min="0" step="1000" value="<?= (int) $b['daily'] ?>"><span class="soft nowrap">tokens/día</span></div>
            <?php if ($b['key'] === 'student'): ?><span class="hint">≈ <?= (int) floor($b['daily'] / $qTok) ?> consultas al tutor por día. El estudiante ve consultas, no tokens.</span><?php endif ?>
          </label>
        <?php endforeach ?>
      </div>
    </section>

    <section class="card col-full">
      <div class="card-header"><h2>Uso y transparencia</h2></div>
      <div class="card-body stack">
        <?= $check('enabled', (bool) $s['enabled'], 'IA activa en la institución', 'Apagada: nadie usa el tutor ni el copiloto. El resto de la plataforma sigue igual.') ?>
        <?= $check('label_ai', (bool) $s['label_ai'], 'Marcar las respuestas de IA', 'Muestra «Respuesta generada por IA» en el tutor y «IA» en las preguntas propuestas.') ?>
        <?= $check('alert_80', (bool) $s['alert_80'], 'Avisar al 80 % de un cupo', 'Muestra los avisos en esta página. (El aviso por correo llegará con las notificaciones.)') ?>
        <p class="hint">El modo del tutor (guía, explicación o sin IA) lo elige el docente en cada reto.</p>
      </div>
    </section>
  </form>

  <div class="grid-2 mt-4" style="align-items:start">
    <section class="card col-full">
      <div class="card-header"><h2>Consumo por función este mes</h2></div>
      <?php if (! $r['functions']): ?>
        <div class="card-body"><p class="hint">Aún no hay consumo este mes.</p></div>
      <?php else: ?>
        <div class="table-wrap" style="border:0;border-radius:0">
          <table class="table">
            <thead><tr><th>Función</th><th class="num">Llamadas</th><th class="num">Tokens</th><th style="width:40%">Participación</th></tr></thead>
            <tbody>
              <?php foreach ($r['functions'] as $f): $sh = (int) round($f['tokens'] * 100 / max(1, array_sum(array_column($r['functions'], 'tokens')))); ?>
                <tr><td><?= esc($f['name']) ?></td><td class="num"><?= number_format($f['calls'], 0, ',', '.') ?></td><td class="num"><?= number_format($f['tokens'], 0, ',', '.') ?></td>
                  <td><span class="share" role="img" aria-label="<?= $sh ?> %"><span style="width:<?= $sh ?>%"></span></span><span class="meta"><?= $sh ?> %</span></td></tr>
              <?php endforeach ?>
            </tbody>
          </table>
        </div>
      <?php endif ?>
    </section>

    <section class="card col-full">
      <div class="card-header"><h2>Personas con mayor consumo</h2><span class="text-xs soft">Hoy y en el mes</span></div>
      <?php if (! $r['top']): ?>
        <div class="card-body"><p class="hint">Aún no hay consumo este mes.</p></div>
      <?php else: ?>
        <div class="table-wrap" style="border:0;border-radius:0">
          <table class="table">
            <thead><tr><th>Persona</th><th class="num">Mes</th><th style="width:35%">Hoy</th><th class="text-right">Acciones</th></tr></thead>
            <tbody>
              <?php foreach ($r['top'] as $t): $tp = (int) round($t['today'] * 100 / max(1, $t['daily'])); ?>
                <tr>
                  <td><span class="font-medium"><?= esc($t['name']) ?></span><span class="meta"><?= esc($t['role']) ?></span></td>
                  <td class="num"><?= number_format($t['month'], 0, ',', '.') ?></td>
                  <td><span class="share" role="img" aria-label="Hoy <?= $tp ?> % de su tope"><span style="width:<?= min(100, $tp) ?>%"></span></span>
                    <span class="meta"><?= number_format($t['today'], 0, ',', '.') ?> de <?= number_format($t['daily'], 0, ',', '.') ?><?= $tp >= 100 ? ' · ' . badge('Tope alcanzado', 'danger') : '' ?></span></td>
                  <td class="text-right"><button type="button" class="btn btn-sm" @click="target = 'u:<?= $t['id'] ?>'; grant = true"><?= icon('plus', 14) ?> Cupo adicional</button></td>
                </tr>
              <?php endforeach ?>
            </tbody>
          </table>
        </div>
      <?php endif ?>
    </section>

    <section class="card col-full">
      <div class="card-header"><h2>Cupos adicionales vigentes</h2>
        <button type="button" class="btn btn-sm" @click="target = ''; grant = true"><?= icon('plus', 14) ?> Conceder cupo adicional</button></div>
      <?php if (! $r['grants']): ?>
        <div class="card-body"><p class="hint">No hay cupos adicionales vigentes.</p></div>
      <?php else: ?>
        <div class="table-wrap" style="border:0;border-radius:0">
          <table class="table">
            <thead><tr><th>Para</th><th class="num">Tokens extra</th><th>Motivo</th><th>Vence</th><th>Concedido por</th><th class="text-right">Acciones</th></tr></thead>
            <tbody>
              <?php foreach ($r['grants'] as $g): $for = $g['person'] ?? ('Rol: ' . (config('Ai')->bucketLabels[$g['bucket']] ?? '')); ?>
                <tr>
                  <td class="font-medium"><?= esc($for) ?></td>
                  <td class="num"><?= number_format((int) $g['tokens'], 0, ',', '.') ?><span class="meta"><?= $g['user_id'] ? 'por día' : 'en el mes' ?></span></td>
                  <td><?= esc($g['reason']) ?></td>
                  <td class="nowrap"><?= esc(date_short($g['expires_at'], false)) ?></td>
                  <td><?= esc($g['by_name'] ?? '—') ?></td>
                  <td class="text-right">
                    <form method="post" action="<?= site_url('ajustes/ia/cupos/' . $g['id'] . '/retirar') ?>" onsubmit="return confirm('¿Retirar este cupo adicional desde ya?')">
                      <?= csrf_field() ?><button type="submit" class="btn btn-sm btn-danger" title="Retirar el cupo adicional de <?= esc($for, 'attr') ?>" aria-label="Retirar el cupo adicional de <?= esc($for, 'attr') ?>"><?= icon('x', 14) ?> Retirar</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach ?>
            </tbody>
          </table>
        </div>
      <?php endif ?>
    </section>
  </div>

  <div class="drawer-layer" x-show="grant" x-cloak @keydown.escape.window="grant = false">
    <div class="veil" @click="grant = false"></div>
    <aside class="drawer" role="dialog" aria-modal="true" aria-labelledby="grant-title">
      <form method="post" action="<?= site_url('ajustes/ia/cupos') ?>">
        <?= csrf_field() ?>
        <div class="drawer-head"><h2 id="grant-title">Conceder cupo adicional</h2><button type="button" class="btn btn-ghost btn-sm btn-icon" @click="grant = false" aria-label="Cerrar">×</button></div>
        <div class="drawer-body stack">
          <label class="field"><span class="label">Para <span class="text-danger">*</span></span>
            <select class="select" name="target" x-model="target" required>
              <option value="">Elija…</option>
              <optgroup label="Un rol completo (se suma a su parte del mes)">
                <?php foreach (config('Ai')->bucketLabels as $bk => $bl): ?><option value="b:<?= $bk ?>"><?= esc($bl) ?></option><?php endforeach ?>
              </optgroup>
              <optgroup label="Una persona (se suma a su tope diario)">
                <?php foreach ($members as $m): ?><option value="u:<?= (int) $m['id'] ?>"><?= esc($m['name'] . ' · ' . $m['role']) ?></option><?php endforeach ?>
              </optgroup>
            </select></label>
          <label class="field"><span class="label">Tokens adicionales <span class="text-danger">*</span></span>
            <input class="input" type="number" name="tokens" min="1000" step="1000" value="20000" required>
            <span class="hint">Nunca supera el cupo mensual del plan de la institución.</span></label>
          <label class="field"><span class="label">Vence <span class="text-danger">*</span></span>
            <input class="input" type="date" name="expires" min="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d', strtotime('+90 days')) ?>" value="<?= date('Y-m-d', strtotime('+7 days')) ?>" required></label>
          <label class="field"><span class="label">Motivo <span class="text-danger">*</span></span>
            <textarea class="textarea" name="reason" rows="3" maxlength="255" required placeholder="Ej.: preparación de la evaluación final de Química"></textarea>
            <span class="hint">Queda en la auditoría junto con su nombre.</span></label>
        </div>
        <div class="drawer-foot"><button type="button" class="btn" @click="grant = false">Cancelar</button><button type="submit" class="btn btn-primary" data-loading="Concediendo…">Conceder</button></div>
      </form>
    </aside>
  </div>
</div>
<?= $this->endSection() ?>
