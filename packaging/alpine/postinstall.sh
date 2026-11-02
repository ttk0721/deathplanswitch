#!/bin/sh
# Alpine package post-install and post-upgrade. Runs as root, on busybox.
set -eu
PATH=/usr/sbin:/usr/bin:/sbin:/bin
export PATH

USER_NAME=deathplanswitch
DATA=/var/lib/deathplanswitch

if ! id "$USER_NAME" >/dev/null 2>&1; then
    addgroup -S "$USER_NAME" 2>/dev/null || true
    adduser -S -D -H -h "$DATA" -s /sbin/nologin -G "$USER_NAME" "$USER_NAME"
fi

# The package ships the folder as root; hand it to the service user.
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

# Settings can hold secrets; an upgrade keeps an edited file's old mode.
if [ -e /etc/default/deathplanswitch ]; then
    chown root:root /etc/default/deathplanswitch
    chmod 0600 /etc/default/deathplanswitch
fi

if [ -d /run/openrc ] && command -v rc-update >/dev/null 2>&1; then
    rc-update add deathplanswitch-web default || true
    rc-update add deathplanswitch-scheduler default || true
    # restart, not start: an upgrade must run the new code
    rc-service deathplanswitch-web restart || true
    rc-service deathplanswitch-scheduler restart || true
else
    echo
    echo "OpenRC is not running, so the services were not enabled. Later run:"
    echo "  rc-update add deathplanswitch-web default"
    echo "  rc-update add deathplanswitch-scheduler default"
    echo "  rc-service deathplanswitch-web start"
    echo "  rc-service deathplanswitch-scheduler start"
fi

cat <<MSG

Death Plan Switch is installed.
  1. Open http://127.0.0.1:8080/install.php and set up the switch.
  2. Put HTTPS in front before anyone else can reach it (Caddy, nginx or a tunnel).
Settings: /etc/default/deathplanswitch    Data: $DATA (kept when the package is removed)
MSG
exit 0
