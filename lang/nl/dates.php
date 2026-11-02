<?php
/** Dutch dates: names, patterns and relative-time phrasing. */
declare(strict_types=1);

return [
    'weekdays' => ['zondag', 'maandag', 'dinsdag', 'woensdag', 'donderdag', 'vrijdag', 'zaterdag'],
    'weekdays_short' => ['zo', 'ma', 'di', 'wo', 'do', 'vr', 'za'],
    'months' => ['januari', 'februari', 'maart', 'april', 'mei', 'juni', 'juli', 'augustus', 'september', 'oktober', 'november', 'december'],
    'months_short' => ['jan.', 'feb.', 'mrt.', 'apr.', 'mei', 'jun.', 'jul.', 'aug.', 'sep.', 'okt.', 'nov.', 'dec.'],
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
        'letter_date' => 'l j F Y',
        'letter_day' => 'l j F',
    ],
    'relative' => [
        'future' => 'over {duration}',
        'past' => '{duration} geleden',
        'now' => 'zojuist',
    ],
];
