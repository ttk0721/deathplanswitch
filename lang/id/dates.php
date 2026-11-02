<?php
/** Indonesian dates: names, patterns and relative-time phrasing. */
declare(strict_types=1);

return [
    'weekdays' => ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'],
    'weekdays_short' => ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'],
    'months' => ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'],
    'months_short' => ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'],
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
        'letter_date' => 'l, j F Y',
        'letter_day' => 'l, j F',
    ],
    'relative' => [
        'future' => '{duration} lagi',
        'past' => '{duration} yang lalu',
        'now' => 'baru saja',
    ],
];
