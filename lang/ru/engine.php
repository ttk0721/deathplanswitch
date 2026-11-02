<?php
/** Russian wording for the core classes: engine warnings and checklist, web errors, setup checks, contact labels. */
declare(strict_types=1);

return [
    // Contact labels
    'next of kin' => 'близкий человек',
    'technical 2IC' => 'технический заместитель',
    'legal contact' => 'юридический контакт',
    'contact' => 'контакт',
    'Next of kin' => 'Близкий человек',
    'Technical 2IC' => 'Технический заместитель',
    'Legal contact' => 'Юридический контакт',
    'Other contact' => 'Другой контакт',
    'Contact' => 'Контакт',
    '(none listed)' => '(никого не указано)',

    // Web
    'The data folder needs attention' => 'Папка с данными требует внимания',
    'Nothing has been changed. See "If a data file is damaged" in the README.' => 'Ничего не изменено. Прочитай в README раздел «If a data file is damaged».',
    'Too many wrong passwords. Try again {when}.' => 'Слишком много неверных паролей. Попробуй ещё раз {when}.',
    'That password is not right.' => 'Этот пароль неверный.',
    'That form had expired. Go back, reload the page and try again.' => 'Эта форма устарела. Вернись назад, обнови страницу и попробуй ещё раз.',

    // Setup
    'PHP {version} or newer' => 'PHP {version} или новее',
    'This server runs PHP {version}. Ask your host to switch to a current version.' => 'На этом сервере работает PHP {version}. Попроси хостинг перейти на актуальную версию.',
    'The OpenSSL extension, for encrypted mail' => 'Расширение OpenSSL, для шифрованной почты',
    'Ask your host to enable the PHP openssl extension.' => 'Попроси хостинг включить расширение PHP openssl.',
    'The mbstring extension' => 'Расширение mbstring',
    'Ask your host to enable the PHP mbstring extension.' => 'Попроси хостинг включить расширение PHP mbstring.',
    'The data folder can be written to' => 'В папку с данными можно записывать',
    'PHP cannot write to {dir}. Give that folder to the user the web pages run as' => 'PHP не может записывать в {dir}. Передай эту папку пользователю, от имени которого работают веб-страницы',
    'Choose a password of at least {min} characters.' => 'Выбери пароль не короче {min} символов.',
    'That password is too long.' => 'Этот пароль слишком длинный.',
    'Enter the mail server host, for example smtp.example.com.' => 'Введи адрес почтового сервера, например smtp.example.com.',
    'Enter the mail server port. 587 is the usual one.' => 'Введи порт почтового сервера. Обычно это 587.',
    'Enter the address the switch sends from. Your mail service must allow it.' => 'Введи адрес, с которого переключатель отправляет почту. Твоя почтовая служба должна это разрешать.',
    'There is no code waiting. Send a new one.' => 'Нет кода, ожидающего ввода. Отправь новый.',
    'That code has expired. Send a new one.' => 'Срок действия этого кода истёк. Отправь новый.',
    'That code is not right. Check the email and try again.' => 'Этот код неверный. Проверь письмо и попробуй ещё раз.',

    // Engine: actions
    'Open the vault' => 'Открыть сейф',
    'You disarmed it from the dashboard.' => 'Ты деактивировал его с панели управления.',
    'Their previous address is still in use.' => 'Предыдущий адрес этого человека по-прежнему используется.',
    '{name} declined to be your {role}, so a required contact is missing.' => '{name} отказывается от роли «{role}», поэтому не хватает обязательного контакта.',
    'Your switch has been disarmed, because it needs this role filled.' => 'Твой переключатель деактивирован, потому что эта роль должна быть заполнена.',

    // Engine: checklist
    'The scheduler is running' => 'Планировщик работает',
    'No run has been recorded yet. Add the crontab line shown in Settings.' => 'Ни одного запуска ещё не записано. Добавь строку crontab, показанную в Настройках.',
    'The last run was {when}. Check the crontab line shown in Settings.' => 'Последний запуск был {when}. Проверь строку crontab, показанную в Настройках.',
    'The data folder cannot be read from the web' => 'Папку с данными нельзя прочитать из интернета',
    'Files in the data folder can be opened in a browser. See "Protecting the data folder" in the README.' => 'Файлы из папки с данными можно открыть в браузере. Прочитай в README раздел «Protecting the data folder».',
    'The server could not test this by itself. Run the check from Settings.' => 'Сервер не смог проверить это самостоятельно. Запусти проверку в Настройках.',
    'Your next of kin has confirmed' => 'Твой близкий человек подтвердил согласие',
    'Add your next of kin.' => 'Добавь своего близкого человека.',
    'Your technical 2IC has confirmed' => 'Твой технический заместитель подтвердил согласие',
    'Add the person who would look after your clients.' => 'Добавь человека, который позаботился бы о твоих клиентах.',
    'Every message says where things are' => 'Каждое сообщение указывает, где что лежит',
    'Still holding starter text in square brackets: {names}.' => 'Всё ещё содержат образец текста в квадратных скобках: {names}.',
    'Checked once your contacts have confirmed.' => 'Проверим, когда контакты подтвердят согласие.',
    'Each contact has been given what they need' => 'Каждому контакту передано всё необходимое',
    'Not ticked yet: {names}.' => 'Ещё не отмечено: {names}.',
    'A second email address for reminders' => 'Второй адрес e-mail для напоминаний',
    'Recommended: an address with a different provider, in case your main one fails. Add it in Settings.' => 'Рекомендуется: адрес у другого провайдера на случай сбоя основного. Добавить его можно в Настройках.',
    '{name} declined. Choose someone else.' => '{name} отказывается. Выбери кого-то другого.',
    'Send {name} their confirmation email.' => 'Отправь контакту {name} письмо с просьбой подтвердить согласие.',
    'Waiting for {name} to confirm. The email went {when}.' => 'Ждём подтверждения от: {name}. Письмо отправлено {when}.',

    // Engine: warnings
    '{name}.json was damaged and the last good copy is in use. Check what changed recently, then save any setting to rewrite the file.' => 'Файл {name}.json был повреждён, поэтому используется последняя исправная копия. Проверь, что недавно менялось, а затем сохрани любую настройку, чтобы записать файл заново.',
    'The scheduler has never run. Nothing will be sent until it does.' => 'Планировщик ни разу не работал. Пока он не заработает, ничего не будет отправляться.',
    'The scheduler last ran {when}. While it is stopped, the switch cannot send anything.' => 'Планировщик последний раз работал {when}. Пока он остановлен, переключатель ничего не отправит.',
    'This node has not heard from any other node for {duration}. Serious steps are delayed while it is cut off.' => 'Этот узел не получал вестей от других узлов уже {duration}. Серьёзные шаги отложены, пока он отключён.',
    'This node has not heard from any other node yet. Serious steps are delayed while it is cut off.' => 'Этот узел ещё не получал вестей от других узлов. Серьёзные шаги отложены, пока он отключён.',
    'The last email could not be sent ({when}): {error}' => 'Последнее письмо не удалось отправить ({when}): {error}',
    'notification' => 'уведомления',
    'unknown error' => 'неизвестная ошибка',
    'The {label} channel failed {when}: {error}' => 'Канал «{label}» дал сбой {when}: {error}',
    'Files in the data folder can be opened in a browser.' => 'Файлы из папки с данными можно открыть в браузере.',
    'The server could not check whether the data folder is blocked from the web.' => 'Сервер не смог проверить, закрыта ли папка с данными от интернета.',
    'A required contact is missing or unconfirmed.' => 'Обязательного контакта нет или он не подтверждён.',
    '{name} declined and will not be contacted.' => '{name} отказывается, поэтому сообщений не получит.',
    '{name} has not confirmed, so will not be contacted.' => 'Согласие от {name} не подтверждено, поэтому сообщений этот контакт не получит.',
    '{name} has not confirmed their new address. The old one is still in use.' => 'Новый адрес для {name} не подтверждён. По-прежнему используется старый.',
    '{name} has not answered their yearly reconfirmation. Check their address still works.' => '{name} не отвечает на ежегодный запрос о подтверждении. Проверь, работает ли этот адрес.',
    'The message for {name} still holds starter text in square brackets.' => 'Сообщение для: {name} всё ещё содержит образец текста в квадратных скобках.',
    'Your yearly review is due: confirm each contact still has what they need.' => 'Пора провести ежегодную проверку: убедись, что у каждого контакта по-прежнему есть всё необходимое.',
    'This install is not using HTTPS. Links in its emails can be read in transit.' => 'Эта установка не использует HTTPS. Ссылки в её письмах могут быть перехвачены по пути.',

    // Engine: message previews
    'When you miss a check-in and its reminders' => 'Когда ты пропустишь подтверждение присутствия и напоминания о нём',
    'When the switch fires' => 'Когда переключатель сработает',
];
