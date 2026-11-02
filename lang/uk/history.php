<?php
/** Ukrainian wording for the history log: entries written from now on, and the words that fill messages in. */
declare(strict_types=1);

return [
    // Check-in methods (also stored as the way the last check-in came in)
    'the dashboard' => 'панель керування',
    'the emailed link' => 'посилання з листа',
    'another node' => 'інший вузол',
    'arming the switch' => 'постановка перемикача на охорону',
    'pausing the switch' => 'призупинення перемикача',
    'ending the pause' => 'завершення призупинення',

    // Cycle
    'Weekly check-in email sent.' => 'Надіслано щотижневий лист із проханням підтвердити присутність.',
    'Reminder {number} sent to the owner.' => 'Власнику надіслано нагадування № {number}.',
    'No check-in after 72 hours. The next of kin has been asked.' => 'Присутність не підтверджено протягом 72 годин. Найближчу людину запитано.',
    'No check-in after 72 hours. The next of kin could not be emailed; the 72-hour wait has started anyway.' => 'Присутність не підтверджено протягом 72 годин. Не вдалося надіслати лист найближчій людині; 72-годинне очікування все одно розпочалося.',
    'The next of kin was reminded.' => 'Найближчій людині надіслано нагадування.',
    'The next of kin answered "Something\'s wrong". The switch has fired.' => 'Найближча людина відповіла «Щось не так». Перемикач спрацював.',
    'Neither the owner nor the next of kin confirmed all is well. The switch has fired.' => 'Ні власник, ні найближча людина не підтвердили, що все гаразд. Перемикач спрацював.',
    '{role} ({name}) was sent their message.' => 'Надіслано повідомлення: {role} ({name}).',
    '{name} was told it was a false alarm.' => 'Про помилкову тривогу повідомлено: {name}.',
    'The next of kin answered "All fine". Nobody else was contacted.' => 'Найближча людина відповіла «Усе гаразд». Нікого іншого не сповіщено.',
    'The next of kin answered "All fine" ({via}). Nobody else was contacted.' => 'Найближча людина відповіла «Усе гаразд» ({via}). Нікого іншого не сповіщено.',
    'The weekly check-in email was sent by another node.' => 'Щотижневий лист із проханням підтвердити присутність надіслав інший вузол.',
    'Another node fired the switch.' => 'Перемикач запустив інший вузол.',
    'Checked in by {via}.' => 'Присутність підтверджено (спосіб: {via}).',
    'Checked in by another node ({via}).' => 'Присутність підтверджено на іншому вузлі ({via}).',
    'False-alarm notices sent to {names}.' => 'Повідомлення про помилкову тривогу надіслано: {names}.',

    // Switch
    'The pause ended. The switch is active again.' => 'Призупинення закінчилося. Перемикач знову працює.',
    'The switch was armed.' => 'Перемикач поставлено на охорону.',
    'The switch was disarmed. {reason}' => 'Перемикач знято з охорони. {reason}',
    'The switch was paused for {duration}.' => 'Перемикач призупинено на {duration}.',
    'The pause was ended early.' => 'Призупинення завершено раніше.',
    'Yearly review completed.' => 'Щорічний перегляд завершено.',
    'The data folder can be read from the web.' => 'Папку з даними можна прочитати з інтернету.',

    // Contacts
    '{name} confirmed as {role}.' => '{name}: роль підтверджено – {role}.',
    'The new address for {name} was declined. The old one is still in use.' => 'Нову адресу для {name} відхилено. Досі використовується стара.',
    '{name} declined to be {role}.' => '{name}: відмова від ролі – {role}.',
    '{name} ({role}) was deleted.' => 'Контакт видалено: {name} ({role}).',
    '{name} was added as {role}.' => 'Контакт додано: {name} ({role}).',

    // Sending
    'Notified {where}' => 'Сповіщено: {where}',
    'Could not notify {where}.' => 'Не вдалося сповістити: {where}.',
    'Sent "{subject}" to {to}' => 'Надіслано «{subject}» на: {to}',
    'Could not send "{subject}" to {to}' => 'Не вдалося надіслати «{subject}» на: {to}',

    // Settings and sign-in
    'Backup email address confirmed: {address}.' => 'Резервну адресу e-mail підтверджено: {address}.',
    'Main email address confirmed: {address}.' => 'Основну адресу e-mail підтверджено: {address}.',
    'A wrong password was entered at sign-in.' => 'Під час входу введено неправильний пароль.',

    // Message placeholders (contact page)
    'The name of the person receiving the message' => 'Імʼя того, хто отримує повідомлення',
    'Whole days since your last check-in' => 'Повні дні від останнього підтвердження присутності',
    'The date of your last check-in' => 'Дата останнього підтвердження присутності',
    'Next of kin: name, email, phone' => 'Найближча людина: імʼя, e-mail, телефон',
    'Technical contact: name, email, phone' => 'Технічний контакт: імʼя, e-mail, телефон',
    'Everyone else on your list, one per line' => 'Усі решта з твого списку, кожен в окремому рядку',

    // Settings, cluster, install, storage
    'The weekly check-in time was changed to {day} at {time} ({tz}).' => 'Час щотижневого підтвердження присутності змінено на: {day}, {time} ({tz}).',
    'Mail settings were changed.' => 'Налаштування пошти змінено.',
    'The owner password was changed.' => 'Пароль власника змінено.',
    'A node was added to the cluster.' => 'До кластера додано вузол.',
    'A node public address was changed.' => 'Публічну адресу вузла змінено.',
    'A node was removed from the cluster.' => 'Вузол вилучено з кластера.',
    'The cluster settings were changed.' => 'Налаштування кластера змінено.',
    'Death Plan Switch {version} was installed.' => 'Встановлено Death Plan Switch {version}.',
    'Vault API error: {detail}' => 'Помилка API сейфа: {detail}',
    '{name}.json was unreadable. The last good copy was used instead.' => 'Не вдалося прочитати файл {name}.json. Використано останню справну копію.',

    'another node ({via})' => 'інший вузол ({via})',
    '{name} was asked whether {owner} is all right.' => 'Запитання, чи у {owner} усе гаразд, надіслано: {name}.',
    '{name} could not be emailed.' => 'Не вдалося надіслати лист: {name}.',
    '{name} answered "Something\'s wrong". Nothing is sent early; the others are still asked in turn.' => '{name}: відповідь «Щось не так». Нічого не надсилається завчасно, решту й далі питають по черзі.',
    '{name} answered "All fine". Nobody else will be asked.' => '{name}: відповідь «Усе гаразд». Більше нікого не питатимуть.',
    'The settings for the trusted people were changed.' => 'Налаштування довірених осіб змінено.',
];
