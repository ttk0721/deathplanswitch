<?php
/** Arabic dates: names, patterns and relative-time phrasing. */
declare(strict_types=1);

return [
    'weekdays' => ['الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'],
    'weekdays_short' => ['الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'],
    'months' => ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'],
    'months_short' => ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'],
    'hour12' => true,
    'am' => 'ص',
    'pm' => 'م',
    'formats' => [
        'full' => 'l j F Y، g:i a',
        'date' => 'j F Y',
        'day' => 'l j F',
        'time' => 'g:i a',
        'long' => 'l j F، g:i a',
        'stamp' => 'j M Y، g:i a',
        'brief' => 'D j M، g:i a',
        'letter_date' => 'l j F Y',
        'letter_day' => 'l j F',
    ],
    'relative' => [
        'future' => 'خلال {duration}',
        'past' => 'منذ {duration}',
        'now' => 'الآن',
    ],
];
