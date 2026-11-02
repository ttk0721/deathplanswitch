#!/bin/sh
# Package post-install (deb and rpm). Runs as root.
set -eu
PATH=/usr/sbin:/usr/bin:/sbin:/bin
export PATH

USER_NAME=deathplanswitch
DATA=/var/lib/deathplanswitch

if ! getent passwd "$USER_NAME" >/dev/null 2>&1; then
    nologin=/bin/false
    for candidate in /usr/sbin/nologin /sbin/nologin; do
        if [ -x "$candidate" ]; then
            nologin=$candidate
            break
        fi
    done
    useradd --system --user-group --home-dir "$DATA" --no-create-home \
        --shell "$nologin" --comment "Death Plan Switch" "$USER_NAME"
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

if [ -d /run/systemd/system ] && command -v systemctl >/dev/null 2>&1; then
    systemctl daemon-reload || true
    systemctl enable deathplanswitch-web.service deathplanswitch-scheduler.service || true
    # restart, not start: an upgrade must run the new code
    systemctl restart deathplanswitch-web.service deathplanswitch-scheduler.service || true
fi

cat <<MSG

Death Plan Switch is installed.
  1. Open http://127.0.0.1:8080/install.php and set up the switch.
  2. Put HTTPS in front before anyone else can reach it (Caddy, nginx or a tunnel).
Settings: /etc/default/deathplanswitch    Data: $DATA (kept when the package is removed)
MSG
exit 0
