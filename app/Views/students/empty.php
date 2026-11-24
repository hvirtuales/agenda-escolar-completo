<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="page-header"><div><h1>Seguimiento</h1><p>Sus grupos, con el avance de cada estudiante.</p></div></div>
<?= view('partials/tabs', ['label' => 'Vistas de seguimiento', 'current' => 'grupo', 'tabs' => ['grupo' => ['Por grupo', 'estudiantes'], 'asignatura' => ['Por asignatura', 'reportes']]]) ?>
<div class="empty">
  <?= icon('users', 32) ?>
  <p>No tiene grupos en el periodo en curso. Cuando la coordinación le asigne asignaturas, aquí verá a sus estudiantes.</p>
</div>
<?= $this->endSection() ?>
