#!/bin/sh
# Alpine package pre-install and pre-upgrade. Runs as root, on busybox.
# apk applies the owner a package declares for a directory when it unpacks it
# (and again after the post-install script), so the service user must exist first.
set -eu
PATH=/usr/sbin:/usr/bin:/sbin:/bin
export PATH

USER_NAME=deathplanswitch
DATA=/var/lib/deathplanswitch

# apk may chown the target of a symlinked data dir while unpacking: refuse early.
if [ -L "$DATA" ]; then
    echo "refusing: $DATA is a symlink" >&2
    exit 1
fi

if ! id "$USER_NAME" >/dev/null 2>&1; then
    addgroup -S "$USER_NAME" 2>/dev/null || true
    adduser -S -D -H -h "$DATA" -s /sbin/nologin -G "$USER_NAME" "$USER_NAME"
fi
exit 0
