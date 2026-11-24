<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
/**
 * Inicio de coordinación y rectoría: cómo va el colegio hoy. Estudiantes que requieren acompañamiento, docentes con
 * señales para acompañar, próximos eventos y últimas noticias; si además dicta clases, sus grupos.
 *
 * @var array $o HomeController::institution()
 */
$first = explode(' ', $user['name'])[0];
$mon   = static fn (string $dt): string => ((array) lang('Dates.monthsShort'))[(int) date('n', strtotime($dt)) - 1] ?? '';
?>
<div class="page-header">
  <div>
    <h1>Hola, <?= esc($first) ?></h1>
    <p>Cómo va <?= esc($institution['name']) ?> hoy.</p>
  </div>
  <div class="actions">
    <a class="btn" href="<?= site_url('agenda') ?>"><?= icon('calendar-days', 16) ?> Agenda</a>
    <a class="btn btn-primary" href="<?= site_url('mensajes/nuevo') ?>"><?= icon('send', 16) ?> Nuevo mensaje</a>
  </div>
</div>

<div class="grid-kpi">
  <?php foreach ($o['kpis'] as [$label, $value, $hint, $tone]): ?>
    <div class="kpi"><div class="k-label"><?= esc($label) ?></div><div class="k-value <?= $tone ? 'text-' . $tone : '' ?>"><?= esc($value) ?></div><div class="k-hint"><?= esc($hint) ?></div></div>
  <?php endforeach ?>
</div>

<div class="grid-3 mt-4" style="align-items:start">
  <div class="span-2 stack">
    <section class="card" aria-labelledby="h-acomp">
      <div class="card-header row-between"><h2 id="h-acomp">Estudiantes para acompañar</h2>
        <a class="btn btn-sm" href="<?= site_url('estudiantes') ?>">Ver seguimiento<?= $o['studentsTotal'] > count($o['students']) ? ' (' . $o['studentsTotal'] . ')' : '' ?></a></div>
      <?php if (! $o['students']): ?>
        <div class="card-body"><p class="hint">Ningún estudiante tiene alertas: nadie con actividades vencidas ni promedio bajo.</p></div>
      <?php else: ?>
        <div class="date-list">
          <?php foreach ($o['students'] as $s): ?>
            <a class="date-row" href="<?= site_url('estudiantes/' . (int) $s['id']) ?>" style="align-items:flex-start">
              <span class="avatar" aria-hidden="true"><?= esc(initials($s['name'])) ?></span>
              <span style="min-width:0;flex:1">
                <span class="block font-medium"><?= esc($s['name']) ?> <span class="soft text-xs">· <?= esc($s['group']) ?></span></span>
                <span class="block text-xs soft"><?= esc(implode(' · ', array_slice($s['reasons'], 0, 2))) ?><?= count($s['reasons']) > 2 ? ' · y ' . (count($s['reasons']) - 2) . ' más' : '' ?></span>
              </span>
              <?= badge(plural(count($s['reasons']), 'asignatura', 'asignaturas'), 'warn') ?>
            </a>
          <?php endforeach ?>
        </div>
      <?php endif ?>
    </section>

    <section class="card" aria-labelledby="h-docentes">
      <div class="card-header row-between"><h2 id="h-docentes">Docentes para acompañar</h2>
        <a class="btn btn-sm" href="<?= site_url('docentes') ?>">Ver docentes<?= $o['teachersTotal'] > count($o['teachers']) ? ' (' . $o['teachersTotal'] . ')' : '' ?></a></div>
      <?php if (! $o['teachers']): ?>
        <div class="card-body"><p class="hint">Sin señales: todos los docentes publican y revisan a tiempo.</p></div>
      <?php else: ?>
        <div class="date-list">
          <?php foreach ($o['teachers'] as $t): ?>
            <a class="date-row" href="<?= site_url('docentes/' . (int) $t['id']) ?>">
              <span class="avatar" aria-hidden="true"><?= esc(initials($t['name'])) ?></span>
              <span style="min-width:0;flex:1">
                <span class="block font-medium"><?= esc($t['name']) ?></span>
                <span class="block text-xs soft"><?= esc(implode(' · ', $t['alerts'])) ?></span>
              </span>
              <?php if ((int) $t['to_review']): ?><?= badge(plural((int) $t['to_review'], 'por revisar', 'por revisar'), 'neutral') ?><?php endif ?>
            </a>
          <?php endforeach ?>
        </div>
      <?php endif ?>
    </section>

    <?php if ($o['groups']): ?>
      <section aria-labelledby="h-grupos">
        <h2 class="section-title" id="h-grupos">Mis grupos</h2>
        <div class="group-cards">
          <?php foreach ($o['groups'] as $g): ?>
            <a class="group-card" href="<?= site_url('cuadernos/' . $g['slug']) ?>" style="--cat:<?= cat_color($g['color']) ?>">
              <div class="g-title"><?= esc($g['title']) ?></div>
              <div class="g-meta"><?= esc(plural((int) $g['students'], 'estudiante', 'estudiantes')) ?></div>
            </a>
          <?php endforeach ?>
        </div>
      </section>
    <?php endif ?>
  </div>

  <div class="stack">
    <section class="card" aria-labelledby="h-eventos">
      <div class="card-header row-between"><h2 id="h-eventos">Próximos eventos</h2><a class="btn btn-sm" href="<?= site_url('agenda?evento=nuevo') ?>"><?= icon('plus', 14) ?> Nuevo</a></div>
      <div class="date-list">
        <?php if (! $o['events']): ?><div class="date-row"><span class="soft text-sm">No hay eventos próximos.</span></div><?php endif ?>
        <?php foreach ($o['events'] as $e): ?>
          <a class="date-row" href="<?= site_url('agenda?dia=' . substr($e['starts_at'], 0, 10)) ?>">
            <span class="date-chip"><strong><?= date('j', strtotime($e['starts_at'])) ?></strong><?= esc($mon($e['starts_at'])) ?></span>
            <span style="min-width:0">
              <span class="block font-medium truncate"><?= esc($e['title']) ?></span>
              <span class="block text-xs soft truncate"><?= (int) $e['all_day'] ? 'Todo el día' : esc(date('g:i a', strtotime($e['starts_at']))) ?><?= $e['location'] ? ' · ' . esc($e['location']) : '' ?></span>
            </span>
          </a>
        <?php endforeach ?>
      </div>
    </section>

    <section class="card" aria-labelledby="h-noticias">
      <div class="card-header row-between"><h2 id="h-noticias">Últimas noticias</h2><a class="btn btn-sm" href="<?= site_url('noticias') ?>">Ver todas</a></div>
      <div class="date-list">
        <?php if (! $o['news']): ?><div class="date-row"><span class="soft text-sm">Aún no hay noticias publicadas.</span></div><?php endif ?>
        <?php foreach ($o['news'] as $n): ?>
          <a class="date-row" href="<?= site_url('noticias/' . (int) $n['id']) ?>">
            <?= icon('newspaper', 18, 'soft') ?>
            <span style="min-width:0">
              <span class="block font-medium truncate"><?= esc($n['title']) ?></span>
              <span class="block text-xs soft"><?= esc(date_short($n['published_at'], false)) ?></span>
            </span>
          </a>
        <?php endforeach ?>
      </div>
    </section>
  </div>
</div>
<?= $this->endSection() ?>
