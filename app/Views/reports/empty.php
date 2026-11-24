<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="page-header"><div><h1>Seguimiento</h1><p>Cómo va cada grupo, en qué temas y en qué preguntas.</p></div></div>
<?= view('partials/tabs', ['label' => 'Vistas de seguimiento', 'current' => 'asignatura', 'tabs' => ['grupo' => ['Por grupo', 'estudiantes'], 'asignatura' => ['Por asignatura', 'reportes']]]) ?>
<div class="empty">
  <?= icon('chart-column', 32) ?>
  <p>No tiene asignaturas en el periodo en curso. Cuando la coordinación le asigne grupos, aquí verá sus reportes.</p>
</div>
<?= $this->endSection() ?>
