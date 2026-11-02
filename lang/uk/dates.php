<?php
/** Ukrainian dates: names, patterns and relative-time phrasing. */
declare(strict_types=1);

return [
    'weekdays' => ['неділя', 'понеділок', 'вівторок', 'середа', 'четвер', 'пʼятниця', 'субота'],
    'weekdays_short' => ['нд', 'пн', 'вт', 'ср', 'чт', 'пт', 'сб'],
    'months' => ['січень', 'лютий', 'березень', 'квітень', 'травень', 'червень', 'липень', 'серпень', 'вересень', 'жовтень', 'листопад', 'грудень'],
    'months_genitive' => ['січня', 'лютого', 'березня', 'квітня', 'травня', 'червня', 'липня', 'серпня', 'вересня', 'жовтня', 'листопада', 'грудня'],
    'months_short' => ['січ', 'лют', 'бер', 'кві', 'тра', 'чер', 'лип', 'сер', 'вер', 'жов', 'лис', 'гру'],
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
        'letter_date' => 'j.m.Y',
        'letter_day' => 'j.m.Y',
    ],
    'relative' => [
        'future' => 'через {duration}',
        'past' => '{duration} тому',
        'now' => 'щойно',
    ],
];
