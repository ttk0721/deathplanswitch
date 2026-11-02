# Jak powstają wydania

Każdy pull request scalony do gałęzi `main` tego forka kończy się przetestowanym, ponumerowanym wydaniem. Nikt nie musi ręcznie tagować ani budować.

## Numer wersji

Wersje mają postać `vMAJOR.MINOR.PATCH`. Numer zależy od scalonego pull requesta:

1. **Etykieta wygrywa.** Jeśli PR ma etykietę `release:major`, `release:minor`, `release:patch` albo `release:skip`, workflow robi dokładnie to. `release:skip` oznacza brak wydania.
2. **Bez etykiety decyduje tytuł:**
   - `typ!:` w tytule (na przykład `feat!: nowy format danych`) albo `BREAKING CHANGE` w opisie podnosi **major**,
   - `feat:` podnosi **minor**,
   - cokolwiek innego (`fix:`, `docs:`, `chore:` i reszta) podnosi **patch**.

Numer liczy się od ostatniego tagu. Gdy tagów jeszcze nie ma, punktem wyjścia jest plik `VERSION` w katalogu głównym repozytorium.

## Co zawiera wydanie

Obraz Dockera `ghcr.io/ttk0721/deathplanswitch` na linux/amd64 i linux/arm64, z tagami:

- `X.Y.Z`, czyli dokładna wersja,
- `X.Y`, czyli najnowsza łatka tej linii,
- `latest`, czyli najnowsze wydanie.

Każdy push do `main` publikuje też obraz z tagami `edge` i `sha-…`, do prób. Na co dzień przypnij numer wersji.

Pliki do pobrania, każdy z platformą w nazwie:

| Plik | Dla kogo |
|---|---|
| `deathplanswitch-X.Y.Z-docker-compose.zip` | Docker, Synology, Portainer, TrueNAS, Unraid, CasaOS |
| `deathplanswitch-X.Y.Z-linux-debian-ubuntu.deb` | Debian, Ubuntu, Mint, Raspberry Pi OS |
| `deathplanswitch-X.Y.Z-linux-fedora-rhel-suse.rpm` | Fedora, RHEL, Alma, Rocky, openSUSE |
| `deathplanswitch-X.Y.Z-linux-arch.pkg.tar.zst` | Arch, Manjaro, EndeavourOS |
| `deathplanswitch-X.Y.Z-linux-alpine.apk` | Alpine |
| `deathplanswitch-X.Y.Z-linux-other.tar.gz` | inne systemy Linux |
| `deathplanswitch-X.Y.Z-freebsd.tar.gz` | FreeBSD |
| `deathplanswitch-X.Y.Z-macos.tar.gz` | macOS |
| `deathplanswitch-X.Y.Z-windows.zip` | Windows 10 i 11, Windows Server |
| `deathplanswitch-X.Y.Z-solaris-illumos.tar.gz` | Solaris, OpenIndiana, OmniOS |
| `SHA256SUMS` | sumy kontrolne wszystkich plików |

Opis wydania zaczyna się tabelą "Który plik potrzebuję?" z odnośnikami do [instalacji](instalacja.md). Pod nią workflow dopisuje listę scalonych pull requestów.

Przed publikacją workflow uruchamia testy. Jeśli któryś nie przejdzie, wydanie nie powstaje. Tag powstaje po testach, przed obrazem. Wydanie jest szkicem, dopóki nie ma obrazów i wszystkich plików. Powtórzone uruchomienie na tym samym commicie dokończy pracę, a nie założy drugiej wersji.

## Wydanie ręczne

Użyj go, gdy chcesz wydać coś poza zwykłym scaleniem PR.

1. Na GitHubie otwórz **Actions** i wybierz workflow **Release**.
2. Kliknij **Run workflow** i wybierz gałąź `main`.
3. W polu `bump` wpisz `major`, `minor` albo `patch`.
4. Uruchom. Workflow wyliczy numer od ostatniego tagu, przetestuje, zbuduje i opublikuje wydanie tak samo jak po scaleniu PR.

Z wiersza poleceń: `gh workflow run release.yml -f bump=patch`.

## Dwa PR-y scalone jeden po drugim

Gdy dwa pull requesty trafią do `main` w krótkim odstępie, GitHub trzyma w kolejce tylko najnowsze oczekujące uruchomienie wydania. Starsze, które jeszcze nie ruszyło, jest pomijane. Powstaje wtedy jedno wydanie za oba PR-y. Jego opis nadal wymienia oba, ale wielkość podbicia wersji (major, minor albo patch) wynika z nowszego PR-a. Jeśli potrzebujesz osobnej wersji dla każdego, uruchom drugie wydanie ręcznie (patrz wyżej).

## Gdy wydanie nie powstało

- PR miał etykietę `release:skip`. Uruchom wydanie ręcznie, jeśli to pomyłka.
- Testy nie przeszły. Sprawdź log workflow, popraw błąd i scal poprawkę. Ona wyda wersję.
- Brakuje pliku w wydaniu. Sprawdź log kroku budowania i uruchom workflow ponownie ręcznie.
