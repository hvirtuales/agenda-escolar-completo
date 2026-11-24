<?php
/**
 * Saldo diario de IA de la persona (documento maestro 5.8). $quota = Quota::balance(): used, limit, per_query, renews,
 * blocked (motivo si la IA no está disponible). $asQueries: el estudiante lo ve como consultas aproximadas, no en tokens.
 * Lleva data-ai-quota para que la pantalla lo actualice tras cada respuesta (window.renderQuota en app.js).
 */
$left = max(0, $quota['limit'] - $quota['used']);
$pct  = (int) round($quota['used'] * 100 / max(1, $quota['limit']));
$tone = ! empty($quota['blocked']) || $pct >= 100 ? 'danger' : ($pct >= 80 ? 'warn' : 'ok');
?>
<div class="ai-quota is-<?= $tone ?>" data-ai-quota data-as-queries="<?= empty($asQueries) ? '0' : '1' ?>" aria-live="polite">
  <?php if (! empty($quota['blocked'])): ?>
    <span><?= esc($quota['blocked']) ?></span>
  <?php else: ?>
    <div class="row-between">
      <span data-q-text>
        <?php if (! empty($asQueries)): ?>
          Te quedan unas <strong><?= (int) floor($left / max(1, $quota['per_query'])) ?> consultas</strong> hoy
        <?php else: ?>
          Hoy: <strong><?= number_format($left, 0, ',', '.') ?></strong> de <?= number_format($quota['limit'], 0, ',', '.') ?> tokens disponibles
        <?php endif ?>
      </span>
      <span class="soft">Se renueva a las <?= esc($quota['renews']) ?></span>
    </div>
    <span class="share" role="img" data-q-bar aria-label="Usado hoy: <?= $pct ?> % del cupo"><span style="width:<?= min(100, $pct) ?>%"></span></span>
  <?php endif ?>
</div>
