<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
/** Ajustes › Personas (datos reales): membresías con rol, sede, vigencia y estado; alta y edición en Drawer. */
$grantable   = array_values(array_filter($roles, static fn ($r) => $r['grantable']));
$defaultRole = '';
foreach ($grantable as $r) {
    if ($r['template_key'] === 'teacher') {
        $defaultRole = (string) $r['id'];
    }
}
$defaultRole = $defaultRole ?: (string) ($grantable[0]['id'] ?? '');
$blank  = ['id' => null, 'name' => '', 'email' => '', 'doc_type' => 'TI', 'doc_number' => '', 'role_id' => $defaultRole, 'campus_id' => '', 'starts_on' => date('Y-m-d'), 'ends_on' => '', 'status' => 'active'];
$today  = date('Y-m-d');
$canManage = in_array('tenant.users.manage', $perms, true);
$total  = array_sum($counts);
?>
<div x-data="crud({ blank: <?= esc(json_encode($blank), 'attr') ?>, reopen: <?= esc(json_encode(session('reopen')), 'attr') ?> })" @keydown.escape.window="close()">
  <div class="page-header">
    <div><h1>Ajustes</h1><p><?= esc($institution['name']) ?></p></div>
  </div>
  <?= view('settings/_tabs', ['section' => 'usuarios']) ?>
  <?php if ($tp = session('tempPassword')): ?>
    <div class="notice notice-accent mb-4" x-data><?= icon('key-round', 16) ?>
      <span>Contraseña temporal de <strong class="font-medium"><?= esc($tp['name']) ?></strong> (<?= esc($tp['email']) ?>):
        <code class="font-medium" x-ref="tp"><?= esc($tp['temp']) ?></code>.
        Entréguesela por un medio seguro: no se volverá a mostrar. Al entrar, se le pedirá cambiarla.</span>
      <button type="button" class="btn btn-sm ml-auto" @click="navigator.clipboard.writeText($refs.tp.textContent).then(() => window.toast('Contraseña copiada.', 'ok'))"><?= icon('copy', 14) ?> Copiar</button>
    </div>
  <?php endif ?>

  <nav class="chips mb-4" aria-label="Filtrar por rol">
    <a class="chip" href="<?= site_url('ajustes/usuarios') ?>" aria-selected="<?= $filters['rol'] === '' ? 'true' : 'false' ?>">Todos (<?= (int) $total ?>)</a>
    <?php foreach ($roles as $r): if (empty($counts[$r['id']])) { continue; } ?>
      <a class="chip" href="<?= site_url('ajustes/usuarios?rol=' . $r['id']) ?>" aria-selected="<?= (string) $filters['rol'] === (string) $r['id'] ? 'true' : 'false' ?>"><?= esc($r['name']) ?> (<?= (int) $counts[$r['id']] ?>)</a>
    <?php endforeach ?>
  </nav>

  <section class="panel">
    <form method="get" class="panel-bar" role="search">
      <?php if ($filters['rol']): ?><input type="hidden" name="rol" value="<?= (int) $filters['rol'] ?>"><?php endif ?>
      <label class="input-icon"><span class="sr-only">Buscar persona</span><?= icon('search', 16) ?><input class="input" type="search" name="q" value="<?= esc($filters['q']) ?>" placeholder="Buscar por nombre, correo o documento"></label>
      <select class="select" name="estado" aria-label="Estado" onchange="this.form.submit()">
        <option value="">Todos los estados</option>
        <option value="active" <?= $filters['estado'] === 'active' ? 'selected' : '' ?>>Activos</option>
        <option value="inactive" <?= $filters['estado'] === 'inactive' ? 'selected' : '' ?>>Inactivos</option>
      </select>
      <?php if (array_filter($filters)): ?>
        <a class="btn btn-ghost btn-sm btn-icon" href="<?= site_url('ajustes/usuarios') ?>" aria-label="Limpiar filtros" title="Limpiar filtros"><?= icon('x', 16) ?></a>
      <?php endif ?>
      <?php if ($canManage): ?>
        <a class="btn panel-action" href="<?= site_url('ajustes/usuarios/importar') ?>"><?= icon('file-up', 16) ?> Importar</a>
        <button type="button" class="btn btn-primary" @click="create()"><?= icon('user-plus', 16) ?> Agregar persona</button>
      <?php endif ?>
    </form>

    <?php if ($rows === []): ?>
      <div class="empty"><?= icon('users', 32) ?><p><?= array_filter($filters) ? 'Nadie coincide con la búsqueda. Pruebe otro nombre o limpie los filtros.' : 'Aún no hay personas. Use «Agregar persona».' ?></p></div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Persona</th><th>Rol</th><th>Sede</th><th>Último acceso</th><th>Estado</th><th class="text-right">Acciones</th></tr></thead>
          <tbody>
            <?php foreach ($rows as $r):
                $data = ['id' => (int) $r['id'], 'name' => $r['name'], 'email' => $r['email'], 'ident' => person_id($r), 'role_id' => (string) $r['role_id'], 'campus_id' => (string) ($r['campus_id'] ?? ''),
                    'starts_on' => (string) ($r['starts_on'] ?? ''), 'ends_on' => (string) ($r['ends_on'] ?? ''), 'status' => $r['status']];
                $expired = $r['ends_on'] && $r['ends_on'] < $today; ?>
              <tr>
                <td>
                  <span class="row">
                    <span class="avatar" aria-hidden="true"><?= esc(initials($r['name'])) ?></span>
                    <span style="min-width:0"><span class="font-medium"><?= esc($r['name']) ?><?= (int) $r['user_id'] === $meId ? ' <span class="soft">(usted)</span>' : '' ?></span>
                      <span class="meta"><?= esc(person_id($r)) ?><?= $expired ? ' · <span class="text-danger">acceso vencido</span>' : ($r['ends_on'] ? ' · hasta ' . esc(date_short($r['ends_on'], false)) : '') ?></span></span>
                  </span>
                </td>
                <td><?= esc($r['role_name'] ?? 'Sin rol') ?></td>
                <td><?= esc($r['campus_name'] ?? 'Todas') ?></td>
                <td class="nowrap"><?= $r['last_access_at'] ? esc(date_human($r['last_access_at'])) : '<span class="soft">Nunca</span>' ?></td>
                <td><?= $r['status'] === 'active' && ! $expired ? badge('Activo', 'ok') : badge($expired ? 'Vencido' : 'Inactivo') ?></td>
                <td class="text-right">
                  <?php if ($r['manageable']): ?>
                    <span class="row" style="gap:0.25rem;justify-content:flex-end">
                    <?php if ($r['mfa'] && $r['reset'] === null): ?>
                      <form method="post" action="<?= site_url('ajustes/usuarios/' . $r['id'] . '/dos-pasos') ?>" onsubmit="return confirm('¿Quitar la verificación en dos pasos de <?= esc($r['name'], 'js') ?>? Hazlo solo si perdió su celular y sus códigos de respaldo.')">
                        <?= csrf_field() ?><button type="submit" class="btn btn-sm btn-icon" title="Quitar la verificación en dos pasos de <?= esc($r['name'], 'attr') ?>" aria-label="Quitar la verificación en dos pasos de <?= esc($r['name'], 'attr') ?>"><?= icon('shield-off', 14) ?></button></form>
                    <?php endif ?>
                    <?php if ($r['reset'] === null): ?>
                      <form method="post" action="<?= site_url('ajustes/usuarios/' . $r['id'] . '/contrasena') ?>" onsubmit="return confirm('¿Asignar una contraseña temporal a <?= esc($r['name'], 'js') ?>? Su contraseña actual dejará de funcionar.')">
                        <?= csrf_field() ?><button type="submit" class="btn btn-sm btn-icon" title="Asignar una contraseña temporal a <?= esc($r['name'], 'attr') ?>" aria-label="Asignar una contraseña temporal a <?= esc($r['name'], 'attr') ?>"><?= icon('key-round', 14) ?></button></form>
                    <?php else: ?>
                      <button type="button" class="btn btn-sm btn-icon" disabled title="<?= esc($r['reset'], 'attr') ?>" aria-label="No se puede asignar una contraseña temporal a <?= esc($r['name'], 'attr') ?>: <?= esc($r['reset'], 'attr') ?>"><?= icon('key-round', 14) ?></button>
                    <?php endif ?>
                    <?php if (! empty($isSuperadmin) && (int) $r['user_id'] !== $meId && ! (int) ($r['is_superadmin'] ?? 0) && $r['status'] === 'active' && $r['user_status'] === 'active' && ! $expired): ?>
                      <button type="button" class="btn btn-sm btn-icon" title="Ver como <?= esc($r['name'], 'attr') ?> (solo lectura)" aria-label="Ver como <?= esc($r['name'], 'attr') ?> (solo lectura)"
                              @click="$dispatch('impersonate', <?= esc(json_encode(['id' => (int) $r['user_id'], 'name' => $r['name'], 'role' => $r['role_name'] ?? '']), 'attr') ?>)"><?= icon('eye', 14) ?></button>
                    <?php endif ?>
                    <button type="button" class="btn btn-sm btn-icon" title="Editar a <?= esc($r['name'], 'attr') ?>" aria-label="Editar a <?= esc($r['name'], 'attr') ?>" @click="edit(<?= esc(json_encode($data), 'attr') ?>)"><?= icon('pencil-line', 14) ?></button>
                    </span>
                  <?php else: ?>
                    <span class="soft" title="Tiene permisos que usted no tiene"><?= icon('lock', 14) ?></span>
                  <?php endif ?>
                </td>
              </tr>
            <?php endforeach ?>
          </tbody>
        </table>
      </div>
    <?php endif ?>
  </section>
  <p class="count"><?= plural(count($rows), 'persona', 'personas') ?></p>

  <div class="drawer-layer" x-show="open" x-cloak>
    <div class="veil" @click="close()"></div>
    <aside class="drawer" role="dialog" aria-modal="true" aria-labelledby="u-title">
      <form method="post" novalidate :action="mode === 'create' ? '<?= site_url('ajustes/usuarios') ?>' : '<?= site_url('ajustes/usuarios') ?>/' + form.id">
        <?= csrf_field() ?>
        <div class="drawer-head">
          <h2 id="u-title" x-text="mode === 'create' ? 'Agregar persona' : form.name"></h2>
          <button type="button" class="btn btn-ghost btn-sm btn-icon" @click="close()" aria-label="Cerrar">×</button>
        </div>
        <div class="drawer-body stack">
          <template x-if="mode === 'create'">
            <div class="stack">
              <label class="field"><span class="label">Nombre completo <span class="text-danger">*</span></span><input class="input" name="name" x-model="form.name" maxlength="120" data-autofocus required><?= field_error('name') ?></label>
              <div class="grid-2">
                <label class="field"><span class="label">Tipo de documento</span>
                  <select class="select" name="doc_type" x-model="form.doc_type"><?php foreach (\App\Models\UserModel::DOC_TYPES as $dk => $dl): ?><option value="<?= $dk ?>"><?= esc($dk . ' · ' . $dl) ?></option><?php endforeach ?></select><?= field_error('doc_type') ?></label>
                <label class="field"><span class="label">Número de documento</span><input class="input" name="doc_number" x-model="form.doc_number" inputmode="numeric" maxlength="20" placeholder="1012345678"><?= field_error('doc_number') ?></label>
              </div>
              <label class="field"><span class="label">Correo</span><input class="input" type="email" name="email" x-model="form.email" maxlength="160" placeholder="nombre@institucion.edu.co">
                <span class="hint">Correo o documento: con cualquiera de los dos la persona inicia sesión. Para muchas personas a la vez, use «Importar».</span><?= field_error('email') ?></label>
              <label class="field"><span class="label">Contraseña inicial <span class="text-danger">*</span></span><input class="input" type="password" name="password" autocomplete="new-password" minlength="10" maxlength="72" required>
                <span class="hint">Mínimo 10 caracteres. Compártala por un medio seguro; la persona la cambia en Mi perfil.</span><?= field_error('password') ?></label>
            </div>
          </template>
          <template x-if="mode === 'edit'">
            <div class="notice"><?= icon('id-card', 16, 'soft') ?><span class="font-medium" x-text="form.ident"></span></div>
          </template>

          <fieldset class="fieldset">
            <legend class="label">Rol en esta institución <span class="text-danger">*</span></legend>
            <div class="radio-cards">
              <?php foreach ($roles as $r): ?>
                <label class="radio-card" <?= $r['grantable'] ? '' : 'style="opacity:.55;cursor:not-allowed"' ?>>
                  <input type="radio" name="role_id" value="<?= $r['id'] ?>" x-model="form.role_id" <?= $r['grantable'] ? '' : 'disabled' ?>>
                  <span><span class="block font-medium"><?= esc($r['name']) ?></span><span class="block text-xs soft"><?= esc($r['description'] ?? '') ?></span>
                    <?php if (! $r['grantable']): ?><span class="block text-xs soft">Tiene permisos que usted no tiene: no puede asignarlo.</span><?php endif ?></span>
                </label>
              <?php endforeach ?>
            </div>
            <?= field_error('role_id') ?>
          </fieldset>

          <?php if ($campuses): ?>
            <label class="field"><span class="label">Sede</span>
              <select class="select" name="campus_id" x-model="form.campus_id"><option value="">Todas</option>
                <?php foreach ($campuses as $c): ?><option value="<?= $c['id'] ?>"><?= esc($c['name']) ?></option><?php endforeach ?>
              </select><span class="hint">Limita lo que puede ver a esa sede.</span><?= field_error('campus_id') ?></label>
          <?php endif ?>

          <div class="grid-2" style="gap:0.75rem">
            <label class="field"><span class="label">Acceso desde</span><input class="input" type="date" name="starts_on" x-model="form.starts_on"><?= field_error('starts_on') ?></label>
            <label class="field"><span class="label">Acceso hasta</span><input class="input" type="date" name="ends_on" x-model="form.ends_on"><span class="hint">Vacío = sin vencimiento</span><?= field_error('ends_on') ?></label>
          </div>

          <template x-if="mode === 'edit'">
            <fieldset class="fieldset">
              <legend class="label">Estado</legend>
              <div class="row">
                <label class="btn btn-sm" :class="{ 'btn-active': form.status === 'active' }"><input type="radio" class="sr-only" name="status" value="active" x-model="form.status"> Activo</label>
                <label class="btn btn-sm" :class="{ 'btn-active': form.status === 'inactive' }"><input type="radio" class="sr-only" name="status" value="inactive" x-model="form.status"> Inactivo</label>
              </div>
              <span class="hint">Una persona inactiva no puede entrar a esta institución; su historial se conserva.</span>
            </fieldset>
          </template>
        </div>
        <div class="drawer-foot">
          <button type="button" class="btn" @click="close()">Cancelar</button>
          <button type="submit" class="btn btn-primary" data-loading="Guardando…" x-text="mode === 'create' ? 'Crear' : 'Guardar'"></button>
        </div>
      </form>
    </aside>
  </div>

  <?php if (! empty($isSuperadmin)): /* «Ver como» (suplantación de soporte, solo lectura) */ ?>
    <div class="drawer-layer" x-data="{ on: false, p: {} }" @impersonate.window="p = $event.detail; on = true; $nextTick(() => $refs.reason.focus())" x-show="on" x-cloak @keydown.escape.window="on = false">
      <div class="veil" @click="on = false"></div>
      <aside class="drawer" role="dialog" aria-modal="true" aria-labelledby="imp-title">
        <form method="post" :action="'<?= site_url('suplantacion/iniciar') ?>/' + p.id" autocomplete="off">
          <?= csrf_field() ?>
          <div class="drawer-head">
            <h2 id="imp-title">Ver como <span x-text="p.name"></span></h2>
            <button type="button" class="btn btn-ghost btn-sm btn-icon" @click="on = false" aria-label="Cerrar">×</button>
          </div>
          <div class="drawer-body stack">
            <div class="notice"><?= icon('eye', 16, 'soft') ?><span>Verá la plataforma exactamente como <strong x-text="p.name"></strong> (<span x-text="p.role"></span>), en <strong>solo lectura</strong> y durante <?= \App\Libraries\Impersonation::MINUTES ?> minutos. No podrá guardar nada ni usar la IA.</span></div>
            <label class="field"><span class="label">Motivo <span class="text-danger">*</span></span>
              <input class="input" name="reason" x-ref="reason" minlength="10" maxlength="255" required placeholder="Ej.: caso 1234, no le aparece el grupo 7B">
              <span class="hint">Queda en la auditoría y se avisa a los administradores de la institución.</span></label>
            <label class="field"><span class="label">Su contraseña <span class="text-danger">*</span></span>
              <input class="input" type="password" name="password" required autocomplete="current-password"></label>
          </div>
          <div class="drawer-foot">
            <button type="button" class="btn" @click="on = false">Cancelar</button>
            <button type="submit" class="btn btn-primary" data-loading="Entrando…"><?= icon('eye', 14) ?> Ver como</button>
          </div>
        </form>
      </aside>
    </div>
  <?php endif ?>
</div>
<?= $this->endSection() ?>
