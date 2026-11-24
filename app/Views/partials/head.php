<?php
/** @var string|null $title */
$appName = config('AgendaEscolar')->appName;
?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= csrf_hash() ?>">
<meta name="csrf-name" content="<?= csrf_token() ?>">
<?php /* Título de pestaña: «Pantalla · Agenda Escolar» (el nombre de la institución ya se ve en la barra superior). */ ?>
<title><?= esc(($title ?? null) ? $title . ' · ' . $appName : $appName) ?></title>
<script>
  /* Textos del JavaScript en el idioma de la interfaz (app/Language/{es,en}/Js.php); app.js los usa con t(). */
  window.I18N = <?= json_encode((static function () { $f = APPPATH . 'Language/' . service('locale')->current() . '/Js.php'; return is_file($f) ? (array) require $f : []; })(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  /* Tema antes de pintar para evitar parpadeo. */
  try { var t = localStorage.getItem('agenda-theme'); if (t) document.documentElement.setAttribute('data-theme', t); } catch (e) {}
</script>
<link rel="icon" href="<?= base_url('favicon.ico') ?>?v=2" sizes="any">
<link rel="icon" type="image/png" href="<?= base_url('assets/img/favicon-32.png') ?>?v=2" sizes="32x32">
<link rel="apple-touch-icon" href="<?= base_url('assets/img/apple-touch-icon.png') ?>?v=2">
<link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>?v=63">
<?php if ($brandCss = \App\Libraries\Brand::css()): /* fuente elegida en Consola › Configuración › Marca */ ?><style><?= $brandCss ?></style><?php endif ?>
<script defer src="<?= base_url('assets/js/app.js') ?>?v=33"></script>
<script defer src="<?= base_url('assets/vendor/lucide.min.js') ?>"></script>
<script defer src="<?= base_url('assets/vendor/alpine.min.js') ?>"></script>
