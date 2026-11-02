<?php
/** Russian wording for the history log: entries written from now on, and the words that fill messages in. */
declare(strict_types=1);

return [
    // Check-in methods (also stored as the way the last check-in came in)
    'the dashboard' => 'панель управления',
    'the emailed link' => 'ссылка из письма',
    'another node' => 'другой узел',
    'arming the switch' => 'активация переключателя',
    'pausing the switch' => 'приостановка переключателя',
    'ending the pause' => 'завершение приостановки',

    // Cycle
    'Weekly check-in email sent.' => 'Отправлено еженедельное письмо с просьбой подтвердить присутствие.',
    'Reminder {number} sent to the owner.' => 'Владельцу отправлено напоминание № {number}.',
    'No check-in after 72 hours. The next of kin has been asked.' => 'Присутствие не подтверждено в течение 72 часов. Близкому человеку задан вопрос.',
    'No check-in after 72 hours. The next of kin could not be emailed; the 72-hour wait has started anyway.' => 'Присутствие не подтверждено в течение 72 часов. Не удалось отправить письмо близкому человеку; 72-часовое ожидание всё равно началось.',
    'The next of kin was reminded.' => 'Близкому человеку отправлено напоминание.',
    'The next of kin answered "Something\'s wrong". The switch has fired.' => 'Близкий человек ответил «Что-то не так». Переключатель сработал.',
    'Neither the owner nor the next of kin confirmed all is well. The switch has fired.' => 'Ни владелец, ни близкий человек не подтвердили, что всё в порядке. Переключатель сработал.',
    '{role} ({name}) was sent their message.' => 'Отправлено сообщение: {role} ({name}).',
    '{name} was told it was a false alarm.' => 'О ложной тревоге сообщено: {name}.',
    'The next of kin answered "All fine". Nobody else was contacted.' => 'Близкий человек ответил «Всё в порядке». Больше никому не писали.',
    'The next of kin answered "All fine" ({via}). Nobody else was contacted.' => 'Близкий человек ответил «Всё в порядке» ({via}). Больше никому не писали.',
    'The weekly check-in email was sent by another node.' => 'Еженедельное письмо с просьбой подтвердить присутствие отправил другой узел.',
    'Another node fired the switch.' => 'Переключатель запустил другой узел.',
    'Checked in by {via}.' => 'Присутствие подтверждено (способ: {via}).',
    'Checked in by another node ({via}).' => 'Присутствие подтверждено на другом узле ({via}).',
    'False-alarm notices sent to {names}.' => 'Уведомления о ложной тревоге отправлены: {names}.',

    // Switch
    'The pause ended. The switch is active again.' => 'Приостановка закончилась. Переключатель снова работает.',
    'The switch was armed.' => 'Переключатель активирован.',
    'The switch was disarmed. {reason}' => 'Переключатель деактивирован. {reason}',
    'The switch was paused for {duration}.' => 'Переключатель приостановлен на {duration}.',
    'The pause was ended early.' => 'Приостановка завершена досрочно.',
    'Yearly review completed.' => 'Ежегодная проверка завершена.',
    'The data folder can be read from the web.' => 'Папку с данными можно прочитать из интернета.',

    // Contacts
    '{name} confirmed as {role}.' => '{name}: роль подтверждена – {role}.',
    'The new address for {name} was declined. The old one is still in use.' => 'Новый адрес для {name} отклонён. По-прежнему используется старый.',
    '{name} declined to be {role}.' => '{name}: отказ от роли – {role}.',
    '{name} ({role}) was deleted.' => 'Контакт удалён: {name} ({role}).',
    '{name} was added as {role}.' => 'Контакт добавлен: {name} ({role}).',

    // Sending
    'Notified {where}' => 'Уведомление отправлено: {where}',
    'Could not notify {where}.' => 'Не удалось уведомить: {where}.',
    'Sent "{subject}" to {to}' => 'Отправлено «{subject}» на: {to}',
    'Could not send "{subject}" to {to}' => 'Не удалось отправить «{subject}» на: {to}',

    // Settings and sign-in
    'Backup email address confirmed: {address}.' => 'Резервный адрес e-mail подтверждён: {address}.',
    'Main email address confirmed: {address}.' => 'Основной адрес e-mail подтверждён: {address}.',
    'A wrong password was entered at sign-in.' => 'При входе введён неверный пароль.',

    // Message placeholders (contact page)
    'The name of the person receiving the message' => 'Имя того, кто получает сообщение',
    'Whole days since your last check-in' => 'Полные дни с последнего подтверждения присутствия',
    'The date of your last check-in' => 'Дата последнего подтверждения присутствия',
    'Next of kin: name, email, phone' => 'Близкий человек: имя, e-mail, телефон',
    'Technical contact: name, email, phone' => 'Технический контакт: имя, e-mail, телефон',
    'Everyone else on your list, one per line' => 'Все остальные из твоего списка, каждый в отдельной строке',

    // Settings, cluster, install, storage
    'The weekly check-in time was changed to {day} at {time} ({tz}).' => 'Время еженедельного подтверждения присутствия изменено на: {day}, {time} ({tz}).',
    'Mail settings were changed.' => 'Настройки почты изменены.',
    'The owner password was changed.' => 'Пароль владельца изменён.',
    'A node was added to the cluster.' => 'В кластер добавлен узел.',
    'A node public address was changed.' => 'Публичный адрес узла изменён.',
    'A node was removed from the cluster.' => 'Узел удалён из кластера.',
    'The cluster settings were changed.' => 'Настройки кластера изменены.',
    'Death Plan Switch {version} was installed.' => 'Установлен Death Plan Switch {version}.',
    'Vault API error: {detail}' => 'Ошибка API сейфа: {detail}',
    '{name}.json was unreadable. The last good copy was used instead.' => 'Не удалось прочитать файл {name}.json. Использована последняя исправная копия.',
];
