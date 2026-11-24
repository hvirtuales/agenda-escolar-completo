<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
/**
 * Inicio del estudiante (une el antiguo «Mi progreso»): qué tengo por hacer, cómo voy en cada asignatura,
 * la retroalimentación de mis docentes y mis logros. Datos de StudentProgress (los mismos de «Mis actividades»).
 *
 * @var array $p, $acts; string $period
 */
?>
<div class="page-header">
  <div>
    <h1><?= ! empty($studentPreview) ? 'Hola' : 'Hola, ' . esc(explode(' ', $user['name'])[0]) ?></h1>
    <p><?= esc(trim(($user['group'] ?? '') . ' · ' . $institution['name'] . ($period ? ' · ' . $period : ''), ' ·')) ?></p>
  </div>
  <div class="actions"><a class="btn" href="<?= site_url('estudiante/actividades') ?>"><?= icon('list-checks', 16) ?> Mis actividades</a></div>
</div>
<?= view('partials/progress', ['p' => $p, 'acts' => $acts, 'mode' => 'student']) ?>
<?= $this->endSection() ?>
