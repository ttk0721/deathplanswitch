<?php
/** English dates: names, patterns and relative-time phrasing. See lang/TRANSLATING.md. */
declare(strict_types=1);

return [
    // Index 0 is Sunday. Patterns use PHP date() letters: l D F M j n Y G g h H i a A.
    'weekdays' => ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
    'weekdays_short' => ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
    'months' => ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
    // Months as written beside a day number ("3 November"). Leave out when the same as 'months'.
    'months_genitive' => ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
    'months_short' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
    // true: 12-hour clock with am/pm; false: 24-hour clock (g and h become G and H, a and A print nothing).
    'hour12' => true,
    'am' => 'am',
    'pm' => 'pm',
    'formats' => [
        'full' => 'l j F Y, g:ia',
        'date' => 'j F Y',
        'day' => 'l j F',
        'time' => 'g:ia',
        'long' => 'l j F \a\t g:ia',
        'stamp' => 'j M Y, g:ia',
        'brief' => 'D j M, g:ia',
        'letter_date' => 'l j F Y',
        'letter_day' => 'l j F',
    ],
    'relative' => [
        'future' => 'in {duration}',
        'past' => '{duration} ago',
        'now' => 'just now',
    ],
];
