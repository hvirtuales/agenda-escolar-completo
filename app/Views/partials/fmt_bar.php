<?php
/**
 * Barra de formato flotante (fmtBar en app.js): aparece al seleccionar texto en un campo [data-rich][contenteditable="true"].
 * La usan el cuaderno y el editor de la página pública del colegio. Avisa los cambios con el evento «fmt-changed».
 */
$hl = ['yellow' => 'Amarillo', 'green' => 'Verde', 'blue' => 'Azul', 'pink' => 'Rosado'];
// Color del texto: el de marca de la institución y 3 variaciones (RichText::BRAND_COLORS; CSS .tc-brand*).
$tc = ['brand' => 'Color de marca', 'brand-dark' => 'Marca oscuro', 'brand-deep' => 'Marca muy oscuro', 'brand-light' => 'Marca claro'];
$fs = ['sm' => 'Pequeño', '' => 'Normal', 'lg' => 'Grande', 'xl' => 'Muy grande'];
?>
<div class="fmt-bar" x-data="fmtBar" x-show="show" x-cloak :style="`left:${x}px;top:${y}px`" role="toolbar" aria-label="Formato del texto"
     @mousedown.prevent @keydown.escape="pop = null; show = false">
  <button type="button" class="btn btn-sm btn-icon" :aria-pressed="!!state.bold" @click="cmd('bold')" title="Negrita (Ctrl+B)" aria-label="Negrita"><?= icon('bold', 14) ?></button>
  <button type="button" class="btn btn-sm btn-icon" :aria-pressed="!!state.italic" @click="cmd('italic')" title="Cursiva (Ctrl+I)" aria-label="Cursiva"><?= icon('italic', 14) ?></button>
  <button type="button" class="btn btn-sm btn-icon" :aria-pressed="!!state.underline" @click="cmd('underline')" title="Subrayado (Ctrl+U)" aria-label="Subrayado"><?= icon('underline', 14) ?></button>
  <button type="button" class="btn btn-sm btn-icon" :aria-pressed="!!state.strike" @click="cmd('strikeThrough')" title="Tachado" aria-label="Tachado"><?= icon('strikethrough', 14) ?></button>
  <span class="fmt-sep" aria-hidden="true"></span>
  <div class="fmt-pop-wrap">
    <button type="button" class="btn btn-sm btn-icon" @click="pop = pop === 'fs' ? null : 'fs'" :aria-expanded="pop === 'fs'" title="Tamaño de la letra" aria-label="Tamaño de la letra"><?= icon('a-large-small', 14) ?></button>
    <div class="fmt-pop fmt-pop-col" x-show="pop === 'fs'" x-cloak role="menu" aria-label="Tamaño de la letra">
      <?php foreach ($fs as $k => $name): ?>
        <button type="button" class="fmt-size<?= $k ? ' fs-' . $k : '' ?>" role="menuitem" @click="wrap('fs', <?= $k ? "'" . $k . "'" : 'null' ?>)"><?= esc($name) ?></button>
      <?php endforeach ?>
    </div>
  </div>
  <button type="button" class="btn btn-sm btn-icon" :aria-pressed="!!state.ul" @click="cmd('insertUnorderedList')" title="Lista con viñetas (Tab: sublista)" aria-label="Lista con viñetas"><?= icon('list', 14) ?></button>
  <button type="button" class="btn btn-sm btn-icon" :aria-pressed="!!state.ol" @click="cmd('insertOrderedList')" title="Lista numerada (Tab: sublista)" aria-label="Lista numerada"><?= icon('list-ordered', 14) ?></button>
  <button type="button" class="btn btn-sm btn-icon" :aria-pressed="!!state.sup" @click="cmd('superscript')" title="Superíndice (x²)" aria-label="Superíndice"><?= icon('superscript', 14) ?></button>
  <button type="button" class="btn btn-sm btn-icon" :aria-pressed="!!state.sub" @click="cmd('subscript')" title="Subíndice (H₂O)" aria-label="Subíndice"><?= icon('subscript', 14) ?></button>
  <span class="fmt-sep" aria-hidden="true"></span>
  <div class="fmt-pop-wrap">
    <button type="button" class="btn btn-sm btn-icon" @click="pop = pop === 'hl' ? null : 'hl'" :aria-expanded="pop === 'hl'" title="Resaltar" aria-label="Resaltar"><?= icon('highlighter', 14) ?></button>
    <div class="fmt-pop" x-show="pop === 'hl'" x-cloak role="menu" aria-label="Color de resaltado">
      <?php foreach ($hl as $k => $name): ?>
        <button type="button" class="fmt-swatch hl-<?= $k ?>" role="menuitem" @click="wrap('hl', '<?= $k ?>')" title="Resaltar en <?= strtolower($name) ?>" aria-label="Resaltar en <?= strtolower($name) ?>">A</button>
      <?php endforeach ?>
      <button type="button" class="fmt-swatch fmt-none" role="menuitem" @click="wrap('hl', null)" title="Quitar el resaltado" aria-label="Quitar el resaltado"><?= icon('ban', 12) ?></button>
    </div>
  </div>
  <div class="fmt-pop-wrap">
    <button type="button" class="btn btn-sm btn-icon" @click="pop = pop === 'tc' ? null : 'tc'" :aria-expanded="pop === 'tc'" title="Color del texto" aria-label="Color del texto"><?= icon('baseline', 14) ?></button>
    <div class="fmt-pop" x-show="pop === 'tc'" x-cloak role="menu" aria-label="Color del texto">
      <?php foreach ($tc as $k => $name): ?>
        <button type="button" class="fmt-swatch tc-<?= $k ?>" role="menuitem" @click="wrap('tc', '<?= $k ?>')" title="Texto: <?= mb_strtolower($name) ?>" aria-label="Texto: <?= mb_strtolower($name) ?>">A</button>
      <?php endforeach ?>
      <button type="button" class="fmt-swatch fmt-none" role="menuitem" @click="wrap('tc', null)" title="Color automático" aria-label="Color automático"><?= icon('ban', 12) ?></button>
    </div>
  </div>
  <span class="fmt-sep" aria-hidden="true"></span>
  <button type="button" class="btn btn-sm btn-icon" @click="clear()" title="Quitar todo el formato" aria-label="Quitar todo el formato"><?= icon('remove-formatting', 14) ?></button>
</div>
