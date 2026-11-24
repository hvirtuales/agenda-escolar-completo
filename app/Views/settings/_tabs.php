<?php
/** Pestañas de Ajustes (settings_tabs(), ui_helper). Solo muestra las secciones que la persona puede abrir. $section = clave actual. */
?>
<nav class="tabs" aria-label="Secciones de ajustes">
  <?php foreach (settings_tabs() as $k => [$label, $url]): ?>
    <a class="tab" href="<?= site_url($url) ?>" <?= $section === $k ? 'aria-selected="true" aria-current="page"' : '' ?>><?= esc($label) ?></a>
  <?php endforeach ?>
</nav>
