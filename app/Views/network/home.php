<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
/**
 * Consola de la red › Resumen: cifras consolidadas por colegio (solo agregados, sin datos personales).
 * «Entrar» abre el colegio como administrador de la red; queda en la auditoría.
 *
 * @var array $network red activa (con role), $r Network::report()
 */
$t   = $r['totals'];
$num = static fn ($v) => $v === null ? '—' : number_format((float) $v, 1, ',', '');
$tok = static fn (int $n): string => $n >= 1000000 ? str_replace('.', ',', rtrim(rtrim(number_format($n / 1000000, 2, '.', ''), '0'), '.')) . ' M' : number_format($n, 0, ',', '.');
$st  = ['active' => ['Activo', 'ok'], 'trial' => ['En prueba', 'accent'], 'suspended' => ['Suspendido', 'danger']];
$isAdmin = $network['role'] === 'admin';
?>
<div class="page-header">
  <div><h1><?= esc($network['name']) ?></h1><p>Cómo van los colegios de la red. Solo cifras agregadas: los datos de cada estudiante quedan en su colegio.</p></div>
</div>

<div class="grid-kpi">
  <div class="kpi"><div class="k-label">Colegios</div><div class="k-value"><?= $t['schools'] ?></div><div class="k-hint"><?= number_format($t['teachers'], 0, ',', '.') ?> docentes y coordinadores</div></div>
  <div class="kpi"><div class="k-label">Estudiantes</div><div class="k-value"><?= number_format($t['students'], 0, ',', '.') ?></div><div class="k-hint">Con membresía activa</div></div>
  <div class="kpi"><div class="k-label">Promedio de la red</div><div class="k-value"><?= $num($t['avg']) ?></div><div class="k-hint">Escala de 1 a 5 · <?= number_format($t['activities'], 0, ',', '.') ?> actividades publicadas</div></div>
  <div class="kpi"><div class="k-label">IA este mes</div><div class="k-value"><?= $tok($t['ai_month']) ?> <span class="text-sm soft"><?= $t['ai_cap'] ? 'de ' . $tok($t['ai_cap']) : 'tokens' ?></span></div>
    <?php if ($t['ai_cap']): $p = (int) round($t['ai_month'] * 100 / $t['ai_cap']); ?><span class="share" role="img" aria-label="<?= $p ?> % del cupo común"><span style="width:<?= min(100, $p) ?>%"></span></span><?php endif ?>
    <div class="k-hint"><?= $t['ai_cap'] ? 'Cupo común de la red' : 'Cada colegio con el cupo de su plan' ?></div></div>
</div>

<section class="panel mt-4">
  <?php if (! $r['schools']): ?>
    <div class="empty"><?= icon('school', 32) ?><p>La red aún no tiene colegios. El superadministrador los asigna desde la consola de plataforma.</p></div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Colegio</th><th class="num">Estudiantes</th><th class="num">Docentes</th><th class="num">Actividades</th><th style="width:16%">Participación</th><th class="num">Promedio</th><th class="num">IA del mes</th><th>Estado</th><?php if ($isAdmin): ?><th class="text-right">Acciones</th><?php endif ?></tr></thead>
        <tbody>
          <?php foreach ($r['schools'] as $s): [$sl, $stone] = $st[$s['status']] ?? [$s['status'], 'neutral']; ?>
            <tr>
              <td><span class="font-medium"><?= esc($s['name']) ?></span><span class="meta"><?= esc(trim(($s['city'] ?? '') . ' · Plan ' . $s['plan'], ' ·')) ?></span></td>
              <td class="num"><?= number_format($s['students'], 0, ',', '.') ?></td>
              <td class="num"><?= number_format($s['teachers'], 0, ',', '.') ?></td>
              <td class="num"><?= number_format($s['activities'], 0, ',', '.') ?></td>
              <td><?php if ($s['participation'] === null): ?><span class="soft">—</span><?php else: ?>
                <span class="share" role="img" aria-label="Participación <?= $s['participation'] ?> %"><span style="width:<?= $s['participation'] ?>%"></span></span><span class="meta"><?= $s['participation'] ?> %</span><?php endif ?></td>
              <td class="num"><?= $s['avg'] === null ? '<span class="soft">—</span>' : badge($num($s['avg']), $s['avg'] < 3 ? 'danger' : ($s['avg'] < 4 ? 'warn' : 'ok')) ?></td>
              <td class="num"><?= $tok($s['ai_month']) ?></td>
              <td><?= badge($sl, $stone) ?></td>
              <?php if ($isAdmin): ?>
                <td class="text-right">
                  <?php if ($s['status'] !== 'suspended'): ?>
                    <form method="post" action="<?= site_url('red/colegios/' . $s['id'] . '/entrar') ?>">
                      <?= csrf_field() ?><button type="submit" class="btn btn-sm" title="Entrar a <?= esc($s['name'], 'attr') ?>"><?= icon('log-in', 14) ?> Entrar</button>
                    </form>
                  <?php endif ?>
                </td>
              <?php endif ?>
            </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>
  <?php endif ?>
</section>
<p class="hint mt-2">Participación: estudiantes con al menos una entrega o evaluación terminada. Promedio: notas de retos revisados y evaluaciones calificadas.<?= $isAdmin ? ' Al entrar a un colegio trabaja con todos los permisos y queda registrado en la auditoría.' : '' ?></p>
<?= $this->endSection() ?>
