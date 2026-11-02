#!/usr/bin/env bash
# Builds every release asset and checks names, contents, checksums and a real
# .deb install in debian:bookworm-slim, plus real Arch and Alpine installs.
# Runs from a temporary copy of the repo; install files that other packaging
# work has not delivered yet are replaced by placeholders there (never in the
# repo), and the install steps are then skipped.
set -u
cd "$(dirname "$0")/.." || exit 1
REPO="$PWD"
V="0.0.0-rc1"  # a suffix apk accepts (_rc1)

TMP="$(mktemp -d)"
cleanup() { rm -rf "$TMP"; }
trap cleanup EXIT

fail=0
ok() { echo "ok - $1"; }
bad() { echo "not ok - $1"; fail=1; }
check() { # check <description> <command...>
    local d="$1"; shift
    if "$@" >/dev/null 2>&1; then ok "$d"; else bad "$d"; fi
}

# Temporary copy (no .git, no data files).
COPY="$TMP/repo"
mkdir -p "$COPY"
git -C "$REPO" ls-files -z --cached --others --exclude-standard |
    while IFS= read -r -d '' f; do
        case "$f" in data/*.json) continue ;; esac
        [ -e "$REPO/$f" ] || [ -L "$REPO/$f" ] || continue
        printf '%s\0' "$f"
    done | tar -C "$REPO" --null -T - -cf - | tar -C "$COPY" -xf -

placeholders=0
ph() { # ph <path> : create a placeholder in the copy when the file is absent
    if [ ! -e "$COPY/$1" ]; then
        mkdir -p "$COPY/$(dirname "$1")"
        printf '#!/bin/sh\n# placeholder\n' > "$COPY/$1"
        placeholders=1
        echo "note - placeholder for $1"
    fi
}
for p in packaging/linux/install.sh packaging/linux/postinstall.sh packaging/linux/preremove.sh \
         packaging/linux/deathplanswitch-web.service packaging/linux/deathplanswitch-scheduler.service \
         packaging/linux/deathplanswitch.default packaging/openrc/deathplanswitch-web \
         packaging/openrc/deathplanswitch-scheduler packaging/freebsd/install.sh packaging/macos/install.sh \
         packaging/windows/install.ps1; do
    ph "$p"
done
[ -f "$COPY/bin/router.php" ] || { placeholders=1; echo "note - bin/router.php not present yet"; }

OUT="$TMP/out"
if bash "$COPY/packaging/build.sh" "$V" "$OUT" >"$TMP/build.log" 2>&1; then
    ok "build.sh finished"
else
    bad "build.sh finished"; cat "$TMP/build.log"; exit 1
fi

# 1. Exact file set and checksums.
expected="deathplanswitch-$V-docker-compose.zip
deathplanswitch-$V-freebsd.tar.gz
deathplanswitch-$V-linux-alpine.apk
deathplanswitch-$V-linux-arch.pkg.tar.zst
deathplanswitch-$V-linux-debian-ubuntu.deb
deathplanswitch-$V-linux-fedora-rhel-suse.rpm
deathplanswitch-$V-linux-other.tar.gz
deathplanswitch-$V-macos.tar.gz
deathplanswitch-$V-solaris-illumos.tar.gz
deathplanswitch-$V-windows.zip
SHA256SUMS"
actual="$(ls "$OUT" | LC_ALL=C sort)"
if [ "$actual" = "$(printf '%s\n' "$expected" | LC_ALL=C sort)" ]; then ok "exactly the 11 expected files"; else bad "file set: $actual"; fi
check "SHA256SUMS verifies" bash -c "cd '$OUT' && sha256sum -c SHA256SUMS"
check "SHA256SUMS lists 10 assets" test "$(wc -l < "$OUT/SHA256SUMS")" -eq 10

# 2. Contents.
lin="$(tar -tzf "$OUT/deathplanswitch-$V-linux-other.tar.gz")"
top="deathplanswitch-$V"
echo "$lin" | grep -q "^$top/" && ok "linux tarball unpacks into $top/" || bad "linux tarball top folder"
echo "$lin" | grep -Eq "^$top/(tests|\.github|docs|docker)(/|$)" && bad "linux tarball has tests/.github/docs/docker" || ok "linux tarball has no tests/.github/docs/docker"
echo "$lin" | grep -Eq "^$top/data/[^/]+\.json$" && bad "linux tarball has data/*.json" || ok "linux tarball has no data/*.json"
echo "$lin" | grep -E "/\.[^/]+/?$" | grep -vq "^$top/data/\.htaccess$" && bad "linux tarball has dotfiles" || ok "linux tarball has no dotfiles but data/.htaccess"
echo "$lin" | grep -q "^$top/data/\.htaccess$" && ok "linux tarball keeps data/.htaccess" || bad "data/.htaccess missing"
echo "$lin" | grep -q "^$top/install.sh$" && ok "linux tarball has install.sh at the top" || bad "install.sh at the top"
echo "$lin" | grep -q "^$top/packaging/openrc/" && ok "linux tarball has packaging/openrc" || bad "packaging/openrc"
echo "$lin" | grep -Eq "^$top/packaging/(windows|freebsd|macos)" && bad "linux tarball has other platforms" || ok "linux tarball has only its own platform"
ver="$(tar -xzOf "$OUT/deathplanswitch-$V-linux-other.tar.gz" "$top/VERSION")"
[ "$ver" = "$V" ] && ok "linux tarball VERSION is $V" || bad "VERSION is '$ver'"

win="$(unzip -Z1 "$OUT/deathplanswitch-$V-windows.zip")"
echo "$win" | grep -q "^$top/install.ps1$" && ok "windows zip has install.ps1 at the top" || bad "windows install.ps1 at the top"
echo "$win" | grep -q "packaging/linux" && bad "windows zip has packaging/linux" || ok "windows zip has no packaging/linux"

fb="$(tar -tzf "$OUT/deathplanswitch-$V-freebsd.tar.gz")"
echo "$fb" | grep -q "packaging/windows" && bad "freebsd tarball has packaging/windows" || ok "freebsd tarball has no packaging/windows"
echo "$fb" | grep -q "^$top/install.sh$" && ok "freebsd tarball has install.sh at the top" || bad "freebsd install.sh"

mac="$(tar -tzf "$OUT/deathplanswitch-$V-macos.tar.gz")"
echo "$mac" | grep -q "^$top/install.sh$" && ok "macos tarball has install.sh at the top" || bad "macos install.sh"

sol="$(tar -tzf "$OUT/deathplanswitch-$V-solaris-illumos.tar.gz")"
echo "$sol" | grep -q "^$top/INSTALL-solaris.txt$" && ok "solaris tarball has INSTALL-solaris.txt" || bad "INSTALL-solaris.txt"
echo "$sol" | grep -q "/packaging/" && bad "solaris tarball has packaging/" || ok "solaris tarball has no packaging/"

comp="$(unzip -p "$OUT/deathplanswitch-$V-docker-compose.zip" "$top/docker-compose.yml")"
echo "$comp" | grep -q "deathplanswitch:$V}" && ok "compose zip names :$V" || bad "compose image tag"
[ "$(unzip -Z1 "$OUT/deathplanswitch-$V-docker-compose.zip" | grep -c '\.php$')" -eq 0 ] && ok "compose zip holds no app code" || bad "compose zip holds php"
unzip -Z1 "$OUT/deathplanswitch-$V-docker-compose.zip" | grep -q "\.env\.example$" && ok "compose zip has .env.example" || bad ".env.example"

notes="$(bash "$COPY/packaging/release-notes.sh" "$V")"
missing=0
for f in $(printf '%s\n' "$expected" | grep -v SHA256SUMS); do
    echo "$notes" | grep -qF "$f" || { missing=1; echo "notes miss $f"; }
done
[ "$missing" -eq 0 ] && ok "release notes name every asset" || bad "release notes miss assets"

anchors="$(printf '%s\n' "$notes" | grep -o 'instalacja\.md#[A-Za-z0-9_-]*' | sed 's/.*#//' | sort -u)"
missing=0
[ -n "$anchors" ] || missing=1
for a in $anchors; do
    grep -qF "<a id=\"$a\"></a>" "$COPY/docs/pl/instalacja.md" || { missing=1; echo "guide misses anchor $a"; }
done
[ "$missing" -eq 0 ] && ok "every release-notes anchor exists in the guide" || bad "release-notes anchors missing in the guide"

# Package metadata and permissions (inspected without installing).
deb="$OUT/deathplanswitch-$V-linux-debian-ubuntu.deb"
if docker run --rm -v "$OUT:/out:ro" debian:bookworm-slim sh -c "dpkg-deb -c /out/${deb##*/}" > "$TMP/deb.list" 2>&1; then
    ok "deb is readable"
    grep -Eq "\./var/lib/deathplanswitch/$" "$TMP/deb.list" && ok "deb carries the data dir" || bad "deb data dir"
    grep -E "\./var/lib/deathplanswitch/$" "$TMP/deb.list" | grep -q '^drwxr-x---' && ok "data dir mode is 0750" || bad "data dir mode"
    grep -Eq '\.json$|/\.env' "$TMP/deb.list" && bad "deb holds .json or .env" || ok "deb holds no .json or .env"
    grep -q 'usr/share/deathplanswitch/app/' "$TMP/deb.list" && ok "deb carries the app" || bad "deb app"
else
    bad "deb is readable"; cat "$TMP/deb.list"
fi

# 3-4. Real install, only when every install file is the real one.
if [ "$placeholders" -ne 0 ]; then
    echo "skip - deb install in debian:bookworm-slim (install files or bin/router.php not delivered yet)"
else
    cat > "$TMP/inside.sh" <<'INSIDE'
set -eu
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq >/dev/null
apt-get install -y -qq /pkg.deb curl >/dev/null
for u in web scheduler; do
    test -f /lib/systemd/system/deathplanswitch-$u.service && echo "ok - unit $u installed" || { echo "not ok - unit $u"; exit 1; }
done
id deathplanswitch >/dev/null && echo "ok - user deathplanswitch exists" || { echo "not ok - user"; exit 1; }
owner="$(stat -c %U:%G /var/lib/deathplanswitch)"
[ "$owner" = "deathplanswitch:deathplanswitch" ] && echo "ok - data dir owned by $owner" || { echo "not ok - data dir owner $owner"; exit 1; }
smode="$(stat -c %a:%U:%G /etc/default/deathplanswitch)"
[ "$smode" = "600:root:root" ] && echo "ok - settings file is 0600 root:root" || { echo "not ok - settings file $smode"; exit 1; }
cmd="$(sed -n 's/^ExecStart=//p' /lib/systemd/system/deathplanswitch-web.service | head -1)"
set -a; . /etc/default/deathplanswitch; set +a
# Run the unit's command as the service user, in the background.
su -s /bin/sh deathplanswitch -c "exec $cmd" >/tmp/web.log 2>&1 &
listen="${DPS_LISTEN:-127.0.0.1:8080}"
i=0
while [ $i -lt 30 ]; do
    curl -s -o /dev/null "http://$listen/health.php" && break
    i=$((i+1)); sleep 1
done
c1="$(curl -s -o /dev/null -w '%{http_code}' "http://$listen/install.php")"
c2="$(curl -s -o /dev/null -w '%{http_code}' "http://$listen/app/x")"
[ "$c1" = 200 ] && echo "ok - /install.php 200" || { echo "not ok - /install.php $c1"; cat /tmp/web.log; exit 1; }
[ "$c2" = 403 ] && echo "ok - /app/x 403" || { echo "not ok - /app/x $c2"; exit 1; }
want_uid="$(id -u deathplanswitch)"
found=0
for d in /proc/[0-9]*; do
    [ -r "$d/cmdline" ] || continue
    args="$(tr '\0' ' ' < "$d/cmdline" 2>/dev/null || true)"
    argv0="${args%% *}"
    case "${argv0##*/}" in
        php|php[0-9]*) ;;
        *) continue ;;
    esac
    case " $args" in
        *" -S "*) ;;
        *) continue ;;
    esac
    found=$((found+1))
    uid="$(awk '/^Uid:/{print $2}' "$d/status" 2>/dev/null || true)"
    if [ "$uid" != "$want_uid" ]; then
        echo "not ok - php pid ${d#/proc/} runs as uid $uid, want $want_uid ($args)"; exit 1
    fi
done
[ "$found" -ge 1 ] && echo "ok - php runs as deathplanswitch (uid $want_uid, $found process(es))" || { echo "not ok - no php -S process found"; exit 1; }
for u in web scheduler; do
    f=/lib/systemd/system/deathplanswitch-$u.service
    grep -qx 'User=deathplanswitch' "$f" && grep -qx 'NoNewPrivileges=yes' "$f" \
        && echo "ok - unit $u has User=deathplanswitch and NoNewPrivileges=yes" \
        || { echo "not ok - unit $u missing User= or NoNewPrivileges="; exit 1; }
done
INSIDE
    if docker run --rm -v "$deb:/pkg.deb:ro" -v "$TMP/inside.sh:/inside.sh:ro" debian:bookworm-slim sh /inside.sh; then
        ok "deb install and serve"
    else
        bad "deb install and serve"
    fi
fi

# 5. Arch and Alpine: inspect, then install for real.
arch="$OUT/deathplanswitch-$V-linux-arch.pkg.tar.zst"
apk="$OUT/deathplanswitch-$V-linux-alpine.apk"
check "arch package is a zstd tar" bash -c "zstd -dc '$arch' | tar -t >/dev/null || docker run --rm -v '$OUT:/out:ro' archlinux:latest sh -c 'zstd -dc /out/${arch##*/} | tar -t'"
check "alpine package is a gzip tar" bash -c "tar -tzf '$apk'"

# Common checks, run inside the container after the package is installed.
# Expects $UNITCMD (the web service command line) and a login-less service user.
cat > "$TMP/common.sh" <<'COMMON'
id deathplanswitch >/dev/null && echo "ok - user deathplanswitch exists" || { echo "not ok - user"; exit 1; }
owner="$(stat -c %U:%G:%a /var/lib/deathplanswitch)"
[ "$owner" = "deathplanswitch:deathplanswitch:700" ] && echo "ok - data dir is $owner" || { echo "not ok - data dir $owner"; exit 1; }
smode="$(stat -c %a:%U:%G /etc/default/deathplanswitch)"
[ "$smode" = "600:root:root" ] && echo "ok - settings file is 0600 root:root" || { echo "not ok - settings file $smode"; exit 1; }
[ -n "${ENV_LOADED:-}" ] || { set -a; . /etc/default/deathplanswitch; set +a; }
su -s /bin/sh deathplanswitch -c "exec $UNITCMD" >/tmp/web.log 2>&1 &
listen="${DPS_LISTEN:-127.0.0.1:8080}"
i=0
while [ $i -lt 30 ]; do
    curl -s -o /dev/null "http://$listen/health.php" && break
    i=$((i+1)); sleep 1
done
c1="$(curl -s -o /dev/null -w '%{http_code}' "http://$listen/install.php")"
c2="$(curl -s -o /dev/null -w '%{http_code}' "http://$listen/app/x")"
[ "$c1" = 200 ] && echo "ok - /install.php 200" || { echo "not ok - /install.php $c1"; cat /tmp/web.log; exit 1; }
[ "$c2" = 403 ] && echo "ok - /app/x 403" || { echo "not ok - /app/x $c2"; exit 1; }
want_uid="$(id -u deathplanswitch)"
found=0
for d in /proc/[0-9]*; do
    [ -r "$d/cmdline" ] || continue
    args="$(tr '\0' ' ' < "$d/cmdline" 2>/dev/null || true)"
    argv0="${args%% *}"
    case "${argv0##*/}" in
        php|php[0-9]*) ;;
        *) continue ;;
    esac
    case " $args" in
        *" -S "*) ;;
        *) continue ;;
    esac
    found=$((found+1))
    uid="$(awk '/^Uid:/{print $2}' "$d/status" 2>/dev/null || true)"
    if [ "$uid" != "$want_uid" ]; then
        echo "not ok - php pid ${d#/proc/} runs as uid $uid, want $want_uid ($args)"; exit 1
    fi
done
[ "$found" -ge 1 ] && echo "ok - php runs as deathplanswitch (uid $want_uid, $found process(es))" || { echo "not ok - no php -S process found"; exit 1; }
COMMON

if [ "$placeholders" -ne 0 ]; then
    echo "skip - Arch and Alpine installs (install files or bin/router.php not delivered yet)"
else
    cat > "$TMP/inside-arch.sh" <<'INSIDE'
set -eu
pacman -Sy --noconfirm >/dev/null 2>&1
pacman -S --noconfirm --needed curl >/dev/null 2>&1
pacman -U --noconfirm /pkg.pkg.tar.zst >/dev/null
# An upgrade must not warn about the data dir (owner and mode are the package's).
pacman -U --noconfirm /pkg.pkg.tar.zst >/tmp/upgrade.log 2>&1
if grep -qi 'warning: directory' /tmp/upgrade.log; then
    echo "not ok - pacman warns on upgrade"; cat /tmp/upgrade.log; exit 1
fi
echo "ok - no pacman warning on upgrade"
[ "$(stat -c %U:%G:%a /var/lib/deathplanswitch)" = "deathplanswitch:deathplanswitch:700" ] \
    && echo "ok - data dir still deathplanswitch 0700 after upgrade" || { echo "not ok - data dir after upgrade"; exit 1; }
for u in web scheduler; do
    f=/usr/lib/systemd/system/deathplanswitch-$u.service
    test -f "$f" && grep -qx 'User=deathplanswitch' "$f" && grep -qx 'NoNewPrivileges=yes' "$f" \
        && echo "ok - unit $u installed with User= and NoNewPrivileges=yes" || { echo "not ok - unit $u"; exit 1; }
done
test -f /etc/php/conf.d/deathplanswitch-sodium.ini && echo "ok - sodium drop-in installed" || { echo "not ok - sodium drop-in"; exit 1; }
php -m | grep -qx sodium && echo "ok - php loads sodium" || { echo "not ok - php lacks sodium"; exit 1; }
for m in ctype curl filter hash json mbstring openssl posix random session sodium; do
    php -m | grep -qix "$m" || { echo "not ok - php lacks $m"; exit 1; }
done
echo "ok - php has every extension the app needs"
UNITCMD="$(sed -n 's/^ExecStart=//p' /usr/lib/systemd/system/deathplanswitch-web.service | head -1)"
export UNITCMD
. /common.sh
INSIDE
    if docker run --rm -v "$arch:/pkg.pkg.tar.zst:ro" -v "$TMP/inside-arch.sh:/inside.sh:ro" -v "$TMP/common.sh:/common.sh:ro" archlinux:latest sh /inside.sh; then
        ok "arch install and serve"
    else
        bad "arch install and serve"
    fi

    cat > "$TMP/inside-alpine.sh" <<'INSIDE'
set -eu
apk add -q curl >/dev/null
apk add --allow-untrusted /pkg.apk >/dev/null
for u in web scheduler; do
    test -x /etc/init.d/deathplanswitch-$u && grep -q '^command=/usr/bin/php83$' /etc/init.d/deathplanswitch-$u \
        && echo "ok - OpenRC script $u installed with /usr/bin/php83" || { echo "not ok - OpenRC script $u"; exit 1; }
done
for m in ctype curl filter hash json mbstring openssl posix random session sodium; do
    php83 -m | grep -qix "$m" || { echo "not ok - php83 lacks $m"; exit 1; }
done
echo "ok - php83 has every extension the app needs"
# The OpenRC scripts read /etc/default/deathplanswitch as data. Stubs stand in for openrc-run.
eerror() { echo "eerror: $*" >&2; }
checkpath() { :; }
cp /etc/default/deathplanswitch /tmp/default.orig
setting() { # setting KEY VALUE: replace the KEY= line, keeping the file's mode
    { grep -v "^$1=" /tmp/default.orig; printf '%s=%s\n' "$1" "$2"; } > /etc/default/deathplanswitch
}
# A value with command substitution: nothing runs and the service refuses to start.
for u in web scheduler; do
    for k in HEARTBEAT_URL DPS_APPRISE_URL; do
        setting $k 'https://x.example/p?a=1&b=$(touch /tmp/pwned)'
        if ( . /etc/init.d/deathplanswitch-$u; start_pre ) 2>/dev/null; then
            echo "not ok - $u starts with a command substitution in $k"; exit 1
        fi
        test ! -e /tmp/pwned || { echo "not ok - $u ran a command from $k"; exit 1; }
    done
done
echo "ok - command substitution in the settings is neither run nor started"
setting DPS_LISTEN '127.0.0.1:8080; touch /tmp/pwned'
if ( . /etc/init.d/deathplanswitch-web; start_pre ) 2>/dev/null; then echo "not ok - web starts with a bad DPS_LISTEN"; exit 1; fi
test ! -e /tmp/pwned && echo "ok - a bad DPS_LISTEN is refused" || { echo "not ok - DPS_LISTEN ran"; exit 1; }
# A URL with & and ? must reach php's environment intact.
HB='https://kuma.example/api/push/abc?status=up&msg=OK&ping='
setting HEARTBEAT_URL "$HB"
. /etc/init.d/deathplanswitch-web
start_pre && echo "ok - start_pre accepts a URL with & and ?" || { echo "not ok - start_pre refuses a good URL"; exit 1; }
UNITCMD="$command $command_args"
export UNITCMD ENV_LOADED=1
. /common.sh
# Read the environment as the service user (root lacks ptrace rights in a container).
cat > /tmp/envcheck.sh <<'ENVCHECK'
found=0
for d in /proc/[0-9]*; do
    args="$(tr '\0' ' ' < "$d/cmdline" 2>/dev/null || true)"
    case "$args" in */php*" -S "*) ;; *) continue ;; esac
    tr '\0' '\n' < "$d/environ" | grep -qxF "HEARTBEAT_URL=$HB" || { echo "not ok - php pid ${d#/proc/} lacks HEARTBEAT_URL"; exit 1; }
    found=$((found+1))
done
[ "$found" -ge 1 ] && echo "ok - php has HEARTBEAT_URL with & and ? in its environment" || { echo "not ok - no php -S process"; exit 1; }
ENVCHECK
export HB
su -s /bin/sh deathplanswitch -c "sh /tmp/envcheck.sh"
INSIDE
    if docker run --rm -v "$apk:/pkg.apk:ro" -v "$TMP/inside-alpine.sh:/inside.sh:ro" -v "$TMP/common.sh:/common.sh:ro" alpine:3.20 sh /inside.sh; then
        ok "alpine install and serve"
    else
        bad "alpine install and serve"
    fi
fi

if [ "$fail" -eq 0 ]; then echo "ok - all packaging checks passed"; else echo "FAILED"; fi
exit "$fail"
