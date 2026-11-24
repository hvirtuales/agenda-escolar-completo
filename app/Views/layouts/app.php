<?php
/**
 * Shell de la aplicación: barra global (rail) + barra superior + contenido.
 * Variables: $user, $institution, $institutions, $perms, $title. Sección opcional 'bare' = '1' para pantallas
 * a altura completa sin relleno (el cuaderno).
 *
 * @var \CodeIgniter\View\View $this
 */
$rail = rail_items($perms ?? [], $railItems ?? null);
$bare = trim($this->renderSection('bare')) === '1';
/* Preferencias de accesibilidad de la persona (Mi perfil): tamaño del texto, menos movimiento, voz del tutor. */
$a11y = trim((in_array($user['text_size'] ?? 'normal', ['large', 'xlarge'], true) ? 'text-' . $user['text_size'] : '') . (! empty($user['reduce_motion']) ? ' reduce-motion' : ''));
?>
<!doctype html>
<html lang="<?= esc(service('locale')->current(), 'attr') ?>"<?= $a11y !== '' ? ' class="' . $a11y . '"' : '' ?> style="<?= esc(brand_vars($institution['accent_color'] ?? ($network['accent_color'] ?? null)), 'attr') ?>">
<head>
  <?= $this->include('partials/head') ?>
</head>
<body<?= ! empty($user['tutor_tts']) ? ' data-tts="1"' : '' ?>>
<div class="app" x-data="shell" @keydown.escape.window="navOpen = false">

  <div class="rail-veil" x-show="navOpen" x-cloak @click="navOpen = false"></div>

  <?php
  $appName  = config('AgendaEscolar')->appName;
  $brandSub = ! empty($platform) ? t('App.platformConsole') : (! empty($network) ? $network['name'] : ($institution['name'] ?? ''));
  // Redes que administra la persona (para «Volver a la red» en el selector de institución).
  $myNetworks = $networks ?? [];
  $homeNet    = null;
  $imp        = service('impersonation')->current(); // «Ver como» (solo lectura)
  // Identificador de rol (barra superior): con qué rol trabaja la persona en este momento.
  // Los roles base se traducen (Roles.*); un rol con nombre propio de la institución se muestra tal cual.
  $roleName  = service('tenant')->role()['name'] ?? null;
  $roleLabel = ! empty($isSuperadmin) ? t('App.roleSuperadmin')
      : (! empty($platform) ? null : (! empty($network) ? t('App.roleNetworkAdmin') : ($roleName !== null ? t('Roles.' . $roleName, [], $roleName) : null)));
  $locale    = service('locale')->current();
  foreach ($myNetworks as $n) {
      if ((int) $n['id'] === (int) ($institution['network_id'] ?? 0) && $n['role'] === 'admin') {
          $homeNet = $n;
      }
  }
  ?>
  <nav class="rail" :class="{ 'is-open': navOpen, 'is-collapsed': collapsed && !navOpen }" aria-label="<?= esc(t('App.mainNav'), 'attr') ?>">
    <a href="<?= site_url(! empty($platform) ? 'plataforma' : (! empty($network) ? 'red' : (empty($studentPreview) ? 'inicio' : 'estudiante'))) ?>" class="rail-brand" title="<?= esc($appName . ($brandSub ? ' · ' . $brandSub : ''), 'attr') ?>">
      <img src="<?= base_url('assets/img/logo-mark.png') ?>" alt="" width="32" height="32">
      <span class="rail-label" style="min-width:0">
        <span class="block name truncate"><?= esc($appName) ?></span>
        <span class="block sub truncate"><?= esc($brandSub) ?></span>
      </span>
    </a>
    <?php foreach ($rail['top'] as $item): ?>
      <a href="<?= site_url($item['route']) ?>" class="rail-item" title="<?= esc(t('Nav.' . ($item['nav'] ?? $item['route']), [], $item['label']), 'attr') ?>" <?= $item['active'] ? 'aria-current="page"' : '' ?>>
        <?= icon($item['icon'], 18) ?>
        <span class="rail-label"><?= esc(t('Nav.' . ($item['nav'] ?? $item['route']), [], $item['label'])) ?></span>
      </a>
    <?php endforeach ?>
    <div class="rail-bottom">
      <?php foreach ($rail['bottom'] as $item): ?>
        <a href="<?= site_url($item['route']) ?>" class="rail-item" title="<?= esc(t('Nav.' . ($item['nav'] ?? $item['route']), [], $item['label']), 'attr') ?>" <?= $item['active'] ? 'aria-current="page"' : '' ?>>
          <?= icon($item['icon'], 18) ?>
          <span class="rail-label"><?= esc(t('Nav.' . ($item['nav'] ?? $item['route']), [], $item['label'])) ?></span>
        </a>
      <?php endforeach ?>
      <button type="button" class="btn btn-ghost btn-sm rail-toggle hide-phone" @click="toggleCollapsed()" :aria-expanded="(!collapsed).toString()"
              :title="collapsed ? <?= esc(json_encode(t('App.expandMenu')), 'attr') ?> : <?= esc(json_encode(t('App.collapseMenu')), 'attr') ?>">
        <span x-show="!collapsed"><?= icon('panel-left-close', 18) ?></span>
        <span x-show="collapsed" x-cloak><?= icon('panel-left-open', 18) ?></span>
        <span class="rail-label" x-text="collapsed ? <?= esc(json_encode(t('App.expandMenuShort')), 'attr') ?> : <?= esc(json_encode(t('App.collapseMenuShort')), 'attr') ?>"><?= esc(t('App.collapseMenuShort')) ?></span>
      </button>
    </div>
  </nav>

  <div class="app-main">
    <header class="topbar">
      <button type="button" class="btn btn-ghost btn-sm btn-icon only-phone" @click="navOpen = true" aria-label="<?= esc(t('App.openMenu'), 'attr') ?>">
        <?= icon('menu', 18) ?>
      </button>
      <?php if (! empty($platform)): ?>
        <span class="row font-semibold"><?= icon('shield-check', 16, 'text-accent') ?> <?= esc(t('App.platformConsole')) ?></span>
      <?php elseif (! empty($network)): ?>
        <span class="row font-semibold truncate"><?= icon('network', 16, 'text-accent') ?> <?= esc(t('App.networkConsole')) ?></span>
      <?php else: ?>
        <span class="section truncate hide-mobile"><?= esc($institution['period'] ?? '') ?></span>
      <?php endif ?>

      <?php
      // Búsqueda global: el alcance (plataforma, red o institución) y lo que se busca dependen del contexto y los permisos.
      // En la vista previa de estudiante no hay búsqueda: el docente vería sus propios resultados, no los del estudiante.
      $searchScope = ! empty($platform) ? 'platform' : (! empty($network) ? 'network' : 'institution');
      ?>
      <?php if (empty($studentPreview) && ($searchScope !== 'institution' || ! empty($institution))): $ph = \App\Libraries\Search::placeholder($searchScope); ?>
        <div class="topbar-search" x-data="globalSearch(<?= esc(json_encode(['url' => site_url('buscar'), 'scope' => $searchScope]), 'attr') ?>)" @click.outside="open = false">
          <label class="input-icon">
            <span class="sr-only"><?= esc($ph) ?></span>
            <?= icon('search', 16) ?>
            <input id="global-search" class="input" type="search" placeholder="<?= esc($ph, 'attr') ?>" autocomplete="off"
                   role="combobox" aria-autocomplete="list" aria-controls="search-results" :aria-expanded="open.toString()"
                   :aria-activedescendant="active >= 0 ? 'sr-' + active : null"
                   x-model="q" @input="queue()" @focus="if (results.length) open = true"
                   @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)" @keydown.enter.prevent="go(active >= 0 ? active : 0)" @keydown.escape="open = false">
            <span class="kbd hide-mobile" aria-hidden="true">Ctrl K</span>
          </label>
          <div class="search-panel" id="search-results" role="listbox" aria-label="<?= esc(t('App.results'), 'attr') ?>" x-show="open" x-cloak>
            <p class="search-empty" x-show="loading && !results.length"><?= esc(t('App.searching')) ?></p>
            <p class="search-empty" x-show="!loading && !results.length"><?= esc(t('App.noResultsFor')) ?> «<span x-text="q"></span>».</p>
            <template x-for="(r, i) in results" :key="r.url + r.label + i">
              <div>
                <div class="menu-label" x-show="i === 0 || results[i - 1].group !== r.group" x-text="r.group"></div>
                <div class="search-item" role="option" :id="'sr-' + i" :aria-selected="(active === i).toString()" @mouseenter="active = i" @click="go(i)">
                  <i class="icon soft" :data-lucide="r.icon" style="width:16px;height:16px" aria-hidden="true"></i>
                  <span style="min-width:0;flex:1"><span class="block truncate" x-text="r.label"></span><span class="block text-xs soft truncate" x-text="r.sub"></span></span>
                </div>
              </div>
            </template>
          </div>
        </div>
      <?php endif ?>

      <div class="row ml-auto">
        <?php
        $nUnread = service('notify')->unread((int) $user['id']);
        $nItems  = service('notify')->latest((int) $user['id'], 8);
        $nLabel  = $nUnread ? t('App.notificationsUnread', [$nUnread]) : t('App.notifications');
        ?>
        <div class="menu" x-data="dropdown" @click.outside="close()" @keydown.escape="close()">
          <button type="button" class="btn btn-sm btn-icon bell" @click="toggle()" :aria-expanded="open" aria-haspopup="true" title="<?= esc($nLabel, 'attr') ?>" aria-label="<?= esc($nLabel, 'attr') ?>">
            <?= icon('bell', 16) ?>
            <?php if ($nUnread): ?><span class="bell-count" aria-hidden="true"><?= $nUnread > 9 ? '9+' : $nUnread ?></span><?php endif ?>
          </button>
          <div class="menu-panel notif-panel" x-show="open" x-cloak>
            <div class="row-between notif-head">
              <span class="font-medium"><?= esc(t('App.notifications')) ?></span>
              <?php if ($nUnread): ?>
                <form method="post" action="<?= site_url('notificaciones/leidas') ?>"><?= csrf_field() ?><button type="submit" class="btn btn-sm"><?= esc(t('App.markAllRead')) ?></button></form>
              <?php endif ?>
            </div>
            <?php if (! $nItems): ?><p class="search-empty"><?= esc(t('App.noNotifications')) ?></p><?php endif ?>
            <?php foreach ($nItems as $n): ?>
              <a class="notif-item<?= $n['read_at'] ? '' : ' is-unread' ?>" href="<?= site_url('notificaciones/' . $n['id']) ?>">
                <?= icon(notif_icon($n['kind']), 16, 'soft') ?>
                <span style="min-width:0;flex:1">
                  <span class="block"><?= esc($n['title']) ?></span>
                  <span class="block text-xs soft"><?= esc(trim(($n['body'] ?? '') . ' · ' . date_short($n['created_at']), ' ·')) ?></span>
                </span>
                <?php if (! $n['read_at']): ?><span class="notif-dot" aria-label="<?= esc(t('App.unread'), 'attr') ?>"></span><?php endif ?>
              </a>
            <?php endforeach ?>
            <a class="menu-item notif-all" href="<?= site_url('notificaciones') ?>"><?= esc(t('App.seeAll')) ?></a>
          </div>
        </div>

        <?php if ($imp): ?>
          <div class="menu" x-data="dropdown" @click.outside="close()" @keydown.escape="close()">
            <button type="button" class="btn btn-sm" @click="toggle()" :aria-expanded="open" aria-haspopup="true" title="<?= esc(t('App.institution'), 'attr') ?>">
              <?= icon('school', 16) ?><span class="hide-mobile truncate" style="max-width:12rem"><?= esc($institution['name'] ?? '') ?></span><?= icon('chevrons-up-down', 14, 'soft') ?>
            </button>
            <div class="menu-panel" x-show="open" x-cloak>
              <form method="post" action="<?= site_url('suplantacion/terminar') ?>"><?= csrf_field() ?>
                <button type="submit" class="menu-item menu-item-platform"><?= icon('shield-check', 16) ?> <?= esc(t('App.impEndAndBack')) ?></button></form>
              <div class="menu-sep"></div>
              <div class="menu-label"><?= esc(t('App.impCantSwitch', [$imp['target_name']])) ?></div>
            </div>
          </div>
        <?php elseif (! empty($platform)): ?>
          <?php /* Consola de plataforma: no hay institución activa; el selector lleva a una red o a una institución. */ ?>
          <?php if ($myNetworks || ! empty($institutions)): ?>
            <div class="menu" x-data="dropdown" @click.outside="close()" @keydown.escape="close()">
              <button type="button" class="btn btn-sm" @click="toggle()" :aria-expanded="open" aria-haspopup="true" title="<?= esc(t('App.enterNetworkOrInstitution'), 'attr') ?>">
                <?= icon('school', 16) ?><span class="hide-mobile"><?= esc(t('App.institutionsAndNetworks')) ?></span><?= icon('chevrons-up-down', 14, 'soft') ?>
              </button>
              <div class="menu-panel" x-show="open" x-cloak>
                <?= view('partials/scope_lists', ['nets' => $myNetworks, 'insts' => $institutions ?? []]) ?>
              </div>
            </div>
          <?php endif ?>
        <?php elseif (! empty($network)): ?>
          <div class="menu" x-data="dropdown" @click.outside="close()" @keydown.escape="close()">
            <button type="button" class="btn btn-sm" @click="toggle()" :aria-expanded="open" aria-haspopup="true" title="<?= esc(t('App.changeNetwork'), 'attr') ?>">
              <?= icon('network', 16) ?><span class="hide-mobile truncate" style="max-width:14rem"><?= esc($network['name']) ?></span><?= icon('chevrons-up-down', 14, 'soft') ?>
            </button>
            <div class="menu-panel" x-show="open" x-cloak>
              <?php if (! empty($isSuperadmin)): /* Regla general: el superadministrador siempre puede volver a la plataforma. */ ?>
                <a class="menu-item menu-item-platform" href="<?= site_url('plataforma') ?>"><?= icon('shield-check', 16) ?> <?= esc(t('App.backToPlatform')) ?></a>
                <div class="menu-sep"></div>
              <?php endif ?>
              <?= view('partials/scope_lists', ['nets' => count($myNetworks) > 1 || ! empty($isSuperadmin) ? $myNetworks : [], 'insts' => $institutions ?? [], 'currentNet' => $network['id']]) ?>
              <?php if (empty($institutions)): ?><a class="menu-item" href="<?= site_url('instituciones') ?>"><?= icon('school', 16) ?> <?= esc(t('App.goToSchool')) ?></a><?php endif ?>
            </div>
          </div>
        <?php elseif (count($institutions ?? []) <= 1 && empty($isSuperadmin) && ! $myNetworks): ?>
          <span class="row text-sm hide-mobile" title="<?= esc(t('App.institution'), 'attr') ?>"><?= icon('school', 16, 'soft') ?> <?= esc($institution['name'] ?? '') ?></span>
        <?php else: ?>
          <div class="menu" x-data="dropdown" @click.outside="close()" @keydown.escape="close()">
            <button type="button" class="btn btn-sm" @click="toggle()" :aria-expanded="open" aria-haspopup="true" title="<?= esc(t('App.changeInstitution'), 'attr') ?>">
              <?= icon('school', 16) ?>
              <span class="hide-mobile truncate" style="max-width:12rem"><?= esc($institution['name'] ?? t('App.chooseInstitution')) ?></span>
              <?= icon('chevrons-up-down', 14, 'soft') ?>
            </button>
            <div class="menu-panel" x-show="open" x-cloak>
              <?php if (! empty($isSuperadmin)): /* Regla general: el superadministrador siempre puede volver a la plataforma (aquí, en el selector). */ ?>
                <a class="menu-item menu-item-platform" href="<?= site_url('plataforma') ?>"><?= icon('shield-check', 16) ?> <?= esc(t('App.backToPlatform')) ?></a>
                <div class="menu-sep"></div>
              <?php elseif ($myNetworks): /* El administrador de red siempre puede volver a su red (aquí, en el selector). */ ?>
                <form method="post" action="<?= site_url('red/entrar') ?>"><?= csrf_field() ?><input type="hidden" name="network_id" value="<?= (int) ($homeNet ?? $myNetworks[0])['id'] ?>">
                  <button type="submit" class="menu-item menu-item-platform"><?= icon('network', 16) ?> <?= esc(t('App.backToNetwork')) ?><?= count($myNetworks) === 1 || $homeNet ? ' · ' . esc(($homeNet ?? $myNetworks[0])['name']) : '' ?></button></form>
                <div class="menu-sep"></div>
              <?php endif ?>
              <?php /* El superadministrador ve también las redes; el administrador de red ya tiene «Volver a la red» arriba. */ ?>
              <?= view('partials/scope_lists', ['nets' => ! empty($isSuperadmin) ? $myNetworks : (count($myNetworks) > 1 ? $myNetworks : []), 'insts' => $institutions ?? [], 'currentInst' => $institution['id'] ?? 0]) ?>
            </div>
          </div>
        <?php endif ?>

        <div class="menu" x-data="dropdown" @click.outside="close()" @keydown.escape="close()">
          <button type="button" class="btn btn-sm" @click="toggle()" :aria-expanded="open" aria-haspopup="true" title="<?= esc(t('App.myAccount'), 'attr') ?>">
            <?= my_avatar($user, 24) ?>
            <span class="hide-mobile"><?= esc($user['name']) ?></span>
            <?php if ($roleLabel): ?><span class="role-chip hide-mobile" title="<?= esc(! empty($institution['name']) && empty($isSuperadmin) ? t('App.yourRoleIn', [$institution['name']]) : t('App.yourRole'), 'attr') ?>"><?= esc($roleLabel) ?></span><?php endif ?>
          </button>
          <div class="menu-panel" x-show="open" x-cloak>
            <div class="menu-label truncate"><?= esc(person_id($user)) ?></div>
            <?php if ($roleLabel): ?><div class="menu-label" style="padding-top:0"><span class="role-chip"><?= icon(! empty($isSuperadmin) ? 'shield-check' : 'badge-check', 12) ?> <?= esc($roleLabel) ?></span></div><?php endif ?>
            <a class="menu-item" href="<?= site_url('perfil') ?>"><?= icon('user', 16) ?> <?= esc(t('App.myProfile')) ?></a>
            <button type="button" class="menu-item" @click="$dispatch('assistant-toggle'); close()"><?= icon('bot', 16) ?> <?= esc(t('App.toggleAssistant')) ?></button>
            <?php if (! $imp): /* Idioma de la interfaz (durante «Ver como» no se cambia: es solo lectura). */ ?>
              <div class="menu-sep"></div>
              <div class="menu-label"><?= esc(t('App.language')) ?></div>
              <?php foreach (\App\Libraries\Locale::SUPPORTED as $code => $name): ?>
                <form method="post" action="<?= site_url('idioma') ?>"><?= csrf_field() ?>
                  <input type="hidden" name="locale" value="<?= $code ?>"><input type="hidden" name="back" value="<?= esc(current_url() . ($_SERVER['QUERY_STRING'] ?? '' ? '?' . $_SERVER['QUERY_STRING'] : ''), 'attr') ?>">
                  <button type="submit" class="menu-item" <?= $locale === $code ? 'aria-current="true"' : '' ?> lang="<?= $code ?>"><?= icon('languages', 16) ?> <?= esc($name) ?></button></form>
              <?php endforeach ?>
            <?php endif ?>
            <div class="menu-sep"></div>
            <div class="menu-label"><?= esc(t('App.theme')) ?></div>
            <button type="button" class="menu-item" :aria-current="theme === 'auto'" @click="setTheme('auto')"><?= icon('monitor', 16) ?> <?= esc(t('App.themeAuto')) ?></button>
            <button type="button" class="menu-item" :aria-current="theme === 'light'" @click="setTheme('light')"><?= icon('sun', 16) ?> <?= esc(t('App.themeLight')) ?></button>
            <button type="button" class="menu-item" :aria-current="theme === 'dark'" @click="setTheme('dark')"><?= icon('moon', 16) ?> <?= esc(t('App.themeDark')) ?></button>
            <div class="menu-sep"></div>
            <form method="post" action="<?= site_url('logout') ?>">
              <?= csrf_field() ?>
              <button type="submit" class="menu-item"><?= icon('log-out', 16) ?> <?= esc(t('App.logout')) ?></button>
            </form>
          </div>
        </div>
      </div>
    </header>

    <?php if ($imp): ?>
      <div class="preview-bar impersonation-bar" role="status" x-data="{ left: <?= max(0, (int) $imp['expires'] - time()) ?>, get mmss() { return Math.floor(this.left / 60) + ':' + String(this.left % 60).padStart(2, '0') } }"
           x-init="setInterval(() => { if (--left <= 0) location.reload() }, 1000)">
        <?= icon('eye', 16) ?>
        <span><?= esc(t('App.impViewingAs')) ?> <strong class="font-medium"><?= esc($imp['target_name']) ?></strong> · <strong class="font-medium"><?= esc(t('App.impReadOnly')) ?></strong>, <?= esc(t('App.impNothingSaved')) ?> · <?= esc(t('App.impEndsIn')) ?> <span style="font-variant-numeric:tabular-nums" x-text="mmss"></span></span>
        <span class="hide-mobile soft"><?= esc(t('App.impSupport')) ?>: <?= esc($imp['by_name']) ?></span>
        <form method="post" action="<?= site_url('suplantacion/terminar') ?>" class="ml-auto"><?= csrf_field() ?>
          <button type="submit" class="btn btn-sm"><?= icon('log-out', 14) ?> <?= esc(t('App.impEnd')) ?></button></form>
      </div>
    <?php endif ?>

    <?php if (! empty($studentPreview)): ?>
      <div class="preview-bar" role="note">
        <?= icon('eye', 16) ?>
        <?php $pg = $previewGroups ?? []; ?>
        <span><?= esc(t('App.previewAs')) ?></span>
        <?php if (count($pg) > 1): ?>
          <form method="get" action="<?= site_url('estudiante') ?>">
            <select class="select" name="grupo" onchange="this.form.submit()" aria-label="<?= esc(t('App.previewGroup'), 'attr') ?>" style="width:auto;height:2rem">
              <?php foreach ($pg as $g): ?><option value="<?= (int) $g['id'] ?>" <?= (int) $g['id'] === (int) ($previewGroup['id'] ?? 0) ? 'selected' : '' ?>><?= esc($g['name']) ?></option><?php endforeach ?>
            </select>
          </form>
        <?php else: ?>
          <strong class="font-medium"><?= esc($previewGroup['name'] ?? t('App.previewNoGroup')) ?></strong>
        <?php endif ?>
        <span class="hide-mobile"><?= esc(t('App.previewNote')) ?></span>
        <a class="btn btn-sm ml-auto" href="<?= site_url('cuadernos') ?>"><?= icon('arrow-left', 14) ?> <?= esc(t('App.previewBack')) ?></a>
        <?php if (! empty($isSuperadmin)): ?>
          <a class="btn btn-sm" href="<?= site_url('plataforma') ?>"><?= icon('shield-check', 14) ?> <?= esc(t('App.backToPlatform')) ?></a>
        <?php elseif ($myNetworks): ?>
          <a class="btn btn-sm" href="<?= site_url('red') ?>"><?= icon('network', 14) ?> <?= esc(t('App.backToNetwork')) ?></a>
        <?php endif ?>
      </div>
    <?php endif ?>

    <main class="content<?= $bare ? ' bare' : '' ?>" id="contenido">
      <?= $this->renderSection('content') ?>
    </main>
  </div>
</div>

<?php $as = service('helpAssistant')->settings(); if ($as['enabled']): $af = service('helpAssistant')->avatarFile(); ?>
  <?= view('partials/assistant', ['assistant' => $as + ['image' => $af ? site_url('asistente/imagen') . '?v=' . $af['version'] : base_url('assets/img/logo-mark.png')]]) ?>
<?php endif ?>
<?= $this->include('partials/toasts') ?>
</body>
</html>
