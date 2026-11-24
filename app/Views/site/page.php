<?php
/**
 * Página pública de un colegio (agendaescolar.co/{slug}) y, con $edit, la misma página editable en vivo:
 * los textos se escriben encima (contenteditable de texto plano), cada sección se puede subir, bajar u ocultar, y la
 * portada y «Quiénes somos» llevan foto. El borrador se guarda solo; «Publicar» lo hace visible al público.
 *
 * @var array      $inst   institución
 * @var array      $site   contenido (App\Libraries\SchoolSite::clean)
 * @var bool       $edit   modo editor
 * @var array      $news   noticias públicas
 * @var array      $events próximos eventos públicos
 * @var array|null $status estado del borrador (solo editor)
 * @var bool       $canEdit muestra «Editar esta página» (público, a quien puede editarla)
 */
$slug    = $inst['slug'];
$fotoUrl = static fn (?string $n): string => $n ? site_url($slug . '/foto/' . $n) : '';
$visible = array_values(array_filter($site['sections'], static fn ($s) => $edit || $s['visible']));
$anchor  = ['hero' => 'inicio', 'about' => 'nosotros', 'offer' => 'oferta', 'admissions' => 'admisiones', 'gallery' => 'galeria', 'news' => 'noticias', 'events' => 'eventos', 'documents' => 'documentos', 'contact' => 'contacto'];
$secName = static fn (string $k): string => t('Site.sec' . ucfirst($k));

// Texto editable: en el editor, contenteditable con marcador; en público, solo si tiene contenido.
$txt = static function (string $path, string $tag = 'span', string $class = '', string $ph = '') use ($site, $edit): string {
    [$sec, $field] = explode('.', $path);
    $v    = (string) ($site[$sec][$field] ?? '');
    $rich = ! in_array($path, \App\Libraries\SchoolSite::PLAIN, true);
    $html = $rich ? \App\Libraries\RichText::clean($v) : esc($v); // se vuelve a limpiar al mostrar
    if (! $edit) {
        return $v === '' ? '' : "<{$tag} class=\"{$class}\">" . $html . "</{$tag}>";
    }
    if ($rich) {
        return "<{$tag} class=\"{$class} site-edit\" contenteditable=\"true\" data-rich spellcheck=\"true\" data-path=\"{$path}\" data-ph=\"" . esc($ph, 'attr') . '">' . $html . "</{$tag}>";
    }

    return "<{$tag} class=\"{$class} site-edit\" contenteditable=\"plaintext-only\" spellcheck=\"true\" data-path=\"{$path}\" data-ph=\"" . esc($ph, 'attr') . '">' . esc($v) . "</{$tag}>";
};

// En público, las secciones automáticas sin contenido no se muestran.
$hasContent = static function (string $k) use ($site, $news, $events, $offer): bool {
    return match ($k) {
        'offer'     => $offer['levels'] !== [] || $offer['subjects'] !== [],
        'gallery'   => $site['gallery']['items'] !== [],
        'documents' => $site['documents']['items'] !== [],
        'news'    => $news !== [],
        'events'  => $events !== [],
        'contact' => array_filter(array_intersect_key($site['contact'], array_flip(['address', 'phone', 'whatsapp', 'email', 'hours']))) !== [],
        default   => true,
    };
};
if (! $edit) {
    $visible = array_values(array_filter($visible, static fn ($s) => $hasContent($s['key'])));
}
$c       = $site['contact'];
// Si la página no tiene teléfono o correo propios, se muestran los de la institución (Ajustes › Institución), siempre al día.
if (! $edit) {
    $c['phone'] = $c['phone'] !== '' ? $c['phone'] : (string) ($inst['phone'] ?? '');
    $c['email'] = $c['email'] !== '' ? $c['email'] : (string) ($inst['email'] ?? '');
}
$waLink  = $c['whatsapp'] !== '' ? 'https://wa.me/' . preg_replace('/\D/', '', $c['whatsapp']) : null;
$mapLink = $c['address'] !== '' ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($c['address'] . ', ' . ($inst['city'] ?? '')) : null;
$initials = mb_strtoupper(implode('', array_map(static fn ($w) => mb_substr($w, 0, 1), array_slice(array_values(array_filter(explode(' ', preg_replace('/\b(colegio|instituto|institución|escuela|liceo|gimnasio|de|del|la|el|los|las)\b/iu', '', $inst['name'])))), 0, 2)))) ?: mb_strtoupper(mb_substr($inst['name'], 0, 1));
$editorData = $edit ? [
    'saveUrl'    => site_url('pagina-publica'),
    'publishUrl' => site_url('pagina-publica/publicar'),
    'uploadUrl'  => site_url('pagina-publica/foto'),
    'fotoBase'   => site_url($slug . '/foto/'),
    'images'     => ['hero' => $site['hero']['image'], 'about' => $site['about']['image']],
    'hidden'     => array_combine(array_column($site['sections'], 'key'), array_map(static fn ($s) => ! $s['visible'], $site['sections'])),
    'values'     => $site['about']['values'],
    'steps'      => $site['admissions']['steps'],
    'admissionsForm' => (bool) $site['admissions']['form'],
    'gallery'    => $site['gallery']['items'],
    'docs'       => $site['documents']['items'],
    'docUrl'     => site_url('pagina-publica/documento'),
    'pending'    => (bool) ($status['pending'] ?? false),
    'maxValues'  => \App\Libraries\SchoolSite::MAX_VALUES,
] : null;

// Barra de herramientas de una sección (solo editor). Botones con borde, título y aria-label (regla de diseño).
$tools = static function (string $k, bool $photo = false) use ($secName): string {
    $n = esc($secName($k), 'attr');
    $b = static fn (string $click, string $ic, string $label, string $extra = '') => '<button type="button" class="btn btn-sm btn-icon" @click="' . $click . '" title="' . $label . '" aria-label="' . $label . '"' . $extra . '>' . icon($ic, 15) . '</button>';
    $h = '<div class="site-tools" role="toolbar" aria-label="' . esc(t('Site.sectionTools', ['name' => $secName($k)]), 'attr') . '">'
        . '<span class="site-tools-name">' . esc($secName($k)) . '</span>'
        . $b("move('{$k}', -1)", 'arrow-up', esc(t('Site.moveUp', ['name' => $secName($k)]), 'attr'))
        . $b("move('{$k}', 1)", 'arrow-down', esc(t('Site.moveDown', ['name' => $secName($k)]), 'attr'));
    if ($photo) {
        $h .= '<button type="button" class="btn btn-sm" @click="pick(\'' . $k . '\')" :disabled="uploading === \'' . $k . '\'">' . icon('image', 15) . ' <span x-text="images.' . $k . ' ? ' . esc(json_encode(t('Site.changePhoto')), 'attr') . ' : ' . esc(json_encode(t('Site.addPhoto')), 'attr') . '">' . esc(t('Site.changePhoto')) . '</span></button>'
            . '<button type="button" class="btn btn-sm btn-icon btn-danger" x-show="images.' . $k . '" @click="images.' . $k . ' = null; queue()" title="' . esc(t('Site.removePhoto', ['name' => $secName($k)]), 'attr') . '" aria-label="' . esc(t('Site.removePhoto', ['name' => $secName($k)]), 'attr') . '">' . icon('image-off', 15) . '</button>';
    }
    $h .= '<button type="button" class="btn btn-sm" @click="toggle(\'' . $k . '\')" :aria-pressed="hidden.' . $k . ' ? \'true\' : \'false\'">'
        . '<span x-show="!hidden.' . $k . '" class="row" style="gap:0.375rem">' . icon('eye-off', 15) . ' ' . esc(t('Site.hide')) . '</span>'
        . '<span x-show="hidden.' . $k . '" x-cloak class="row" style="gap:0.375rem">' . icon('eye', 15) . ' ' . esc(t('Site.show')) . '</span></button>'
        . '</div>'
        . '<p class="site-off-note" x-show="hidden.' . $k . '" x-cloak>' . icon('eye-off', 14) . ' ' . esc(t('Site.hiddenNote')) . '</p>';

    return $h;
};
?>
<!doctype html>
<html lang="<?= esc(service('locale')->current(), 'attr') ?>" style="<?= esc(brand_vars($inst['accent_color'] ?? null), 'attr') ?>">
<head>
  <?= $this->include('partials/head') ?>
  <meta name="description" content="<?= esc(mb_substr(\App\Libraries\SchoolSite::plain($site['hero']['tagline'] ?: $site['about']['text']), 0, 160), 'attr') ?>">
  <?php if ($edit): ?><meta name="robots" content="noindex"><?php else: ?>
  <meta property="og:title" content="<?= esc($inst['name'], 'attr') ?>">
  <meta property="og:description" content="<?= esc(mb_substr(\App\Libraries\SchoolSite::plain($site['hero']['tagline']), 0, 200), 'attr') ?>">
  <?php if ($site['hero']['image']): ?><meta property="og:image" content="<?= esc($fotoUrl($site['hero']['image']), 'attr') ?>"><?php endif ?>
  <?php endif ?>
</head>
<body class="site<?= $edit ? ' site-editing' : '' ?>"<?= $edit ? ' data-fmt-always x-data="siteEditor(' . esc(json_encode($editorData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') . ')" @input="onInput($event)" @paste="onPaste($event)" @fmt-changed="queue()"' : '' ?>>
<a class="skip-link" href="#contenido"><?= esc(t('App.skipToContent', [], 'Saltar al contenido')) ?></a>

<?php if ($edit): ?>
  <!-- Barra del editor -->
  <div class="site-editbar" role="region" aria-label="<?= esc(t('Site.editorBar'), 'attr') ?>">
    <div class="site-editbar-in">
      <div class="site-editbar-info">
        <span class="site-editbar-title"><?= icon('pencil', 15) ?> <?= esc(t('Site.editing')) ?></span>
        <span class="site-editbar-state text-xs" aria-live="polite">
          <span x-show="state === 'saving'" x-cloak><?= esc(t('Site.saving')) ?></span>
          <span x-show="state === 'error'" x-cloak class="danger"><?= esc(t('Site.saveFailed')) ?></span>
          <span x-show="state === 'saved' && pending" x-cloak><?= esc(t('Site.unpublished')) ?></span>
          <span x-show="state === 'saved' && !pending" x-cloak><?= esc(t('Site.upToDate')) ?></span>
        </span>
      </div>
      <div class="row site-editbar-actions">
        <a class="btn btn-sm" href="<?= site_url('ajustes') ?>"><?= icon('arrow-left', 14) ?> <?= esc(t('Site.exit')) ?></a>
        <a class="btn btn-sm hide-mobile" href="<?= site_url($slug) ?>" target="_blank" rel="noopener"><?= icon('external-link', 14) ?> <?= esc(t('Site.viewPublished')) ?></a>
        <form method="post" action="<?= site_url('pagina-publica/descartar') ?>" x-show="pending" x-cloak @submit="if (!confirm(<?= esc(json_encode(t('Site.discardConfirm')), 'attr') ?>)) $event.preventDefault()">
          <?= csrf_field() ?>
          <button type="submit" class="btn btn-sm btn-danger"><?= icon('undo-2', 14) ?> <span class="hide-mobile"><?= esc(t('Site.discard')) ?></span></button>
        </form>
        <button type="button" class="btn btn-sm btn-primary" @click="publish()" :disabled="publishing"><?= icon('globe', 14) ?> <span x-text="publishing ? <?= esc(json_encode(t('Site.publishing')), 'attr') ?> : <?= esc(json_encode(t('Site.publish')), 'attr') ?>"><?= esc(t('Site.publish')) ?></span></button>
      </div>
    </div>
    <p class="site-editbar-tip text-xs"><?= icon('mouse-pointer-click', 13) ?> <?= esc(t('Site.tip')) ?></p>
  </div>
  <input type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" x-ref="file" @change="upload($event)" tabindex="-1" aria-hidden="true">
  <input type="file" accept="application/pdf,.pdf" class="sr-only" x-ref="doc" @change="uploadDoc($event)" tabindex="-1" aria-hidden="true">
  <?= view('partials/fmt_bar') ?>
<?php endif ?>

<header class="site-top">
  <div class="site-wrap site-top-in">
    <a class="site-brand" href="<?= $edit ? '#inicio' : site_url($slug) ?>">
      <span class="site-mark" aria-hidden="true"><?= esc($initials) ?></span>
      <span><?= esc($inst['name']) ?></span>
    </a>
    <nav class="site-nav hide-mobile" aria-label="<?= esc(t('Site.sections'), 'attr') ?>">
      <?php foreach ($visible as $s): if ($s['key'] === 'hero') { continue; } ?>
        <a href="#<?= $anchor[$s['key']] ?>"<?= $edit ? ' x-show="!hidden.' . $s['key'] . '"' : '' ?>><?= esc($secName($s['key'])) ?></a>
      <?php endforeach ?>
    </nav>
    <a class="btn btn-sm" href="<?= site_url('login') ?>"><?= icon('log-in', 15) ?> <?= esc(t('Site.login')) ?></a>
  </div>
</header>

<main id="contenido">
<?php foreach ($visible as $s): $k = $s['key']; $offAttr = $edit ? ' data-sec="' . $k . '" :class="{ \'is-off\': hidden.' . $k . ' }"' : ''; ?>

  <?php if ($k === 'hero'): ?>
  <section id="inicio" class="site-hero<?= $site['hero']['image'] ? ' has-img' : '' ?>"
    <?= $edit ? 'data-sec="hero" :class="{ \'has-img\': images.hero, \'is-off\': hidden.hero }" :style="images.hero ? \'--hero-img:url(\' + fotoBase + images.hero + \')\' : \'\'"' : ($site['hero']['image'] ? 'style="--hero-img:url(' . esc($fotoUrl($site['hero']['image']), 'attr') . ')"' : '') ?>>
    <?= $edit ? $tools('hero', true) : '' ?>
    <div class="site-wrap site-hero-in">
      <?php if (! empty($inst['city'])): ?><p class="site-hero-kicker"><?= icon('map-pin', 14) ?> <?= esc($inst['city']) ?></p><?php endif ?>
      <?= $txt('hero.title', 'h1', 'site-hero-title', t('Site.phTitle')) ?>
      <?= $txt('hero.tagline', 'p', 'site-hero-lead', t('Site.phTagline')) ?>
      <div class="row site-hero-cta">
        <?php if ($edit): ?>
          <span class="btn btn-lg btn-primary"><?= $txt('hero.cta', 'span', '', t('Site.phCta')) ?></span>
        <?php elseif ($site['hero']['cta'] !== ''): ?>
          <a class="btn btn-lg btn-primary" href="#nosotros"><?= esc($site['hero']['cta']) ?></a>
        <?php endif ?>
        <a class="btn btn-lg site-hero-ghost" href="#contacto"><?= icon('message-circle', 16) ?> <?= esc(t('Site.secContact')) ?></a>
      </div>
    </div>
  </section>

  <?php elseif ($k === 'about'): $a = $site['about']; ?>
  <section id="nosotros" class="site-sec"<?= $offAttr ?>>
    <?= $edit ? $tools('about', true) : '' ?>
    <div class="site-wrap">
      <div class="site-about<?= $a['image'] ? ' has-img' : '' ?>"<?= $edit ? ' :class="{ \'has-img\': images.about }"' : '' ?>>
        <div>
          <p class="site-kicker"><?= esc(t('Site.secAbout')) ?></p>
          <?= $txt('about.heading', 'h2', 'site-h2', t('Site.phHeading')) ?>
          <?= $txt('about.text', 'div', 'site-prose', t('Site.phText')) ?>
        </div>
        <?php if ($edit): ?>
          <figure class="site-about-photo" x-show="images.about" x-cloak><img :src="images.about ? fotoBase + images.about : ''" alt=""></figure>
        <?php elseif ($a['image']): ?>
          <figure class="site-about-photo"><img src="<?= esc($fotoUrl($a['image']), 'attr') ?>" alt="<?= esc($inst['name'], 'attr') ?>" loading="lazy" decoding="async"></figure>
        <?php endif ?>
      </div>

      <?php if ($edit || $a['mission'] !== '' || $a['vision'] !== ''): ?>
        <div class="site-mv">
          <?php foreach (['mission' => 'target', 'vision' => 'telescope'] as $f => $ic): if (! $edit && $a[$f] === '') { continue; } ?>
            <article class="site-card">
              <span class="site-card-ic"><?= icon($ic, 18) ?></span>
              <h3><?= esc(t('Site.' . $f)) ?></h3>
              <?= $txt('about.' . $f, 'div', 'site-prose', t('Site.ph' . ucfirst($f))) ?>
            </article>
          <?php endforeach ?>
        </div>
      <?php endif ?>

      <?php if ($edit): ?>
        <div class="site-values-wrap">
          <h3 class="site-h3"><?= esc(t('Site.values')) ?></h3>
          <ul class="site-values">
            <template x-for="(v, i) in values" :key="v.id">
              <li class="site-value">
                <span class="site-edit" contenteditable="true" data-rich data-value :data-ph="<?= esc(json_encode(t('Site.phValue')), 'attr') ?>" x-init="$el.innerHTML = v.t" @input.stop="v.t = $el.innerHTML; queue()" @fmt-changed.window="v.t = $el.innerHTML" @keydown.enter.prevent></span>
                <button type="button" class="site-value-x" @click="values.splice(i, 1); queue()" :title="<?= esc(json_encode(t('Site.removeValue')), 'attr') ?> + ' ' + v.t" :aria-label="<?= esc(json_encode(t('Site.removeValue')), 'attr') ?> + ' ' + v.t">×</button>
              </li>
            </template>
            <li x-show="values.length < maxValues"><button type="button" class="btn btn-sm" @click="addValue()"><?= icon('plus', 14) ?> <?= esc(t('Site.addValue')) ?></button></li>
          </ul>
        </div>
      <?php elseif ($a['values']): ?>
        <div class="site-values-wrap">
          <h3 class="site-h3"><?= esc(t('Site.values')) ?></h3>
          <ul class="site-values"><?php foreach ($a['values'] as $v): ?><li class="site-value"><?= icon('sparkle', 14) ?> <span><?= \App\Libraries\RichText::clean($v) ?></span></li><?php endforeach ?></ul>
        </div>
      <?php endif ?>
    </div>
  </section>

  <?php elseif ($k === 'offer'): $of = $offer; ?>
  <section id="oferta" class="site-sec site-alt"<?= $offAttr ?>>
    <?= $edit ? $tools('offer') : '' ?>
    <div class="site-wrap">
      <p class="site-kicker"><?= esc(t('Site.secOffer')) ?></p>
      <?= $txt('offer.heading', 'h2', 'site-h2', t('Site.phHeading')) ?>
      <?= $txt('offer.intro', 'div', 'site-prose', t('Site.phIntro')) ?>
      <?php if ($edit): ?><p class="site-auto"><?= icon('info', 14) ?> <?= esc(t('Site.offerAuto')) ?> <a class="link" href="<?= site_url('ajustes/estructura') ?>"><?= esc(t('Site.manageStructure')) ?></a></p><?php endif ?>
      <?php if ($of['levels'] || $of['subjects']): ?>
        <div class="site-offer">
          <article class="site-card">
            <span class="site-card-ic"><?= icon('graduation-cap', 18) ?></span>
            <h3><?= esc(t('Site.offerGrades')) ?></h3>
            <ul class="site-values"><?php foreach ($of['levels'] as [$lv, $gs]): ?><li class="site-value" title="<?= esc(implode(', ', $gs), 'attr') ?>"><?= esc($lv) ?><?= count($gs) > 1 ? ' <span class="soft">· ' . count($gs) . '</span>' : '' ?></li><?php endforeach ?></ul>
          </article>
          <article class="site-card">
            <span class="site-card-ic"><?= icon('book-open', 18) ?></span>
            <h3><?= esc(t('Site.offerSubjects')) ?></h3>
            <ul class="site-chips"><?php foreach ($of['subjects'] as $sb): ?><li><?= esc($sb) ?></li><?php endforeach ?></ul>
          </article>
        </div>
      <?php elseif ($edit): ?>
        <div class="site-empty"><?= icon('graduation-cap', 22) ?><p><?= esc(t('Site.offerEmpty')) ?></p></div>
      <?php endif ?>
    </div>
  </section>

  <?php elseif ($k === 'admissions'): $ad = $site['admissions']; ?>
  <section id="admisiones" class="site-sec"<?= $offAttr ?>>
    <?= $edit ? $tools('admissions') : '' ?>
    <div class="site-wrap site-admissions">
      <div>
        <p class="site-kicker"><?= esc(t('Site.secAdmissions')) ?></p>
        <?= $txt('admissions.heading', 'h2', 'site-h2', t('Site.phHeading')) ?>
        <?= $txt('admissions.intro', 'div', 'site-prose', t('Site.phAdmissions')) ?>
        <?php if ($edit): ?>
          <ol class="site-steps">
            <template x-for="(v, i) in steps" :key="v.id">
              <li class="site-step"><span class="site-step-n" x-text="i + 1"></span>
                <span class="site-edit" contenteditable="true" data-rich data-step :data-ph="<?= esc(json_encode(t('Site.phStep')), 'attr') ?>" x-init="$el.innerHTML = v.t" @input.stop="v.t = $el.innerHTML; queue()" @fmt-changed.window="v.t = $el.innerHTML" @keydown.enter.prevent></span>
                <button type="button" class="site-value-x" @click="steps.splice(i, 1); queue()" :title="<?= esc(json_encode(t('Site.removeStep')), 'attr') ?>" :aria-label="<?= esc(json_encode(t('Site.removeStep')), 'attr') ?> + ' ' + (i + 1)">×</button>
              </li>
            </template>
          </ol>
          <button type="button" class="btn btn-sm" x-show="steps.length < <?= \App\Libraries\SchoolSite::MAX_STEPS ?>" @click="addStep()"><?= icon('plus', 14) ?> <?= esc(t('Site.addStep')) ?></button>
        <?php elseif ($ad['steps']): ?>
          <ol class="site-steps"><?php foreach ($ad['steps'] as $i => $st): ?><li class="site-step"><span class="site-step-n"><?= $i + 1 ?></span><span><?= \App\Libraries\RichText::clean($st) ?></span></li><?php endforeach ?></ol>
        <?php endif ?>
        <?php if ($edit || $ad['dates'] !== ''): ?><p class="site-dates"><?= icon('calendar-days', 16) ?> <?= $txt('admissions.dates', 'span', '', t('Site.phDates')) ?></p><?php endif ?>
      </div>
      <aside class="site-contact-card">
        <?php if ($edit): ?>
          <h3><?= esc(t('Site.reqTitle')) ?></h3>
          <label class="row text-sm" style="gap:0.5rem"><input type="checkbox" x-model="admissionsForm" @change="queue()"> <?= esc(t('Site.reqToggle')) ?></label>
          <p class="text-sm soft"><?= esc(t('Site.reqEditorNote')) ?></p>
          <a class="btn" href="<?= site_url('pagina-publica/solicitudes') ?>"><?= icon('inbox', 16) ?> <?= esc(t('Site.reqList')) ?><?= $newRequests ? ' (' . $newRequests . ')' : '' ?></a>
        <?php elseif ($ad['form']): ?>
          <?php if (session('admissionSent')): ?>
            <div class="site-sent"><?= icon('circle-check', 28) ?><h3><?= esc(t('Site.reqThanks')) ?></h3><p class="text-sm soft"><?= esc(t('Site.reqThanksText')) ?></p></div>
          <?php else: ?>
            <h3><?= esc(t('Site.reqTitle')) ?></h3>
            <?php if ($e = session('admissionError')): ?><p class="text-sm text-danger"><?= esc($e) ?></p><?php endif ?>
            <form class="stack" method="post" action="<?= site_url($slug . '/admisiones') ?>">
              <?= csrf_field() ?>
              <input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="sr-only" aria-hidden="true">
              <label class="field"><span class="label"><?= esc(t('Site.reqName')) ?> *</span><input class="input" name="name" required maxlength="120" value="<?= esc(old('name') ?? '', 'attr') ?>"></label>
              <div class="grid-2">
                <label class="field"><span class="label"><?= esc(t('Site.phone')) ?></span><input class="input" name="phone" inputmode="tel" maxlength="40" value="<?= esc(old('phone') ?? '', 'attr') ?>"></label>
                <label class="field"><span class="label"><?= esc(t('Site.email')) ?></span><input class="input" type="email" name="email" maxlength="160" value="<?= esc(old('email') ?? '', 'attr') ?>"></label>
              </div>
              <label class="field"><span class="label"><?= esc(t('Site.reqGrade')) ?></span>
                <select class="select" name="grade"><option value=""><?= esc(t('Site.reqGradeAny')) ?></option><?php foreach ($offer['levels'] as [$lv]): ?><option <?= old('grade') === $lv ? 'selected' : '' ?>><?= esc($lv) ?></option><?php endforeach ?></select></label>
              <label class="field"><span class="label"><?= esc(t('Site.reqMessage')) ?></span><textarea class="textarea" name="message" rows="3" maxlength="1000"><?= esc(old('message') ?? '') ?></textarea></label>
              <button type="submit" class="btn btn-lg btn-primary"><?= icon('send', 16) ?> <?= esc(t('Site.reqSend')) ?></button>
              <p class="text-xs soft"><?= esc(t('Site.reqPrivacy')) ?></p>
            </form>
          <?php endif ?>
        <?php else: ?>
          <h3><?= esc(t('Site.talkToUs')) ?></h3>
          <p class="text-sm soft"><?= esc(t('Site.talkToUsText')) ?></p>
          <a class="btn btn-lg btn-primary" href="#contacto"><?= icon('message-circle', 16) ?> <?= esc(t('Site.secContact')) ?></a>
        <?php endif ?>
      </aside>
    </div>
  </section>

  <?php elseif ($k === 'gallery'): $ga = $site['gallery']; ?>
  <section id="galeria" class="site-sec site-alt"<?= $offAttr ?>>
    <?= $edit ? $tools('gallery') : '' ?>
    <div class="site-wrap">
      <p class="site-kicker"><?= esc(t('Site.secGallery')) ?></p>
      <?= $txt('gallery.heading', 'h2', 'site-h2', t('Site.phHeading')) ?>
      <?php if ($edit): ?>
        <div class="site-gallery">
          <template x-for="(g, i) in gallery" :key="g.id">
            <figure class="site-photo">
              <img :src="fotoBase + g.image" alt="">
              <figcaption class="site-edit" contenteditable="true" data-rich :data-ph="<?= esc(json_encode(t('Site.phCaption')), 'attr') ?>" x-init="$el.innerHTML = g.caption" @input.stop="g.caption = $el.innerHTML; queue()" @fmt-changed.window="g.caption = $el.innerHTML" @keydown.enter.prevent></figcaption>
              <button type="button" class="btn btn-sm btn-icon btn-danger site-photo-x" @click="gallery.splice(i, 1); queue()" :title="<?= esc(json_encode(t('Site.removePhotoItem')), 'attr') ?>" :aria-label="<?= esc(json_encode(t('Site.removePhotoItem')), 'attr') ?> + ' ' + (i + 1)"><?= icon('trash-2', 14) ?></button>
            </figure>
          </template>
          <button type="button" class="site-photo-add" x-show="gallery.length < <?= \App\Libraries\SchoolSite::MAX_PHOTOS ?>" @click="pick('gallery')" :disabled="uploading === 'gallery'">
            <?= icon('image-plus', 24) ?><span x-text="uploading === 'gallery' ? <?= esc(json_encode(t('Site.uploading')), 'attr') ?> : <?= esc(json_encode(t('Site.addPhotoItem')), 'attr') ?>"></span></button>
        </div>
      <?php else: ?>
        <div class="site-gallery">
          <?php foreach ($ga['items'] as $g): ?>
            <figure class="site-photo"><img src="<?= esc($fotoUrl($g['image']), 'attr') ?>" alt="<?= esc(\App\Libraries\SchoolSite::plain($g['caption']) ?: $inst['name'], 'attr') ?>" loading="lazy" decoding="async"><?php if ($g['caption'] !== ''): ?><figcaption><?= \App\Libraries\RichText::clean($g['caption']) ?></figcaption><?php endif ?></figure>
          <?php endforeach ?>
        </div>
      <?php endif ?>
    </div>
  </section>

  <?php elseif ($k === 'documents'): $dc = $site['documents']; ?>
  <section id="documentos" class="site-sec"<?= $offAttr ?>>
    <?= $edit ? $tools('documents') : '' ?>
    <div class="site-wrap">
      <p class="site-kicker"><?= esc(t('Site.secDocuments')) ?></p>
      <?= $txt('documents.heading', 'h2', 'site-h2', t('Site.phHeading')) ?>
      <?php if ($edit): ?>
        <ul class="site-docs">
          <template x-for="(d, i) in docs" :key="d.id">
            <li class="site-doc"><span class="site-doc-ic"><?= icon('file-text', 20) ?></span>
              <span style="flex:1;min-width:0"><span class="site-edit font-medium block" contenteditable="true" data-rich :data-ph="<?= esc(json_encode(t('Site.phDocTitle')), 'attr') ?>" x-init="$el.innerHTML = d.title" @input.stop="d.title = $el.innerHTML; queue()" @fmt-changed.window="d.title = $el.innerHTML" @keydown.enter.prevent></span>
                <span class="text-xs soft" x-text="'PDF · ' + Math.max(1, Math.round(d.size / 1024)) + ' KB'"></span></span>
              <button type="button" class="btn btn-sm btn-icon btn-danger" @click="docs.splice(i, 1); queue()" :title="<?= esc(json_encode(t('Site.removeDoc')), 'attr') ?>" :aria-label="<?= esc(json_encode(t('Site.removeDoc')), 'attr') ?> + ' ' + d.title"><?= icon('trash-2', 14) ?></button>
            </li>
          </template>
        </ul>
        <button type="button" class="btn btn-sm" x-show="docs.length < <?= \App\Libraries\SchoolSite::MAX_DOCS ?>" @click="$refs.doc.value = ''; $refs.doc.click()" :disabled="uploading === 'doc'">
          <?= icon('file-up', 14) ?> <span x-text="uploading === 'doc' ? <?= esc(json_encode(t('Site.uploading')), 'attr') ?> : <?= esc(json_encode(t('Site.addDoc')), 'attr') ?>"></span></button>
        <p class="site-auto mt-2"><?= icon('info', 14) ?> <?= esc(t('Site.docHint')) ?></p>
      <?php else: ?>
        <ul class="site-docs">
          <?php foreach ($dc['items'] as $d): ?>
            <li><a class="site-doc" href="<?= site_url($slug . '/documento/' . $d['file']) ?>" target="_blank" rel="noopener">
              <span class="site-doc-ic"><?= icon('file-text', 20) ?></span>
              <span style="flex:1;min-width:0"><span class="font-medium block"><?= \App\Libraries\RichText::clean($d['title']) ?></span><span class="text-xs soft">PDF · <?= max(1, (int) round($d['size'] / 1024)) ?> KB</span></span>
              <?= icon('download', 16, 'soft') ?></a></li>
          <?php endforeach ?>
        </ul>
      <?php endif ?>
    </div>
  </section>

  <?php elseif ($k === 'news'): ?>
  <section id="noticias" class="site-sec site-alt"<?= $offAttr ?>>
    <?= $edit ? $tools('news') : '' ?>
    <div class="site-wrap">
      <p class="site-kicker"><?= esc(t('Site.secNews')) ?></p>
      <?= $txt('news.heading', 'h2', 'site-h2', t('Site.phHeading')) ?>
      <?php if ($edit): ?><p class="site-auto"><?= icon('info', 14) ?> <?= esc(t('Site.newsAuto')) ?> <a class="link" href="<?= site_url('noticias') ?>"><?= esc(t('Site.manageNews')) ?></a></p><?php endif ?>
      <?php if ($news): ?>
        <div class="site-news">
          <?php foreach ($news as $n): ?>
            <a class="site-news-card" href="<?= site_url($slug . '/noticias/' . $n['id']) ?>"<?= $edit ? ' target="_blank" rel="noopener"' : '' ?>>
              <?php if ($n['cover_path']): ?>
                <img src="<?= site_url($slug . '/noticias/' . $n['id'] . '/imagen') ?>" alt="" loading="lazy" decoding="async">
              <?php else: ?>
                <span class="site-news-ph" aria-hidden="true"><?= icon('newspaper', 28) ?></span>
              <?php endif ?>
              <span class="site-news-body">
                <span class="text-xs soft"><?= esc(date_short($n['published_at'], false)) ?></span>
                <strong><?= esc($n['title']) ?></strong>
                <?php if ($n['summary']): ?><span class="text-sm soft"><?= esc($n['summary']) ?></span><?php endif ?>
              </span>
            </a>
          <?php endforeach ?>
        </div>
      <?php elseif ($edit): ?>
        <div class="site-empty"><?= icon('newspaper', 22) ?><p><?= esc(t('Site.newsEmpty')) ?></p></div>
      <?php endif ?>
    </div>
  </section>

  <?php elseif ($k === 'events'): ?>
  <section id="eventos" class="site-sec"<?= $offAttr ?>>
    <?= $edit ? $tools('events') : '' ?>
    <div class="site-wrap">
      <p class="site-kicker"><?= esc(t('Site.secEvents')) ?></p>
      <?= $txt('events.heading', 'h2', 'site-h2', t('Site.phHeading')) ?>
      <?php if ($edit): ?><p class="site-auto"><?= icon('info', 14) ?> <?= esc(t('Site.eventsAuto')) ?> <a class="link" href="<?= site_url('eventos') ?>"><?= esc(t('Site.manageEvents')) ?></a></p><?php endif ?>
      <?php if ($events): ?>
        <ul class="site-events">
          <?php foreach ($events as $e): $ts = strtotime($e['starts_at']); ?>
            <li class="site-event">
              <span class="site-event-date" aria-hidden="true"><strong><?= date('j', $ts) ?></strong><span><?= esc(((array) lang('Dates.monthsShort'))[(int) date('n', $ts) - 1] ?? '') ?></span></span>
              <span class="site-event-body">
                <strong><?= esc($e['title']) ?></strong>
                <span class="text-sm soft"><?= esc(date_short($e['starts_at'])) ?> · <?= (int) $e['all_day'] ? esc(t('Site.allDay')) : esc(date('g:i a', $ts)) ?><?= $e['location'] ? ' · ' . esc($e['location']) : '' ?></span>
                <?php if ($e['description']): ?><span class="text-sm site-event-desc"><?= esc($e['description']) ?></span><?php endif ?>
              </span>
            </li>
          <?php endforeach ?>
        </ul>
      <?php elseif ($edit): ?>
        <div class="site-empty"><?= icon('calendar-check', 22) ?><p><?= esc(t('Site.eventsEmpty')) ?></p></div>
      <?php endif ?>
    </div>
  </section>

  <?php elseif ($k === 'contact'): ?>
  <section id="contacto" class="site-sec site-alt"<?= $offAttr ?>>
    <?= $edit ? $tools('contact') : '' ?>
    <div class="site-wrap site-contact">
      <div>
        <p class="site-kicker"><?= esc(t('Site.secContact')) ?></p>
        <?= $txt('contact.heading', 'h2', 'site-h2 site-left', t('Site.phHeading')) ?>
        <?= $txt('contact.intro', 'div', 'site-prose', t('Site.phIntro')) ?>
        <ul class="site-contact-list">
          <?php foreach (['address' => 'map-pin', 'phone' => 'phone', 'whatsapp' => 'message-circle', 'email' => 'mail', 'hours' => 'clock'] as $f => $ic): if (! $edit && $c[$f] === '') { continue; } ?>
            <li><span class="site-contact-ic"><?= icon($ic, 16) ?></span><span><span class="text-xs soft"><?= esc(t('Site.' . $f)) ?></span><?= $txt('contact.' . $f, 'span', 'site-contact-val', t('Site.ph' . ucfirst($f))) ?></span></li>
          <?php endforeach ?>
        </ul>
      </div>
      <?php if (! $edit && ($waLink || $c['email'] !== '' || $mapLink)): ?>
        <aside class="site-contact-card">
          <h3><?= esc(t('Site.talkToUs')) ?></h3>
          <p class="text-sm soft"><?= esc(t('Site.talkToUsText')) ?></p>
          <?php if ($waLink): ?><a class="btn btn-lg btn-primary" href="<?= esc($waLink, 'attr') ?>" target="_blank" rel="noopener"><?= icon('message-circle', 16) ?> <?= esc(t('Site.writeWhatsapp')) ?></a><?php endif ?>
          <?php if ($c['email'] !== ''): ?><a class="btn btn-lg" href="mailto:<?= esc($c['email'], 'attr') ?>"><?= icon('mail', 16) ?> <?= esc(t('Site.sendEmail')) ?></a><?php endif ?>
          <?php if ($mapLink): ?><a class="btn btn-lg" href="<?= esc($mapLink, 'attr') ?>" target="_blank" rel="noopener"><?= icon('map', 16) ?> <?= esc(t('Site.directions')) ?></a><?php endif ?>
        </aside>
      <?php elseif ($edit): ?>
        <aside class="site-contact-card">
          <h3><?= esc(t('Site.talkToUs')) ?></h3>
          <p class="text-sm soft"><?= esc(t('Site.contactButtonsNote')) ?></p>
        </aside>
      <?php endif ?>
    </div>
  </section>
  <?php endif ?>
<?php endforeach ?>
</main>

<footer class="site-foot">
  <div class="site-wrap site-foot-in">
    <span>© <?= date('Y') ?> <?= esc($inst['name']) ?></span>
    <a href="<?= site_url('/') ?>" class="site-powered"><img src="<?= base_url('assets/img/logo-mark.png') ?>" alt="" width="18" height="18"> <?= esc(t('Site.poweredBy', ['app' => config('AgendaEscolar')->appName])) ?></a>
  </div>
</footer>

<?php if ($canEdit): ?>
  <a class="btn btn-primary site-edit-fab" href="<?= site_url('pagina-publica') ?>"><?= icon('pencil', 15) ?> <?= esc(t('Site.editPage')) ?></a>
<?php endif ?>
<?= $this->include('partials/toasts') ?>
</body>
</html>
