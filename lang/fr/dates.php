<?php
/** French dates: names, patterns and relative-time phrasing. */
declare(strict_types=1);

return [
    'weekdays' => ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'],
    'weekdays_short' => ['dim.', 'lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.'],
    'months' => ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'],
    'months_short' => ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'],
    'hour12' => false,
    'am' => '',
    'pm' => '',
    'formats' => [
        'full' => 'l j F Y, G:i',
        'date' => 'j F Y',
        'day' => 'l j F',
        'time' => 'G:i',
        'long' => 'l j F, G:i',
        'stamp' => 'j M Y, G:i',
        'brief' => 'D j M, G:i',
        'letter_date' => 'l j F Y',
        'letter_day' => 'l j F',
    ],
    'relative' => [
        'future' => 'dans {duration}',
        'past' => 'il y a {duration}',
        'now' => 'à l’instant',
    ],
];
