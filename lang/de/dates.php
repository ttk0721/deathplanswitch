<?php
/** German dates: names, patterns and relative-time phrasing. */
declare(strict_types=1);

return [
    'weekdays' => ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'],
    'weekdays_short' => ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'],
    'months' => ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'],
    'months_short' => ['Jan.', 'Feb.', 'März', 'Apr.', 'Mai', 'Juni', 'Juli', 'Aug.', 'Sept.', 'Okt.', 'Nov.', 'Dez.'],
    'hour12' => false,
    'am' => '',
    'pm' => '',
    'formats' => [
        'full' => 'l, j. F Y, H:i',
        'date' => 'j. F Y',
        'day' => 'l, j. F',
        'time' => 'H:i',
        'long' => 'l, j. F, H:i',
        'stamp' => 'j. M Y, H:i',
        'brief' => 'D, j. M, H:i',
        'letter_date' => 'l, j. F Y',
        'letter_day' => 'l, j. F',
    ],
    'relative' => [
        'future' => 'in {duration}',
        'past' => 'vor {duration}',
        'now' => 'gerade eben',
    ],
];
