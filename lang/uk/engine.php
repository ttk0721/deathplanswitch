<?php
/** Ukrainian wording for the core classes: engine warnings and checklist, web errors, setup checks, contact labels. */
declare(strict_types=1);

return [
    // Contact labels
    'next of kin' => 'найближча людина',
    'legal contact' => 'юридичний контакт',
    'contact' => 'контакт',
    'Next of kin' => 'Найближча людина',
    'Legal contact' => 'Юридичний контакт',
    'Other contact' => 'Інший контакт',
    'Contact' => 'Контакт',
    '(none listed)' => '(нікого не вказано)',

    // Web
    'The data folder needs attention' => 'Папка з даними потребує уваги',
    'Nothing has been changed. See "If a data file is damaged" in the README.' => 'Нічого не змінено. Прочитай у README розділ «If a data file is damaged».',
    'Too many wrong passwords. Try again {when}.' => 'Забагато неправильних паролів. Спробуй ще раз {when}.',
    'That password is not right.' => 'Цей пароль неправильний.',
    'That form had expired. Go back, reload the page and try again.' => 'Ця форма застаріла. Поверніться назад, онови сторінку й спробуй ще раз.',

    // Setup
    'PHP {version} or newer' => 'PHP {version} або новіший',
    'This server runs PHP {version}. Ask your host to switch to a current version.' => 'На цьому сервері працює PHP {version}. Попроси хостинг перейти на актуальну версію.',
    'The OpenSSL extension, for encrypted mail' => 'Розширення OpenSSL, для шифрованої пошти',
    'Ask your host to enable the PHP openssl extension.' => 'Попроси хостинг увімкнути розширення PHP openssl.',
    'The mbstring extension' => 'Розширення mbstring',
    'Ask your host to enable the PHP mbstring extension.' => 'Попроси хостинг увімкнути розширення PHP mbstring.',
    'The data folder can be written to' => 'До папки з даними можна записувати',
    'PHP cannot write to {dir}. Give that folder to the user the web pages run as' => 'PHP не може записувати до {dir}. Передай цю папку користувачеві, від імені якого працюють вебсторінки',
    'Choose a password of at least {min} characters.' => 'Вибери пароль щонайменше з {min} символів.',
    'That password is too long.' => 'Цей пароль надто довгий.',
    'Enter the mail server host, for example smtp.example.com.' => 'Введи адресу поштового сервера, наприклад smtp.example.com.',
    'Enter the mail server port. 587 is the usual one.' => 'Введи порт поштового сервера. Зазвичай це 587.',
    'Enter the address the switch sends from. Your mail service must allow it.' => 'Введи адресу, з якої перемикач надсилає пошту. Твоя поштова служба має це дозволяти.',
    'There is no code waiting. Send a new one.' => 'Немає коду, що очікує на введення. Надішли новий.',
    'That code has expired. Send a new one.' => 'Термін дії цього коду минув. Надішли новий.',
    'That code is not right. Check the email and try again.' => 'Цей код неправильний. Перевір лист і спробуй ще раз.',

    // Engine: actions
    'Open the vault' => 'Відкрити сейф',
    'You disarmed it from the dashboard.' => 'Перемикач знято з охорони з панелі керування.',
    'Their previous address is still in use.' => 'Попередня адреса цієї людини досі використовується.',
    '{name} declined to be your {role}, so a required contact is missing.' => '{name} відмовляється від ролі «{role}», тож бракує обовʼязкового контакту.',
    'Your switch has been disarmed, because it needs this role filled.' => 'Твій перемикач знято з охорони, бо ця роль має бути заповнена.',

    // Engine: checklist
    'The scheduler is running' => 'Планувальник працює',
    'No run has been recorded yet. Add the crontab line shown in Settings.' => 'Жодного запуску ще не записано. Додай рядок crontab, показаний у Налаштуваннях.',
    'The last run was {when}. Check the crontab line shown in Settings.' => 'Останній запуск був {when}. Перевір рядок crontab, показаний у Налаштуваннях.',
    'The data folder cannot be read from the web' => 'Папку з даними не можна прочитати з інтернету',
    'Files in the data folder can be opened in a browser. See "Protecting the data folder" in the README.' => 'Файли з папки з даними можна відкрити в браузері. Прочитай у README розділ «Protecting the data folder».',
    'The server could not test this by itself. Run the check from Settings.' => 'Сервер не зміг перевірити це самостійно. Запусти перевірку в Налаштуваннях.',
    'Your next of kin has confirmed' => 'Твоя найближча людина підтвердила згоду',
    'Add your next of kin.' => 'Додай свою найближчу людину.',
    'Every message says where things are' => 'Кожне повідомлення вказує, де що лежить',
    'Still holding starter text in square brackets: {names}.' => 'Усе ще містять зразок тексту в квадратних дужках: {names}.',
    'Checked once your contacts have confirmed.' => 'Перевіримо, коли контакти підтвердять згоду.',
    'Each contact has been given what they need' => 'Кожен контакт отримав усе потрібне',
    'Not ticked yet: {names}.' => 'Ще не позначено: {names}.',
    'A second email address for reminders' => 'Друга адреса e-mail для нагадувань',
    'Recommended: an address with a different provider, in case your main one fails. Add it in Settings.' => 'Рекомендовано: адреса в іншого постачальника на випадок збою основної. Додати її можна в Налаштуваннях.',
    '{name} declined. Choose someone else.' => '{name} відмовляється. Вибери когось іншого.',
    'Send {name} their confirmation email.' => 'Надішли контакту {name} лист із проханням підтвердити згоду.',
    'Waiting for {name} to confirm. The email went {when}.' => 'Чекаємо на підтвердження від: {name}. Лист надіслано {when}.',

    // Engine: warnings
    '{name}.json was damaged and the last good copy is in use. Check what changed recently, then save any setting to rewrite the file.' => 'Файл {name}.json було пошкоджено, тож використовується остання справна копія. Перевір, що нещодавно змінювалося, а потім збережи будь-яке налаштування, щоб записати файл заново.',
    'The scheduler has never run. Nothing will be sent until it does.' => 'Планувальник ніколи не працював. Доки він не запрацює, нічого не надсилатиметься.',
    'The scheduler last ran {when}. While it is stopped, the switch cannot send anything.' => 'Планувальник востаннє працював {when}. Поки він зупинений, перемикач нічого не надішле.',
    'This node has not heard from any other node for {duration}. Serious steps are delayed while it is cut off.' => 'Цей вузол не чув жодного іншого вузла вже {duration}. Серйозні кроки відкладено, доки він відʼєднаний.',
    'This node has not heard from any other node yet. Serious steps are delayed while it is cut off.' => 'Цей вузол ще не чув жодного іншого вузла. Серйозні кроки відкладено, доки він відʼєднаний.',
    'The last email could not be sent ({when}): {error}' => 'Останній лист не вдалося надіслати ({when}): {error}',
    'notification' => 'сповіщення',
    'unknown error' => 'невідома помилка',
    'The {label} channel failed {when}: {error}' => 'Канал «{label}» не спрацював {when}: {error}',
    'Files in the data folder can be opened in a browser.' => 'Файли з папки з даними можна відкрити в браузері.',
    'The server could not check whether the data folder is blocked from the web.' => 'Сервер не зміг перевірити, чи закрито папку з даними від інтернету.',
    'A required contact is missing or unconfirmed.' => 'Обовʼязкового контакту немає або його не підтверджено.',
    '{name} declined and will not be contacted.' => '{name} відмовляється, тож повідомлень не отримає.',
    '{name} has not confirmed, so will not be contacted.' => 'Згоду від {name} не підтверджено, тож повідомлень цей контакт не отримає.',
    '{name} has not confirmed their new address. The old one is still in use.' => 'Нову адресу для {name} не підтверджено. Досі використовується стара.',
    '{name} has not answered their yearly reconfirmation. Check their address still works.' => '{name} не відповідає на щорічний запит про підтвердження. Перевір, чи ця адреса досі працює.',
    'The message for {name} still holds starter text in square brackets.' => 'Повідомлення для: {name} усе ще містить зразок тексту в квадратних дужках.',
    'Your yearly review is due: confirm each contact still has what they need.' => 'Настав час щорічного перегляду: переконайся, що кожен контакт досі має все потрібне.',
    'This install is not using HTTPS. Links in its emails can be read in transit.' => 'Ця інсталяція не використовує HTTPS. Посилання в її листах можуть бути перехоплені дорогою.',

    // Engine: message previews
    'When you miss a check-in and its reminders' => 'Коли ти пропустиш підтвердження присутності та нагадування про нього',
    'When the switch fires' => 'Коли перемикач спрацює',

    'technical helper' => 'технічний помічник',
    'Technical helper' => 'Технічний помічник',
    'Technical helper (optional)' => 'Технічний помічник (за бажанням)',
    'Someone who can keep your servers, accounts or projects running.' => 'Той, хто зможе підтримувати роботу твоїх серверів, облікових записів чи проєктів.',
];
