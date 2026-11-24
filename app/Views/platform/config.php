<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
/**
 * Consola › Configuración, en pestañas: General (asistente y contacto comercial), Marca (color y fuente), Correo (SMTP),
 * Base de datos (cambios pendientes) y Sistema (entorno y plantillas de rol). La pestaña se elige con el #ancla
 * (#correo, #base-de-datos…), así las redirecciones después de guardar vuelven a la pestaña correcta.
 * La clave y los modelos de IA están en Consola › IA. Los secretos nunca se muestran.
 *
 * @var array $mail Mailer::describe(), $roles, $assistant, $contact, $dbState; string $env, $tz
 */
$tabs    = array (
  'general' => 
  array (
    0 => 'General',
    1 => 'settings',
  ),
  'marca' => 
  array (
    0 => 'Marca',
    1 => 'palette',
  ),
  'correo' => 
  array (
    0 => 'Correo',
    1 => 'mail',
  ),
  'base-de-datos' => 
  array (
    0 => 'Base de datos',
    1 => 'database',
  ),
  'sistema' => 
  array (
    0 => 'Sistema',
    1 => 'server',
  ),
);
$aliases = array (
  'asistente' => 'general',
  'contacto' => 'general',
  'marca' => 'marca',
  'correo' => 'correo',
  'base-de-datos' => 'base-de-datos',
  'sistema' => 'sistema',
);
?>
<div class="page-header">
  <div><h1>Configuración</h1><p>Ajustes de toda la plataforma. Los secretos nunca se muestran completos.</p></div>
</div>

<div x-data="{ tab: 'general', aliases: <?= esc(json_encode($aliases), 'attr') ?>,
  pick() { const h = location.hash.slice(1); this.tab = this.aliases[h] || 'general'; } }" x-init="pick()" @hashchange.window="pick()">
  <nav class="tabs" role="tablist" aria-label="Secciones de la configuración">
    <?php foreach ($tabs as $k => [$label, $ic]): ?>
      <a class="tab" href="#<?= $k ?>" role="tab" :aria-selected="(tab === '<?= $k ?>').toString()" @click.prevent="tab = '<?= $k ?>'; history.replaceState(null, '', '#<?= $k ?>')"><?= icon($ic, 15) ?> <?= esc($label) ?></a>
    <?php endforeach ?>
  </nav>

  <div class="grid-2" style="align-items:start" x-show="tab === 'general'" role="tabpanel">
  <section class="card" id="asistente">
    <div class="card-header"><h2>Asistente de la interfaz</h2><?= $assistant['enabled'] ? badge('Activo', 'ok') : badge('Apagado') ?></div>
    <form class="card-body stack" method="post" action="<?= site_url('plataforma/asistente') ?>" enctype="multipart/form-data"
          x-data="{ on: <?= $assistant['enabled'] ? 'true' : 'false' ?>, preview: '<?= $assistant['file'] ? esc(site_url('asistente/imagen') . '?v=' . $assistant['file']['version'], 'js') : esc(base_url('assets/img/logo-mark.png'), 'js') ?>' }">
      <?= csrf_field() ?>
      <p class="text-sm soft">Burbuja flotante en todas las pantallas que responde cómo usar la plataforma. Cada persona puede moverla u ocultarla. Sin IA real responde con la guía integrada.</p>
      <label class="toggle"><span><span class="block t-title">Mostrar el asistente</span><span class="block t-desc">Para todas las personas de la plataforma.</span></span>
        <input type="hidden" name="enabled" :value="on ? 1 : 0"><button type="button" class="switch" role="switch" :aria-checked="on.toString()" @click="on = !on" aria-label="Mostrar el asistente"></button></label>
      <label class="field"><span class="label">Nombre</span><input class="input" name="name" maxlength="40" value="<?= esc($assistant['name'], 'attr') ?>" placeholder="Asistente"></label>
      <div class="field"><span class="label">Imagen</span>
        <div class="row" style="gap:1rem;align-items:center">
          <img :src="preview" alt="Imagen actual del asistente" class="assistant-preview">
          <div class="stack-sm">
            <input class="input" type="file" name="avatar" accept="image/gif,image/png,image/webp,image/jpeg" @change="if ($event.target.files[0]) preview = URL.createObjectURL($event.target.files[0])">
            <span class="hint">GIF (se conserva la animación), PNG, WebP o JPG de hasta 2 MB. Se muestra en un círculo de 56 px: mejor una imagen cuadrada.</span>
          </div>
        </div></div>
      <div class="row" style="gap:0.5rem"><button type="submit" class="btn btn-primary btn-sm" data-loading="Guardando…">Guardar</button></div>
    </form>
    <?php if ($assistant['file']): ?>
      <form class="card-body" style="padding-top:0" method="post" action="<?= site_url('plataforma/asistente/imagen/quitar') ?>"><?= csrf_field() ?>
        <button type="submit" class="btn btn-sm"><?= icon('rotate-ccw', 14) ?> Usar la imagen por defecto</button></form>
    <?php endif ?>
  </section>

  <section class="card" id="contacto">
    <div class="card-header"><h2>Contacto comercial</h2><?= $contact['whatsapp'] || $contact['email'] ? badge('Visible', 'ok') : badge('Oculto') ?></div>
    <form class="card-body stack" method="post" action="<?= site_url('plataforma/configuracion/contacto') ?>">
      <?= csrf_field() ?>
      <p class="text-sm soft">Botón flotante de la página de inicio y del inicio de sesión. Vacíos los dos, el botón no aparece.</p>
      <label class="field"><span class="label">WhatsApp</span><input class="input" name="whatsapp" inputmode="tel" maxlength="20" value="<?= esc($contact['whatsapp'], 'attr') ?>" placeholder="573001234567">
        <span class="hint">Con el indicativo del país y solo números.</span></label>
      <label class="field"><span class="label">Correo</span><input class="input" type="email" name="email" maxlength="160" value="<?= esc($contact['email'], 'attr') ?>" placeholder="ventas@tudominio.com"></label>
      <div class="row"><button type="submit" class="btn btn-primary btn-sm" data-loading="Guardando…"><?= icon('save', 14) ?> Guardar</button></div>
    </form>
  </section>
  </div>

  <?php $fonts = \App\Libraries\Brand::FONTS; ?>
  <div class="grid-2" style="align-items:start" x-show="tab === 'marca'" x-cloak role="tabpanel" x-data="{ color: '<?= esc($brand['accent'], 'js') ?>', font: '<?= esc($brand['font'], 'js') ?>', stacks: <?= esc(json_encode(array_map(static fn ($f) => $f[1], $fonts)), 'attr') ?> }">
    <style><?php foreach (array_keys($fonts) as $fk): ?><?= \App\Libraries\Brand::fontFace($fk) ?><?php endforeach ?></style>
    <section class="card" id="marca">
      <div class="card-header"><h2>Marca de la plataforma</h2></div>
      <form class="card-body stack" method="post" action="<?= site_url('plataforma/configuracion/marca') ?>">
        <?= csrf_field() ?>
        <p class="text-sm soft">Color y fuente de la consola, la página principal, el inicio de sesión y toda la plataforma. Cada colegio conserva su propio color (Ajustes › Institución).</p>
        <fieldset class="field"><legend class="label">Color de acento</legend>
          <div class="row" style="gap:0.5rem;flex-wrap:wrap;align-items:center">
            <?php foreach (\App\Libraries\Brand::SWATCHES as $sw): ?>
              <button type="button" class="swatch" style="background:<?= $sw ?>" :aria-pressed="(color.toUpperCase() === '<?= $sw ?>').toString()" @click="color = '<?= $sw ?>'" title="Usar <?= $sw ?>" aria-label="Usar el color <?= $sw ?>"></button>
            <?php endforeach ?>
            <input class="input" type="color" x-model="color" aria-label="Otro color" style="width:3rem;height:2.25rem;padding:0.2rem">
            <input type="hidden" name="accent" :value="color">
            <code class="text-xs soft" x-text="color.toUpperCase()"></code>
          </div></fieldset>
        <label class="field"><span class="label">Fuente</span>
          <select class="select" name="font" x-model="font">
            <?php foreach ($fonts as $fk => [$fname]): ?><option value="<?= $fk ?>"><?= esc($fname) ?></option><?php endforeach ?>
          </select>
          <span class="hint">Las fuentes se sirven desde este servidor (licencia libre OFL), sin servicios externos.</span></label>
        <div class="row"><button type="submit" class="btn btn-primary" data-loading="Guardando…"><?= icon('save', 14) ?> Guardar</button></div>
      </form>
    </section>
    <section class="card">
      <div class="card-header"><h2>Vista previa</h2></div>
      <div class="card-body stack" :style="'font-family:' + stacks[font] + ';--app-accent:' + color">
        <p style="font-size:1.5rem;font-weight:700;line-height:1.25;margin:0">La agenda del colegio, en un solo lugar</p>
        <p class="soft" style="margin:0">Los docentes publican contenido y actividades; los estudiantes aprenden con un tutor que los guía.</p>
        <div class="row" style="gap:0.5rem;flex-wrap:wrap">
          <span class="btn" :style="'background:' + color + ';color:#fff;border-color:transparent'">Botón principal</span>
          <span class="btn">Botón secundario</span>
          <span class="badge" :style="'background:color-mix(in srgb,' + color + ' 14%, #fff);color:' + color">Etiqueta</span>
          <a href="#marca" @click.prevent :style="'color:' + color">Un enlace</a>
        </div>
      </div>
    </section>
  </div>
  <div class="grid-2" style="align-items:start" x-show="tab === 'correo'" x-cloak role="tabpanel">
  <?php $m = $mail; $old = static fn (string $k, $def) => old($k) ?? $def; ?>
  <section class="card" id="correo">
    <div class="card-header"><h2>Servidor de correo (SMTP)</h2>
      <?= $m['configured'] ? badge($m['source'] === 'env' ? 'Configurado en .env' : 'Configurado', 'ok') : badge('Sin configurar', 'warn') ?></div>
    <form class="card-body stack" method="post" action="<?= site_url('plataforma/configuracion/correo') ?>" autocomplete="off">
      <?= csrf_field() ?>
      <p class="text-sm soft">Un solo servidor para toda la plataforma. Cada colegio sale con su nombre como remitente y su correo como «responder a».
        Datos de su proveedor: en cPanel, «Cuentas de correo electrónico» › «Connect Devices».</p>
      <div class="grid-2">
        <label class="field"><span class="label">Servidor SMTP <span class="text-danger">*</span></span><input class="input" name="host" required maxlength="190" value="<?= esc($old('host', $m['host']), 'attr') ?>" placeholder="mail.sudominio.com"></label>
        <div class="grid-2" style="gap:0.75rem">
          <label class="field"><span class="label">Puerto <span class="text-danger">*</span></span><input class="input" name="port" inputmode="numeric" required maxlength="5" value="<?= esc((string) $old('port', $m['port'] ?: 587), 'attr') ?>"></label>
          <label class="field"><span class="label">Cifrado</span>
            <select class="select" name="crypto"><?php foreach (\App\Libraries\Mailer::CRYPTOS as $k => $l): ?><option value="<?= $k ?>" <?= (string) $old('crypto', $m['crypto']) === (string) $k ? 'selected' : '' ?>><?= esc($l) ?></option><?php endforeach ?></select></label>
        </div>
        <label class="field"><span class="label">Usuario</span><input class="input" name="user" maxlength="190" autocomplete="off" value="<?= esc($old('user', $m['user']), 'attr') ?>" placeholder="notificaciones@sudominio.com"></label>
        <label class="field"><span class="label">Contraseña del correo</span><input class="input" type="password" name="smtp_pass" autocomplete="new-password" placeholder="<?= $m['has_pass'] && $m['source'] === 'console' ? 'Guardada · déjela vacía para conservarla' : '' ?>">
          <span class="hint">Se guarda cifrada y no se vuelve a mostrar.</span></label>
        <label class="field"><span class="label">Correo remitente <span class="text-danger">*</span></span><input class="input" type="email" name="from" required maxlength="160" value="<?= esc($old('from', $m['from']), 'attr') ?>" placeholder="notificaciones@sudominio.com">
          <span class="hint">Debe pertenecer a la cuenta del servidor, o el correo puede llegar a spam.</span></label>
        <label class="field"><span class="label">Nombre del remitente</span><input class="input" name="from_name" maxlength="80" value="<?= esc($old('from_name', $m['from_name']), 'attr') ?>" placeholder="<?= esc(config('AgendaEscolar')->appName, 'attr') ?>"></label>
      </div>
      <div class="row" style="gap:0.5rem;align-items:end;flex-wrap:wrap">
        <label class="field" style="flex:1;min-width:14rem"><span class="label">Su contraseña de superadministrador</span><input class="input" type="password" name="password" required autocomplete="current-password"></label>
        <button type="submit" class="btn btn-primary" data-loading="Guardando…"><?= icon('save', 14) ?> Guardar</button>
      </div>
    </form>
  </section>

  <div class="stack">
    <section class="card">
      <div class="card-header"><h2>Enviar una prueba</h2></div>
      <form class="card-body stack" method="post" action="<?= site_url('plataforma/configuracion/correo/probar') ?>">
        <?= csrf_field() ?>
        <p class="text-sm soft">Confirma que el servidor acepta y entrega el correo.</p>
        <label class="field"><span class="label">Enviar a</span><input class="input" type="email" name="to" maxlength="160" value="<?= esc($user['email'] ?? '', 'attr') ?>"></label>
        <div class="row"><button type="submit" class="btn" <?= $m['configured'] ? '' : 'disabled' ?> data-loading="Enviando…"><?= icon('send', 14) ?> Enviar correo de prueba</button></div>
      </form>
    </section>
    <section class="card">
      <div class="card-header"><h2>Para qué se usa</h2></div>
      <div class="card-body stack-sm text-sm">
        <p>Por ahora, para el correo de prueba. Los avisos de la plataforma (actividades, notas, eventos) se ven dentro de la plataforma, en la campana.</p>
        <?php if ($m['source'] === 'env'): ?><p class="hint">Hoy se usa la configuración del <code>.env</code>. Si guarda aquí, esta tiene prioridad.</p><?php endif ?>
      </div>
      <?php if ($m['source'] === 'console'): ?>
        <form class="card-body row" style="padding-top:0;gap:0.5rem;align-items:end" method="post" action="<?= site_url('plataforma/configuracion/correo/quitar') ?>" autocomplete="off"
              x-data @submit="if (!confirm('¿Quitar la configuración de correo guardada en la consola?')) $event.preventDefault()">
          <?= csrf_field() ?>
          <label class="field" style="flex:1"><span class="label">Su contraseña</span><input class="input" type="password" name="password" required autocomplete="current-password"></label>
          <button type="submit" class="btn btn-danger"><?= icon('trash-2', 14) ?> Quitar</button>
        </form>
      <?php endif ?>
    </section>
  </div>
  </div>

  <div class="grid-2" style="align-items:start" x-show="tab === 'base-de-datos'" x-cloak role="tabpanel">
  <?php $db = $dbState; $np = count($db['pending']); ?>
  <section class="card" id="base-de-datos">
    <div class="card-header"><h2>Base de datos</h2><?= $db['error'] ? badge('Sin leer', 'danger') : ($np ? badge(plural($np, 'cambio pendiente', 'cambios pendientes'), 'warn') : badge('Al día', 'ok')) ?></div>
    <div class="card-body stack-sm">
      <?php if ($db['error']): ?>
        <p class="hint" style="color:var(--danger)"><?= esc($db['error']) ?> Revise <code>writable/logs</code>.</p>
      <?php else: ?>
        <dl class="plan-limits">
          <div><dt>Último cambio aplicado</dt><dd><?= $db['last'] ? esc($db['last']['name']) . ' · ' . esc($db['last']['at']) : '—' ?></dd></div>
        </dl>
        <?php if ($np): ?>
          <div class="text-sm"><span class="font-medium">Pendientes:</span>
            <ul style="margin:0.25rem 0 0;padding-left:1.25rem;list-style:disc">
              <?php foreach ($db['pending'] as $m): ?><li><?= esc($m['name']) ?> <span class="soft text-xs">(<?= esc($m['version']) ?>)</span></li><?php endforeach ?>
            </ul></div>
        <?php endif ?>
      <?php endif ?>
      <p class="hint">Después de subir una versión nueva de la plataforma, aplique aquí los cambios de la base de datos (equivale a <code>php spark migrate</code>). <strong>Antes, haga una copia de la base</strong> desde phpMyAdmin o «Copias de seguridad» de cPanel.</p>
    </div>
    <?php if (! $db['error'] && $np): ?>
      <form class="card-body row" style="padding-top:0;gap:0.5rem;align-items:end" method="post" action="<?= site_url('plataforma/configuracion/base-de-datos') ?>" autocomplete="off"
            x-data @submit="if (!confirm('¿Aplicar <?= $np ?> <?= $np === 1 ? 'cambio' : 'cambios' ?> a la base de datos? ¿Ya hizo la copia de seguridad?')) $event.preventDefault()">
        <?= csrf_field() ?>
        <label class="field" style="flex:1"><span class="label">Su contraseña</span><input class="input" type="password" name="password" required autocomplete="current-password"></label>
        <button type="submit" class="btn btn-primary btn-sm" data-loading="Actualizando…"><?= icon('database', 14) ?> Actualizar base de datos</button>
      </form>
    <?php endif ?>
  </section>
  </div>

  <div class="grid-2" style="align-items:start" x-show="tab === 'sistema'" x-cloak role="tabpanel" id="sistema">
  <section class="card">
    <div class="card-header"><h2>Entorno</h2></div>
    <div class="card-body">
      <dl class="plan-limits">
        <div><dt>Modo</dt><dd><?= $env === 'production' ? 'Producción' : esc(ucfirst($env)) ?></dd></div>
        <div><dt>Zona horaria</dt><dd><?= esc($tz) ?></dd></div>
        <div><dt>Versión de PHP</dt><dd><?= esc(PHP_VERSION) ?></dd></div>
        <div><dt>CodeIgniter</dt><dd><?= esc(\CodeIgniter\CodeIgniter::CI_VERSION) ?></dd></div>
      </dl>
    </div>
  </section>

  <section class="card">
    <div class="card-header"><h2>Plantillas de rol globales</h2></div>
    <div class="date-list">
      <?php foreach ($roles as $r): ?>
        <div class="date-row"><?= icon('shield', 18, 'soft') ?>
          <span style="flex:1;min-width:0"><span class="block font-medium"><?= esc($r['name']) ?></span><span class="block text-xs soft"><?= esc(($r['description'] ?? '') . ' · ' . plural((int) $r['perms_count'], 'permiso', 'permisos')) ?></span></span>
        </div>
      <?php endforeach ?>
    </div>
    <div class="card-body" style="padding-top:0"><p class="hint">Cada institución recibe una copia al crearse; los roles son fijos y se asignan a cada persona en Ajustes › Personas.</p></div>
  </section>
  </div>
</div>
<?= $this->endSection() ?>
