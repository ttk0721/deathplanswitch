<?php
/** Turkish dates: names, patterns and relative-time phrasing. */
declare(strict_types=1);

return [
    'weekdays' => ['Pazar', 'Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi'],
    'weekdays_short' => ['Paz', 'Pzt', 'Sal', 'Çar', 'Per', 'Cum', 'Cmt'],
    'months' => ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'],
    'months_short' => ['Oca', 'Şub', 'Mar', 'Nis', 'May', 'Haz', 'Tem', 'Ağu', 'Eyl', 'Eki', 'Kas', 'Ara'],
    'hour12' => false,
    'am' => '',
    'pm' => '',
    'formats' => [
        'full' => 'j F Y l, H:i',
        'date' => 'j F Y',
        'day' => 'j F l',
        'time' => 'H:i',
        'long' => 'j F l, H:i',
        'stamp' => 'j M Y, H:i',
        'brief' => 'j M D, H:i',
        'letter_date' => 'j F Y l',
        'letter_day' => 'j F l',
    ],
    'relative' => [
        'future' => '{duration} sonra',
        'past' => '{duration} önce',
        'now' => 'az önce',
    ],
];
