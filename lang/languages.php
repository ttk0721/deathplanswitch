<?php
/**
 * The registry of interface languages: the one place that says which exist.
 *
 * Per code:
 *   name   the language's own name, shown in the language selects
 *   dir    'ltr' or 'rtl'
 *   plural plural rule id: one_other, fr (0 and 1 are one), none, pl, ru (also uk), ar
 *
 * Translations live in lang/<code>/*.php and assets/lang/<code>.js; month names,
 * weekday names and date patterns in lang/<code>/dates.php. A registered
 * language without a lang/<code>/ folder is not offered and falls back to English.
 * See lang/TRANSLATING.md.
 */
declare(strict_types=1);

return [
    'en' => ['name' => 'English', 'dir' => 'ltr', 'plural' => 'one_other'],
    'pl' => ['name' => 'Polski', 'dir' => 'ltr', 'plural' => 'pl'],
    'es' => ['name' => 'Español', 'dir' => 'ltr', 'plural' => 'one_other'],
    'fr' => ['name' => 'Français', 'dir' => 'ltr', 'plural' => 'fr'],
    'de' => ['name' => 'Deutsch', 'dir' => 'ltr', 'plural' => 'one_other'],
    'it' => ['name' => 'Italiano', 'dir' => 'ltr', 'plural' => 'one_other'],
    'pt-BR' => ['name' => 'Português (Brasil)', 'dir' => 'ltr', 'plural' => 'fr'],
    'nl' => ['name' => 'Nederlands', 'dir' => 'ltr', 'plural' => 'one_other'],
    'uk' => ['name' => 'Українська', 'dir' => 'ltr', 'plural' => 'ru'],
    'ru' => ['name' => 'Русский', 'dir' => 'ltr', 'plural' => 'ru'],
    'tr' => ['name' => 'Türkçe', 'dir' => 'ltr', 'plural' => 'one_other'],
    'ar' => ['name' => 'العربية', 'dir' => 'rtl', 'plural' => 'ar'],
    'hi' => ['name' => 'हिन्दी', 'dir' => 'ltr', 'plural' => 'fr'],
    'zh-CN' => ['name' => '简体中文', 'dir' => 'ltr', 'plural' => 'none'],
    'ja' => ['name' => '日本語', 'dir' => 'ltr', 'plural' => 'none'],
    'ko' => ['name' => '한국어', 'dir' => 'ltr', 'plural' => 'none'],
    'id' => ['name' => 'Bahasa Indonesia', 'dir' => 'ltr', 'plural' => 'none'],
    'vi' => ['name' => 'Tiếng Việt', 'dir' => 'ltr', 'plural' => 'none'],
];
