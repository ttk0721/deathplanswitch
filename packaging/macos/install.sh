#!/bin/sh
# DeathPlanSwitch installer for macOS (LaunchDaemons).
#   sudo sh install.sh               install or update (data is kept)
#   sudo sh install.sh uninstall     remove services and program files, keep data
#   sudo sh install.sh uninstall --remove-data
# Optional: PHP=/opt/homebrew/bin/php to choose the php binary, LISTEN=127.0.0.1:8080.
set -eu

ROLE_USER=_deathplanswitch
# Program files: /usr/local/share/deathplanswitch, or /Library/DeathPlanSwitch when
# /usr/local/share can be written by someone other than root (Homebrew on Intel).
APP_PRIMARY=/usr/local/share/deathplanswitch
APP_FALLBACK=/Library/DeathPlanSwitch
# Data and logs live under root-owned parents. The log directory is root-owned
# because launchd opens the log files as root.
DATA=/var/db/deathplanswitch
LOG=/var/log/deathplanswitch
LOGFILES="web.log web.err scheduler.log scheduler.err"
DAEMONS=/Library/LaunchDaemons
LABELS="pl.ttk0721.deathplanswitch.web pl.ttk0721.deathplanswitch.scheduler"

die() { echo "error: $*" >&2; exit 1; }

# A directory and every ancestor must be owned by root and not writable by group or others.
# A missing directory is judged by its nearest existing ancestor.
parents_safe() {
	d=$1
	while [ ! -e "$d" ]; do d=$(dirname "$d"); done
	while :; do
		[ -d "$d" ] || return 1
		[ "$(ls -ldnL "$d" | awk '{ print $3 }')" = "0" ] || return 1
		[ -z "$(find -L "$d" -maxdepth 0 \( -perm -0020 -o -perm -0002 \) -print)" ] || return 1
		[ "$d" = "/" ] && return 0
		d=$(dirname "$d")
	done
}

# Refuse a symlink at a path we are about to write, chown or delete.
no_symlink() {
	[ ! -L "$1" ] || die "$1 is a symbolic link; remove it first and run again"
}

[ "$(id -u)" = "0" ] || die "run with sudo (the services themselves do not run as root)"

if [ "${1:-}" = "uninstall" ]; then
	for l in $LABELS; do
		launchctl bootout "system/$l" >/dev/null 2>&1 || true
		if [ -L "$DAEMONS/$l.plist" ]; then
			echo "Skipped $DAEMONS/$l.plist: it is a symbolic link." >&2
		else
			rm -f "$DAEMONS/$l.plist"
		fi
	done
	# Only these fixed directories are deleted, and never through a symbolic link.
	for d in "$APP_PRIMARY" "$APP_FALLBACK" "$LOG"; do
		if [ -L "$d" ]; then
			echo "Skipped $d: it is a symbolic link." >&2
		elif [ -d "$d" ]; then
			rm -rf "$d"
		fi
	done
	if [ "${2:-}" = "--remove-data" ]; then
		if [ -L "$DATA" ]; then
			echo "Skipped $DATA: it is a symbolic link." >&2
		elif [ -d "$DATA" ]; then
			rm -rf "$DATA"
		fi
		if dscl . -read "/Users/$ROLE_USER" >/dev/null 2>&1; then
			dscl . -delete "/Users/$ROLE_USER" || true
			dscl . -delete "/Groups/$ROLE_USER" >/dev/null 2>&1 || true
		fi
		echo "Removed services, program files and data."
	else
		echo "Removed services and program files. Data kept in $DATA (use: sudo sh install.sh uninstall --remove-data)."
	fi
	exit 0
fi

# Locate the app and the plists relative to this script.
HERE=$(cd "$(dirname "$0")" && pwd)
if [ -f "$HERE/bin/scheduler.php" ]; then
	SRC=$HERE
else
	SRC=$(cd "$HERE/../.." && pwd)
fi
[ -f "$SRC/bin/scheduler.php" ] || die "app files not found near $HERE"
PLSRC=
for d in "$HERE" "$HERE/packaging/macos" "$SRC/packaging/macos"; do
	if [ -f "$d/pl.ttk0721.deathplanswitch.web.plist" ]; then PLSRC=$d; break; fi
done
[ -n "$PLSRC" ] || die "plist files not found near $HERE"

# Resolve php once (Homebrew); its absolute path goes into the plists (no PATH lookup at run time).
PHP=${PHP:-}
if [ -z "$PHP" ]; then
	for c in /opt/homebrew/bin/php /usr/local/bin/php; do
		if [ -x "$c" ]; then PHP=$c; break; fi
	done
fi
[ -n "$PHP" ] || PHP=$(command -v php || true)
if [ -z "$PHP" ] || [ ! -x "$PHP" ]; then
	echo "php not found. Install it with Homebrew (as your normal user): brew install php" >&2
	exit 1
fi
case "$PHP" in /*) ;; *) die "php path must be absolute: $PHP" ;; esac
case "$PHP" in *[!A-Za-z0-9/._+-]*) die "unsupported characters in php path: $PHP" ;; esac

# The php binary may belong to the Homebrew user, so root never runs it. The checks
# run as the service user, or as nobody when that user does not exist yet.
if dscl . -read "/Users/$ROLE_USER" >/dev/null 2>&1; then CHK_USER=$ROLE_USER; else CHK_USER=nobody; fi
run_php() { (cd / && sudo -n -H -u "$CHK_USER" "$PHP" "$@"); }
run_php -r 'exit(PHP_VERSION_ID >= 80100 ? 0 : 1);' || die "PHP 8.1 or newer is required (checked as $CHK_USER, which must be able to run $PHP)"
missing=
for ext in curl mbstring openssl sodium; do
	run_php -r "exit(extension_loaded('$ext') ? 0 : 1);" || missing="$missing $ext"
done
[ -z "$missing" ] || die "missing PHP extensions:$missing"

LISTEN=${LISTEN:-127.0.0.1:8080}
case "$LISTEN" in *[!A-Za-z0-9.:_-]*) die "invalid LISTEN value: $LISTEN" ;; esac

# Pick where the program files go. Only root may be able to change the parent directories.
if parents_safe "$(dirname "$APP_PRIMARY")"; then
	APP=$APP_PRIMARY
elif parents_safe "$(dirname "$APP_FALLBACK")"; then
	APP=$APP_FALLBACK
	echo "Note: /usr/local/share can be changed by a non-root user (Homebrew on Intel), so the program files go to $APP."
else
	die "no safe place for the program files: /usr/local/share and /Library can be changed by non-root users"
fi
parents_safe "$(dirname "$DATA")" || die "$(dirname "$DATA") is not owned by root or can be changed by others"
parents_safe "$(dirname "$LOG")" || die "$(dirname "$LOG") is not owned by root or can be changed by others"
parents_safe "$DAEMONS" || die "$DAEMONS is not owned by root or can be changed by others"
no_symlink "$APP"
no_symlink "$DATA"
no_symlink "$LOG"
for f in $LOGFILES; do no_symlink "$LOG/$f"; done
for l in $LABELS; do no_symlink "$DAEMONS/$l.plist"; done
[ ! -e "$DATA" ] || [ -d "$DATA" ] || die "$DATA exists and is not a directory"
[ ! -e "$LOG" ] || [ -d "$LOG" ] || die "$LOG exists and is not a directory"

# Service user: a hidden role account, or (fallback) the user who ran sudo. Never root.
if dscl . -read "/Users/$ROLE_USER" >/dev/null 2>&1; then
	SVC_USER=$ROLE_USER
else
	uid=$(dscl . -list /Users UniqueID | awk '$2 >= 300 && $2 < 400 { print $2 }' | sort -n | tail -1)
	uid=$(( ${uid:-299} + 1 ))
	if [ "$uid" -lt 400 ] && dscl . -create "/Groups/$ROLE_USER" 2>/dev/null \
		&& dscl . -create "/Groups/$ROLE_USER" PrimaryGroupID "$uid" \
		&& dscl . -create "/Users/$ROLE_USER" \
		&& dscl . -create "/Users/$ROLE_USER" UserShell /usr/bin/false \
		&& dscl . -create "/Users/$ROLE_USER" RealName "DeathPlanSwitch" \
		&& dscl . -create "/Users/$ROLE_USER" UniqueID "$uid" \
		&& dscl . -create "/Users/$ROLE_USER" PrimaryGroupID "$uid" \
		&& dscl . -create "/Users/$ROLE_USER" NFSHomeDirectory /var/empty \
		&& dscl . -create "/Users/$ROLE_USER" IsHidden 1; then
		SVC_USER=$ROLE_USER
	else
		SVC_USER=${SUDO_USER:-}
		if [ -z "$SVC_USER" ] || [ "$SVC_USER" = "root" ]; then
			die "could not create $ROLE_USER and no non-root sudo user to fall back to"
		fi
		echo "Note: could not create the role user $ROLE_USER; the services will run as $SVC_USER."
	fi
fi
SVC_GROUP=$(id -gn "$SVC_USER")

# Idempotent: stop running services before replacing files.
for l in $LABELS; do
	launchctl bootout "system/$l" >/dev/null 2>&1 || true
done

# Program files: owned by root, not writable by the service user.
rm -rf "$APP"
mkdir -p "$APP"
for e in "$SRC"/*; do
	case "$(basename "$e")" in
		packaging|tests|docs|docker|data|install.sh|install.ps1|uninstall.ps1|README.txt|*.plist) continue ;;
	esac
	cp -R "$e" "$APP/"
done
mkdir -p "$APP/data"
[ ! -f "$SRC/data/.htaccess" ] || cp "$SRC/data/.htaccess" "$APP/data/.htaccess"
[ -z "$(find "$APP" -type l -print)" ] || die "the program files contain a symbolic link; refusing to continue"
chown -R root:wheel "$APP"
chmod -R go-w "$APP"
# An older install may have used the other location. It was ours; remove it (never through a link).
for old in "$APP_PRIMARY" "$APP_FALLBACK"; do
	if [ "$old" != "$APP" ] && [ -d "$old" ] && [ ! -L "$old" ]; then rm -rf "$old"; fi
done
if [ -d /usr/local/var/deathplanswitch ] && [ ! -L /usr/local/var/deathplanswitch ]; then
	echo "Note: older data exists in /usr/local/var/deathplanswitch. It is not used any more; copy it into $DATA by hand if you need it."
fi

# Data: 0700, owned by the service user.
[ -d "$DATA" ] || mkdir "$DATA"
chown -h "$SVC_USER:$SVC_GROUP" "$DATA"
chmod 0700 "$DATA"

# Logs: the directory is owned by root; the files are created now, owned by the service user.
[ -d "$LOG" ] || mkdir "$LOG"
chown -h root:wheel "$LOG"
chmod 0755 "$LOG"
for f in $LOGFILES; do
	( umask 037; : >> "$LOG/$f" )
	chown -h "$SVC_USER:wheel" "$LOG/$f"
	chmod 0640 "$LOG/$f"
done

# LaunchDaemons (must be root:wheel 0644).
for l in $LABELS; do
	sed -e "s|@PHP@|$PHP|g" -e "s|@APP@|$APP|g" -e "s|@DATA@|$DATA|g" \
		-e "s|@LOG@|$LOG|g" -e "s|@LISTEN@|$LISTEN|g" \
		-e "s|<string>$ROLE_USER</string>|<string>$SVC_USER</string>|" \
		"$PLSRC/$l.plist" > "$DAEMONS/$l.plist"
	chown -h root:wheel "$DAEMONS/$l.plist"
	chmod 0644 "$DAEMONS/$l.plist"
	launchctl enable "system/$l" >/dev/null 2>&1 || true
	launchctl bootstrap system "$DAEMONS/$l.plist"
done

echo "Installed. Open http://$LISTEN/ to finish setup."
echo "Program files: $APP   Data: $DATA   Logs: $LOG   Running as: $SVC_USER"
