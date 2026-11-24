<?php
/**
 * Listas del selector de la barra superior: redes (POST red/entrar) e instituciones (POST instituciones/cambiar).
 * Variables: $nets (redes a las que puede entrar), $insts (instituciones), $currentNet, $currentInst (ids actuales).
 */
$nets ??= [];
$insts ??= [];
?>
<?php if ($nets): ?>
  <div class="menu-label">Redes</div>
  <?php foreach ($nets as $n): ?>
    <form method="post" action="<?= site_url('red/entrar') ?>"><?= csrf_field() ?><input type="hidden" name="network_id" value="<?= (int) $n['id'] ?>">
      <button type="submit" class="menu-item" <?= (int) $n['id'] === (int) ($currentNet ?? 0) ? 'aria-current="true"' : '' ?>>
        <?= icon('network', 16, 'soft') ?><span class="truncate"><?= esc($n['name']) ?></span>
        <span class="soft text-xs ml-auto"><?= esc(plural((int) $n['schools'], 'colegio', 'colegios')) ?></span>
      </button></form>
  <?php endforeach ?>
  <?php if ($insts): ?><div class="menu-sep"></div><?php endif ?>
<?php endif ?>
<?php if ($insts): ?>
  <div class="menu-label">Instituciones</div>
  <?php foreach ($insts as $i): ?>
    <form method="post" action="<?= site_url('instituciones/cambiar') ?>"><?= csrf_field() ?><input type="hidden" name="institution_id" value="<?= (int) $i['id'] ?>">
      <button type="submit" class="menu-item" <?= (int) $i['id'] === (int) ($currentInst ?? 0) ? 'aria-current="true"' : '' ?>>
        <?= icon('school', 16, 'soft') ?><span class="truncate"><?= esc($i['name']) ?></span>
        <span class="soft text-xs ml-auto"><?= esc($i['role_name']) ?></span>
      </button></form>
  <?php endforeach ?>
<?php endif ?>
