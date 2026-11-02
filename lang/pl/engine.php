<?php
/** Polish wording for the core classes: engine warnings and checklist, web errors, setup checks, contact labels. */
declare(strict_types=1);

return [
    // Contact labels
    'next of kin' => 'najbliższa osoba',
    'technical 2IC' => 'zastępca techniczny',
    'legal contact' => 'kontakt prawny',
    'contact' => 'kontakt',
    'Next of kin' => 'Najbliższa osoba',
    'Technical 2IC' => 'Zastępca techniczny',
    'Legal contact' => 'Kontakt prawny',
    'Other contact' => 'Inny kontakt',
    'Contact' => 'Kontakt',
    '(none listed)' => '(nikogo nie wpisano)',

    // Web
    'The data folder needs attention' => 'Folder z danymi wymaga uwagi',
    'Nothing has been changed. See "If a data file is damaged" in the README.' => 'Nic nie zostało zmienione. Zajrzyj do README, do części „Gdy plik z danymi jest uszkodzony”.',
    'Too many wrong passwords. Try again {when}.' => 'Zbyt wiele błędnych haseł. Spróbuj ponownie {when}.',
    'That password is not right.' => 'To hasło jest nieprawidłowe.',
    'That form had expired. Go back, reload the page and try again.' => 'Ten formularz wygasł. Wróć, odśwież stronę i spróbuj jeszcze raz.',

    // Setup
    'PHP {version} or newer' => 'PHP {version} lub nowszy',
    'This server runs PHP {version}. Ask your host to switch to a current version.' => 'Ten serwer ma PHP {version}. Poproś hosting o przełączenie na aktualną wersję.',
    'The OpenSSL extension, for encrypted mail' => 'Rozszerzenie OpenSSL, do szyfrowanej poczty',
    'Ask your host to enable the PHP openssl extension.' => 'Poproś hosting o włączenie rozszerzenia PHP openssl.',
    'The mbstring extension' => 'Rozszerzenie mbstring',
    'Ask your host to enable the PHP mbstring extension.' => 'Poproś hosting o włączenie rozszerzenia PHP mbstring.',
    'The data folder can be written to' => 'Do folderu z danymi można zapisywać',
    'PHP cannot write to {dir}. Give that folder to the user the web pages run as' => 'PHP nie może zapisywać w {dir}. Przekaż ten folder użytkownikowi, z którego działają strony',
    'Choose a password of at least {min} characters.' => 'Wybierz hasło o długości co najmniej {min} znaków.',
    'That password is too long.' => 'To hasło jest zbyt długie.',
    'Enter the mail server host, for example smtp.example.com.' => 'Wpisz adres serwera poczty, na przykład smtp.example.com.',
    'Enter the mail server port. 587 is the usual one.' => 'Wpisz port serwera poczty. Zwykle jest to 587.',
    'Enter the address the switch sends from. Your mail service must allow it.' => 'Wpisz adres, z którego przełącznik wysyła pocztę. Twoja usługa poczty musi na to pozwalać.',
    'There is no code waiting. Send a new one.' => 'Żaden kod nie czeka na wpisanie. Wyślij nowy.',
    'That code has expired. Send a new one.' => 'Ten kod wygasł. Wyślij nowy.',
    'That code is not right. Check the email and try again.' => 'Ten kod jest nieprawidłowy. Sprawdź e-mail i spróbuj jeszcze raz.',

    // Engine: actions
    'Open the vault' => 'Otwórz sejf',
    'You disarmed it from the dashboard.' => 'Rozbrojono go z pulpitu.',
    'Their previous address is still in use.' => 'Ich poprzedni adres nadal jest używany.',
    '{name} declined to be your {role}, so a required contact is missing.' => '{name} odmawia roli: {role}, więc brakuje wymaganego kontaktu.',
    'Your switch has been disarmed, because it needs this role filled.' => 'Twój przełącznik został rozbrojony, bo ta rola musi być obsadzona.',

    // Engine: checklist
    'The scheduler is running' => 'Harmonogram działa',
    'No run has been recorded yet. Add the crontab line shown in Settings.' => 'Nie zapisano jeszcze żadnego uruchomienia. Dodaj wiersz crontab pokazany w Ustawieniach.',
    'The last run was {when}. Check the crontab line shown in Settings.' => 'Ostatnie uruchomienie było {when}. Sprawdź wiersz crontab pokazany w Ustawieniach.',
    'The data folder cannot be read from the web' => 'Folderu z danymi nie da się odczytać z internetu',
    'Files in the data folder can be opened in a browser. See "Protecting the data folder" in the README.' => 'Pliki z folderu z danymi można otworzyć w przeglądarce. Zajrzyj do README, do części „Ochrona folderu z danymi”.',
    'The server could not test this by itself. Run the check from Settings.' => 'Serwer nie mógł tego sprawdzić sam. Uruchom kontrolę w Ustawieniach.',
    'Your next of kin has confirmed' => 'Twoja najbliższa osoba potwierdziła zgodę',
    'Add your next of kin.' => 'Dodaj swoją najbliższą osobę.',
    'Your technical 2IC has confirmed' => 'Twój zastępca techniczny potwierdził zgodę',
    'Add the person who would look after your clients.' => 'Dodaj osobę, która zaopiekuje się twoimi klientami.',
    'Every message says where things are' => 'Każda wiadomość mówi, gdzie co jest',
    'Still holding starter text in square brackets: {names}.' => 'Nadal zawierają tekst startowy w nawiasach kwadratowych: {names}.',
    'Checked once your contacts have confirmed.' => 'Sprawdzimy, gdy kontakty potwierdzą zgodę.',
    'Each contact has been given what they need' => 'Każdy kontakt dostał to, czego potrzebuje',
    'Not ticked yet: {names}.' => 'Jeszcze nie zaznaczono: {names}.',
    'A second email address for reminders' => 'Drugi adres e-mail do przypomnień',
    'Recommended: an address with a different provider, in case your main one fails. Add it in Settings.' => 'Zalecane: adres u innego dostawcy, na wypadek awarii głównego. Dodasz go w Ustawieniach.',
    '{name} declined. Choose someone else.' => '{name} odmawia. Wybierz kogoś innego.',
    'Send {name} their confirmation email.' => 'Wyślij do: {name} e-mail z prośbą o potwierdzenie zgody.',
    'Waiting for {name} to confirm. The email went {when}.' => 'Czekamy na potwierdzenie od: {name}. E-mail poszedł {when}.',

    // Engine: warnings
    '{name}.json was damaged and the last good copy is in use. Check what changed recently, then save any setting to rewrite the file.' => 'Plik {name}.json był uszkodzony i używana jest ostatnia dobra kopia. Sprawdź, co ostatnio się zmieniło, potem zapisz dowolne ustawienie, żeby zapisać plik od nowa.',
    'The scheduler has never run. Nothing will be sent until it does.' => 'Harmonogram nigdy nie działał. Dopóki się nie uruchomi, nic nie zostanie wysłane.',
    'The scheduler last ran {when}. While it is stopped, the switch cannot send anything.' => 'Harmonogram ostatnio działał {when}. Gdy stoi, przełącznik nic nie wyśle.',
    'This node has not heard from any other node for {duration}. Serious steps are delayed while it is cut off.' => 'Ten węzeł nie miał wieści od innych węzłów od {duration}. Poważne kroki są wstrzymane, dopóki jest odcięty.',
    'This node has not heard from any other node yet. Serious steps are delayed while it is cut off.' => 'Ten węzeł nie miał jeszcze wieści od innych węzłów. Poważne kroki są wstrzymane, dopóki jest odcięty.',
    'The last email could not be sent ({when}): {error}' => 'Ostatniego e-maila nie udało się wysłać ({when}): {error}',
    'notification' => 'powiadomień',
    'unknown error' => 'nieznany błąd',
    'The {label} channel failed {when}: {error}' => 'Kanał „{label}” zawiódł {when}: {error}',
    'Files in the data folder can be opened in a browser.' => 'Pliki z folderu z danymi można otworzyć w przeglądarce.',
    'The server could not check whether the data folder is blocked from the web.' => 'Serwer nie mógł sprawdzić, czy folder z danymi jest zablokowany dla internetu.',
    'A required contact is missing or unconfirmed.' => 'Brakuje wymaganego kontaktu albo nie potwierdził zgody.',
    '{name} declined and will not be contacted.' => '{name} odmawia i nie dostanie wiadomości.',
    '{name} has not confirmed, so will not be contacted.' => '{name} nie potwierdza zgody, więc nie dostanie wiadomości.',
    '{name} has not confirmed their new address. The old one is still in use.' => '{name} nie potwierdza nowego adresu. Nadal używany jest stary.',
    '{name} has not answered their yearly reconfirmation. Check their address still works.' => '{name} nie odpowiada na coroczną prośbę o potwierdzenie. Sprawdź, czy ten adres nadal działa.',
    'The message for {name} still holds starter text in square brackets.' => 'Wiadomość dla: {name} nadal zawiera tekst startowy w nawiasach kwadratowych.',
    'Your yearly review is due: confirm each contact still has what they need.' => 'Czas na coroczny przegląd: upewnij się, że każdy kontakt nadal ma to, czego potrzebuje.',
    'This install is not using HTTPS. Links in its emails can be read in transit.' => 'Ta instalacja nie używa HTTPS. Linki w jej e-mailach można przechwycić po drodze.',

    // Engine: message previews
    'When you miss a check-in and its reminders' => 'Gdy pominiesz potwierdzenie i przypomnienia',
    'When the switch fires' => 'Gdy przełącznik się uruchomi',
];
