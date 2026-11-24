<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php /** Consola de la red › Biblioteca compartida: bancos que cada colegio ofrece a la red. @var array $banks */ ?>
<div class="page-header">
  <div><h1>Biblioteca compartida</h1><p>Bancos de preguntas que los colegios ofrecen a la red. Los docentes de los demás colegios los copian desde su Biblioteca.</p></div>
</div>
<section class="panel">
  <?php if (! $banks): ?>
    <div class="empty"><?= icon('library', 32) ?><p>Ningún colegio ha compartido bancos todavía. Se comparten desde la página de cada banco, con «Compartir con la red».</p></div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Banco</th><th>Colegio</th><th>Asignatura</th><th class="num">Preguntas</th><th>Autor</th></tr></thead>
        <tbody>
          <?php foreach ($banks as $b): ?>
            <tr>
              <td><span class="font-medium"><?= esc($b['name']) ?></span><?php if ($b['description']): ?><span class="bank-desc"><?= esc($b['description']) ?></span><?php endif ?></td>
              <td><?= esc($b['school']) ?></td>
              <td><?= $b['subject'] ? esc($b['subject']) : '<span class="soft">General</span>' ?></td>
              <td class="num"><?= (int) $b['questions'] ?></td>
              <td class="nowrap"><?= esc($b['author'] ?? '—') ?></td>
            </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>
  <?php endif ?>
</section>
<?= $this->endSection() ?>
