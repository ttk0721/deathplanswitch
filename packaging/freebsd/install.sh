#!/bin/sh
# DeathPlanSwitch installer for FreeBSD.
#   sh install.sh               install or update (data is kept)
#   sh install.sh uninstall     remove services and program files, keep data
#   sh install.sh uninstall --remove-data
# Optional: PHP=/usr/local/bin/php to choose the php binary.
set -eu

SVC_USER=deathplanswitch
APP=/usr/local/share/deathplanswitch
DATA=/var/db/deathplanswitch
RCD=/usr/local/etc/rc.d
SERVICES="deathplanswitch_web deathplanswitch_scheduler"

die() { echo "error: $*" >&2; exit 1; }

[ "$(id -u)" = "0" ] || die "run as root (the services themselves run as $SVC_USER)"

if [ "${1:-}" = "uninstall" ]; then
	for s in $SERVICES; do
		service "$s" onestop >/dev/null 2>&1 || true
		sysrc -x "${s}_enable" >/dev/null 2>&1 || true
		rm -f "$RCD/$s"
	done
	rm -rf "$APP"
	if [ "${2:-}" = "--remove-data" ]; then
		rm -rf "$DATA"
		pw userdel "$SVC_USER" >/dev/null 2>&1 || true
		echo "Removed services, program files and data."
	else
		echo "Removed services and program files. Data kept in $DATA (use: sh install.sh uninstall --remove-data)."
	fi
	exit 0
fi

# Locate the app and the rc.d scripts relative to this script.
HERE=$(cd "$(dirname "$0")" && pwd)
if [ -f "$HERE/bin/scheduler.php" ]; then
	SRC=$HERE
else
	SRC=$(cd "$HERE/../.." && pwd)
fi
[ -f "$SRC/bin/scheduler.php" ] || die "app files not found near $HERE"
RCSRC=
for d in "$HERE" "$HERE/packaging/freebsd" "$SRC/packaging/freebsd"; do
	if [ -f "$d/deathplanswitch_web" ]; then RCSRC=$d; break; fi
done
[ -n "$RCSRC" ] || die "rc.d scripts not found near $HERE"

# Resolve php once; its absolute path goes into rc.conf (no PATH lookup at run time).
PHP=${PHP:-}
[ -n "$PHP" ] || PHP=$(command -v php || true)
if [ -z "$PHP" ] || [ ! -x "$PHP" ]; then
	echo "php not found. Install it first:" >&2
	echo "  pkg install php83 php83-curl php83-mbstring php83-openssl php83-sodium" >&2
	exit 1
fi
case "$PHP" in /*) ;; *) die "php path must be absolute: $PHP" ;; esac
case "$PHP" in *[!A-Za-z0-9/._+-]*) die "unsupported characters in php path: $PHP" ;; esac
"$PHP" -r 'exit(PHP_VERSION_ID >= 80100 ? 0 : 1);' || die "PHP 8.1 or newer is required"
missing=
for ext in curl mbstring openssl sodium; do
	"$PHP" -r "exit(extension_loaded('$ext') ? 0 : 1);" || missing="$missing $ext"
done
if [ -n "$missing" ]; then
	echo "missing PHP extensions:$missing" >&2
	echo "  pkg install php83-curl php83-mbstring php83-openssl php83-sodium" >&2
	exit 1
fi

# Service user (never root).
if ! pw usershow "$SVC_USER" >/dev/null 2>&1; then
	pw useradd "$SVC_USER" -d /nonexistent -s /usr/sbin/nologin -c "DeathPlanSwitch"
fi

# Idempotent: stop running services before replacing files.
for s in $SERVICES; do
	service "$s" onestop >/dev/null 2>&1 || true
done

# Program files: owned by root, not writable by the service user.
rm -rf "$APP"
mkdir -p "$APP"
for e in "$SRC"/*; do
	case "$(basename "$e")" in
		packaging|tests|docs|docker|data|install.sh|install.ps1|uninstall.ps1|README.txt|deathplanswitch_*|*.plist) continue ;;
	esac
	cp -R "$e" "$APP/"
done
mkdir -p "$APP/data"
[ ! -f "$SRC/data/.htaccess" ] || cp "$SRC/data/.htaccess" "$APP/data/.htaccess"
chown -R root:wheel "$APP"
chmod -R go-w "$APP"

# Data: 0700, owned by the service user.
mkdir -p "$DATA"
chown "$SVC_USER" "$DATA"
chmod 0700 "$DATA"

# rc.d scripts.
mkdir -p "$RCD"
for s in $SERVICES; do
	install -o root -g wheel -m 0555 "$RCSRC/$s" "$RCD/$s"
done

# rc.subr evals the service arguments, so rc.conf values must be plain text.
# Check what is already set (kept on re-install) before anything is written.
check_listen() {
	case "$1" in
		''|*[!A-Za-z0-9.:_-]*|*:|:*) die "invalid listen address '$1': use host:port, for example 127.0.0.1:8080" ;;
		*:*) ;;
		*) die "invalid listen address '$1': use host:port, for example 127.0.0.1:8080" ;;
	esac
	case "${1##*:}" in ''|*[!0-9]*) die "invalid listen address '$1': the port must be a number" ;; esac
}
check_url() {
	[ -z "$2" ] && return 0
	case "$2" in http://?*|https://?*) ;; *) die "invalid $1: must start with http:// or https://" ;; esac
	case "$2" in *[!A-Za-z0-9._~:/?#@!%+,=-]*) die "invalid $1: contains a character that is not allowed (& ; | \$ quotes, spaces and brackets are rejected)" ;; esac
}
case "$DATA$APP" in *[!A-Za-z0-9._/+-]*) die "unsupported characters in program or data path" ;; esac
for s in $SERVICES; do
	case "$s" in
		deathplanswitch_web)
			v=$(sysrc -n deathplanswitch_web_listen 2>/dev/null || true)
			[ -z "$v" ] || check_listen "$v"
			v=$(sysrc -n deathplanswitch_web_workers 2>/dev/null || true)
			case "$v" in *[!0-9]*) die "invalid deathplanswitch_web_workers: must be a number" ;; esac ;;
		deathplanswitch_scheduler)
			v=$(sysrc -n deathplanswitch_scheduler_heartbeat_url 2>/dev/null || true)
			check_url deathplanswitch_scheduler_heartbeat_url "$v"
			v=$(sysrc -n deathplanswitch_scheduler_apprise_url 2>/dev/null || true)
			check_url deathplanswitch_scheduler_apprise_url "$v" ;;
	esac
done

# Settings. User choices (listen address) are kept on re-install.
sysrc deathplanswitch_web_enable=YES deathplanswitch_scheduler_enable=YES >/dev/null
sysrc deathplanswitch_web_php="$PHP" deathplanswitch_scheduler_php="$PHP" >/dev/null
sysrc deathplanswitch_web_data="$DATA" deathplanswitch_scheduler_data="$DATA" >/dev/null
sysrc deathplanswitch_web_app="$APP" deathplanswitch_scheduler_app="$APP" >/dev/null
sysrc -n deathplanswitch_web_listen >/dev/null 2>&1 || sysrc deathplanswitch_web_listen="127.0.0.1:8080" >/dev/null
LISTEN=$(sysrc -n deathplanswitch_web_listen)
check_listen "$LISTEN"

for s in $SERVICES; do
	service "$s" start
done

echo "Installed. Open http://$LISTEN/ to finish setup."
echo "Program files: $APP   Data: $DATA   Settings: /etc/rc.conf"
