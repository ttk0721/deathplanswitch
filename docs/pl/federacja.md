# Kilka węzłów: ten sam przełącznik w dwóch lub trzech miejscach

Jeden NAS to jeden punkt awarii. Jeśli w domu padnie prąd albo internet, przełącznik milczy, a Ty nie masz jak o tym wiedzieć. Dlatego przełącznik może działać na kilku **węzłach**, na przykład: NAS w domu (główny), Raspberry Pi w innym pokoju i Raspberry Pi u przyjaciela.

## 1. Co robią węzły

- **Potwierdzenie obecności na jednym węźle liczy się na wszystkich.** Klikasz link w mailu lub przycisk na dowolnym węźle, a pozostałe dowiadują się o tym przy najbliższej synchronizacji. Synchronizacja odbywa się przed każdym przebiegiem harmonogramu (co `DPS_TICK_INTERVAL`, domyślnie 15 minut).
- **Każdy krok eskalacji jest wykonywany tylko raz.** Jeśli milczysz, jako pierwszy działa węzeł o najniższej randze, który jeszcze działa. Pozostałe widzą, że krok już wykonano, i go nie powtarzają. Nikt nie dostaje podwójnych maili.
- **Węzły pomocnicze (drugorzędne) kopiują ustawienia głównego.** Przejmują jego ustawienia, listę kontaktów i zaszyfrowany sejf, więc mogą działać samodzielnie, gdy główny zniknie. Własny adres, klucze do `tick.php` i `health.php` oraz datę instalacji każdy węzeł zachowuje u siebie.
- **Węzeł odcięty od wszystkich pozostałych nie działa pochopnie.** Najpoważniejsze kroki (weryfikacja u bliskiej osoby i uruchomienie przełącznika) czekają wtedy dłużej. Szczegóły w punkcie 6.

Ustawienia zmieniasz **tylko na węźle głównym**. Na pomocniczych widać u góry informację, że węzeł kopiuje główny.

## 2. Jak połączyć węzły

Najlepiej użyć **Tailscale**. Każdy węzeł dostaje stały adres w prywatnej sieci (zaczyna się od `100.`), nie trzeba otwierać portów w routerze, a ruch jest szyfrowany. Adresy typu `http://100.x.y.z:8080` są akceptowane.

Alternatywa: adresy **HTTPS** wystawione na świat, na przykład przez Cloudflare Tunnel. Zwykły `http://` jest dozwolony tylko w sieci prywatnej (10.x, 172.16–31.x, 192.168.x), przez Tailscale albo przy nazwach `.local` i `.ts.net`. Powód: wymiana między węzłami przenosi hasło poczty i skrót hasła, więc nie może iść jawnie przez internet.

Każde żądanie między węzłami jest podpisane kluczem węzła, więc obcy nic nie zrobi, nawet jeśli zna adres.

## 3. Konfiguracja krok po kroku

Zakładamy: węzeł główny to NAS (już działa), pomocniczy to nowe Raspberry Pi z zainstalowanym przełącznikiem.

1. **Na każdym węźle** (także na głównym) nadaj mu tożsamość:

   ```
   php cli/node.php init --name=nas
   ```

   W Dockerze: `docker compose exec web php cli/node.php init --name=nas`. Polecenie tworzy klucze w `data/node.json` i można je wykonać tylko raz.

2. **Na węźle pomocniczym** wypisz jego tożsamość i skopiuj ją:

   ```
   php cli/node.php show
   ```

3. **Na węźle pomocniczym** dołącz go do głównego. Podaj adres głównego i wklejoną tożsamość **głównego** (wypisz ją poleceniem `show` na NAS-ie):

   ```
   php cli/node.php join --primary-url=http://100.64.0.10:8080 --primary='{"id":"n_...","name":"nas",...}'
   ```

   Jeśli adres tego węzła nie jest ustawiony w Ustawieniach, dodaj `--url=http://100.64.0.11:8080`.

4. **Na węźle głównym** otwórz zakładkę **Nodes**. W sekcji "Add a node" wklej tożsamość węzła pomocniczego (z kroku 2), podaj jego adres i rangę, a potem zatwierdź. Zakładka pokazuje też, kiedy ostatnio słyszano każdy węzeł.

5. Po minucie węzeł pomocniczy skopiuje ustawienia, kontakty i sejf. W zakładce Nodes na głównym zobaczysz czas ostatniego kontaktu.

Trzeci węzeł dodajesz tak samo (kroki 1 do 4, ranga 2).

## 4. Ranga i opóźnienie kroków

Ranga mówi, kto działa pierwszy. Węzeł główny ma zwykle rangę 0, kolejne 1 i 2. Węzeł o randze N czeka z każdym krokiem N razy **opóźnienie między węzłami** (domyślnie 30 minut). Przykład: przypomnienie ma wyjść o 9:00. NAS (ranga 0) wyśle je o 9:00, Pi (ranga 1) dopiero o 9:30, a tylko jeśli NAS tego nie zrobił. Opóźnienie ustawisz w zakładce Nodes na głównym (od 15 do 240 minut).

## 5. Co się dzieje, gdy coś zawiedzie

- **NAS przestaje działać** (zepsuty dysk, wyłączony). Pozostałe węzły widzą brak odpowiedzi. Gdy przychodzi termin potwierdzenia, działa węzeł o następnej randze, bo miał kopię ustawień i kontaktów. Przełącznik idzie dalej, tylko z opóźnieniem rzędu rangi razy 30 minut.
- **Cały dom jest bez prądu lub internetu** (awaria, pożar). Węzeł u przyjaciela nadal działa i nadal widzi, że NAS milczy. Jeśli w tym czasie milczysz także Ty, przełącznik przechodzi przez kroki eskalacji bez Twojego domu. Pamiętaj, że taki węzeł wie tyle, ile skopiował z NAS-a przy ostatniej synchronizacji.
- **Jeden węzeł traci internet.** Taki węzeł nie wie, czy to inni zawiedli, czy to on. Dlatego pierwsze kroki (przypomnienia) wykonuje jak zwykle, a **poważne kroki** (weryfikacja u bliskiej osoby i uruchomienie przełącznika) odkłada o czas izolacji, domyślnie **48 godzin**, o ile przez ostatnie 6 godzin nie słyszał żadnego węzła. Jeśli w tym czasie internet wróci i inny węzeł już wszystko zrobił, nie robi nic podwójnie. Czas izolacji ustawisz w zakładce Nodes (od 12 do 168 godzin).

Przy tylko dwóch węzłach, gdy jeden z nich padnie, drugi uznaje się za odcięty. Opóźnia wtedy pytanie do bliskiej osoby i uruchomienie przełącznika, każde o czas izolacji (domyślnie 48 godzin, razem około czterech dni później). Możesz skrócić ten czas w zakładce Nodes (od 12 do 168 godzin) albo dodać trzeci węzeł.

## 6. Udziały klucza (K z N)

Sejf jest zaszyfrowany kluczem, który serwer wydaje dopiero po uruchomieniu przełącznika. Bez udziałów ten klucz leży w całości na węźle głównym, więc kto włamie się na ten jeden węzeł po uruchomieniu przełącznika, dostaje klucz.

Udziały klucza to temu zapobiegają. **N** to liczba węzłów, między które dzielony jest klucz. **K** to liczba udziałów potrzebnych do jego odtworzenia (ustawienie K jest na stronie Nodes). Każdy węzeł dostaje jeden udział, a udział jednego węzła nie ujawnia nic o kluczu. Dopiero K udziałów razem otwiera sejf. Udziały są szyfrowane w Twojej przeglądarce kluczem danego węzła, więc nawet węzeł główny nie widzi cudzych udziałów. Udział wychodzi z węzła dopiero wtedy, gdy przełącznik na tym węźle został uruchomiony.

**Adresy publiczne.** Bliska osoba otwiera sejf w przeglądarce, a ta zbiera udziały z K węzłów. Dlatego każdy węzeł musi mieć adres publiczny z **HTTPS**, np. osobną nazwę z Cloudflare Tunnel dla każdego węzła. Wpisz go w polu `public_url` węzła na stronie Nodes (bez nazwy użytkownika, bez znaku `?` i `#`). Węzły bez adresu publicznego nie mogą wydać udziału odbiorcy.

**Jak wybrać K.** Przykład: NAS, Pi w domu i Pi u przyjaciela.
- **K = 2.** Kradzież lub włamanie na jeden węzeł nic nie daje. Ale jeśli dom spłonie, zostaje tylko węzeł przyjaciela, czyli jeden udział, a sejf **pozostaje zamknięty**.
- Dlatego albo ustaw **K = 1** (wtedy każdy węzeł sam wystarcza, ale też jeden przejęty węzeł otwiera sejf), albo dodaj **czwarty węzeł w innym miejscu**, tak aby po utracie domu zostały co najmniej dwa.
- Zasada: K musi być mniejsze lub równe liczbie węzłów, które przetrwają najgorszy dla Ciebie scenariusz.

**Rozdawanie udziałów.** Na stronie Sejf, w sekcji z udziałami, kliknij **Rozdaj udziały klucza** (do czasu tłumaczenia interfejsu przycisk nazywa się "Distribute key shares"). Trzeba to zrobić po każdej zmianie węzłów, ich adresów lub wartości K. Strona ostrzega, gdy lista węzłów lub K zmieniły się po ostatnim rozdaniu.

**Klucz pełny znika.** Gdy tylko udziały zostaną zapisane, węzeł główny kasuje swój pełny klucz. Od tego momentu sejf da się otworzyć wyłącznie z K udziałów. Bez udziałów (jeden węzeł) sejf działa jak dotąd.

## 7. Gdy coś nie działa

- W zakładce **Nodes** na węźle głównym sprawdź "Last heard" przy każdym węźle. Puste pole lub stara data znaczy, że węzły się nie widzą (zły adres, zapora, wyłączony Tailscale).
- Zegary węzłów muszą się różnić o mniej niż 5 minut (żądania z innym czasem są odrzucane). Włącz automatyczną synchronizację czasu.
- Test ręczny: `php cli/sync.php` na węźle pomocniczym wypisuje, co udało się odczytać i skopiować. Brak odpowiedzi innego węzła nie przerywa pracy, tylko trafia do wydruku.
