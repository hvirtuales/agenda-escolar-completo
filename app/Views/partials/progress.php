<?php
/**
 * Progreso de un estudiante (compartido): inicio del estudiante y portal del acudiente.
 * KPIs, tarjetas por asignatura, retroalimentación de los docentes, «Por hacer», vencidas y (estudiante) logros.
 *
 * @var array  $p    StudentProgress::progress()
 * @var array  $acts StudentProgress::activities()
 * @var string $mode 'student' (con enlaces a sus actividades) o 'guardian' (solo lectura, en tercera persona «usted»)
 */
use App\Libraries\StudentProgress;

$student = ($mode ?? 'student') === 'student';
$num     = static fn ($v) => $v === null ? '—' : number_format((float) $v, 1, ',', '');
$tone    = static fn ($v) => $v === null ? 'neutral' : ($v < 3 ? 'danger' : ($v < 4 ? 'warn' : 'ok'));
$c       = $p['counts'];
$pending = array_values(array_filter($acts, static fn ($a) => in_array($a['state'], StudentProgress::GROUPS['pending'][1], true)));
usort($pending, static fn ($a, $b) => strcmp($a['due_at'], $b['due_at']));
$missed  = array_values(array_filter($acts, static fn ($a) => $a['state'] === 'missed'));
$stateLabel = ['todo' => ['Por hacer', 'neutral'], 'in_progress' => ['En curso', 'accent'], 'returned' => ['Devuelta para corregir', 'warn'], 'late' => ['Vencida, aún se puede entregar', 'warn']];
$maxTodo = $student ? 5 : 50;
$aside   = $student || $missed; // columna derecha (logros o vencidas); sin ella el contenido usa todo el ancho
// Cuándo cierra: las vencidas que aún se pueden entregar ya «cerraron».
$due     = static fn (array $a): string => ($a['state'] === 'late' ? 'Cerró ' : 'Cierra ') . date_short($a['due_at']);
// Fila de una actividad: enlace para el estudiante, texto para el acudiente.
$row = static function (array $a, string $badge, string $meta) use ($student): string {
    $inner = '<span class="act-icon">' . icon($a['kind'] === 'quiz' ? 'clipboard-list' : 'target', 16) . '</span>'
        . '<span style="min-width:0;flex:1"><span class="block font-medium truncate">' . esc($a['title']) . '</span><span class="block text-xs soft">' . esc($meta) . '</span></span>' . $badge;

    return $student ? '<a class="date-row" href="' . site_url($a['url']) . '">' . $inner . '</a>' : '<div class="date-row">' . $inner . '</div>';
};
?>
<div class="grid-kpi">
  <div class="kpi"><div class="k-label"><?= $student ? 'Mi promedio' : 'Promedio' ?></div><div class="k-value"><?= $num($p['avg']) ?></div><div class="k-hint">Escala de 1 a 5</div></div>
  <div class="kpi"><div class="k-label">Entregadas</div><div class="k-value"><?= $c['done'] ?> <span class="text-sm soft">de <?= $c['total'] ?></span></div><div class="k-hint">Retos y evaluaciones asignados</div></div>
  <div class="kpi"><div class="k-label">Por hacer</div><div class="k-value"><?= count($pending) ?></div><div class="k-hint"><?= ! $pending ? 'Nada pendiente' : ($pending[0]['state'] === 'late' ? 'Una venció y aún se puede entregar' : 'La próxima cierra ' . esc(date_short($pending[0]['due_at']))) ?></div></div>
  <div class="kpi"><div class="k-label">Vencidas sin entregar</div><div class="k-value <?= $c['missed'] ? 'text-danger' : '' ?>"><?= $c['missed'] ?></div>
    <div class="k-hint"><?= $c['missed'] ? ($student ? 'Habla con tu docente si necesitas ayuda' : 'Conviene hablar con su docente') : ($student ? '¡Vas al día!' : 'Va al día') ?></div></div>
</div>

<div class="<?= $aside ? 'grid-3' : '' ?> mt-4" style="align-items:start">
  <section class="<?= $aside ? 'span-2 ' : '' ?>stack">
    <section class="card">
      <div class="card-header row-between"><h2>Por hacer</h2>
        <?php if ($student && count($pending) > $maxTodo): ?><a class="btn btn-sm" href="<?= site_url('estudiante/actividades') ?>">Ver todas (<?= count($pending) ?>)</a><?php endif ?></div>
      <?php if (! $pending): ?>
        <div class="card-body"><p class="hint"><?= $student ? 'No tienes actividades pendientes.' : 'No tiene actividades pendientes.' ?></p></div>
      <?php else: ?>
        <div class="date-list">
          <?php foreach (array_slice($pending, 0, $maxTodo) as $a): [$sl, $st] = $stateLabel[$a['state']] ?? ['Por hacer', 'neutral']; ?>
            <?= $row($a, badge($sl, $st), $a['subject'] . ' · ' . $due($a)) ?>
          <?php endforeach ?>
        </div>
      <?php endif ?>
    </section>

    <?php if ($p['subjects']): ?>
      <div class="group-cards">
        <?php foreach ($p['subjects'] as $s):
            $tag  = $student ? 'a' : 'div';
            $href = $student ? ' href="' . site_url('estudiante/actividades?ver=pending&asignatura=' . rawurlencode($s['name'])) . '"' : ''; ?>
          <<?= $tag ?> class="group-card"<?= $href ?> style="--cat:<?= cat_color($s['color']) ?>">
            <div class="row-between"><span class="g-title"><?= esc($s['name']) ?></span><?= $s['avg'] === null ? badge('Sin notas') : badge('Nota ' . $num($s['avg']), $tone($s['avg'])) ?></div>
            <div class="g-meta"><?= $s['total'] ? $s['done'] . ' de ' . $s['total'] . ' actividades entregadas' : 'Sin actividades todavía' ?><?= $s['missed'] ? ' · ' . plural($s['missed'], 'vencida', 'vencidas') : '' ?></div>
            <span class="share" role="img" aria-label="Avance en <?= esc($s['name'], 'attr') ?>: <?= (int) $s['progress'] ?> %"><span style="width:<?= (int) $s['progress'] ?>%"></span></span>
          </<?= $tag ?>>
        <?php endforeach ?>
      </div>
    <?php else: ?>
      <div class="empty"><?= icon('book-open', 32) ?><p><?= $student ? 'Aún no tienes asignaturas en este periodo.' : 'Aún no tiene asignaturas en este periodo.' ?></p></div>
    <?php endif ?>

    <section class="card">
      <div class="card-header"><h2><?= $student ? 'Retroalimentación de tus docentes' : 'Retroalimentación de los docentes' ?></h2></div>
      <?php if (! $p['feedback']): ?>
        <div class="card-body"><p class="hint"><?= $student ? 'Cuando tus docentes califiquen tus entregas, sus comentarios aparecerán aquí.' : 'Cuando los docentes califiquen sus entregas, sus comentarios aparecerán aquí.' ?></p></div>
      <?php else: ?>
        <div class="date-list">
          <?php foreach ($p['feedback'] as $f):
              $body = '<span style="flex:1;min-width:0"><span class="row-between"><span class="font-medium">' . esc($f['title']) . ' <span class="soft text-xs">· ' . esc($f['subject'])
                  . ($f['reviewed_at'] ? ' · ' . esc(date_short($f['reviewed_at'])) : '') . '</span></span>' . ($f['grade'] !== null ? badge($num($f['grade']), $tone((float) $f['grade'])) : '') . '</span>'
                  . '<span class="block mt-1">' . ($f['feedback'] ? esc($f['feedback']) : '<span class="soft">' . ($f['kind'] === 'quiz' ? 'Evaluación calificada.' : 'Revisado sin comentarios.') . '</span>') . '</span></span>'; ?>
            <?= $student ? '<a class="date-row" href="' . site_url($f['url']) . '" style="align-items:flex-start">' . $body . '</a>' : '<div class="date-row" style="align-items:flex-start">' . $body . '</div>' ?>
          <?php endforeach ?>
        </div>
      <?php endif ?>
    </section>
  </section>

  <?php if ($aside): ?><section class="stack">
    <?php if ($missed): ?>
      <section class="card">
        <div class="card-header"><h2>Vencidas sin entregar</h2></div>
        <div class="date-list">
          <?php foreach ($missed as $a): ?><?= $row($a, badge('Vencida', 'danger'), $a['subject'] . ' · Cerró ' . date_short($a['due_at'])) ?><?php endforeach ?>
        </div>
      </section>
    <?php endif ?>
    <?php if ($student): ?>
      <section class="card">
        <div class="card-header"><h2>Logros</h2></div>
        <div class="date-list">
          <?php foreach ($p['achievements'] as [$name, $ic, $why, $on]): ?>
            <div class="date-row" style="<?= $on ? '' : 'opacity:0.6' ?>">
              <span class="act-icon"><?= icon($ic, 18) ?></span>
              <span><span class="block font-medium"><?= esc($name) ?></span><span class="block text-xs soft"><?= esc($why) ?></span></span>
            </div>
          <?php endforeach ?>
        </div>
      </section>
    <?php endif ?>
  </section><?php endif ?>
</div>
