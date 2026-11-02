<?php
/** Vietnamese dates: names, patterns and relative-time phrasing. */
declare(strict_types=1);

return [
    'weekdays' => ['Chủ nhật', 'Thứ hai', 'Thứ ba', 'Thứ tư', 'Thứ năm', 'Thứ sáu', 'Thứ bảy'],
    'weekdays_short' => ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'],
    'months' => ['tháng 1', 'tháng 2', 'tháng 3', 'tháng 4', 'tháng 5', 'tháng 6', 'tháng 7', 'tháng 8', 'tháng 9', 'tháng 10', 'tháng 11', 'tháng 12'],
    'months_short' => ['thg 1', 'thg 2', 'thg 3', 'thg 4', 'thg 5', 'thg 6', 'thg 7', 'thg 8', 'thg 9', 'thg 10', 'thg 11', 'thg 12'],
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
        'future' => 'sau {duration}',
        'past' => '{duration} trước',
        'now' => 'vừa xong',
    ],
];
