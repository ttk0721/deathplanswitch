#!/bin/sh
# Arch package pre-install and pre-upgrade. Runs as root.
# The package ships the data dir owned by the service user (so pacman finds no
# difference on upgrade), which therefore has to exist before unpacking.
set -eu
PATH=/usr/sbin:/usr/bin:/sbin:/bin
export PATH

USER_NAME=deathplanswitch
DATA=/var/lib/deathplanswitch

if [ -L "$DATA" ]; then
    echo "refusing: $DATA is a symlink" >&2
    exit 1
fi
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
exit 0
