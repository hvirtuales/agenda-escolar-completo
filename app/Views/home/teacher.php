<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php $first = explode(' ', $user['name'])[0]; ?>

<div class="page-header">
  <div>
    <h1>Hola, <?= esc($first) ?></h1>
    <p>Lo que necesita tu atención hoy en <?= esc($institution['name']) ?>.</p>
  </div>
  <div class="actions">
    <a class="btn" href="<?= site_url('cuadernos') ?>"><?= icon('notebook-tabs', 16) ?> Abrir cuadernos</a>
    <a class="btn btn-primary" href="<?= site_url('cuadernos?ia=1') ?>"><?= icon('sparkles', 16) ?> Crear actividad con IA</a>
  </div>
</div>

<div class="grid-kpi">
  <?php foreach ($data['kpis'] as $k): ?>
    <div class="kpi">
      <div class="k-label"><?= esc($k['label']) ?></div>
      <div class="k-value"><?= esc($k['value']) ?></div>
      <div class="k-hint"><?= esc($k['hint']) ?></div>
    </div>
  <?php endforeach ?>
</div>

<div class="alert-list mt-4">
  <?php foreach ($data['alerts'] as $a): ?>
    <a class="alert-row" href="<?= site_url($a['route']) ?>">
      <?= icon($a['icon'], 18, 'text-' . ($a['tone'] === 'accent' ? 'accent' : $a['tone'])) ?>
      <span><?= esc($a['text']) ?></span>
      <span class="go">Revisar <?= icon('chevron-right', 14) ?></span>
    </a>
  <?php endforeach ?>
</div>

<div class="grid-3 mt-4" style="align-items:start">
  <section class="span-2" aria-labelledby="mis-grupos">
    <h2 class="section-title" id="mis-grupos">Mis grupos</h2>
    <div class="group-cards">
      <?php foreach ($data['groups'] as $g): ?>
        <a class="group-card" href="<?= site_url('cuadernos/' . $g['slug']) ?>" style="--cat:<?= cat_color($g['color']) ?>">
          <div class="g-title"><?= esc($g['title']) ?></div>
          <div class="g-meta"><?= esc(plural($g['students'], 'estudiante', 'estudiantes')) ?></div>
          <div class="g-next"><?= icon('calendar-days', 14) ?> <?= esc($g['next']) ?></div>
        </a>
      <?php endforeach ?>
    </div>
  </section>

  <div class="stack">
    <section class="card" aria-labelledby="proximas">
      <div class="card-header"><h2 id="proximas">Próximas fechas</h2></div>
      <div class="date-list">
        <?php foreach ($data['upcoming'] as $u): $ts = strtotime($u['date']); $mon = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'][(int) date('n', $ts) - 1]; ?>
          <div class="date-row">
            <span class="date-chip"><strong><?= date('j', $ts) ?></strong><?= $mon ?></span>
            <span style="min-width:0">
              <span class="block font-medium truncate"><?= esc($u['title']) ?></span>
              <span class="block text-xs soft"><?= esc(page_kind($u['kind'])['label'] . ' · ' . $u['group']) ?></span>
            </span>
          </div>
        <?php endforeach ?>
        <?php if (! $data['upcoming']): ?><div class="date-row"><span class="soft text-sm">Sin retos abiertos.</span></div><?php endif ?>
      </div>
    </section>

    <section class="card" aria-labelledby="recientes">
      <div class="card-header"><h2 id="recientes">Continuar donde ibas</h2></div>
      <div class="date-list">
        <?php if (! $data['recent']): ?><div class="date-row"><span class="soft text-sm">Aún no ha editado páginas.</span></div><?php endif ?>
        <?php foreach ($data['recent'] as $r): ?>
          <a class="date-row" href="<?= site_url($r['url']) ?>">
            <?= icon('file-text', 18, 'soft') ?>
            <span style="min-width:0">
              <span class="block font-medium truncate"><?= esc($r['title']) ?></span>
              <span class="block text-xs soft truncate"><?= esc($r['where']) ?> · <?= esc($r['when']) ?></span>
            </span>
          </a>
        <?php endforeach ?>
      </div>
    </section>
  </div>
</div>
<?= $this->endSection() ?>
