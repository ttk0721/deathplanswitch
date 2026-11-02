<?php
/** Simplified Chinese dates: names, patterns and relative-time phrasing. */
declare(strict_types=1);

return [
    'weekdays' => ['星期日', '星期一', '星期二', '星期三', '星期四', '星期五', '星期六'],
    'weekdays_short' => ['周日', '周一', '周二', '周三', '周四', '周五', '周六'],
    'months' => ['一月', '二月', '三月', '四月', '五月', '六月', '七月', '八月', '九月', '十月', '十一月', '十二月'],
    'months_genitive' => ['一月', '二月', '三月', '四月', '五月', '六月', '七月', '八月', '九月', '十月', '十一月', '十二月'],
    'months_short' => ['1月', '2月', '3月', '4月', '5月', '6月', '7月', '8月', '9月', '10月', '11月', '12月'],
    'hour12' => false,
    'am' => '',
    'pm' => '',
    'formats' => [
        'full' => 'Y年n月j日 l G:i',
        'date' => 'Y年n月j日',
        'day' => 'n月j日 l',
        'time' => 'G:i',
        'long' => 'n月j日 l G:i',
        'stamp' => 'Y年n月j日 G:i',
        'brief' => 'n月j日 D G:i',
        'letter_date' => 'Y年n月j日',
        'letter_day' => 'n月j日',
    ],
    'relative' => [
        'future' => '{duration}后',
        'past' => '{duration}前',
        'now' => '刚刚',
    ],
];
