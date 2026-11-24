<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
/**
 * Consola de la red › Detalle de una comunicación: contenido y cifras por colegio (leídos o alcance). Sin nombres de
 * personas: la red solo ve totales.
 *
 * @var array $p, $categories, $audiences
 * @var bool  $canSend
 */
use App\Libraries\Events;
use App\Libraries\NetworkComms;

$it      = $p['item'] ?? [];
$audText = $p['audience'] === 'all' ? 'Todos' : implode(', ', array_map(static fn ($k) => $audiences[$k] ?? $k, explode(',', $p['audience'])));
?>
<nav class="crumbs" aria-label="Ruta"><a href="<?= site_url('red/comunicaciones') ?>">Comunicaciones</a> <span aria-hidden="true">›</span> <span><?= esc(NetworkComms::KINDS[$p['kind']]) ?></span></nav>
<div class="page-header">
  <div><h1><?= esc($p['title']) ?></h1><p><?= esc(NetworkComms::KINDS[$p['kind']]) ?> · para <?= esc($audText) ?> · <?= (int) $p['schools'] ?> colegios · <?= esc($p['author'] ?? '') ?>, <?= esc(date_short($p['created_at'])) ?></p></div>
  <?php if ($canSend): ?>
    <div class="actions">
      <form method="post" action="<?= site_url('red/comunicaciones/' . $p['id'] . '/retirar') ?>" onsubmit="return confirm('<?= $p['kind'] === 'message' ? '¿Quitarlo del historial? Los mensajes ya entregados se conservan en cada bandeja.' : '¿Retirarlo de todos los colegios?' ?>')">
        <?= csrf_field() ?><button type="submit" class="btn btn-danger"><?= icon('trash-2', 16) ?> <?= $p['kind'] === 'message' ? 'Quitar del historial' : 'Retirar de los colegios' ?></button>
      </form>
    </div>
  <?php endif ?>
</div>

<div class="grid-3" style="align-items:start">
  <section class="card span-2">
    <div class="card-body stack">
      <?php if ($p['kind'] === 'event' && $it): ?>
        <?php [$cl, $ci, $cc] = $categories[$it['category']] ?? $categories['other']; ?>
        <p class="row" style="gap:0.5rem"><span style="color:<?= $cc ?>"><?= icon($ci, 18) ?></span> <strong><?= esc($cl) ?></strong> · <?= esc(Events::when($it)) ?><?= $it['location'] ? ' · ' . esc($it['location']) : '' ?></p>
        <?php if ($it['description']): ?><p class="soft"><?= nl2br(esc($it['description'])) ?></p><?php endif ?>
      <?php elseif ($it): ?>
        <?php if (! empty($it['summary'])): ?><p class="font-medium"><?= esc($it['summary']) ?></p><?php endif ?>
        <div class="msg-body"><?= nl2br(esc($it['body'] ?? '')) ?></div>
      <?php endif ?>
    </div>
  </section>

  <section class="card">
    <div class="card-header"><h2><?= $p['kind'] === 'message' ? 'Lectura por colegio' : 'Alcance por colegio' ?></h2></div>
    <div class="card-body stack">
      <?php if ($p['kind'] === 'message'): ?><p class="text-sm"><strong><?= (int) $p['read_count'] ?></strong> de <?= (int) $p['total'] ?> personas lo leyeron.</p><?php endif ?>
      <?php foreach ($p['copies'] as $c): ?>
        <div>
          <div class="row text-sm" style="justify-content:space-between"><span class="font-medium"><?= esc($c['school']) ?></span>
            <span class="soft"><?= $p['kind'] === 'message' ? (int) $c['read_count'] . ' de ' . (int) $c['recipients'] : (int) $c['reach'] . ' personas' ?></span></div>
          <?php if ($p['kind'] === 'message'): ?><span class="share mt-1"><span style="width:<?= $c['recipients'] ? round(100 * $c['read_count'] / $c['recipients']) : 0 ?>%"></span></span><?php endif ?>
        </div>
      <?php endforeach ?>
      <p class="hint">La red ve solo totales por colegio, no nombres de personas.</p>
    </div>
  </section>
</div>
<?= $this->endSection() ?>
