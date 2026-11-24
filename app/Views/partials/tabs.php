<?php
/**
 * Pestañas que unen pantallas hermanas (Seguimiento: Por grupo / Por asignatura; Comunicaciones: Mensajes / Noticias).
 *
 * @var array  $tabs    clave => [etiqueta, ruta]
 * @var string $current clave activa
 * @var string $label   nombre accesible del grupo de pestañas
 */
?>
<nav class="tabs mb-4" aria-label="<?= esc($label ?? 'Secciones', 'attr') ?>">
  <?php foreach ($tabs as $k => [$text, $url]): ?>
    <a class="tab" href="<?= site_url($url) ?>" <?= $current === $k ? 'aria-selected="true" aria-current="page"' : '' ?>><?= esc($text) ?></a>
  <?php endforeach ?>
</nav>
