<?php
/** Brazilian Portuguese dates: names, patterns and relative-time phrasing. */
declare(strict_types=1);

return [
    'weekdays' => ['domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado'],
    'weekdays_short' => ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'],
    'months' => ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'],
    'months_short' => ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'],
    'hour12' => false,
    'am' => '',
    'pm' => '',
    'formats' => [
        'full' => 'l, j \d\e F \d\e Y, G:i',
        'date' => 'j \d\e F \d\e Y',
        'day' => 'l, j \d\e F',
        'time' => 'G:i',
        'long' => 'l, j \d\e F, G:i',
        'stamp' => 'j M Y, G:i',
        'brief' => 'D, j M, G:i',
        'letter_date' => 'l, j \d\e F \d\e Y',
        'letter_day' => 'l, j \d\e F',
    ],
    'relative' => [
        'future' => 'em {duration}',
        'past' => 'há {duration}',
        'now' => 'agora há pouco',
    ],
];
