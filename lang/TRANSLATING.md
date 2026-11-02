# Adding a language

You do not need to know how to code. You copy files, translate the text on the right-hand side, and add one line to a list. The test suite then tells you what is missing.

Language codes in use: en, pl, es, fr, de, it, pt-BR, nl, uk, ru, tr, ar, hi, zh-CN, ja, ko, id, vi. Below, `<code>` is yours, for example `de`.

## 1. What to create

```
lang/<code>/core.php  switch.php  contacts.php  settings.php  install.php
              pages.php  messages.php  engine.php  history.php   (the app and the emails)
lang/<code>/dates.php                                              (months, weekdays, date and time style)
assets/lang/<code>.js                                              (the vault pages, in the browser)
```

Copy `lang/pl/` to `lang/<code>/` and `assets/lang/pl.js` to `assets/lang/<code>.js`, then translate. Polish is the structural example: it has every key. `lang/en/dates.php` is the English example for dates.

Then add your language to `lang/languages.php`:

```php
'de' => ['name' => 'Deutsch', 'dir' => 'ltr', 'plural' => 'one_other'],
```

- `name`: the language's own name, as its speakers write it (Deutsch, العربية, 日本語). It appears in the language lists.
- `dir`: `'rtl'` for Arabic, otherwise `'ltr'`.
- `plural`: the plural rule, see section 3.

Nothing else in the code changes.

## 2. Translating the strings

Every file returns a list of `'English' => 'Your language'` lines.

- Translate the value on the right. Never change the key on the left: the program finds your text by the English key.
- Keep every `{placeholder}` exactly as written, for example `{owner_name}` or `{days}`. You may move it to where your grammar needs it. Do not translate what is inside the braces.
- Message templates contain gap markers in square brackets, such as `[write here: where the documents are kept]`. Keep the `[write here: ` opening exactly. You may translate the hint after it. The owner fills these in.
- Keep the punctuation style of your language (quotation marks, spaces before colons, and so on).
- No value may be empty.

To see everything that needs translating, run `php bin/i18n-catalog.php`. It prints JSON with, for each file area (core, switch, contacts, settings, install, pages, messages, engine, history, dates, js), the English keys, and for the plural keys how many forms each plural rule needs.

All 18 languages are already listed in `lang/languages.php`, so you only create your files. A language is offered in the app only once its `lang/<code>/` folder exists.

To check your work, run these two commands (replace `de` with your code):

```
DPS_I18N_ONLY=de bash tests/all.sh tests/i18n_test.php
DPS_I18N_ONLY=de bash tests/all.sh tests/js/i18n_coverage.test.mjs
```

Without `DPS_I18N_ONLY` every language that has files is checked. The tests check that every string has an entry, every plural has the right number of forms, the placeholders match the English, and nothing is empty. If your language is registered but has no files yet, the tests simply skip it.

## 3. Plurals

Phrases that depend on a count are lists of forms instead of one text. The number is `{n}`.

```php
'{n} day' => ['{n} dzień', '{n} dni', '{n} dni'],   // Polish: one, few, many
```

The key is the English singular. The forms are in the order of your rule:

| `plural` | Languages | Forms, in order |
|---|---|---|
| `one_other` | en, de, es, it, nl, tr, ... | one, other |
| `fr` | fr, pt-BR, hi (0 and 1 count as one) | one, other |
| `none` | zh-CN, ja, ko, id, vi | other |
| `pl` | pl | one, few, many |
| `ru` | ru, uk | one, few, many |
| `ar` | ar | zero, one, two, few, many, other |

For example `one` is 1 in German and 1, 21, 31, ... in Russian (but not 11); `few` is 2 to 4 in Polish (but not 12 to 14). Give exactly as many forms as your rule lists, even if some are the same text. The same format is used in `assets/lang/<code>.js`, where you also set the rule once:

```js
export default {
  '{n} day': ['{n} dzień', '{n} dni', '{n} dni'],
  // ...
};
export const plural = 'pl';
```

## 4. Dates

`lang/<code>/dates.php` holds what is needed to write a date and time in your language.

```php
return [
    'weekdays' => ['Sonntag', 'Montag', /* ... seven, Sunday first */],
    'weekdays_short' => ['So', 'Mo', /* ... */],
    'months' => ['Januar', /* ... twelve */],
    'months_genitive' => [/* only if the month changes beside a day number, as in Polish */],
    'months_short' => ['Jan', /* ... */],
    'hour12' => false,          // false: 24-hour clock; true: 12-hour with 'am' and 'pm'
    'am' => '', 'pm' => '',
    'formats' => [
        'full' => 'l j F Y, G:i',   // Dienstag 3 November 2026, 9:00
        'date' => 'j F Y', 'day' => 'l j F', 'time' => 'G:i',
        'long' => 'l j F, G:i', 'stamp' => 'j M Y, G:i', 'brief' => 'D j M, G:i',
        'letter_date' => 'l j F Y', 'letter_day' => 'l j F',
    ],
    'relative' => ['future' => 'in {duration}', 'past' => 'vor {duration}', 'now' => 'gerade eben'],
];
```

The letters in `formats` are PHP date letters: `l` weekday, `D` short weekday, `j` day, `F` month, `M` short month, `n` month number, `m` two-digit month, `Y` year, `G` hour 0-23, `g` hour 1-12, `H`/`h` the same with a leading zero, `i` minutes, `a`/`A` am/pm. Any other letter would be turned into a date value, so to print a literal word or letter put a backslash before every character of it (`\a\t`). Put the day, month and year in the order your readers expect, with their punctuation. Anything you leave out is taken from English.

## 5. Tone

- Plain, warm, sentence case. Short sentences. No jargon.
- Emails go to family and friends of someone who may be unwell or gone. Be polite and neutral. Do not alarm, do not joke, and do not assume the worst.
- This is a dead man's switch, so wording must be legally careful: the app asks whether someone is all right, and it never states that anyone has died or is incapacitated. Keep "has not checked in", "we could not reach", "asked whether everything is all right". Do not turn these into stronger claims.
- Use the formal "you" where your language has one, unless that sounds cold for a note to a friend.
- Keep product names (Death Plan Switch, ntfy, Apprise) untranslated.
- Keep the same word for the same thing everywhere (switch, check-in, next of kin, vault, contact).
