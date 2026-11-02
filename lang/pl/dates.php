<?php
/** Polish dates: names, patterns and relative-time phrasing. */
declare(strict_types=1);

return [
    'weekdays' => ['niedziela', 'poniedziałek', 'wtorek', 'środa', 'czwartek', 'piątek', 'sobota'],
    'weekdays_short' => ['niedz.', 'pon.', 'wt.', 'śr.', 'czw.', 'pt.', 'sob.'],
    'months' => ['styczeń', 'luty', 'marzec', 'kwiecień', 'maj', 'czerwiec', 'lipiec', 'sierpień', 'wrzesień', 'październik', 'listopad', 'grudzień'],
    'months_genitive' => ['stycznia', 'lutego', 'marca', 'kwietnia', 'maja', 'czerwca', 'lipca', 'sierpnia', 'września', 'października', 'listopada', 'grudnia'],
    'months_short' => ['sty', 'lut', 'mar', 'kwi', 'maj', 'cze', 'lip', 'sie', 'wrz', 'paź', 'lis', 'gru'],
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
        'letter_date' => 'j.m.Y',
        'letter_day' => 'j.m.Y',
    ],
    'relative' => [
        'future' => 'za {duration}',
        'past' => '{duration} temu',
        'now' => 'przed chwilą',
    ],
];
