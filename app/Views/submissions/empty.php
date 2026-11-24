<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="page-header"><div><h1>Entregas</h1><p>Aquí revisa y califica lo que entregan sus estudiantes.</p></div></div>
<div class="empty">
  <?= icon('inbox', 32) ?>
  <p>Aún no tiene actividades asignadas. Publique un reto para que sus estudiantes empiecen a entregar.</p>
  <a class="btn btn-primary mt-4" href="<?= site_url('actividades') ?>"><?= icon('target', 16) ?> Ir a Actividades</a>
</div>
<?= $this->endSection() ?>
