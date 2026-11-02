<?php
/** Polish wording for the history log: entries written from now on, and the words that fill messages in. */
declare(strict_types=1);

return [
    // Check-in methods (also stored as the way the last check-in came in)
    'the dashboard' => 'pulpit',
    'the emailed link' => 'link z e-maila',
    'another node' => 'inny węzeł',
    'arming the switch' => 'uzbrojenie przełącznika',
    'pausing the switch' => 'wstrzymanie przełącznika',
    'ending the pause' => 'zakończenie pauzy',

    // Cycle
    'Weekly check-in email sent.' => 'Wysłano cotygodniowy e-mail z prośbą o potwierdzenie obecności.',
    'Reminder {number} sent to the owner.' => 'Wysłano właścicielowi przypomnienie nr {number}.',
    'No check-in after 72 hours. The next of kin has been asked.' => 'Brak potwierdzenia obecności po 72 godzinach. Zapytano najbliższą osobę.',
    'No check-in after 72 hours. The next of kin could not be emailed; the 72-hour wait has started anyway.' => 'Brak potwierdzenia obecności po 72 godzinach. Nie udało się wysłać e-maila do najbliższej osoby; 72-godzinne oczekiwanie i tak się rozpoczęło.',
    'The next of kin was reminded.' => 'Najbliższa osoba dostała przypomnienie.',
    'The next of kin answered "Something\'s wrong". The switch has fired.' => 'Najbliższa osoba odpowiedziała „Coś jest nie tak”. Przełącznik został uruchomiony.',
    'Neither the owner nor the next of kin confirmed all is well. The switch has fired.' => 'Ani właściciel, ani najbliższa osoba nie potwierdzili, że wszystko w porządku. Przełącznik został uruchomiony.',
    '{role} ({name}) was sent their message.' => 'Wysłano wiadomość: {role} ({name}).',
    '{name} was told it was a false alarm.' => 'Wysłano informację o fałszywym alarmie: {name}.',
    'The next of kin answered "All fine". Nobody else was contacted.' => 'Najbliższa osoba odpowiedziała „Wszystko w porządku”. Nikt inny nie został powiadomiony.',
    'The next of kin answered "All fine" ({via}). Nobody else was contacted.' => 'Najbliższa osoba odpowiedziała „Wszystko w porządku” ({via}). Nikt inny nie został powiadomiony.',
    'The weekly check-in email was sent by another node.' => 'Cotygodniowy e-mail z prośbą o potwierdzenie obecności wysłał inny węzeł.',
    'Another node fired the switch.' => 'Przełącznik uruchomił inny węzeł.',
    'Checked in by {via}.' => 'Obecność potwierdzona (sposób: {via}).',
    'Checked in by another node ({via}).' => 'Obecność potwierdzona na innym węźle ({via}).',
    'False-alarm notices sent to {names}.' => 'Informacje o fałszywym alarmie wysłano do: {names}.',

    // Switch
    'The pause ended. The switch is active again.' => 'Pauza się skończyła. Przełącznik znów działa.',
    'The switch was armed.' => 'Przełącznik został uzbrojony.',
    'The switch was disarmed. {reason}' => 'Przełącznik został rozbrojony. {reason}',
    'The switch was paused for {duration}.' => 'Przełącznik wstrzymano na {duration}.',
    'The pause was ended early.' => 'Pauzę zakończono wcześniej.',
    'Yearly review completed.' => 'Coroczny przegląd zakończony.',
    'The data folder can be read from the web.' => 'Folder z danymi można odczytać z internetu.',

    // Contacts
    '{name} confirmed as {role}.' => '{name}: potwierdzono rolę – {role}.',
    'The new address for {name} was declined. The old one is still in use.' => 'Nowy adres dla {name} został odrzucony. Nadal używany jest stary.',
    '{name} declined to be {role}.' => '{name}: odmowa roli – {role}.',
    '{name} ({role}) was deleted.' => 'Usunięto kontakt: {name} ({role}).',
    '{name} was added as {role}.' => 'Dodano kontakt: {name} ({role}).',

    // Sending
    'Notified {where}' => 'Powiadomiono: {where}',
    'Could not notify {where}.' => 'Nie udało się powiadomić: {where}.',
    'Sent "{subject}" to {to}' => 'Wysłano „{subject}” do: {to}',
    'Could not send "{subject}" to {to}' => 'Nie udało się wysłać „{subject}” do: {to}',

    // Settings and sign-in
    'Backup email address confirmed: {address}.' => 'Zapasowy adres e-mail potwierdzony: {address}.',
    'Main email address confirmed: {address}.' => 'Główny adres e-mail potwierdzony: {address}.',
    'A wrong password was entered at sign-in.' => 'Przy logowaniu wpisano błędne hasło.',

    // Message placeholders (contact page)
    'The name of the person receiving the message' => 'Imię i nazwisko osoby, która dostaje wiadomość',
    'Whole days since your last check-in' => 'Pełne dni od ostatniego potwierdzenia obecności',
    'The date of your last check-in' => 'Data ostatniego potwierdzenia obecności',
    'Next of kin: name, email, phone' => 'Najbliższa osoba: imię i nazwisko, e-mail, telefon',
    'Technical contact: name, email, phone' => 'Kontakt techniczny: imię i nazwisko, e-mail, telefon',
    'Everyone else on your list, one per line' => 'Wszyscy pozostali z twojej listy, każdy w osobnej linii',

    // Settings, cluster, install, storage
    'The weekly check-in time was changed to {day} at {time} ({tz}).' => 'Zmieniono termin cotygodniowego potwierdzenia obecności na: {day}, {time} ({tz}).',
    'Mail settings were changed.' => 'Zmieniono ustawienia poczty.',
    'The owner password was changed.' => 'Zmieniono hasło właściciela.',
    'A node was added to the cluster.' => 'Dodano węzeł do klastra.',
    'A node public address was changed.' => 'Zmieniono publiczny adres węzła.',
    'A node was removed from the cluster.' => 'Usunięto węzeł z klastra.',
    'The cluster settings were changed.' => 'Zmieniono ustawienia klastra.',
    'Death Plan Switch {version} was installed.' => 'Zainstalowano Death Plan Switch {version}.',
    'Vault API error: {detail}' => 'Błąd API sejfu: {detail}',
    '{name}.json was unreadable. The last good copy was used instead.' => 'Nie udało się odczytać pliku {name}.json. Użyto ostatniej poprawnej kopii.',
    // trusted people
    '{name} was asked whether {owner} is all right.' => '{name} dostał(a) pytanie, czy u {owner} wszystko w porządku.',
    '{name} could not be emailed.' => 'Nie udało się wysłać e-maila do: {name}.',
    '{name} answered "Something\'s wrong". Nothing is sent early; the others are still asked in turn.' => '{name} odpowiedział(a): „Coś jest nie tak”. Nic nie jest wysyłane wcześniej, a kolejne osoby nadal dostają pytanie po kolei.',
    '{name} answered "All fine". Nobody else will be asked.' => '{name} odpowiedział(a): „Wszystko w porządku”. Nikt więcej nie dostanie pytania.',
    'The settings for the trusted people were changed.' => 'Zmieniono ustawienia zaufanych osób.',
];
