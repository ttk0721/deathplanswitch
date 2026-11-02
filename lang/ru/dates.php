<?php
/** Russian dates: names, patterns and relative-time phrasing. */
declare(strict_types=1);

return [
    'weekdays' => ['воскресенье', 'понедельник', 'вторник', 'среда', 'четверг', 'пятница', 'суббота'],
    'weekdays_short' => ['вс', 'пн', 'вт', 'ср', 'чт', 'пт', 'сб'],
    'months' => ['январь', 'февраль', 'март', 'апрель', 'май', 'июнь', 'июль', 'август', 'сентябрь', 'октябрь', 'ноябрь', 'декабрь'],
    'months_genitive' => ['января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'],
    'months_short' => ['янв', 'фев', 'мар', 'апр', 'мая', 'июн', 'июл', 'авг', 'сен', 'окт', 'ноя', 'дек'],
    'hour12' => false,
    'am' => '',
    'pm' => '',
    'formats' => [
        'full' => 'l, j F Y, G:i',
        'date' => 'j F Y',
        'day' => 'l, j F',
        'time' => 'G:i',
        'long' => 'l, j F, G:i',
        'stamp' => 'j M Y, G:i',
        'brief' => 'D, j M, G:i',
        'letter_date' => 'j.m.Y',
        'letter_day' => 'j.m.Y',
    ],
    'relative' => [
        'future' => 'через {duration}',
        'past' => '{duration} назад',
        'now' => 'только что',
    ],
];
