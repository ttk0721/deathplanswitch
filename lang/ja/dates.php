<?php
/** Japanese dates: names, patterns and relative-time phrasing. */
declare(strict_types=1);

return [
    'weekdays' => ['日曜日', '月曜日', '火曜日', '水曜日', '木曜日', '金曜日', '土曜日'],
    'weekdays_short' => ['日', '月', '火', '水', '木', '金', '土'],
    'months' => ['1月', '2月', '3月', '4月', '5月', '6月', '7月', '8月', '9月', '10月', '11月', '12月'],
    'months_genitive' => ['1月', '2月', '3月', '4月', '5月', '6月', '7月', '8月', '9月', '10月', '11月', '12月'],
    'months_short' => ['1月', '2月', '3月', '4月', '5月', '6月', '7月', '8月', '9月', '10月', '11月', '12月'],
    'hour12' => false,
    'am' => '',
    'pm' => '',
    'formats' => [
        'full' => 'Y年n月j日（D） G:i',
        'date' => 'Y年n月j日',
        'day' => 'n月j日（D）',
        'time' => 'G:i',
        'long' => 'n月j日（D） G:i',
        'stamp' => 'Y年n月j日 G:i',
        'brief' => 'n月j日（D） G:i',
        'letter_date' => 'Y年n月j日',
        'letter_day' => 'n月j日（D）',
    ],
    'relative' => [
        'future' => '{duration}後',
        'past' => '{duration}前',
        'now' => 'たった今',
    ],
];
