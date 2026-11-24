<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php /** Consola de la red › Administradores: quién gestiona la red. @var array $members */ ?>
<div x-data="{ open: <?= session('errors') ? 'true' : 'false' ?> }">
  <div class="page-header">
    <div><h1>Administradores de la red</h1><p>Administradores: ven el resumen y entran a los colegios (queda en la auditoría). Analistas: solo ven el resumen.</p></div>
    <div class="actions"><button type="button" class="btn btn-primary" @click="open = true"><?= icon('user-plus', 16) ?> Agregar persona</button></div>
  </div>
  <section class="panel">
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Persona</th><th>Rol</th><th>Desde</th><th class="text-right">Acciones</th></tr></thead>
        <tbody>
          <?php foreach ($members as $m): ?>
            <tr>
              <td><span class="font-medium"><?= esc($m['name']) ?></span><span class="meta"><?= esc($m['email']) ?></span></td>
              <td><?= $m['role'] === 'admin' ? badge('Administrador', 'accent') : badge('Analista') ?></td>
              <td class="nowrap"><?= esc(date_short($m['created_at'], false)) ?></td>
              <td class="text-right">
                <form method="post" action="<?= site_url('red/administradores/' . $m['id'] . '/quitar') ?>" onsubmit="return confirm('¿Quitar a <?= esc($m['name'], 'js') ?> de la red?')">
                  <?= csrf_field() ?><button type="submit" class="btn btn-sm btn-danger" title="Quitar a <?= esc($m['name'], 'attr') ?> de la red" aria-label="Quitar a <?= esc($m['name'], 'attr') ?> de la red"><?= icon('user-minus', 14) ?> Quitar</button>
                </form>
              </td>
            </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>
  </section>

  <div class="modal-layer" x-show="open" x-cloak @keydown.escape.window="open = false">
    <div class="veil" @click="open = false"></div>
    <div class="modal lg" role="dialog" aria-modal="true" aria-labelledby="nm-title">
      <form method="post" action="<?= site_url('red/administradores') ?>">
        <?= csrf_field() ?>
        <div class="modal-head"><h2 id="nm-title">Agregar persona a la red</h2><button type="button" class="btn btn-ghost btn-sm btn-icon" @click="open = false" aria-label="Cerrar">×</button></div>
        <div class="modal-body stack">
          <label class="field"><span class="label">Correo <span class="text-danger">*</span></span><input class="input" type="email" name="email" required maxlength="160" value="<?= esc(old('email') ?? '', 'attr') ?>"><span class="hint">La persona debe tener ya una cuenta en la plataforma.</span></label>
          <label class="field"><span class="label">Rol</span>
            <select class="select" name="role"><option value="admin">Administrador: resumen y entrar a los colegios</option><option value="analyst">Analista: solo el resumen</option></select></label>
        </div>
        <div class="modal-foot"><button type="button" class="btn" @click="open = false">Cancelar</button><button type="submit" class="btn btn-primary" data-loading="Agregando…">Agregar</button></div>
      </form>
    </div>
  </div>
</div>
<?= $this->endSection() ?>
