<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
/**
 * Mi perfil: datos y foto, contraseña, accesibilidad (6.6) y avisos (11.1). Datos reales de la persona en sesión.
 *
 * @var array $user, $prefs, $sizes, $memberships, $mfa (enabled, required, since, left, pending, codes)
 * @var bool  $mailReady
 */
$err  = session('errors') ?? [];
$e    = static fn (string $k): string => isset($err[$k]) ? '<span class="field-error" id="err-' . $k . '">' . esc($err[$k]) . '</span>' : '';
$inv  = static fn (string $k): string => isset($err[$k]) ? ' aria-invalid="true" aria-describedby="err-' . $k . '"' : '';
$name = old('name', $user['name']);
?>
<div class="page-header">
  <div><h1>Mi perfil</h1><p><?= esc(person_id($user)) ?></p></div>
</div>
<?php if (! empty($user['must_change_password'])): ?>
  <div class="notice notice-warn mb-4" role="alert"><?= icon('key-round', 16) ?><span><strong class="font-medium">Cambia tu contraseña para continuar.</strong> La que usaste la asignó otra persona; escribe una nueva solo tuya en «Contraseña».</span></div>
<?php endif ?>

<div class="grid-2" style="align-items:start">
  <form id="profile-form" class="stack" method="post" action="<?= site_url('perfil') ?>" x-data="{ dirty: false }" @input="dirty = true" @change="dirty = true">
    <?= csrf_field() ?>
    <section class="card">
      <div class="card-header"><h2>Datos personales</h2></div>
      <div class="card-body stack">
        <label class="field"><span class="label">Nombre <span class="text-danger">*</span></span>
          <input class="input" name="name" value="<?= esc($name, 'attr') ?>" maxlength="120" required autocomplete="name"<?= $inv('name') ?>><?= $e('name') ?></label>
        <?php if (! empty($user['doc_number'])): ?><label class="field"><span class="label">Documento</span><input class="input" value="<?= esc(\App\Models\UserModel::docLabel($user['doc_type'], $user['doc_number']), 'attr') ?>" readonly style="background:var(--app-muted)"></label><?php endif ?>
        <label class="field"><span class="label">Correo</span><input class="input" value="<?= esc($user['email'] ?: '—', 'attr') ?>" readonly style="background:var(--app-muted)">
          <span class="hint">Con este correo inicias sesión. Para cambiarlo, pídeselo al administrador de tu institución.</span></label>
        <?php if ($memberships || ! empty($isSuperadmin)): ?>
          <div class="field"><span class="label">Dónde participas</span>
            <ul class="plain-list text-sm">
              <?php if (! empty($isSuperadmin)): ?><li><span class="font-medium">Plataforma</span> · <span class="soft">Superadministrador</span></li><?php endif ?>
              <?php foreach ($memberships as $m): ?><li><span class="font-medium"><?= esc($m['institution']) ?></span> · <span class="soft"><?= esc($m['role'] ?? 'Sin rol') ?></span></li><?php endforeach ?>
            </ul></div>
        <?php endif ?>
      </div>
    </section>

    <section class="card">
      <div class="card-header"><h2>Accesibilidad</h2></div>
      <div class="card-body stack">
        <label class="field"><span class="label">Tamaño del texto</span>
          <select class="select" name="text_size"><?php foreach ($sizes as $k => $l): ?><option value="<?= $k ?>" <?= ($user['text_size'] ?? 'normal') === $k ? 'selected' : '' ?>><?= esc($l) ?></option><?php endforeach ?></select></label>
        <?php foreach ([['reduce_motion', 'Reducir movimiento', 'Quita animaciones y transiciones.'], ['tutor_tts', 'Lectura en voz alta en el tutor', 'El tutor lee sus respuestas con la voz del navegador.']] as [$k, $t, $d]): ?>
          <label class="toggle" x-data="{ on: <?= ! empty($user[$k]) ? 'true' : 'false' ?> }">
            <span><span class="block t-title"><?= esc($t) ?></span><span class="block t-desc"><?= esc($d) ?></span></span>
            <input type="hidden" name="<?= $k ?>" :value="on ? 1 : 0">
            <button type="button" class="switch" role="switch" :aria-checked="on.toString()" @click="on = !on; dirty = true" aria-label="<?= esc($t, 'attr') ?>"></button>
          </label>
        <?php endforeach ?>
      </div>
    </section>

    <section class="card">
      <div class="card-header"><h2>Avisos</h2></div>
      <div class="table-wrap" style="border:0;border-radius:0">
        <table class="table notif-table" style="min-width:0">
          <thead><tr><th>Aviso</th><th class="text-right">En la plataforma</th><th class="text-right">Correo</th></tr></thead>
          <tbody>
            <?php foreach ($prefs as $k => $p): ?>
              <tr>
                <td><?= esc($p['label']) ?><span class="meta"><?= esc($p['hint']) ?></span></td>
                <td class="text-right"><input type="checkbox" name="in_app[<?= $k ?>]" value="1" <?= $p['in_app'] ? 'checked' : '' ?> aria-label="<?= esc($p['label'], 'attr') ?> en la plataforma"></td>
                <td class="text-right"><input type="checkbox" name="email[<?= $k ?>]" value="1" <?= $p['email'] ? 'checked' : '' ?> aria-label="<?= esc($p['label'], 'attr') ?> por correo"></td>
              </tr>
            <?php endforeach ?>
          </tbody>
        </table>
      </div>
      <?php if (! $mailReady): ?><div class="card-body"><p class="hint">La plataforma aún no envía correos: tu elección queda guardada y se aplicará cuando se configure el correo saliente.</p></div><?php endif ?>
    </section>

    <div class="row" style="justify-content:flex-end">
      <span class="soft text-sm" x-show="dirty" x-cloak>Tienes cambios sin guardar</span>
      <button type="submit" class="btn btn-primary">Guardar cambios</button>
    </div>
  </form>

  <div class="stack">
    <section class="card">
      <div class="card-header"><h2>Foto</h2></div>
      <div class="card-body">
        <div class="row" style="gap:1rem;align-items:center">
          <?= my_avatar($user, 64) ?>
          <div class="stack" style="gap:0.5rem">
            <form method="post" action="<?= site_url('perfil/foto') ?>" enctype="multipart/form-data" x-data>
              <?= csrf_field() ?>
              <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="sr-only" id="photo-input" x-ref="f" @change="$el.form.submit()">
              <button type="button" class="btn btn-sm" @click="$refs.f.click()"><?= icon('upload', 14) ?> <?= empty($user['avatar_path']) ? 'Subir foto' : 'Cambiar foto' ?></button>
            </form>
            <?php if (! empty($user['avatar_path'])): ?>
              <form method="post" action="<?= site_url('perfil/foto/quitar') ?>"><?= csrf_field() ?>
                <button type="submit" class="btn btn-sm btn-danger"><?= icon('trash-2', 14) ?> Quitar foto</button></form>
            <?php endif ?>
            <span class="hint">JPG, PNG o WebP de hasta 2 MB. Se recorta en cuadrado.</span>
          </div>
        </div>
      </div>
    </section>

    <section class="card" id="seguridad">
      <div class="card-header"><h2>Contraseña</h2></div>
      <form class="card-body stack" method="post" action="<?= site_url('perfil/contrasena') ?>">
        <?= csrf_field() ?>
        <input type="text" name="username" value="<?= esc($user['email'] ?: ($user['doc_number'] ?? ''), 'attr') ?>" autocomplete="username" class="sr-only" tabindex="-1" aria-hidden="true" readonly>
        <label class="field"><span class="label">Contraseña actual</span><input class="input" type="password" name="current" required autocomplete="current-password"<?= $inv('current') ?>><?= $e('current') ?></label>
        <label class="field"><span class="label">Nueva contraseña</span><input class="input" type="password" name="new" required minlength="10" autocomplete="new-password"<?= $inv('new') ?>>
          <?= $e('new') ?: '<span class="hint">Mínimo 10 caracteres. Evita la misma de otros sitios.</span>' ?></label>
        <label class="field"><span class="label">Repite la nueva contraseña</span><input class="input" type="password" name="confirm" required minlength="10" autocomplete="new-password"<?= $inv('confirm') ?>><?= $e('confirm') ?></label>
        <div class="row" style="justify-content:space-between">
          <span class="meta"><?= ! empty($user['password_changed_at']) ? 'Último cambio: ' . esc(date_human($user['password_changed_at'])) : '' ?></span>
          <button type="submit" class="btn"><?= icon('key-round', 14) ?> Cambiar contraseña</button>
        </div>
      </form>
    </section>

    <section class="card" id="dos-pasos">
      <div class="card-header"><h2>Verificación en dos pasos</h2>
        <?= $mfa['enabled'] ? badge('Activa', 'ok') : ($mfa['required'] ? badge('Obligatoria', 'warn') : badge('Desactivada')) ?></div>
      <div class="card-body stack">
        <?php if ($mfa['codes']): ?>
          <div class="notice notice-accent" x-data role="status"><?= icon('key-round', 16) ?>
            <div style="flex:1;min-width:0">
              <p><strong class="font-medium">Guarda tus códigos de respaldo.</strong> Sirven para entrar si pierdes el celular; cada uno se usa una sola vez y no se volverán a mostrar.</p>
              <ul class="mfa-codes" x-ref="codes"><?php foreach ($mfa['codes'] as $c): ?><li><code><?= esc($c) ?></code></li><?php endforeach ?></ul>
              <div class="row" style="gap:0.5rem">
                <button type="button" class="btn btn-sm" @click="navigator.clipboard.writeText([...$refs.codes.querySelectorAll('code')].map(c => c.textContent).join('\n')).then(() => window.toast('Códigos copiados.', 'ok'))"><?= icon('copy', 14) ?> Copiar</button>
                <button type="button" class="btn btn-sm" @click="const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob(['<?= esc(config('AgendaEscolar')->appName, 'js') ?> · códigos de respaldo de <?= esc(person_id($user), 'js') ?>\n\n' + [...$refs.codes.querySelectorAll('code')].map(c => c.textContent).join('\n') + '\n'], { type: 'text/plain' })); a.download = 'codigos-respaldo.txt'; a.click(); URL.revokeObjectURL(a.href);"><?= icon('download', 14) ?> Descargar</button>
              </div>
            </div>
          </div>
        <?php endif ?>

        <?php if ($mfa['enabled']): ?>
          <p class="text-sm">Al iniciar sesión te pedimos el código de tu aplicación autenticadora. Activa desde <?= esc(date_human($mfa['since'], false)) ?>.</p>
          <p class="text-sm <?= $mfa['left'] <= 2 ? 'text-warn' : 'soft' ?>">Te quedan <?= plural($mfa['left'], 'código de respaldo', 'códigos de respaldo') ?>.</p>
          <details class="mfa-action">
            <summary class="btn btn-sm"><?= icon('refresh-cw', 14) ?> Generar nuevos códigos de respaldo</summary>
            <form method="post" action="<?= site_url('perfil/dos-pasos/codigos') ?>" class="stack mt-2">
              <?= csrf_field() ?>
              <div class="grid-2">
                <label class="field"><span class="label">Contraseña</span><input class="input" type="password" name="password" required autocomplete="current-password"></label>
                <label class="field"><span class="label">Código actual</span><input class="input" name="code" required inputmode="numeric" autocomplete="one-time-code" maxlength="11"></label>
              </div>
              <div><button type="submit" class="btn btn-sm">Generar códigos</button></div>
            </form>
          </details>
          <?php if ($mfa['required']): ?>
            <p class="hint">Por tu rol, la verificación en dos pasos es obligatoria y no se puede desactivar.</p>
          <?php else: ?>
            <details class="mfa-action">
              <summary class="btn btn-sm btn-danger"><?= icon('shield-off', 14) ?> Desactivar</summary>
              <form method="post" action="<?= site_url('perfil/dos-pasos/desactivar') ?>" class="stack mt-2">
                <?= csrf_field() ?>
                <div class="grid-2">
                  <label class="field"><span class="label">Contraseña</span><input class="input" type="password" name="password" required autocomplete="current-password"></label>
                  <label class="field"><span class="label">Código actual</span><input class="input" name="code" required inputmode="numeric" autocomplete="one-time-code" maxlength="11"></label>
                </div>
                <div><button type="submit" class="btn btn-sm btn-danger">Desactivar la verificación</button></div>
              </form>
            </details>
          <?php endif ?>

        <?php elseif ($mfa['pending']): ?>
          <ol class="mfa-steps text-sm">
            <li>Instala una aplicación autenticadora en tu celular (Google Authenticator, Microsoft Authenticator u otra).</li>
            <li>En la aplicación, agrega una cuenta y escanea este código:</li>
          </ol>
          <div class="mfa-qr" id="mfa-qr" role="img" aria-label="Código QR para la aplicación autenticadora"></div>
          <script src="<?= base_url('assets/vendor/qrcode-generator.js') ?>"></script>
          <script>
            (function () {
              try { var q = qrcode(0, 'M'); q.addData(<?= json_encode($mfa['pending']['uri']) ?>); q.make();
                document.getElementById('mfa-qr').innerHTML = q.createSvgTag({ cellSize: 5, margin: 2, scalable: true }); } catch (e) {}
            })();
          </script>
          <p class="text-sm">¿No puedes escanear? Escribe esta clave en la aplicación:<br><code class="mfa-key"><?= esc(trim(chunk_split($mfa['pending']['secret'], 4, ' '))) ?></code></p>
          <form method="post" action="<?= site_url('perfil/dos-pasos/activar') ?>" class="stack">
            <?= csrf_field() ?>
            <label class="field"><span class="label">3. Escribe el código de 6 dígitos que muestra la aplicación</span>
              <input class="input" name="code" required inputmode="numeric" autocomplete="one-time-code" maxlength="7" placeholder="000000" style="max-width:10rem"></label>
            <div class="row" style="gap:0.5rem"><button type="submit" class="btn btn-primary btn-sm">Activar</button></div>
          </form>
          <form method="post" action="<?= site_url('perfil/dos-pasos/cancelar') ?>"><?= csrf_field() ?><button type="submit" class="btn btn-sm">Cancelar</button></form>

        <?php else: ?>
          <?php if ($mfa['required']): ?>
            <div class="notice notice-warn" role="alert"><?= icon('shield-alert', 16) ?><span><strong class="font-medium">Por tu rol debes activarla</strong> para seguir usando la plataforma.</span></div>
          <?php endif ?>
          <p class="text-sm soft">Además de la contraseña, al entrar te pediremos un código de la aplicación autenticadora de tu celular. Así nadie entra a tu cuenta aunque sepa tu contraseña.</p>
          <form method="post" action="<?= site_url('perfil/dos-pasos/iniciar') ?>"><?= csrf_field() ?><button type="submit" class="btn btn-sm btn-primary"><?= icon('shield-check', 14) ?> Activar la verificación</button></form>
        <?php endif ?>
      </div>
    </section>
  </div>
</div>
<?= $this->endSection() ?>
