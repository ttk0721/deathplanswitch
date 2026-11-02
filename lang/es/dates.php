<?php
/** Spanish dates: names, patterns and relative-time phrasing. */
declare(strict_types=1);

return [
    'weekdays' => ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'],
    'weekdays_short' => ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'],
    'months' => ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'],
    'months_short' => ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'],
    'hour12' => false,
    'am' => '',
    'pm' => '',
    'formats' => [
        'full' => 'l j \d\e F \d\e Y, G:i',
        'date' => 'j \d\e F \d\e Y',
        'day' => 'l j \d\e F',
        'time' => 'G:i',
        'long' => 'l j \d\e F, G:i',
        'stamp' => 'j M Y, G:i',
        'brief' => 'D j M, G:i',
        'letter_date' => 'l j \d\e F \d\e Y',
        'letter_day' => 'l j \d\e F',
    ],
    'relative' => [
        'future' => 'en {duration}',
        'past' => 'hace {duration}',
        'now' => 'hace un momento',
    ],
];
