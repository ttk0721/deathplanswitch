<?php
/** Hindi dates: names, patterns and relative-time phrasing. */
declare(strict_types=1);

return [
    'weekdays' => ['रविवार', 'सोमवार', 'मंगलवार', 'बुधवार', 'गुरुवार', 'शुक्रवार', 'शनिवार'],
    'weekdays_short' => ['रवि', 'सोम', 'मंगल', 'बुध', 'गुरु', 'शुक्र', 'शनि'],
    'months' => ['जनवरी', 'फ़रवरी', 'मार्च', 'अप्रैल', 'मई', 'जून', 'जुलाई', 'अगस्त', 'सितंबर', 'अक्तूबर', 'नवंबर', 'दिसंबर'],
    'months_short' => ['जन॰', 'फ़र॰', 'मार्च', 'अप्रैल', 'मई', 'जून', 'जुल॰', 'अग॰', 'सित॰', 'अक्तू॰', 'नव॰', 'दिस॰'],
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
        'future' => '{duration} में',
        'past' => '{duration} पहले',
        'now' => 'अभी-अभी',
    ],
];
