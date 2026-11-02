<?php
/** Italian dates: names, patterns and relative-time phrasing. */
declare(strict_types=1);

return [
    'weekdays' => ['domenica', 'lunedì', 'martedì', 'mercoledì', 'giovedì', 'venerdì', 'sabato'],
    'weekdays_short' => ['dom', 'lun', 'mar', 'mer', 'gio', 'ven', 'sab'],
    'months' => ['gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno', 'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre'],
    'months_short' => ['gen', 'feb', 'mar', 'apr', 'mag', 'giu', 'lug', 'ago', 'set', 'ott', 'nov', 'dic'],
    'hour12' => false,
    'am' => '',
    'pm' => '',
    'formats' => [
        'full' => 'l j F Y, G:i',
        'date' => 'j F Y',
        'day' => 'l j F',
        'time' => 'G:i',
        'long' => 'l j F, G:i',
        'stamp' => 'j M Y, G:i',
        'brief' => 'D j M, G:i',
        'letter_date' => 'j F Y',
        'letter_day' => 'l j F',
    ],
    'relative' => [
        'future' => 'tra {duration}',
        'past' => '{duration} fa',
        'now' => 'poco fa',
    ],
];
