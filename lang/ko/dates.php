<?php
/** Korean dates: names, patterns and relative-time phrasing. */
declare(strict_types=1);

return [
    'weekdays' => ['일요일', '월요일', '화요일', '수요일', '목요일', '금요일', '토요일'],
    'weekdays_short' => ['일', '월', '화', '수', '목', '금', '토'],
    'months' => ['1월', '2월', '3월', '4월', '5월', '6월', '7월', '8월', '9월', '10월', '11월', '12월'],
    'months_genitive' => ['1월', '2월', '3월', '4월', '5월', '6월', '7월', '8월', '9월', '10월', '11월', '12월'],
    'months_short' => ['1월', '2월', '3월', '4월', '5월', '6월', '7월', '8월', '9월', '10월', '11월', '12월'],
    'hour12' => false,
    'am' => '',
    'pm' => '',
    'formats' => [
        'full' => 'Y년 n월 j일 (D) G:i',
        'date' => 'Y년 n월 j일',
        'day' => 'n월 j일 (D)',
        'time' => 'G:i',
        'long' => 'n월 j일 (D) G:i',
        'stamp' => 'Y년 n월 j일 G:i',
        'brief' => 'n월 j일 (D) G:i',
        'letter_date' => 'Y년 n월 j일',
        'letter_day' => 'n월 j일 (D)',
    ],
    'relative' => [
        'future' => '{duration} 후',
        'past' => '{duration} 전',
        'now' => '방금',
    ],
];
