<?php

/** Fechas (helpers date_human / date_short / date_long). Días: 1 = lunes … 7 = domingo. */
return [
    'monthsShort' => ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'],
    'months'      => ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'],
    'daysShort'   => [1 => 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb', 'dom'],
    'days'        => [1 => 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo'],
    'time'        => 'g:i a',
    // {d} día, {m} mes, {y} año, {w} día de la semana
    'short'       => '{w} {d} {m}',
    'shortNoDay'  => '{d} {m}',
    'human'       => '{d} {m} {y}',
    'long'        => '{W} {d} de {M}',
    'monthYear'   => '{M} de {y}',
];
