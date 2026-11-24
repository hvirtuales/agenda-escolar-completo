<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
/**
 * Acudiente › un estudiante (solo lectura). El contenido es el mismo partial del inicio del estudiante (partials/progress).
 * Sin enlaces a las actividades (el acudiente no las resuelve) y sin las observaciones internas de los docentes.
 *
 * @var array $kid, $kids, $p StudentProgress::progress(), $acts StudentProgress::activities()
 */
?>
<div class="page-header">
  <div>
    <?php if (count($kids) > 1): ?><nav class="crumbs text-xs" aria-label="Ubicación"><a class="link" href="<?= site_url('acudiente') ?>">Mis estudiantes</a><?= icon('chevron-right', 12) ?><span><?= esc($kid['name']) ?></span></nav><?php endif ?>
    <div class="row mt-1"><span class="avatar" aria-hidden="true"><?= esc(initials($kid['name'])) ?></span><h1><?= esc($kid['name']) ?></h1></div>
    <p><?= esc(($kid['group_name'] ?? 'Sin grupo') . ' · Usted figura como ' . mb_strtolower($kid['relationship'])) ?></p>
  </div>
  <div class="actions"><a class="btn" href="<?= site_url('acudiente/agenda?estudiante=' . (int) $kid['id']) ?>"><?= icon('calendar-days', 16) ?> Ver su agenda</a></div>
</div>

<?= view('partials/progress', ['p' => $p, 'acts' => $acts, 'mode' => 'guardian']) ?>
<?= $this->endSection() ?>
