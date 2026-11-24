<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
/**
 * Mi agenda (docente, estudiante, acudiente y vista de estudiante): lista del día y próximos días a la izquierda,
 * calendario del mes a la derecha. Datos de service('agenda'); textos en app/Language/{es,en}/Agenda.php.
 *
 * Filtros (componente Alpine agendaFilters en app.js): tipo (eventos, actividades, notas, recordatorios), asignatura y
 * «ocultar lo hecho». Filtran en el navegador, sin recargar, la lista del día, los próximos días y el calendario; cada
 * persona los encuentra como los dejó (localStorage, por tipo de agenda).
 *
 * @var string $mode (student | teacher), $base, $month, $monthLabel, $prev, $next, $gridFrom, $gridTo, $day
 * @var array  $byDay, $upcoming, $offerings
 * @var bool   $readOnly (vista de estudiante o acudiente)
 * @var array  $baseQuery parámetros que viajan en cada enlace (acudiente: estudiante), $kid y $kids (acudiente)
 */
$icons   = ['due' => 'flag', 'opens' => 'play', 'note' => 'sticky-note', 'personal' => 'bell', 'event' => 'calendar-check'];
$cats    = ['due' => 'activity', 'opens' => 'activity', 'note' => 'note', 'personal' => 'personal', 'event' => 'event']; // tipo → filtro
$bq      = $baseQuery ?? [];
$url     = static fn (array $q): string => site_url($base) . '?' . http_build_query($bq + $q);
$guard   = ! empty($kid);
$today   = date('Y-m-d');
$student = $mode === 'student';
$canAdd  = ! $readOnly;
$blank   = ['id' => null, 'title' => '', 'entry_date' => $day, 'entry_time' => '', 'offering_id' => '', 'tag_offering_id' => '', 'notes' => ''];
$tagOfferings ??= [];
$long    = static fn (string $d): string => date_long($d);
$hm      = static fn (?string $t): string => $t ? date((string) lang('Dates.time'), strtotime($t)) : '';
$js      = static fn ($v): string => esc((string) json_encode($v, JSON_UNESCAPED_UNICODE), 'attr');
$editData = static fn (array $e): array => ['id' => $e['entry'], 'title' => $e['title'], 'entry_date' => $e['date'], 'entry_time' => (string) $e['time'],
    'offering_id' => (string) ($e['offering_id'] ?? ''), 'tag_offering_id' => (string) ($e['tag_offering_id'] ?? ''), 'notes' => $e['notes'] ?? ''];
$noteLabel = t($guard ? 'Agenda.typeNoteGuardian' : ($student ? 'Agenda.typeNoteStudent' : 'Agenda.typeNoteTeacher'));
$types   = ['due' => t('Agenda.typeDue'), 'opens' => t('Agenda.typeOpens'), 'note' => $noteLabel, 'personal' => t('Agenda.typePersonal'), 'event' => t('Agenda.typeEvent')];

/** Cuándo, en palabras (los eventos de la institución pueden durar varios días). */
$whenOf = static function (array $e) use ($long, $hm): string {
    if (! empty($e['event'])) {
        $s  = $e['event']['starts_at'];
        $en = $e['event']['ends_at'];
        $d1 = substr($s, 0, 10);
        $d2 = $en ? substr($en, 0, 10) : $d1;
        $days = $d1 === $d2 ? $long($d1) : t('Agenda.fromTo', [$long($d1), $long($d2)]);
        if ($e['event']['all_day']) {
            return $days . ' · ' . t('Agenda.allDayLower');
        }

        return $days . ' · ' . ($en ? t('Agenda.timeRange', [$hm(substr($s, 11, 5)), $hm(substr($en, 11, 5))]) : $hm(substr($s, 11, 5)));
    }

    return $long($e['date']) . ' · ' . ($e['time'] ? $hm($e['time']) : t('Agenda.allDayLower'));
};

/** Botón principal de la ventana: a dónde lleva el enlace del elemento. */
$goLabel = static function (array $e) use ($guard): ?string {
    $u = (string) ($e['url'] ?? '');

    return match (true) {
        $u === ''                        => null,
        $e['type'] === 'event'           => t('Agenda.goEvents'),
        str_starts_with($u, 'entregas/') => t('Agenda.goSubmissions'),
        $guard                           => t('Agenda.goProgress'),
        default                          => t('Agenda.goActivity'),
    };
};

/** Datos de la ventana de detalle (todo se muestra con x-text: no se interpreta como HTML). */
$detailOf = static fn (array $e): string => $js([
    'title' => $e['title'], 'type' => $types[$e['type']] ?? '', 'when' => $whenOf($e), 'meta' => (string) $e['meta'],
    'notes' => (string) ($e['notes'] ?? ''), 'color' => $e['color'] ? cat_color($e['color']) : null, 'done' => (bool) $e['done'],
    'url' => $e['url'] ? site_url($e['url']) : null, 'go' => $goLabel($e), 'edit' => $e['entry'] && $e['editable'] && ! $readOnly ? $editData($e) : null,
    'manage' => ! $readOnly && ! empty($canEvents) ? ($e['manage'] ?? null) : null, // evento de la institución editable aquí
]);
/** Lo que miran los filtros de un elemento: tipo, asignatura y si está hecho. */
$metaOf = static fn (array $e): array => ['t' => $cats[$e['type']] ?? 'event', 's' => $e['subject'] ?? null, 'd' => (bool) $e['done']];

/** Un elemento en la lista del día o de los próximos días. Al elegirlo se abre su detalle en una ventana. */
$item = static function (array $e, string $show, bool $withDate = false) use ($icons, $readOnly, $day, $detailOf, $editData, $js, $hm): string {
    $color = $e['color'] ? cat_color($e['color']) : null;
    ob_start(); ?>
    <div class="ag-item<?= $e['done'] ? ' is-done' : '' ?>"<?= $color ? ' style="--cat:' . $color . '"' : '' ?> x-show="<?= esc($show, 'attr') ?>">
      <span class="ag-bar" aria-hidden="true"></span>
      <span class="ag-time"><?= $withDate ? esc(date_short($e['date'])) . '<br>' : '' ?><?= $e['time'] ? esc($hm($e['time'])) : esc(t('Agenda.allDay')) ?></span>
      <span style="flex:1;min-width:0">
        <span class="row" style="gap:0.375rem"><?= icon($icons[$e['type']], 14, 'soft') ?>
          <button type="button" class="ag-title ag-open" @click="ev = <?= $detailOf($e) ?>" aria-haspopup="dialog"><?= esc($e['title']) ?></button></span>
        <span class="block text-xs soft"><?= esc($e['meta']) ?></span>
        <?php if (! empty($e['notes'])): ?><span class="block text-sm mt-1" style="white-space:pre-wrap"><?= esc($e['notes']) ?></span><?php endif ?>
      </span>
      <?php if ($e['entry'] && ! $readOnly): $mark = t($e['done'] ? 'Agenda.markPending' : 'Agenda.markDone', [$e['title']]); ?>
        <span class="row" style="gap:0.25rem">
          <form method="post" action="<?= site_url('agenda/' . $e['entry'] . '/hecha') ?>"><?= csrf_field() ?><input type="hidden" name="dia" value="<?= esc($day, 'attr') ?>">
            <button type="submit" class="btn btn-sm btn-icon<?= $e['done'] ? ' btn-active' : '' ?>" aria-pressed="<?= $e['done'] ? 'true' : 'false' ?>"
                    title="<?= esc($mark, 'attr') ?>" aria-label="<?= esc($mark, 'attr') ?>"><?= icon($e['done'] ? 'circle-check' : 'circle', 14) ?></button></form>
          <?php if ($e['editable']): ?>
            <button type="button" class="btn btn-sm btn-icon" @click="f = <?= $js($editData($e)) ?>" title="<?= esc(t('Agenda.edit', [$e['title']]), 'attr') ?>" aria-label="<?= esc(t('Agenda.edit', [$e['title']]), 'attr') ?>"><?= icon('pencil', 14) ?></button>
            <form method="post" action="<?= site_url('agenda/' . $e['entry'] . '/eliminar') ?>" onsubmit="return confirm(<?= $js(t('Agenda.confirmDelete', [$e['title']]) . ($e['type'] === 'note' ? t('Agenda.confirmDeleteNote') : '')) ?>)">
              <?= csrf_field() ?><input type="hidden" name="dia" value="<?= esc($day, 'attr') ?>">
              <button type="submit" class="btn btn-sm btn-icon btn-danger" title="<?= esc(t('Agenda.delete', [$e['title']]), 'attr') ?>" aria-label="<?= esc(t('Agenda.delete', [$e['title']]), 'attr') ?>"><?= icon('trash-2', 14) ?></button></form>
          <?php endif ?>
        </span>
      <?php endif ?>
    </div>
    <?php return (string) ob_get_clean();
};

// Asignaturas que aparecen en lo cargado (para el filtro), en orden alfabético.
$subjects = [];
foreach (array_merge(...array_values($byDay ?: [[]])) as $e) {
    if (! empty($e['subject'])) {
        $subjects[$e['subject']] = true;
    }
}
$subjects = array_keys($subjects);
$coll     = class_exists(\Collator::class) ? new \Collator(service('locale')->current()) : null;
usort($subjects, static fn ($a, $b) => $coll ? $coll->compare($a, $b) : strcasecmp($a, $b));
$dayList  = $byDay[$day] ?? [];
$filterTypes = ['event' => ['', t('Agenda.fEvents'), 'calendar-check'], 'activity' => ['', t('Agenda.fActivities'), 'flag'], 'note' => ['', t('Agenda.fNotes'), 'sticky-note']]
    + ($readOnly ? [] : ['personal' => ['', t('Agenda.fReminders'), 'bell']]);
$blankEvent = ['id' => null, 'title' => '', 'description' => '', 'location' => '', 'category' => 'academic', 'date' => $day, 'end_date' => '',
    'start_time' => '07:00', 'end_time' => '', 'all_day' => false, 'audience' => array_keys($eventAudiences ?? []), 'is_public' => false];
$opts = [
    'evf'      => ! empty($newEvent) ? $blankEvent : null,
    'key'      => 'agenda-filters-' . ($guard ? 'guardian' : ($readOnly ? 'preview' : $mode)),
    'subjects' => $subjects,
    'types'    => array_keys($filterTypes),
    'day'      => array_map($metaOf, $dayList),
    'next'     => array_map($metaOf, $upcoming),
    // Textos de conteo del calendario en el idioma activo ({n} = número).
    'l'        => ['zero' => t('Agenda.dayEvents', [0]), 'one' => t('Agenda.dayEvents', [1]), 'many' => str_replace('99', '{n}', t('Agenda.dayEvents', [99])), 'more' => t('Agenda.more', ['{n}'])],
];
?>
<div x-data="agendaFilters(<?= $js($opts) ?>)">
  <div class="page-header">
    <div><h1><?= $guard ? esc(t('Agenda.titleOf', [explode(' ', $kid['name'])[0]])) : esc(t('Agenda.title')) ?></h1>
      <p><?= esc(t($guard ? 'Agenda.introGuardian' : ($student ? ($readOnly ? 'Agenda.introPreview' : 'Agenda.introStudent') : 'Agenda.introTeacher'))) ?></p></div>
    <?php if ($guard && count($kids) > 1): ?>
      <form class="actions" method="get" action="<?= site_url('acudiente/agenda') ?>">
        <select class="select" name="estudiante" onchange="this.form.submit()" aria-label="<?= esc(t('Agenda.student'), 'attr') ?>" style="width:auto">
          <?php foreach ($kids as $k): ?><option value="<?= (int) $k['id'] ?>" <?= (int) $k['id'] === (int) $kid['id'] ? 'selected' : '' ?>><?= esc($k['name']) ?></option><?php endforeach ?>
        </select>
      </form>
    <?php endif ?>
    <?php if ($canAdd): ?>
      <div class="actions">
        <?php if (! empty($canEvents)): ?><button type="button" class="btn" @click="evf = <?= esc($js($blankEvent), 'attr') ?>"><?= icon('calendar-check', 16) ?> <?= esc(t('Agenda.newEvent')) ?></button><?php endif ?>
        <button type="button" class="btn btn-primary" @click="f = <?= $js($blank) ?>"><?= icon('calendar-plus', 16) ?> <?= esc(t($offerings ? 'Agenda.newNote' : 'Agenda.newReminder')) ?></button>
      </div>
    <?php endif ?>
  </div>

  <?php /* Filtros: tipo (se encienden y apagan), asignatura y ocultar lo hecho. */ ?>
  <div class="ag-filters" role="group" aria-label="<?= esc(t('Agenda.filters'), 'attr') ?>">
    <?php foreach ($filterTypes as $k => [, $label, $ic]): ?>
      <button type="button" class="btn btn-sm ag-chip" :class="{ 'btn-active': types.<?= $k ?> }" :aria-pressed="types.<?= $k ?>.toString()" @click="toggle('<?= $k ?>')"
              title="<?= esc(t('Agenda.fShowHide', [$label]), 'attr') ?>"><?= icon($ic, 14) ?> <?= esc($label) ?></button>
    <?php endforeach ?>
    <?php if (count($subjects) > 1): ?>
      <select class="select ag-subject" x-model="subj" @change="persist()" aria-label="<?= esc(t('Agenda.fSubject'), 'attr') ?>">
        <option value=""><?= esc(t('Agenda.fAllSubjects')) ?></option>
        <?php foreach ($subjects as $s): ?><option value="<?= esc($s, 'attr') ?>"><?= esc($s) ?></option><?php endforeach ?>
      </select>
    <?php endif ?>
    <label class="ag-done"><input type="checkbox" x-model="hideDone" @change="persist()"> <?= esc(t('Agenda.fHideDone')) ?></label>
    <button type="button" class="btn btn-ghost btn-sm" x-show="filtered" x-cloak @click="clear()"><?= icon('x', 14) ?> <?= esc(t('Agenda.fClear')) ?></button>
  </div>

  <div class="agenda">
    <div class="stack">
      <section class="card" aria-labelledby="day-title">
        <div class="card-header"><h2 id="day-title"><?= esc($long($day)) ?><?= $day === $today ? esc(t('Agenda.todaySuffix')) : '' ?></h2>
          <?php if ($canAdd): ?><button type="button" class="btn btn-sm btn-icon" @click="f = <?= $js($blank) ?>" title="<?= esc(t('Agenda.addOnDay'), 'attr') ?>" aria-label="<?= esc(t('Agenda.addOnDayLong', [$long($day)]), 'attr') ?>"><?= icon('plus', 14) ?></button><?php endif ?></div>
        <?php if ($dayList): ?>
          <div class="ag-list"><?php foreach ($dayList as $i => $e): ?><?= $item($e, 'showAt(day, ' . $i . ')') ?><?php endforeach ?></div>
        <?php endif ?>
        <div class="card-body" x-show="!vis(day).length" <?= $dayList ? 'x-cloak' : '' ?>>
          <p class="soft text-sm" x-text="day.length ? <?= $js(t('Agenda.nothingMatches')) ?> : <?= $js(t('Agenda.nothingToday')) ?>"><?= esc(t($dayList ? 'Agenda.nothingMatches' : 'Agenda.nothingToday')) ?></p></div>
      </section>

      <section class="card" aria-labelledby="next-title">
        <div class="card-header"><h2 id="next-title"><?= esc(t('Agenda.next14')) ?></h2></div>
        <?php if ($upcoming): ?>
          <?php /* Hasta 8 visibles: con filtros, siguen apareciendo los siguientes que coinciden. */ ?>
          <div class="ag-list"><?php foreach ($upcoming as $i => $e): ?><?= $item($e, 'topNext(' . $i . ')', true) ?><?php endforeach ?></div>
        <?php endif ?>
        <div class="card-body" x-show="!vis(next).length" <?= $upcoming ? 'x-cloak' : '' ?>>
          <p class="soft text-sm" x-text="next.length ? <?= $js(t('Agenda.nothingMatches')) ?> : <?= $js(t('Agenda.nothingNext')) ?>"><?= esc(t($upcoming ? 'Agenda.nothingMatches' : 'Agenda.nothingNext')) ?></p></div>
      </section>
    </div>

    <section class="card" aria-labelledby="cal-title">
      <div class="card-header">
        <h2 id="cal-title"><?= esc($monthLabel) ?></h2>
        <span class="row" style="gap:0.25rem">
          <a class="btn btn-sm btn-icon" href="<?= $url(['mes' => $prev]) ?>" title="<?= esc(t('Agenda.prevMonth'), 'attr') ?>" aria-label="<?= esc(t('Agenda.prevMonth'), 'attr') ?>"><?= icon('chevron-left', 14) ?></a>
          <a class="btn btn-sm" href="<?= $url(['dia' => $today]) ?>"><?= esc(t('Agenda.today')) ?></a>
          <a class="btn btn-sm btn-icon" href="<?= $url(['mes' => $next]) ?>" title="<?= esc(t('Agenda.nextMonth'), 'attr') ?>" aria-label="<?= esc(t('Agenda.nextMonth'), 'attr') ?>"><?= icon('chevron-right', 14) ?></a>
        </span>
      </div>
      <table class="cal">
        <thead><tr><?php foreach ((array) lang('Dates.days') as $w): ?><th scope="col"><abbr title="<?= esc($w, 'attr') ?>"><?= esc(mb_strtoupper(mb_substr($w, 0, 3))) ?></abbr></th><?php endforeach ?></tr></thead>
        <tbody>
          <?php for ($d = $gridFrom; $d <= $gridTo; $d = date('Y-m-d', strtotime($d . ' +1 day'))):
              // Elementos del día para el calendario: el navegador los filtra y muestra hasta 3 (y «+N más») y los puntos.
              $cell = array_map(static fn ($e) => $metaOf($e) + ['n' => $e['title'], 'c' => $e['color'] ? cat_color($e['color']) : 'var(--app-ink-soft)'], $byDay[$d] ?? []);
              $cls  = trim((substr($d, 0, 7) !== $month ? 'is-out ' : '') . ($d === $today ? 'is-today ' : '') . ($d === $day ? 'is-selected' : ''));
              if (date('N', strtotime($d)) === '1'): ?><tr><?php endif ?>
            <td<?= $cls ? ' class="' . $cls . '"' : '' ?> x-data="{ cell: <?= $js($cell) ?> }">
              <a class="cal-day" href="<?= $url(['mes' => $month, 'dia' => $d]) ?>"<?= $d === $day ? ' aria-current="date"' : '' ?>
                 :aria-label="<?= $js($long($d) . ': ') ?> + dayCount(vis(cell).length)" aria-label="<?= esc($long($d), 'attr') ?>">
                <span class="cal-num"><?= (int) date('j', strtotime($d)) ?></span>
                <?php if ($cell): ?>
                  <template x-for="(e, i) in vis(cell).slice(0, 3)" :key="i">
                    <span class="cal-ev" :class="{ 'is-done': e.d }" :style="'--cat:' + e.c"><span class="cat-dot" aria-hidden="true"></span><span x-text="e.n"></span></span>
                  </template>
                  <span class="cal-more" x-show="vis(cell).length > 3" x-text="moreLabel(vis(cell).length - 3)"></span>
                  <span class="cal-dots" aria-hidden="true" x-show="vis(cell).length">
                    <template x-for="(e, i) in vis(cell).slice(0, 4)" :key="i"><span class="cat-dot" :style="'--cat:' + e.c"></span></template>
                  </span>
                <?php endif ?>
              </a>
            </td>
            <?php if (date('N', strtotime($d)) === '7'): ?></tr><?php endif ?>
          <?php endfor ?>
        </tbody>
      </table>
      <div class="cal-legend text-xs soft">
        <span><?= icon('calendar-check', 12) ?> <?= esc(t('Agenda.typeEvent')) ?></span>
        <span><?= icon('flag', 12) ?> <?= esc(t('Agenda.typeDue')) ?></span>
        <?php if (! $student): ?><span><?= icon('play', 12) ?> <?= esc(t('Agenda.typeOpensShort')) ?></span><?php endif ?>
        <span><?= icon('sticky-note', 12) ?> <?= esc($noteLabel) ?></span>
        <?php if (! $readOnly): ?><span><?= icon('bell', 12) ?> <?= esc(t('Agenda.typePersonal')) ?></span><?php endif ?>
      </div>
    </section>
  </div>

  <?php /* Detalle de un elemento de la agenda: se abre al elegirlo en la lista. */ ?>
  <div class="modal-layer" x-show="ev" x-cloak @keydown.escape.window="ev = null">
    <div class="veil" @click="ev = null"></div>
    <div class="modal ag-modal" role="dialog" aria-modal="true" aria-labelledby="ag-ev-title" :style="ev && ev.color ? '--cat:' + ev.color : ''"
         x-effect="ev && $nextTick(() => $refs.evClose && $refs.evClose.focus())">
      <div class="modal-head">
        <div style="min-width:0">
          <span class="ag-modal-type"><span class="cat-dot" aria-hidden="true"></span><span x-text="ev && ev.type"></span></span>
          <h2 id="ag-ev-title" x-text="ev && ev.title"></h2>
        </div>
        <button type="button" class="btn btn-ghost btn-sm btn-icon" @click="ev = null" aria-label="<?= esc(t('Agenda.close'), 'attr') ?>">×</button>
      </div>
      <div class="modal-body stack-sm">
        <p class="row text-sm" style="gap:0.5rem"><?= icon('clock', 14, 'soft') ?><span x-text="ev && ev.when"></span></p>
        <p class="row text-sm soft" style="gap:0.5rem" x-show="ev && ev.meta"><?= icon('info', 14, 'soft') ?><span x-text="ev && ev.meta"></span></p>
        <p class="row text-sm" style="gap:0.5rem" x-show="ev && ev.done"><?= icon('circle-check', 14, 'text-accent') ?><span><?= esc(t('Agenda.done')) ?></span></p>
        <div class="ag-modal-notes" x-show="ev && ev.notes" x-text="ev && ev.notes"></div>
      </div>
      <div class="modal-foot">
        <?php if ($canAdd): ?>
          <button type="button" class="btn" x-show="ev && ev.edit" @click="f = ev.edit; ev = null"><?= icon('pencil', 14) ?> <?= esc(t('Agenda.editBtn')) ?></button>
        <?php endif ?>
        <?php if (! empty($canEvents)): ?>
          <form method="post" x-show="ev && ev.manage" :action="ev && ev.manage ? '<?= site_url('eventos') ?>/' + ev.manage.id + '/eliminar' : ''" @submit="if (!confirm(<?= esc($js(t('Agenda.deleteEventConfirm')), 'attr') ?>)) $event.preventDefault()" style="margin:0">
            <?= csrf_field() ?><input type="hidden" name="date" :value="ev && ev.manage ? ev.manage.date : ''">
            <button type="submit" class="btn btn-danger"><?= icon('trash-2', 14) ?> <?= esc(t('Agenda.deleteBtn')) ?></button>
          </form>
          <button type="button" class="btn" x-show="ev && ev.manage" @click="evf = JSON.parse(JSON.stringify(ev.manage)); ev = null"><?= icon('pencil', 14) ?> <?= esc(t('Agenda.editEvent')) ?></button>
        <?php endif ?>
        <button type="button" class="btn" @click="ev = null" x-ref="evClose"><?= esc(t('Agenda.close')) ?></button>
        <a class="btn btn-primary" x-show="ev && ev.url" :href="ev && ev.url"><?= icon('arrow-up-right', 14) ?> <span x-text="ev && ev.go"></span></a>
      </div>
    </div>
  </div>

  <?php if ($canAdd): ?>
    <div class="drawer-layer" x-show="f" x-cloak @keydown.escape.window="f = null">
      <div class="veil" @click="f = null"></div>
      <aside class="drawer" role="dialog" aria-modal="true" aria-labelledby="ag-form-title">
        <form method="post" :action="f && f.id ? '<?= site_url('agenda') ?>/' + f.id : '<?= site_url('agenda') ?>'">
          <?= csrf_field() ?>
          <div class="drawer-head"><h2 id="ag-form-title" x-text="f && f.id ? <?= $js(t('Agenda.editNote')) ?> : <?= $js(t($offerings ? 'Agenda.newNote' : 'Agenda.newReminder')) ?>"></h2><button type="button" class="btn btn-ghost btn-sm btn-icon" @click="f = null" aria-label="<?= esc(t('Agenda.close'), 'attr') ?>">×</button></div>
          <div class="drawer-body stack">
            <label class="field"><span class="label"><?= esc(t('Agenda.fieldTitle')) ?> <span class="text-danger">*</span></span>
              <input class="input" name="title" required maxlength="160" :value="f && f.title" placeholder="<?= esc(t($offerings ? 'Agenda.phNote' : 'Agenda.phReminder'), 'attr') ?>"></label>
            <div class="grid-2">
              <label class="field"><span class="label"><?= esc(t('Agenda.fieldDate')) ?> <span class="text-danger">*</span></span><input class="input" type="date" name="entry_date" required :value="f && f.entry_date"></label>
              <label class="field"><span class="label"><?= esc(t('Agenda.fieldTime')) ?></span><input class="input" type="time" name="entry_time" :value="f && f.entry_time"><span class="hint"><?= esc(t('Agenda.timeHint')) ?></span></label>
            </div>
            <?php if ($offerings): ?>
              <label class="field"><span class="label"><?= esc(t('Agenda.fieldFor')) ?></span>
                <select class="select" name="offering_id">
                  <option value="" :selected="f && !f.offering_id"><?= esc(t('Agenda.onlyMe')) ?></option>
                  <?php foreach ($offerings as $o): ?><option value="<?= (int) $o['id'] ?>" :selected="f && f.offering_id == '<?= (int) $o['id'] ?>'"><?= esc($o['subject'] . ' · ' . $o['group']) ?> <?= esc(t('Agenda.groupStudents')) ?></option><?php endforeach ?>
                </select>
                <span class="hint"><?= esc(t('Agenda.forHint')) ?></span></label>
            <?php endif ?>
            <?php if ($tagOfferings): ?>
              <label class="field"><span class="label"><?= esc(t('Agenda.fieldSubject')) ?></span>
                <select class="select" name="tag_offering_id">
                  <option value="" :selected="f && !f.tag_offering_id"><?= esc(t('Agenda.none')) ?></option>
                  <?php foreach ($tagOfferings as $o): ?><option value="<?= (int) $o['id'] ?>" :selected="f && f.tag_offering_id == '<?= (int) $o['id'] ?>'"><?= esc($o['subject']) ?></option><?php endforeach ?>
                </select>
                <span class="hint"><?= esc(t('Agenda.subjectHint')) ?></span></label>
            <?php endif ?>
            <label class="field"><span class="label"><?= esc(t('Agenda.fieldDetail')) ?></span><textarea class="textarea" name="notes" rows="4" maxlength="1000" x-text="f && f.notes" placeholder="<?= esc(t('Agenda.optional'), 'attr') ?>"></textarea></label>
          </div>
          <div class="drawer-foot"><button type="button" class="btn" @click="f = null"><?= esc(t('Agenda.cancel')) ?></button><button type="submit" class="btn btn-primary" data-loading="<?= esc(t('Agenda.saving'), 'attr') ?>" x-text="f && f.id ? <?= $js(t('Agenda.save')) ?> : <?= $js(t('Agenda.add')) ?>"></button></div>
        </form>
      </aside>
    </div>
  <?php endif ?>
  <?php if (! empty($canEvents)): ?><?= view('events/_form', ['categories' => $eventCategories, 'audiences' => $eventAudiences]) ?><?php endif ?>
</div>
<?= $this->endSection() ?>
