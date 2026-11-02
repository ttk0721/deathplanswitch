<?php
declare(strict_types=1);

// Check-in, confirm, respond, history, public page and the vault pages.
// Keys are the English source strings. Pages for family avoid gendered forms.
return [
    // Check-in (owner)
    'Check in: Death Plan Switch' => 'Подтверждение присутствия: Death Plan Switch',
    'Checked in' => 'Присутствие подтверждено',
    'Your switch had fired. Everyone who was contacted has now been told it was a false alarm: {names}.' => 'Твой переключатель сработал. Все, с кем мы связывались, уже получили сообщение, что это была ложная тревога: {names}.',
    'Your switch had fired. Everyone who was contacted has now been told it was a false alarm.' => 'Твой переключатель сработал. Все, с кем мы связывались, уже получили сообщение, что это была ложная тревога.',
    'Your contacts have read their messages, so any pointer in them has been seen. {link} to review what needs changing.' => 'Твои контакты прочитали свои сообщения, поэтому кто-то мог увидеть указания в них. {link}, чтобы посмотреть, что нужно изменить.',
    'Sign in' => 'Войти',
    '{names} had been asked whether you are all right, and has now been told it was a false alarm. Nobody else was contacted.' => 'Мы спрашивали, всё ли с тобой в порядке: {names}. О ложной тревоге уже сообщено. Больше никто не получал сообщений.',
    '{names} had been asked whether you are all right, and have now been told it was a false alarm. Nobody else was contacted.' => 'Мы спрашивали, всё ли с тобой в порядке: {names}. О ложной тревоге уже сообщено. Больше никто не получал сообщений.',
    'Thank you. Nobody was contacted.' => 'Спасибо. Никого не уведомляли.',
    'Your switch is paused until {date}.' => 'Твой переключатель приостановлен до: {date}.',
    'Your next check-in email arrives on {date} at {time}.' => 'Следующее письмо с просьбой подтвердить присутствие придёт: {date}, в {time}.',
    'Are you there, {name}?' => '{name}, ты здесь?',
    'owner' => 'владелец',
    'Press the button to check in. Opening this page is not enough.' => 'Нажми кнопку, чтобы подтвердить присутствие. Одного открытия этой страницы недостаточно.',
    'I am here: check in' => 'Я здесь: подтверждаю',
    'This link is no longer active' => 'Эта ссылка больше не действует',
    'Each check-in link works once, and for 14 days. If you have a newer email from Death Plan Switch, use the link in that one.' => 'Каждая ссылка для подтверждения присутствия действует один раз и в течение 14 дней. Если у тебя есть более новое письмо от Death Plan Switch, воспользуйся ссылкой из него.',
    'You can also {link} and check in from the dashboard.' => 'Также можно {link} и подтвердить присутствие на панели управления.',
    'sign in' => 'войти',

    // Confirm (contact)
    'Death Plan Switch' => 'Death Plan Switch',
    'Confirmed' => 'Подтверждено',
    'Thank you, {name}. {owner} has been told. There is nothing else to do.' => 'Спасибо, {name}. Человека, который вас указал ({owner}), мы известили. Больше ничего делать не нужно.',
    'If {owner} ever stops checking in, you will hear from the same email address that sent you this request. Add it to your contacts so it is not mistaken for spam.' => 'Если человек, который вас указал ({owner}), перестанет подтверждать присутствие, вы получите сообщение с того же адреса, с которого пришёл этот запрос. Добавьте его в контакты, чтобы письмо не попало в спам.',
    'Done' => 'Готово',
    "You will not be contacted by {owner}'s Death Plan Switch. {owner} has been told." => 'Death Plan Switch этого человека ({owner}) не будет с вами связываться. Мы сообщаем об этом этому человеку.',
    "Still happy to be {owner}'s {role}?" => 'Вы по-прежнему согласны быть в роли «{role}» для: {owner}?',
    '{owner} has listed you as their {role}' => '{owner} указывает вас в роли: {role}',
    '{owner} uses Death Plan Switch, a small app on their own server. Every week it asks {owner} to check in. If they stop, it emails the people they have chosen.' => 'Человек по имени {owner} пользуется Death Plan Switch, небольшим приложением на собственном сервере. Каждую неделю оно просит подтвердить, что всё в порядке. Если подтверждения нет, приложение пишет выбранным людям.',
    'As next of kin you would be asked first. If {owner} misses a check-in and three days of reminders, you would get one email asking whether everything is all right, with two buttons to answer.' => 'Как близкого человека, вас спросили бы первым. Если от {owner} нет подтверждения и прошло три дня напоминаний, вы получите одно письмо с вопросом, всё ли в порядке, и двумя кнопками для ответа.',
    'If {owner} stops checking in and their next of kin cannot confirm they are well, you would get one email telling you what {owner} needs you to do.' => 'Если от {owner} нет подтверждения, а близкий человек не может подтвердить, что всё в порядке, вы получите одно письмо с объяснением, чего {owner} ожидает от вас.',
    'Confirming tells {owner} that {address} reaches you, and that you are willing.' => 'Подтвердив, вы сообщите человеку ({owner}), что адрес {address} до вас доходит и что вы согласны.',
    'Yes, I confirm' => 'Да, подтверждаю',
    'No, leave me out' => 'Нет, не добавляйте меня',
    'It has either been used already or has expired. If you meant to confirm, ask the person who listed you to send a new request.' => 'Ссылка уже использована или срок её действия истёк. Если вы хотели подтвердить, попросите человека, который вас указал, отправить новый запрос.',

    // Respond (next of kin)
    'All fine' => 'Всё в порядке',
    'You have seen or spoken to {owner} and they are all right. Nobody else is contacted, and {owner} is told you answered.' => 'Вы виделись или разговаривали с человеком: {owner}, и у этого человека всё в порядке. Никого другого не уведомят, а этому человеку сообщат, что вы ответили.',
    "Something's wrong" => 'Что-то не так',
    '{owner} cannot run their business at the moment.' => 'Человек ({owner}) сейчас не может заниматься своими делами.',
    'These people are sent their instructions straight away: {names}.' => 'Эти люди сразу получат свои указания: {names}.',
    'The people they chose are sent their instructions straight away.' => 'Выбранные люди сразу получат свои указания.',
    'You are sent yours too.' => 'Вы тоже получите своё.',
    'Thank you' => 'Спасибо',
    'Your answer has been recorded. Nobody else has been contacted, and {owner} has been told that you answered.' => 'Ваш ответ записан. Никого другого не уведомляли, а человеку ({owner}) сообщат, что вы ответили.',
    'Your answer has been recorded.' => 'Ваш ответ записан.',
    'These people have been sent their instructions: {names}.' => 'Эти люди получили свои указания: {names}.',
    'The people {owner} chose have been sent their instructions.' => 'Выбранные люди ({owner}) получили свои указания.',
    'You have been emailed a message from {owner} as well, with what to do next.' => 'Мы также отправили вам сообщение от: {owner}, с пояснением, что делать дальше.',
    'Is {owner} all right?' => 'Всё ли в порядке с человеком: {owner}?',
    '{owner} has not answered their weekly check-in or its reminders. You are their next of kin, so you are asked first. Nothing has been sent to anyone else.' => 'Человек ({owner}) не ответил на еженедельное письмо и на напоминания. Вы его близкий человек, поэтому мы спрашиваем в первую очередь вас. Никому другому ничего не отправлялось.',
    'Not sure yet? You can come back to this page. If neither you nor {owner} answers by the time given in the email, the others are sent their instructions.' => 'Пока не знаете? Можете вернуться на эту страницу. Если к сроку, указанному в письме, не ответите ни вы, ни {owner}, остальные люди получат свои указания.',
    'It has either been used already, or the check it belonged to has been settled. You do not need to do anything.' => 'Ссылка уже использована или вопрос, к которому она относилась, решён. Вам ничего делать не нужно.',

    // History
    'History: Death Plan Switch' => 'История: Death Plan Switch',
    'History' => 'История',
    'Everything the switch has done, newest first. Scheduler runs that found nothing to do are not listed.' => 'Всё, что сделал переключатель, от новых к старым. Запуски планировщика, которым нечего было делать, не показаны.',
    'Nothing has happened yet.' => 'Пока ничего не происходило.',

    // Public page
    'This switch is active.' => 'Этот переключатель активен.',
    'This switch is not active.' => 'Этот переключатель не активен.',
    'This switch is armed.' => 'Этот переключатель активирован.',
    'Triggered' => 'Сработал',
    'This switch has been triggered.' => 'Этот переключатель сработал.',
    'Open the vault' => 'Открыть сейф',
    'Armed' => 'Активен',
    'Not active' => 'Не активен',
    'Owner sign-in' => 'Вход для владельца',

    // Vault (owner and recipient)
    'That recipient has no contact with an email address, so no invitation was sent.' => 'У этого получателя нет контакта с адресом e-mail, поэтому приглашение не отправлено.',
    'Invitation sent to {name}.' => 'Приглашение отправлено: {name}.',
    'The invitation could not be sent. Check the mail settings.' => 'Не удалось отправить приглашение. Проверь настройки почты.',
    'Vault: Death Plan Switch' => 'Сейф: Death Plan Switch',
    'Vault' => 'Сейф',
    "What you leave for the people you choose, locked in your browser. Only a recipient's code or password opens it, and only after the switch has fired." => 'То, что ты оставляешь выбранным людям, заперто в твоём браузере. Открыть это можно только кодом или паролем получателя, и лишь после того, как переключатель сработает.',
    'The vault needs JavaScript. Turn it on for this page.' => 'Сейфу нужен JavaScript. Включи его для этой страницы.',
    'This page needs JavaScript, because everything is decrypted in your browser.' => 'Этой странице нужен JavaScript, потому что всё расшифровывается в твоём браузере.',
];
