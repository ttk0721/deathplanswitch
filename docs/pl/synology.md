# Death Plan Switch na Synology (i Raspberry Pi)

Przełącznik ma sens tylko wtedy, gdy działa nieprzerwanie. Ten poradnik ustawia go tak, żeby sam wstał po awarii prądu i żeby ktoś z zewnątrz zauważył, gdy przestanie działać.

## 1. Projekt w Container Manager

1. W File Station utwórz folder `/volume1/docker/deadman`.
2. Wgraj do niego `docker-compose.yml` oraz plik `.env` (skopiuj `.env.example` i uzupełnij).
3. W Container Manager otwórz sekcję **Projekt**, wybierz utworzenie projektu, wskaż folder `/volume1/docker/deadman` i użyj istniejącego pliku compose.
4. Uruchom projekt. Powstaną dwa kontenery: `web` i `scheduler`.
5. Otwórz `http://ADRES-NAS:8080/install.php` i przejdź instalację.

Dane trafiają do podfolderu `data/` obok pliku compose. To jedyne, co trzeba zachować.

## 2. Powrót po awarii prądu

- W compose obie usługi mają `restart: unless-stopped`, więc Docker uruchomi je sam po starcie NAS-a. Nie zatrzymuj ich ręcznie na stałe.
- W ustawieniach DSM (panel sterowania, część o sprzęcie i zasilaniu) włącz opcję uruchamiania urządzenia automatycznie po przywróceniu zasilania. Nazwa w Twojej wersji DSM może brzmieć nieco inaczej: "Sprzęt i zasilanie → Uruchom ponownie automatycznie po przywróceniu zasilania".

## 3. UPS

Podłącz NAS do UPS-a i w ustawieniach UPS w DSM włącz obsługę UPS. Ustaw, po jakim czasie na zasilaniu bateryjnym NAS ma się bezpiecznie wyłączyć (ustaw tak, by bateria na pewno wystarczyła na zamknięcie systemu) oraz tryb bezpieczny, jeśli DSM go oferuje. Po powrocie prądu NAS wstanie sam dzięki punktowi 2.

## 4. Obraz i aktualizacje

NAS używa gotowego, opublikowanego obrazu `ghcr.io/ttk0721/deathplanswitch:latest` i niczego nie buduje. Dlatego do folderu wgrywasz tylko `docker-compose.yml` i `.env`. Żeby zaktualizować, pobierz nowy obraz i uruchom projekt ponownie (w Container Manager: pobierz obraz projektu, albo `docker compose pull && docker compose up -d`).

**Konkretna wersja.** Żeby przypiąć wersję zamiast `latest`, dopisz w `.env` zmienną `DPS_IMAGE`, na przykład `DPS_IMAGE=ghcr.io/ttk0721/deathplanswitch:sha-abc1234`, i zmieniaj ją ręcznie.

**Zanim CI opublikuje pierwszy obraz** (publikuje go po zmianach na gałęzi `main`), zbuduj obraz sam:
- Na komputerze z kodem źródłowym: `docker compose -f docker-compose.yml -f docker-compose.build.yml build`. Potem wyślij obraz do rejestru (otaguj go i użyj `docker push`) i wpisz ten tag w `DPS_IMAGE` w `.env` na NAS-ie.
- Na Raspberry Pi zbuduj w miejscu, w sklonowanym repozytorium: `docker compose -f docker-compose.yml -f docker-compose.build.yml up -d --build`.
- Na Synology możesz też sklonować repozytorium na NAS i użyć tych samych dwóch plików compose (`docker-compose.yml` i `docker-compose.build.yml`). Obraz nazywa się wtedy `dps-local:dev`.

## 5. Monitoring od zewnątrz

Dwa niezależne sygnały:

**Uptime Kuma** sprawdza, czy strona odpowiada. Dodaj monitor HTTP na adres `health.php?key=…`. Pełny adres z kluczem znajdziesz w aplikacji, w Ustawieniach.

**Healthchecks.io** sprawdza, czy działa scheduler:
1. Utwórz check z okresem 15 minut i okresem karencji (grace) 30 minut.
2. Skopiuj jego adres ping do `HEARTBEAT_URL` w pliku `.env`.
3. Uruchom projekt ponownie. Scheduler pinguje ten adres po każdym cyklu, a przy problemach adres z końcówką `/fail`. Gdy pingi ustaną, Healthchecks.io wyśle alarm.

## 6. Dostęp z internetu: deadman.ttk0721.pl

Linki z maili muszą działać spoza domu.

- **Cloudflare Tunnel (zalecane):** nie otwiera portów w routerze. Utwórz tunel, dodaj nazwę publiczną `deadman.ttk0721.pl` kierującą na `http://ADRES-NAS:8080`. Kontener `cloudflared` możesz dodać do tego samego projektu albo uruchomić osobno.
- **Reverse proxy w DSM z Let's Encrypt:** w ustawieniach portalu logowania dodaj regułę reverse proxy z HTTPS na `deadman.ttk0721.pl` do `localhost:8080` i wystaw certyfikat Let's Encrypt. Wymaga przekierowania portów 80 i 443 w routerze.

Aplikacja ufa nagłówkowi `X-Forwarded-For` tylko z sieci prywatnych, więc proxy lub tunel w Twojej sieci domowej działa poprawnie.

## 7. Kopia zapasowa

Zrób w Hyper Backup zadanie kopiujące folder `/volume1/docker/deadman/data` (najlepiej cały folder `deadman`) na inny nośnik lub do chmury, codziennie. Bez tego folderu po awarii dysku tracisz konfigurację i stan przełącznika.

## 8. Próba generalna

Możesz przesunąć czas i zobaczyć, jak przełącznik reaguje, bez czekania tygodniami. Obraz nie zawiera folderu `tests/`, więc skopiuj go najpierw do kontenera:

```
docker compose cp tests scheduler:/var/www/html/tests
docker compose exec -u www-data scheduler php tests/timeshift.php
```

Rób to na kopii danych, nie na produkcji, bo skrypt zmienia stan.

## 9. Raspberry Pi (arm64)

Ten sam `docker-compose.yml` działa na Raspberry Pi z 64-bitowym systemem. Zainstaluj Docker i Docker Compose, skopiuj `docker-compose.yml` i `.env` do folderu, wpisz `docker compose up -d` (gdy obraz jest już opublikowany; w przeciwnym razie zbuduj go w miejscu, patrz punkt 4). Obraz bazowy `php:8.4-apache` ma wersję arm64. Dla odporności na awarię prądu używaj dobrej karty lub dysku SSD, a Pi podłącz do UPS-a. Docker uruchamia się z systemem, więc `restart: unless-stopped` wystarczy.
