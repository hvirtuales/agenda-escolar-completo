<?php
/**
 * Página pública de Agenda Escolar (landing). Estructura de FUNCIONALIDADES.md §5. Las capturas son reales, de la
 * demostración (Colegio Horizonte), en public/assets/img/landing, mostradas dentro de equipos dibujados con CSS. Las fotos
 * (public/assets/img/landing/fotos) llevan en sus pantallas capturas reales de la plataforma, compuestas en perspectiva.
 * Cada rol tiene un tono (t-blue docentes, t-green estudiantes, t-amber familias, t-violet directivos).
 * Las tarjetas flotantes reproducen avisos reales de la plataforma. Sin cifras ni testimonios inventados.
 *
 * @var array $roles, $sizes
 */
$app  = config('AgendaEscolar')->appName;
$err  = session('errors') ?? [];
$e    = static fn (string $k): string => isset($err[$k]) ? '<span class="field-error" id="err-' . $k . '">' . esc($err[$k]) . '</span>' : '';
$inv  = static fn (string $k): string => isset($err[$k]) ? ' aria-invalid="true" aria-describedby="err-' . $k . '"' : '';
$sent = (bool) session('demoSent');
$img  = static fn (string $f): string => base_url('assets/img/landing/' . $f . '.webp');
$foto = static fn (string $n): string => base_url('assets/img/landing/fotos/' . $n . '.webp');
$banners = [
    ['imagen01', '#como-funciona', 'Docentes con IA para enseñar mejor: planeación, actividades, seguimiento y mensajes. Conoce la plataforma'],
    ['imagen02', '#demo', 'Familias siempre conectadas: tareas, avisos, eventos y recordatorios desde el celular. Solicita una demo'],
    ['imagen03', '#demo', 'Aprende, organiza y avanza: tareas, notas, recordatorios y progreso para estudiantes. Empieza hoy'],
    ['imagen04', '#demo', 'Transforma la gestión de tu colegio: gestión escolar, reportes, comunicación y seguimiento. Agenda una demostración'],
    ['imagen05', '#demo', 'Agenda Escolar con inteligencia artificial: organiza, conecta y acompaña a tu comunidad escolar. Solicita una demo'],
];
// Escenas de la galería: [foto, texto alternativo].
$gallery = [
    ['imagen01', 'Estudiante en el patio del colegio revisando su agenda en el portátil'],
    ['imagen02', 'Estudiante en el aula de arte con su cuaderno digital abierto'],
    ['imagen06', 'Estudiante en la sala de informática resolviendo un reto'],
    ['imagen08', 'Estudiante en el vestíbulo del auditorio mirando su progreso'],
];
$people = [
    ['imagen20', 'Una familia en casa mira en el celular las novedades del colegio', 'Familias al tanto, desde el celular'],
    ['imagen22', 'Madres y padres a la salida del colegio consultan la plataforma en el celular', 'Avisos y mensajes del colegio a tiempo'],
    ['imagen24', 'Un directivo revisa la plataforma desde su escritorio', 'Directivos que acompañan con datos'],
];

/** Portátil, tableta o celular con una captura dentro. */
$device = static function (string $type, string $file, string $alt, bool $lazy = true) use ($img): string {
    [$w, $h] = $type === 'phone' ? [500, 1000] : [1280, 800];

    return '<figure class="device device-' . $type . '"><div class="device-screen"><img src="' . $img($file) . '" alt="' . esc($alt, 'attr') . '" width="' . $w . '" height="' . $h . '"'
        . ($lazy ? ' loading="lazy" decoding="async"' : ' fetchpriority="high"') . '></div>' . ($type === 'laptop' ? '<div class="device-base" aria-hidden="true"></div>' : '') . '</figure>';
};

/** Tarjeta flotante decorativa (aviso de la plataforma). */
$float = static fn (string $pos, string $tone, string $ic, string $title, string $text): string => '<div class="lp-float ' . $pos . ' ' . $tone . '" aria-hidden="true"><span class="lp-float-ico">' . icon($ic, 16) . '</span><span><strong>' . esc($title) . '</strong><small>' . esc($text) . '</small></span></div>';

$steps = [
    ['t-blue', 'Docente', 'El docente prepara y publica', 'Desde su inicio ve lo que necesita su atención. Publica páginas en el cuaderno, crea un reto con pasos, pistas y rúbrica, y lo asigna al grupo con sus fechas.', 'laptop', 'docente-inicio', 'Inicio del docente: entregas por revisar, actividades abiertas y sus grupos'],
    ['t-green', 'Estudiante', 'El estudiante lo resuelve con el tutor', 'El reto aparece en su agenda. Sigue los pasos, abre pistas y, si se atasca, le pregunta al tutor de IA, que lo guía con preguntas sin darle la respuesta.', 'laptop', 'estudiante-reto', 'Reto de la estudiante con pasos, pistas, su entrega y el tutor de IA al lado'],
    ['t-blue', 'Docente', 'El docente revisa con rúbrica', 'Marca un nivel por criterio y la nota se calcula sola. Escribe la retroalimentación —o la propone la IA y él la ajusta— y califica o devuelve para corrección.', 'laptop', 'docente-revision', 'Revisión de entregas: lista de estudiantes, respuesta y rúbrica'],
    ['t-amber', 'Familia', 'La familia se entera a tiempo', 'Recibe el aviso y ve en su celular la agenda, las notas y lo que está pendiente. Nada que instalar ni configurar.', 'phone', 'familia-agenda', 'Agenda de la estudiante vista por su familia en el celular'],
];
$features = [
    ['t-green', 'calendar-days', 'Mi agenda', 'Cierres de retos y evaluaciones, notas del docente para el grupo, eventos de la institución y recordatorios personales en un calendario.'],
    ['t-blue', 'notebook-tabs', 'Cuadernos por asignatura', 'Secciones y páginas al estilo de un cuaderno digital. El docente escribe y publica; el estudiante ve solo lo publicado.'],
    ['t-rose', 'puzzle', 'Retos y evaluaciones', 'Retos con pasos, pistas y rúbrica; evaluaciones con tiempo, intentos y bancos de preguntas reutilizables.'],
    ['t-violet', 'sparkles', 'Tutor de IA en modo guía', 'Ayuda al estudiante con preguntas y pistas, sin darle la respuesta. El docente decide en qué reto se usa y cómo.'],
    ['t-teal', 'chart-column', 'Seguimiento y alertas', 'Avance por estudiante y por grupo, entregas pendientes y señales para acompañar a estudiantes y docentes.'],
    ['t-amber', 'megaphone', 'Comunicaciones', 'Mensajes masivos o personales según el rol, noticias con portada y un calendario de eventos para toda la comunidad.'],
];
$audiences = [
    ['t-blue', 'presentation', 'Docentes', ['Publican contenido y actividades en minutos', 'Asistente de IA para proponer preguntas, retos y rúbricas', 'Revisan entregas con rúbrica en una sola pantalla', 'Escriben a sus grupos y a las familias']],
    ['t-green', 'backpack', 'Estudiantes', ['Su agenda con todo lo que tienen por hacer', 'Sus asignaturas siempre a mano', 'Un tutor que les ayuda a pensar, no a copiar', 'Su progreso y la retroalimentación de cada docente']],
    ['t-amber', 'heart-handshake', 'Familias', ['Ven las actividades, notas y agenda de sus hijos', 'Reciben avisos y mensajes de la institución', 'Solo lectura: nada que configurar', 'Cada acudiente ve únicamente a sus estudiantes']],
    ['t-violet', 'school', 'Coordinación y rectoría', ['Estructura académica, personas y roles', 'Acompañamiento a los docentes con señales a tiempo', 'Noticias, eventos y mensajes a toda la comunidad', 'Control del consumo de IA por rol y por persona']],
];
$faq = [
    ['¿La IA les hace las tareas a los estudiantes?', 'No. En modo guía el tutor responde con preguntas y pistas, nunca con la respuesta final. El docente elige en cada reto si el tutor guía, explica o se apaga, y durante las evaluaciones la IA no está disponible.'],
    ['¿Qué ven las familias?', 'En solo lectura: el progreso, las actividades pendientes y vencidas, la retroalimentación de los docentes, la agenda y las noticias y eventos que la institución publique para ellas. No ven las observaciones internas de los docentes.'],
    ['¿Funciona en el celular?', 'Sí. Es una plataforma web que se adapta a computador, tableta y celular, sin instalar nada.'],
    ['¿Qué pasa con los datos de los estudiantes?', 'Cada institución tiene sus datos aislados: ninguna ve los de otra, ni siquiera dentro de una red. Los permisos van por rol, las acciones sensibles quedan en una auditoría y los administradores entran con verificación en dos pasos.'],
    ['¿Sirve para varios colegios?', 'Sí. Cada colegio tiene su espacio y su color, y una red puede ver un resumen de sus colegios y compartir bancos de preguntas sin mezclar datos de estudiantes.'],
    ['¿Cuánto cuesta?', 'Depende del número de estudiantes y del plan de IA. Solicita una demostración y te enviamos una propuesta para tu institución.'],
];
?>
<!doctype html>
<html lang="es" style="<?= esc(brand_vars(null), 'attr') ?>">
<head>
  <?= $this->include('partials/head') ?>
  <meta name="description" content="<?= esc($app) ?>: agenda, cuadernos digitales, retos, evaluaciones, comunicaciones y un tutor de IA que guía sin dar respuestas. Para colegios y redes educativas.">
</head>
<body class="lp">
<a class="skip-link" href="#contenido">Saltar al contenido</a>

<header class="lp-top">
  <div class="lp-wrap lp-top-in">
    <a class="lp-brand" href="<?= site_url('/') ?>"><img src="<?= base_url('assets/img/logo-mark.png') ?>" alt="" width="36" height="36"><span><?= esc($app) ?></span></a>
    <nav class="lp-nav hide-mobile" aria-label="Secciones">
      <a href="#como-funciona">Cómo funciona</a><a href="#funciones">Funciones</a><a href="#para-quien">Para quién</a><a href="#preguntas">Preguntas</a>
    </nav>
    <div class="row" style="gap:0.5rem">
      <a class="btn" href="<?= site_url('login') ?>"><?= icon('log-in', 16) ?> Iniciar sesión</a>
      <a class="btn btn-primary lp-demo-btn" href="#demo">Solicitar demostración</a>
    </div>
  </div>
</header>

<main id="contenido">
  <!-- 1. Portada: carrusel de banners (public/assets/img/landing/banners; originales en herramientas/banners).
       Cada banner trae su título y su botón dibujados: toda la diapositiva es el enlace de ese botón. -->
  <section class="lp-slider" x-data="lpSlider(<?= count($banners) ?>)" @mouseenter="pause = true" @mouseleave="pause = false" @focusin="pause = true" @focusout="pause = false"
           aria-roledescription="carrusel" aria-label="Agenda Escolar para docentes, familias, estudiantes y colegios">
    <h1 class="sr-only">Agenda Escolar: la agenda, los cuadernos y las actividades del colegio en un solo lugar, con inteligencia artificial</h1>
    <div class="lp-slider-track" :style="`transform: translateX(-${i * 100}%)`" @touchstart.passive="touch($event)" @touchend.passive="swipe($event)">
      <?php foreach ($banners as $n => [$file, $href, $alt]): ?>
        <a class="lp-slide" href="<?= $href ?>" role="group" aria-roledescription="diapositiva" aria-label="<?= $n + 1 ?> de <?= count($banners) ?>: <?= esc($alt, 'attr') ?>"
           :aria-hidden="i !== <?= $n ?> ? 'true' : 'false'" :tabindex="i === <?= $n ?> ? 0 : -1">
          <img src="<?= base_url('assets/img/landing/banners/' . $file . '.webp') ?>"
               srcset="<?= base_url('assets/img/landing/banners/' . $file . '-1086.webp') ?> 1086w, <?= base_url('assets/img/landing/banners/' . $file . '.webp') ?> 2172w"
               sizes="100vw" width="2172" height="724" alt="<?= esc($alt, 'attr') ?>" <?= $n === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?> decoding="async">
        </a>
      <?php endforeach ?>
    </div>
    <button type="button" class="btn btn-icon lp-slider-nav is-prev" @click="go(i - 1)" title="Banner anterior" aria-label="Banner anterior"><?= icon('chevron-left', 18) ?></button>
    <button type="button" class="btn btn-icon lp-slider-nav is-next" @click="go(i + 1)" title="Banner siguiente" aria-label="Banner siguiente"><?= icon('chevron-right', 18) ?></button>
    <div class="lp-slider-dots" role="group" aria-label="Elegir banner">
      <?php foreach ($banners as $n => [, , $alt]): ?>
        <button type="button" class="lp-dot" :class="{ 'is-on': i === <?= $n ?> }" @click="go(<?= $n ?>)" :aria-current="i === <?= $n ?> ? 'true' : 'false'" title="Ver banner <?= $n + 1 ?>" aria-label="Ver banner <?= $n + 1 ?>: <?= esc($alt, 'attr') ?>"></button>
      <?php endforeach ?>
    </div>
  </section>
  <!-- En el celular el texto de los banners queda pequeño: debajo van los botones principales. -->
  <div class="lp-wrap lp-slider-cta only-phone">
    <a class="btn btn-primary btn-lg" href="#demo">Solicitar una demostración</a>
    <a class="btn btn-lg" href="#como-funciona">Ver cómo funciona <?= icon('arrow-down', 16) ?></a>
  </div>

  <!-- 1b. Galería: la plataforma en distintos espacios del colegio -->
  <section class="lp-section lp-gallery-sec" aria-labelledby="t-galeria">
    <div class="lp-wrap">
      <p class="lp-kicker">En todo el colegio</p>
      <h2 class="lp-h2" id="t-galeria">En el aula, la sala de informática o el patio</h2>
      <p class="lp-sub">Funciona en el computador, la tableta o el celular, sin instalar nada.</p>
    </div>
    <ul class="lp-wrap lp-gallery" aria-label="Escenas de uso">
      <?php foreach ($gallery as [$n, $alt]): ?>
        <li><img src="<?= $foto($n) ?>" alt="<?= esc($alt, 'attr') ?>" width="900" height="1125" loading="lazy" decoding="async"></li>
      <?php endforeach ?>
    </ul>
  </section>

  <!-- 2. Un día con Agenda Escolar -->
  <section class="lp-section" id="como-funciona" aria-labelledby="t-dia">
    <div class="lp-wrap">
      <p class="lp-kicker">Cómo funciona</p>
      <h2 class="lp-h2" id="t-dia">Un día con <?= esc($app) ?></h2>
      <p class="lp-sub">Del docente al estudiante y a la familia, sin herramientas sueltas.</p>
      <ol class="lp-steps">
        <?php foreach ($steps as $i => [$tone, $who, $t, $d, $type, $file, $alt]): ?>
          <li class="lp-step <?= $tone ?> lp-reveal">
            <div class="lp-step-media"><?= $device($type, $file, $alt) ?></div>
            <div class="lp-step-text">
              <div class="row" style="gap:0.5rem"><span class="lp-step-n"><?= $i + 1 ?></span><span class="lp-tag"><?= esc($who) ?></span></div>
              <h3><?= esc($t) ?></h3><p><?= esc($d) ?></p>
            </div>
          </li>
        <?php endforeach ?>
      </ol>
      <div class="lp-center"><a class="btn btn-primary btn-lg" href="#demo">Quiero verlo en mi colegio</a></div>
    </div>
  </section>

  <!-- 3. Funciones -->
  <section class="lp-section lp-alt" id="funciones" aria-labelledby="t-funciones">
    <div class="lp-wrap">
      <p class="lp-kicker">Funciones</p>
      <h2 class="lp-h2" id="t-funciones">Todo lo que el colegio necesita para enseñar y acompañar</h2>
      <p class="lp-sub">Una sola plataforma en lugar de varias herramientas sueltas.</p>
      <div class="lp-grid3">
        <?php foreach ($features as [$tone, $ic, $t, $d]): ?>
          <article class="lp-card lp-card-hover <?= $tone ?> lp-reveal"><span class="lp-ico"><?= icon($ic, 20) ?></span><h3><?= esc($t) ?></h3><p><?= esc($d) ?></p></article>
        <?php endforeach ?>
      </div>
    </div>
  </section>

  <!-- 4. Cuadernos y tutor -->
  <section class="lp-section" aria-labelledby="t-cuadernos">
    <div class="lp-wrap lp-split lp-show">
      <div>
        <p class="lp-kicker lp-kicker-left">Cuadernos y tutor</p>
        <h2 class="lp-h2" id="t-cuadernos">Cuadernos que se leen y un tutor que explica</h2>
        <p class="lp-text">Cada asignatura tiene su cuaderno con secciones y páginas. El estudiante lee lo que su docente publicó y, si no entiende, el tutor le explica la página con otras palabras, le da un ejemplo o le propone un ejercicio para practicar.</p>
        <ul class="lp-points">
          <li class="t-violet"><span class="lp-pt-ico"><?= icon('compass', 18) ?></span><span><strong>Guía, no resuelve.</strong> En los retos el tutor pregunta y da pistas; el docente elige el modo.</span></li>
          <li class="t-rose"><span class="lp-pt-ico"><?= icon('lock', 18) ?></span><span><strong>Sin IA en las evaluaciones.</strong> Mientras el estudiante presenta, el tutor no está disponible.</span></li>
          <li class="t-teal"><span class="lp-pt-ico"><?= icon('gauge', 18) ?></span><span><strong>Consumo bajo control.</strong> Cupos por institución, por rol y por persona, con avisos al 80 %.</span></li>
        </ul>
      </div>
      <div class="lp-stage t-violet lp-reveal">
        <?= $device('laptop', 'estudiante-cuaderno', 'Página del cuaderno de matemáticas con el tutor de IA abierto') ?>
        <?= $float('lp-float-c', 't-violet', 'message-circle', 'Explícamelo fácil', 'Dos fracciones son equivalentes si…') ?>
      </div>
    </div>
  </section>

  <!-- 5. Para quién -->
  <section class="lp-section lp-alt" id="para-quien" aria-labelledby="t-quien">
    <div class="lp-wrap">
      <p class="lp-kicker">Para quién</p>
      <h2 class="lp-h2" id="t-quien">Cada persona ve lo que necesita</h2>
      <p class="lp-sub">Permisos por rol en cada institución: nadie ve más de lo que le corresponde.</p>
      <div class="lp-grid4">
        <?php foreach ($audiences as [$tone, $ic, $t, $items]): ?>
          <article class="lp-card lp-card-role <?= $tone ?> lp-reveal"><span class="lp-ico"><?= icon($ic, 20) ?></span><h3><?= esc($t) ?></h3>
            <ul class="lp-list"><?php foreach ($items as $it): ?><li><?= icon('check', 14) ?><span><?= esc($it) ?></span></li><?php endforeach ?></ul></article>
        <?php endforeach ?>
      </div>
      <div class="lp-people">
        <?php foreach ($people as [$n, $alt, $cap]): ?>
          <figure class="lp-reveal"><img src="<?= $foto($n) ?>" alt="<?= esc($alt, 'attr') ?>" width="900" height="506" loading="lazy" decoding="async"><figcaption><?= esc($cap) ?></figcaption></figure>
        <?php endforeach ?>
      </div>
    </div>
  </section>

  <!-- 6. Seguimiento -->
  <section class="lp-section" aria-labelledby="t-seguimiento">
    <div class="lp-wrap lp-split lp-show is-flip">
      <div>
        <p class="lp-kicker lp-kicker-left">Seguimiento</p>
        <h2 class="lp-h2" id="t-seguimiento">Acompañar a tiempo, no al final del periodo</h2>
        <p class="lp-text">La coordinación ve la carga de cada docente y las entregas que llevan días sin revisar. Los docentes ven qué estudiantes tienen actividades vencidas o promedio bajo. Son señales para conversar y acompañar, no rankings.</p>
        <ul class="lp-points">
          <li class="t-amber"><span class="lp-pt-ico"><?= icon('triangle-alert', 18) ?></span><span><strong>Alertas con su razón.</strong> «Entregas sin revisar hace 12 días», «vencidas sin entregar».</span></li>
          <li class="t-green"><span class="lp-pt-ico"><?= icon('file-spreadsheet', 18) ?></span><span><strong>Reportes exportables.</strong> Notas por actividad y preguntas más difíciles, en Excel.</span></li>
        </ul>
        <a class="btn mt-4" href="#demo">Ver el seguimiento en una demostración</a>
      </div>
      <div class="lp-stage t-teal lp-reveal">
        <?= $device('tablet', 'coord-docentes', 'Vista Docentes de coordinación: entregas por revisar y señales para acompañar') ?>
        <?= $float('lp-float-d', 't-amber', 'triangle-alert', 'Para acompañar', 'Entregas sin revisar hace 12 días') ?>
      </div>
    </div>
  </section>

  <!-- 7. Comunicaciones -->
  <section class="lp-section lp-alt" aria-labelledby="t-comunicaciones">
    <div class="lp-wrap lp-split lp-show">
      <div>
        <p class="lp-kicker lp-kicker-left">Comunicaciones</p>
        <h2 class="lp-h2" id="t-comunicaciones">Comunicaciones para toda la comunidad</h2>
        <p class="lp-text">Mensajes masivos o personales según el rol, noticias con portada y un calendario de eventos que aparece en la agenda de cada persona.</p>
        <ul class="lp-points">
          <li class="t-blue"><span class="lp-pt-ico"><?= icon('mail', 18) ?></span><span><strong>Mensajes por rol, grupo o persona,</strong> con aviso y confirmación de lectura.</span></li>
          <li class="t-rose"><span class="lp-pt-ico"><?= icon('newspaper', 18) ?></span><span><strong>Noticias y boletines</strong> para docentes, estudiantes, familias o todos.</span></li>
          <li class="t-green"><span class="lp-pt-ico"><?= icon('calendar-check', 18) ?></span><span><strong>Eventos</strong>: reuniones de padres, actos, salidas y festivos en la agenda de todos.</span></li>
        </ul>
      </div>
      <div class="lp-stage t-amber lp-reveal">
        <?= $device('laptop', 'coord-mensaje', 'Nuevo mensaje: destinatarios por rol, por grupo o por persona') ?>
        <?= $float('lp-float-c', 't-blue', 'mail-check', 'Entrega de informes', 'Mensaje importante · todas las familias') ?>
      </div>
    </div>
  </section>

  <!-- 8. Seguridad -->
  <section class="lp-section lp-dark" id="seguridad" aria-labelledby="t-seguridad">
    <div class="lp-wrap">
      <p class="lp-kicker">Seguridad</p>
      <h2 class="lp-h2" id="t-seguridad">Privacidad de los estudiantes, primero</h2>
      <p class="lp-sub">Los datos de menores de edad merecen el máximo cuidado.</p>
      <div class="lp-grid4">
        <article class="lp-card lp-reveal"><span class="lp-ico"><?= icon('building-2', 20) ?></span><h3>Cada institución, aislada</h3><p>Ninguna ve los datos de otra, ni dentro de una misma red.</p></article>
        <article class="lp-card lp-reveal"><span class="lp-ico"><?= icon('eye-off', 20) ?></span><h3>Lo justo para cada rol</h3><p>Las observaciones internas no las ven estudiantes ni familias.</p></article>
        <article class="lp-card lp-reveal"><span class="lp-ico"><?= icon('key-round', 20) ?></span><h3>Acceso protegido</h3><p>Verificación en dos pasos para administradores y límite de intentos.</p></article>
        <article class="lp-card lp-reveal"><span class="lp-ico"><?= icon('scroll-text', 20) ?></span><h3>Todo queda registrado</h3><p>Auditoría de acciones sensibles que no se puede editar.</p></article>
      </div>
    </div>
  </section>

  <!-- 9. Colegios y redes -->
  <section class="lp-section" id="redes" aria-labelledby="t-redes">
    <div class="lp-wrap lp-split">
      <div>
        <p class="lp-kicker lp-kicker-left">Colegios y redes</p>
        <h2 class="lp-h2" id="t-redes">Para un colegio o para una red de colegios</h2>
        <p class="lp-text">Cada institución tiene su espacio, su color, sus grupos y sus usuarios. Las redes ven un resumen de sus colegios y comparten bancos de preguntas, sin mezclar datos de estudiantes. Un docente que trabaja en varios colegios entra con una sola cuenta.</p>
        <a class="btn btn-primary mt-4" href="#demo">Solicitar una demostración</a>
      </div>
      <ul class="lp-points lp-panel lp-reveal">
        <li class="t-rose"><span class="lp-pt-ico"><?= icon('palette', 18) ?></span><span><strong>Con la identidad de tu institución.</strong> Color propio en toda la plataforma.</span></li>
        <li class="t-blue"><span class="lp-pt-ico"><?= icon('library', 18) ?></span><span><strong>Biblioteca institucional.</strong> Plantillas, recursos y bancos de preguntas.</span></li>
        <li class="t-teal"><span class="lp-pt-ico"><?= icon('network', 18) ?></span><span><strong>Redes educativas.</strong> Bancos compartidos y una vista de conjunto.</span></li>
        <li class="t-violet"><span class="lp-pt-ico"><?= icon('accessibility', 18) ?></span><span><strong>Accesible.</strong> Tamaño de texto, menos movimiento, modo oscuro y lectura en voz alta.</span></li>
        <li class="t-amber"><span class="lp-pt-ico"><?= icon('bot', 18) ?></span><span><strong>Ayuda integrada.</strong> Artículos paso a paso y un asistente que responde cómo usar la plataforma.</span></li>
      </ul>
    </div>
  </section>

  <!-- 10. Preguntas frecuentes -->
  <section class="lp-section lp-alt" id="preguntas" aria-labelledby="t-faq">
    <div class="lp-wrap lp-faq-wrap">
      <p class="lp-kicker">Preguntas</p>
      <h2 class="lp-h2" id="t-faq">Preguntas frecuentes</h2>
      <div class="lp-faq">
        <?php foreach ($faq as [$q, $a]): ?>
          <details class="lp-faq-item"><summary><?= esc($q) ?><?= icon('chevron-down', 18, 'lp-faq-chev') ?></summary><p><?= esc($a) ?></p></details>
        <?php endforeach ?>
      </div>
    </div>
  </section>

  <!-- 11. Demostración -->
  <section class="lp-section lp-band" id="demo" aria-labelledby="t-demo">
    <div class="lp-wrap lp-demo">
      <div>
        <h2 class="lp-h2" id="t-demo" style="text-align:left">Solicita una demostración</h2>
        <p class="lp-text">Te mostramos la plataforma con tu estructura académica y resolvemos tus preguntas. Sin compromiso.</p>
        <ul class="lp-list">
          <li><?= icon('check', 14) ?><span>Recorrido guiado para rectoría, coordinación y docentes</span></li>
          <li><?= icon('check', 14) ?><span>Periodo de prueba para tu institución</span></li>
          <li><?= icon('check', 14) ?><span>Acompañamiento en la configuración inicial</span></li>
        </ul>
      </div>

      <div class="card lp-form">
        <?php if ($sent): ?>
          <div class="card-body lp-thanks" role="status">
            <?= icon('circle-check', 32, 'text-ok') ?>
            <h3>¡Gracias! Recibimos tu solicitud.</h3>
            <p class="soft">Te escribiremos al correo que nos dejaste para acordar la demostración.</p>
          </div>
        <?php else: ?>
          <form method="post" action="<?= site_url('demostracion') ?>" class="card-body stack" novalidate>
            <?= csrf_field() ?>
            <div class="lp-hp" aria-hidden="true"><label>No llenes este campo <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
            <div class="grid-2">
              <label class="field"><span class="label">Nombre <span class="text-danger">*</span></span><input class="input" name="name" required maxlength="120" autocomplete="name" value="<?= esc(old('name') ?? '', 'attr') ?>"<?= $inv('name') ?>><?= $e('name') ?></label>
              <label class="field"><span class="label">Correo <span class="text-danger">*</span></span><input class="input" type="email" name="email" required maxlength="160" autocomplete="email" value="<?= esc(old('email') ?? '', 'attr') ?>"<?= $inv('email') ?>><?= $e('email') ?></label>
              <label class="field col-full"><span class="label">Institución <span class="text-danger">*</span></span><input class="input" name="institution" required maxlength="160" autocomplete="organization" value="<?= esc(old('institution') ?? '', 'attr') ?>"<?= $inv('institution') ?>><?= $e('institution') ?></label>
              <label class="field"><span class="label">Cargo</span><select class="select" name="role"><option value="">Elige una opción</option><?php foreach ($roles as $r): ?><option <?= old('role') === $r ? 'selected' : '' ?>><?= esc($r) ?></option><?php endforeach ?></select></label>
              <label class="field"><span class="label">Número de estudiantes</span><select class="select" name="students"><option value="">Elige una opción</option><?php foreach ($sizes as $s): ?><option <?= old('students') === $s ? 'selected' : '' ?>><?= esc($s) ?></option><?php endforeach ?></select></label>
              <label class="field"><span class="label">Ciudad</span><input class="input" name="city" maxlength="80" autocomplete="address-level2" value="<?= esc(old('city') ?? '', 'attr') ?>"></label>
              <label class="field"><span class="label">Teléfono</span><input class="input" type="tel" name="phone" maxlength="40" autocomplete="tel" value="<?= esc(old('phone') ?? '', 'attr') ?>"<?= $inv('phone') ?>><?= $e('phone') ?></label>
              <label class="field col-full"><span class="label">¿Qué te gustaría ver?</span><textarea class="textarea" name="message" rows="3" maxlength="1000"><?= esc(old('message') ?? '') ?></textarea></label>
            </div>
            <p class="hint">Usaremos estos datos solo para contactarte sobre la demostración.</p>
            <button type="submit" class="btn btn-primary btn-lg" data-loading="Enviando…">Solicitar demostración</button>
          </form>
        <?php endif ?>
      </div>
    </div>
  </section>
</main>

<footer class="lp-foot">
  <div class="lp-wrap lp-foot-in">
    <span class="row"><img src="<?= base_url('assets/img/logo-mark.png') ?>" alt="" width="24" height="24"> <?= esc($app) ?> · <?= date('Y') ?></span>
    <span class="row" style="gap:1rem"><a href="#preguntas">Preguntas</a><a href="#demo">Solicitar demostración</a><a href="<?= site_url('login') ?>">Iniciar sesión</a></span>
  </div>
</footer>
<?= $this->include('partials/toasts') ?>
<?= $this->include('partials/contact') ?>
</body>
</html>
