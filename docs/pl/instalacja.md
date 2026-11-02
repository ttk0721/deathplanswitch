# Instalacja Death Plan Switch

Przełącznik działa na wszystkim, co uruchomi PHP 8.1 lub nowszy: w Dockerze albo jako zwykłe usługi systemu. Ten poradnik pokazuje oba sposoby. Każde wydanie ma osobny plik dla każdej platformy, więc nie musisz niczego budować.

## Który plik pobrać?

Pliki są na stronie [wydań](https://github.com/ttk0721/deathplanswitch/releases/latest). W nazwie każdego jest platforma, dla której go zrobiono (`X.Y.Z` to numer wersji).

| Plik | Dla kogo | Sekcja |
|---|---|---|
| `deathplanswitch-X.Y.Z-docker-compose.zip` | Docker, Synology Container Manager, Portainer, TrueNAS, Unraid, CasaOS | [Docker](#docker) |
| `deathplanswitch-X.Y.Z-linux-debian-ubuntu.deb` | Debian, Ubuntu, Mint, Raspberry Pi OS | [Debian i Ubuntu](#deb) |
| `deathplanswitch-X.Y.Z-linux-fedora-rhel-suse.rpm` | Fedora, RHEL, Alma, Rocky, openSUSE | [Fedora, RHEL i openSUSE](#rpm) |
| `deathplanswitch-X.Y.Z-linux-arch.pkg.tar.zst` | Arch, Manjaro, EndeavourOS | [Arch](#arch) |
| `deathplanswitch-X.Y.Z-linux-alpine.apk` | Alpine | [Alpine](#alpine) |
| `deathplanswitch-X.Y.Z-linux-other.tar.gz` | inne systemy Linux | [Inne linuksy](#other) |
| `deathplanswitch-X.Y.Z-freebsd.tar.gz` | FreeBSD | [FreeBSD](#freebsd) |
| `deathplanswitch-X.Y.Z-macos.tar.gz` | macOS | [macOS](#macos) |
| `deathplanswitch-X.Y.Z-windows.zip` | Windows 10 i 11, Windows Server | [Windows](#windows) |
| `deathplanswitch-X.Y.Z-solaris-illumos.tar.gz` | Solaris, OpenIndiana, OmniOS | [Solaris i illumos](#solaris) |
| `SHA256SUMS` | wszyscy | [Sprawdzenie pliku](#sha256) |

## Co dostajesz po instalacji

Bez Dockera przełącznik działa jako dwie usługi. Startują razem z systemem i wstają same po awarii:

- **web** udostępnia stronę. Uruchamia wbudowany serwer PHP (`php -S`) z plikiem `bin/router.php`, który blokuje prywatne ścieżki (`data/`, `app/`, pliki `.json`, `.log` i podobne). Nie potrzebujesz Apache ani nginx.
- **scheduler** odlicza kroki przełącznika (`php bin/scheduler.php`) i pinguje opcjonalny `HEARTBEAT_URL`.

Obie usługi działają na dedykowanym koncie bez uprawnień (w Windows jako LOCAL SERVICE). Domyślnie strona słucha tylko na `127.0.0.1:8080`. Żeby udostępnić ją na zewnątrz, postaw przed nią HTTPS, zgodnie z [ostatnią sekcją](#https-przed-przełącznikiem).

Gdzie co leży:

| System | Aplikacja | Dane | Ustawienia |
|---|---|---|---|
| Linux | `/usr/share/deathplanswitch` | `/var/lib/deathplanswitch` | `/etc/default/deathplanswitch` |
| FreeBSD | `/usr/local/share/deathplanswitch` | `/var/db/deathplanswitch` | `/etc/rc.conf` |
| macOS | `/usr/local/share/deathplanswitch` (albo `/Library/DeathPlanSwitch`, zob. niżej) | `/var/db/deathplanswitch` | pliki `.plist` usług |
| Windows | `%ProgramFiles%\DeathPlanSwitch` | `%ProgramData%\DeathPlanSwitch` | zadania harmonogramu |

Zachowaj tylko folder z danymi. Aktualizacja podmienia aplikację i nie rusza danych.

Po instalacji otwórz `http://127.0.0.1:8080/install.php` i przejdź instalator w przeglądarce. Z innego komputera użyj adresu serwera, o ile ustawisz `DPS_LISTEN` inaczej niż domyślnie.

<a id="docker"></a>

## Docker

Pasuje do Synology, Portainera, TrueNAS-a, Unraida, CasaOS i zwykłego serwera z Dockerem.

1. Pobierz `deathplanswitch-X.Y.Z-docker-compose.zip` i rozpakuj do własnego folderu.
2. Skopiuj `.env.example` jako `.env` i uzupełnij. Plik `docker-compose.yml` wskazuje już obraz tej wersji (`ghcr.io/ttk0721/deathplanswitch:X.Y.Z`).
3. Uruchom `docker compose up -d`.
4. Otwórz `http://ADRES:8080/install.php`.

Obraz działa na linux/amd64 i linux/arm64 (Raspberry Pi także). Dane trafiają do podfolderu `data/` obok pliku compose.

Aktualizacja: zmień numer w `DPS_IMAGE` (albo wgraj nowy plik compose) i uruchom `docker compose pull && docker compose up -d`.

Szczegóły dla Synology i Raspberry Pi, w tym zasilanie awaryjne i monitoring: [synology.md](synology.md).

<a id="deb"></a>

## Debian i Ubuntu (.deb)

```
sudo apt install ./deathplanswitch-X.Y.Z-linux-debian-ubuntu.deb
```

Pakiet instaluje wymagane pakiety PHP, tworzy konto `deathplanswitch`, włącza i uruchamia usługi `deathplanswitch-web` i `deathplanswitch-scheduler` (systemd). Ustawienia są w `/etc/default/deathplanswitch`:

```
DPS_DATA=/var/lib/deathplanswitch
DPS_LISTEN=127.0.0.1:8080
HEARTBEAT_URL=
DPS_APPRISE_URL=
PHP_CLI_SERVER_WORKERS=4
```

Po zmianie ustawień uruchom usługi ponownie:

```
sudo systemctl restart deathplanswitch-web deathplanswitch-scheduler
```

Stan i logi sprawdzisz przez `systemctl status deathplanswitch-web` oraz `journalctl -u deathplanswitch-scheduler`. Aktualizacja to ta sama komenda `apt install` z nowym plikiem. Usunięcie pakietu (`sudo apt remove deathplanswitch`) zostawia dane.

<a id="rpm"></a>

## Fedora, RHEL i openSUSE (.rpm)

```
sudo dnf install ./deathplanswitch-X.Y.Z-linux-fedora-rhel-suse.rpm
```

W openSUSE użyj `sudo zypper install ./plik.rpm`. Reszta jak w Debianie: te same usługi systemd, ten sam plik `/etc/default/deathplanswitch`, te same ścieżki. Aktualizacja to ta sama komenda z nowym plikiem.

<a id="arch"></a>

## Arch Linux, Manjaro, EndeavourOS (.pkg.tar.zst)

```
sudo pacman -U ./deathplanswitch-X.Y.Z-linux-arch.pkg.tar.zst
```

Pakiet sam dociągnie `php` (8.1 lub nowszy) i `php-sodium`. Rozszerzenie sodium jest na Archu domyślnie wyłączone, więc pakiet dodaje plik `/etc/php/conf.d/deathplanswitch-sodium.ini`, który je włącza. Jeśli włączysz sodium sam w `php.ini`, usuń ten plik.

Usługi systemd, plik `/etc/default/deathplanswitch` i ścieżki są takie same jak w pakiecie `.deb`. Pakiet nie jest podpisany, więc sprawdź go przez `SHA256SUMS`. Jeśli systemd działa, pakiet od razu włącza i uruchamia usługi. Jeśli nie, wypisze, co zrobić ręcznie. Aktualizacja to ta sama komenda z nowym plikiem.

<a id="alpine"></a>

## Alpine (.apk)

```
apk add --allow-untrusted ./deathplanswitch-X.Y.Z-linux-alpine.apk
```

Opcja `--allow-untrusted` jest potrzebna, bo pakiet nie jest podpisany kluczem Alpine. Zamiast podpisu sprawdź plik przez `SHA256SUMS`. Pakiet sam dociągnie `php83` i potrzebne rozszerzenia.

Usługi działają pod OpenRC (`deathplanswitch-web` i `deathplanswitch-scheduler`). Jeśli OpenRC działa, pakiet dodaje je do poziomu `default` i uruchamia. Jeśli nie, wypisze komendy `rc-update add` i `rc-service ... start` do wykonania ręcznie. Ustawienia są w `/etc/default/deathplanswitch`, dane w `/var/lib/deathplanswitch`, logi w `/var/log/deathplanswitch-web.log` i `/var/log/deathplanswitch-scheduler.log`. Aktualizacja to ta sama komenda z nowym plikiem.

<a id="other"></a>

## Inne linuksy (archiwum i install.sh)

Ten plik jest dla systemów, które nie mają własnego pakietu powyżej. Potrzebujesz PHP 8.1+ z rozszerzeniami curl, mbstring, openssl i sodium.

```
tar xzf deathplanswitch-X.Y.Z-linux-other.tar.gz
cd deathplanswitch-X.Y.Z
sudo ./install.sh
```

Skrypt rozpoznaje systemd lub OpenRC, tworzy konto i foldery, kopiuje aplikację i włącza usługi. Przy aktualizacji uruchom go ponownie: nie psuje istniejących danych ani ustawień. Układ folderów i ustawienia są takie same jak w pakietach `.deb` i `.rpm`.

<a id="freebsd"></a>

## FreeBSD

```
pkg install php83 php83-curl php83-mbstring php83-sodium php83-session php83-ctype php83-filter php83-posix php83-pcntl php83-tokenizer
tar xzf deathplanswitch-X.Y.Z-freebsd.tar.gz
cd deathplanswitch-X.Y.Z
sudo ./install.sh
```

Skrypt tworzy użytkownika `deathplanswitch` (przez `pw`), kopiuje aplikację do `/usr/local/share/deathplanswitch`, przygotowuje dane w `/var/db/deathplanswitch` i włącza usługi `deathplanswitch_web` oraz `deathplanswitch_scheduler` przez `sysrc`. Ustawienia trafiają do `/etc/rc.conf`:

```
deathplanswitch_web_enable="YES"
deathplanswitch_web_listen="127.0.0.1:8080"
deathplanswitch_web_data="/var/db/deathplanswitch"
deathplanswitch_scheduler_enable="YES"
```

Skrypt zapisuje też w `rc.conf` bezwzględną ścieżkę do `php` (`deathplanswitch_web_php` i `deathplanswitch_scheduler_php`). Przy ponownym uruchomieniu zachowuje twój adres `deathplanswitch_web_listen`.

Wartości z `rc.conf` trafiają do polecenia startowego, więc są sprawdzane i skrypt odmawia startu z czytelnym komunikatem, gdy coś jest nie tak. Adres `deathplanswitch_web_listen` musi mieć postać `host:port` (litery, cyfry, `.`, `:`, `_`, `-`). Adresy `deathplanswitch_scheduler_heartbeat_url` i `deathplanswitch_scheduler_apprise_url` muszą zaczynać się od `http://` lub `https://` i nie mogą zawierać spacji, cudzysłowów, nawiasów ani znaków `&`, `;`, `|`, `$`, `\` i odwrotnego apostrofu. Ten sam test robi `install.sh`, zanim cokolwiek zapisze. Konto usługi ustawia `deathplanswitch_web_runas` (i `deathplanswitch_scheduler_runas`), domyślnie `deathplanswitch`. Nie używaj nazwy `_user`: rc.subr uruchamiałby wtedy polecenie przez `su`, a `daemon` zrzuca uprawnienia sam.

Ręczny start: `service deathplanswitch_web start` i `service deathplanswitch_scheduler start`.

Usunięcie: `sudo ./install.sh uninstall`. Dane zostają w `/var/db/deathplanswitch`; z opcją `--remove-data` są usuwane razem z użytkownikiem.

<a id="macos"></a>

## macOS

Usługi w tle działają przez launchd, który jest częścią systemu. Nie potrzebujesz App Store ani konta Apple.

1. Zainstaluj PHP z [Homebrew](https://brew.sh): `brew install php`.
2. Rozpakuj archiwum i uruchom skrypt:

```
tar xzf deathplanswitch-X.Y.Z-macos.tar.gz
cd deathplanswitch-X.Y.Z
sudo ./install.sh
```

Skrypt kopiuje aplikację do `/usr/local/share/deathplanswitch` (własność roota), trzyma dane w `/var/db/deathplanswitch` (dostęp tylko dla konta usługi, `0700`), tworzy rolę `_deathplanswitch` i rejestruje dwie usługi launchd: `pl.ttk0721.deathplanswitch.web` oraz `pl.ttk0721.deathplanswitch.scheduler`. Gdy utworzenie roli się nie uda, uruchamia usługi na koncie, z którego użyto `sudo` (nigdy jako root), i mówi o tym. Usługi startują razem z komputerem, jeszcze przed zalogowaniem, i wstają same po awarii.

Logi trafiają do `/var/log/deathplanswitch` (katalog roota, pliki `web.log`, `web.err`, `scheduler.log` i `scheduler.err` należą do konta usługi, tryb `0640`). Dzięki temu konto usługi nie może podmienić pliku logu na dowiązanie do innego pliku.

Na Macu z Intelem Homebrew oddaje `/usr/local/share` swojemu użytkownikowi. Wtedy skrypt nie kopiuje tam aplikacji, tylko do `/Library/DeathPlanSwitch`, i mówi o tym. Skrypt odmawia też pracy, gdy któraś z docelowych ścieżek jest dowiązaniem symbolicznym albo gdy katalog nadrzędny nie należy do roota lub zapisuje go ktoś inny. Sprawdzenie wersji PHP i rozszerzeń idzie z konta usługi (a przed jego utworzeniem z konta `nobody`), bo plik `php` z Homebrew należy do zwykłego użytkownika i root go nie uruchamia.

Usunięcie: `sudo ./install.sh uninstall`. Dane zostają w `/var/db/deathplanswitch`; z opcją `--remove-data` są usuwane razem z rolą. Skrypt kasuje tylko te stałe katalogi i nie idzie za dowiązaniami. Dane z wcześniejszej wersji w `/usr/local/var/deathplanswitch` nie są używane; skopiuj je do `/var/db/deathplanswitch` ręcznie, jeśli ich potrzebujesz.

Stan sprawdzisz poleceniem `sudo launchctl print system/pl.ttk0721.deathplanswitch.web`. Żeby Mac wstawał po awarii prądu, włącz w ustawieniach systemu automatyczny start po utracie zasilania (komputery stacjonarne) i wyłącz usypianie.

<a id="windows"></a>

## Windows

1. Zainstaluj PHP 8.1 lub nowszy, na przykład `winget install PHP.PHP.8.4`. W `php.ini` włącz rozszerzenia `curl`, `mbstring`, `openssl` i `sodium`.

   PHP musi leżeć w folderze, który mogą zmieniać tylko administratorzy. Kto podmieni `php.exe`, bibliotekę DLL obok niego albo sam folder (także folder nadrzędny), przejmie usługę. Instalator sprawdza to przed uruchomieniem PHP i odmawia, gdy zapis ma ktokolwiek poza SYSTEM, Administratorami, TrustedInstaller i CREATOR OWNER. Folder taki jak `C:\php` dziedziczy zapis dla Authenticated Users z `C:\`, a instalacja `winget` dla jednego użytkownika ląduje w jego profilu, więc oba przypadki zostaną odrzucone. Najprościej zainstalować PHP w `%ProgramFiles%\PHP`. Albo popraw uprawnienia w PowerShellu jako administrator (podmień `C:\php` na swój folder):

   ```
   icacls "C:\php" /setowner *S-1-5-32-544 /T /C /L
   icacls "C:\php" /reset /T /C /L
   icacls "C:\php" /inheritance:r /grant:r *S-1-5-18:(OI)(CI)F *S-1-5-32-544:(OI)(CI)F *S-1-5-32-545:(OI)(CI)RX
   ```

   Gdy instalator wskaże folder nadrzędny (na przykład `C:\tools`), wykonaj na nim pierwsze polecenie (bez `/T /C /L`) i ostatnie. Zwykli użytkownicy zachowują odczyt i uruchamianie, którego potrzebuje konto usługi.
2. Rozpakuj `deathplanswitch-X.Y.Z-windows.zip`.
3. Otwórz PowerShell **jako administrator** w rozpakowanym folderze i uruchom:

```
.\install.ps1
```

Gdy `php.exe` nie leży w PATH, wskaż go: `.\install.ps1 -PhpPath "C:\php\php.exe"`. Inny adres i port ustawisz przez `-Listen`, na przykład `-Listen 127.0.0.1:9090` (domyślnie `127.0.0.1:8080`).

Skrypt sprawdza wersję PHP i rozszerzenia, kopiuje aplikację do `%ProgramFiles%\DeathPlanSwitch`, tworzy `%ProgramData%\DeathPlanSwitch` (administratorzy i SYSTEM: pełny dostęp, konto LOCAL SERVICE: zmiana, nikt więcej) i rejestruje dwa zadania harmonogramu: "DeathPlanSwitch Web" oraz "DeathPlanSwitch Scheduler". Startują razem z systemem na koncie `NT AUTHORITY\LOCAL SERVICE` (nie SYSTEM), które ma w folderze programu tylko odczyt i zapisuje wyłącznie w folderze danych. Działają bez limitu czasu, i wznawiają się co minutę po awarii. Na końcu skrypt pokazuje adres.

Istniejący folder `%ProgramData%\DeathPlanSwitch` instalator użyje tylko wtedy, gdy należy do SYSTEM lub administratorów i nie ma w nim dowiązań (junction, link symboliczny) ani plików innych właścicieli (konto LOCAL SERVICE jest dozwolone). W przeciwnym razie odmawia: sprawdź zawartość, usuń folder i uruchom skrypt ponownie. Odinstalowanie też zatrzyma się na takim dowiązaniu i nigdy nie idzie za nim.

Usunięcie: `.\uninstall.ps1`. Dane zostają w `%ProgramData%\DeathPlanSwitch`; z opcją `-RemoveData` są usuwane. Opis opcji jest w `README.txt` w archiwum.

<a id="solaris"></a>

## Solaris i illumos

Dotyczy Solarisa, OpenIndiana i OmniOS. **Uwaga:** ta ścieżka to ogólne kroki, których **nie testujemy w CI**. Wypróbuj ją na kopii, zanim zaufasz jej przełącznikowi. Te same kroki są w pliku `INSTALL-solaris.txt` w archiwum.

1. Zainstaluj PHP 8.1+ z rozszerzeniami curl, mbstring, openssl i sodium. Weź je z [OpenCSW](https://www.opencsw.org) albo z pkgsrc (`pkgin install php83`). Na OmniOS i OpenIndiana PHP jest też w repozytoriach systemu.
2. Rozpakuj `deathplanswitch-X.Y.Z-solaris-illumos.tar.gz` do `/opt/deathplanswitch`.
3. Utwórz konto i folder danych:

```
useradd -d /var/lib/deathplanswitch -m deathplanswitch
```

4. Uruchom obie usługi na tym koncie:

```
DPS_DATA=/var/lib/deathplanswitch \
  php -S 127.0.0.1:8080 -t /opt/deathplanswitch /opt/deathplanswitch/bin/router.php

DPS_DATA=/var/lib/deathplanswitch php /opt/deathplanswitch/bin/scheduler.php
```

5. Żeby startowały z systemem, zrób z nich usługi SMF: napisz manifest z metodą `start` i polityką restartu, a potem `svccfg import`. Awaryjnie dodaj wpisy `@reboot` w crontabie konta `deathplanswitch`. Sam `@reboot` nie wznowi procesu po awarii, więc SMF jest lepszy.

## HTTPS przed przełącznikiem

Linki w mailach muszą działać spoza domu, a hasło nie może płynąć otwartym tekstem. Zostaw `DPS_LISTEN=127.0.0.1:8080` i postaw przed nim jedno z poniższych. W przykładach zamień `deadman.example.pl` na własną domenę.

**Caddy** (certyfikat Let's Encrypt wystawia się i odnawia sam):

```
deadman.example.pl {
    reverse_proxy 127.0.0.1:8080
}
```

**nginx** (certyfikat na przykład z certbota):

```nginx
server {
    listen 443 ssl;
    server_name deadman.example.pl;
    ssl_certificate     /etc/letsencrypt/live/deadman.example.pl/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/deadman.example.pl/privkey.pem;

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-For $remote_addr;
        proxy_set_header X-Forwarded-Proto https;
    }
}
```

**Cloudflare Tunnel** (nie otwiera portów w routerze). Utwórz tunel w panelu Cloudflare Zero Trust, dodaj nazwę publiczną `deadman.example.pl` i wskaż usługę `http://127.0.0.1:8080`. Wystarczy, że `cloudflared` działa na tym samym komputerze.

Aplikacja ufa nagłówkowi `X-Forwarded-For` tylko z sieci prywatnych, więc proxy na tym samym komputerze działa poprawnie.

<a id="sha256"></a>

## Sprawdzenie pobranego pliku

Pobierz `SHA256SUMS` razem z plikiem i porównaj sumę:

```
sha256sum -c SHA256SUMS --ignore-missing
```

Na macOS użyj `shasum -a 256 -c SHA256SUMS`, a w Windows `Get-FileHash plik -Algorithm SHA256`.

## Kopia zapasowa

Kopiuj folder z danymi (tabela wyżej), najlepiej codziennie, na inny nośnik. Bez niego po awarii dysku tracisz konfigurację i stan przełącznika.

## Dalej

- Monitoring z zewnątrz i próba generalna: [synology.md](synology.md), sekcje 5 i 8.
- Jak powstają wydania: [wydania.md](wydania.md).
