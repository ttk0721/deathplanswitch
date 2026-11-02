#!/bin/sh
# Death Plan Switch: install from the tarball on Linux without deb or rpm
# (Arch, Alpine, others). Run as root from the unpacked folder:
#   sudo ./install.sh
# Safe to run again: it upgrades the app and keeps the data and the settings.
set -eu
# A fixed PATH, so nothing in the caller's environment can stand in for a tool.
PATH=/usr/sbin:/usr/bin:/sbin:/bin
export PATH

USER_NAME=deathplanswitch
APP=/usr/share/deathplanswitch
DATA=/var/lib/deathplanswitch
SETTINGS=/etc/default/deathplanswitch

die() {
    echo "install.sh: $*" >&2
    exit 1
}

[ "$(id -u)" -eq 0 ] || die "run as root (sudo ./install.sh)"
SRC=$(cd "$(dirname "$0")" && pwd)
[ -f "$SRC/index.php" ] && [ -d "$SRC/app" ] && [ -d "$SRC/packaging/linux" ] || die "run it from the unpacked deathplanswitch folder"

# PHP: 8.1 or newer with the extensions the switch needs.
PHP=
for candidate in /usr/bin/php /usr/local/bin/php /usr/bin/php84 /usr/bin/php83 /usr/bin/php82 /usr/bin/php81; do
    if [ -x "$candidate" ]; then
        PHP=$candidate
        break
    fi
done
[ -n "$PHP" ] || die "PHP not found. Install PHP 8.1+ with curl, mbstring, openssl and sodium (Arch: pacman -S php; Alpine: apk add php83 php83-curl php83-mbstring php83-openssl php83-sodium) and run again"
"$PHP" -r 'exit(PHP_VERSION_ID >= 80100 ? 0 : 1);' || die "$PHP is older than PHP 8.1"
missing=$("$PHP" -r 'foreach (["curl", "mbstring", "openssl", "sodium"] as $e) { if (!extension_loaded($e)) { echo $e, " "; } }')
[ -z "$missing" ] || die "PHP extensions missing: $missing"

if [ -d /run/systemd/system ] && command -v systemctl >/dev/null 2>&1; then
    INIT=systemd
elif command -v rc-service >/dev/null 2>&1 && command -v rc-update >/dev/null 2>&1; then
    INIT=openrc
else
    die "neither systemd nor OpenRC found; start 'php -S 127.0.0.1:8080 -t $APP $APP/bin/router.php' and 'php $APP/bin/scheduler.php' yourself"
fi

# The service user, without a login shell.
if ! getent passwd "$USER_NAME" >/dev/null 2>&1; then
    nologin=/bin/false
    for candidate in /usr/sbin/nologin /sbin/nologin; do
        if [ -x "$candidate" ]; then
            nologin=$candidate
            break
        fi
    done
    if command -v useradd >/dev/null 2>&1; then
        useradd --system --user-group --home-dir "$DATA" --no-create-home --shell "$nologin" "$USER_NAME"
    else
        addgroup -S "$USER_NAME" 2>/dev/null || true
        adduser -S -D -H -h "$DATA" -s "$nologin" -G "$USER_NAME" "$USER_NAME"
    fi
fi

# Never recurse into the data dir as root: the service user could plant a hard
# link to a root-owned file there. Fix up the top directory only.
if [ -L "$DATA" ]; then
    echo "refusing: $DATA is a symlink" >&2
    exit 1
fi
if [ ! -d "$DATA" ]; then
    install -d -m 0700 -o "$USER_NAME" -g "$USER_NAME" "$DATA"
fi
if [ "$(stat -c '%U:%G' "$DATA")" != "$USER_NAME:$USER_NAME" ]; then
    chown -h "$USER_NAME:$USER_NAME" "$DATA"
fi
if [ "$(stat -c '%a' "$DATA")" != 700 ]; then
    chmod 0700 "$DATA"
fi

# The app: copy beside the old one, then swap, so a failed copy leaves it working.
NEW="$APP.new"
rm -rf "$NEW" "$APP.old"
mkdir -p "$NEW"
for entry in "$SRC"/* "$SRC"/.[!.]*; do
    [ -e "$entry" ] || continue
    case "$(basename "$entry")" in
        packaging | install.sh) continue ;;
    esac
    cp -R "$entry" "$NEW/"
done
chmod -R go-w "$NEW"
chown -R root:root "$NEW" 2>/dev/null || true
[ -d "$APP" ] && mv "$APP" "$APP.old"
mv "$NEW" "$APP"
rm -rf "$APP.old"

# Settings: written once, never overwritten. They can hold secrets (the
# heartbeat URL), so only root reads them; an existing file keeps its content.
if [ ! -e "$SETTINGS" ]; then
    install -d -m 0755 /etc/default
    install -m 0600 -o root -g root "$SRC/packaging/linux/deathplanswitch.default" "$SETTINGS"
else
    chown root:root "$SETTINGS" 2>/dev/null || true
    chmod 0600 "$SETTINGS"
fi

# Service definitions, pointing at the PHP that was found.
install_service() { # source target mode
    sed "s|/usr/bin/php|$PHP|g" "$1" >"$2.tmp"
    chmod "$3" "$2.tmp"
    mv "$2.tmp" "$2"
}

if [ "$INIT" = systemd ]; then
    for unit in web scheduler; do
        install_service "$SRC/packaging/linux/deathplanswitch-$unit.service" "/etc/systemd/system/deathplanswitch-$unit.service" 0644
    done
    systemctl daemon-reload
    systemctl enable deathplanswitch-web.service deathplanswitch-scheduler.service
    systemctl restart deathplanswitch-web.service deathplanswitch-scheduler.service
else
    for unit in web scheduler; do
        install_service "$SRC/packaging/openrc/deathplanswitch-$unit" "/etc/init.d/deathplanswitch-$unit" 0755
        rc-update add "deathplanswitch-$unit" default >/dev/null 2>&1 || true
        rc-service "deathplanswitch-$unit" restart
    done
fi

cat <<MSG

Death Plan Switch is installed ($INIT).
  1. Open http://127.0.0.1:8080/install.php and set up the switch.
  2. Put HTTPS in front before anyone else can reach it (Caddy, nginx or a tunnel).
App: $APP    Data: $DATA    Settings: $SETTINGS
MSG
