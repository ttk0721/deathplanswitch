#!/bin/sh
# Package pre-remove (deb and rpm). Stops the services; the data stays.
set -eu
PATH=/usr/sbin:/usr/bin:/sbin:/bin
export PATH

# deb passes "remove" or "upgrade", rpm passes the number of copies left (0 = removal).
case "${1:-remove}" in
    remove | deconfigure | 0) ;;
    *) exit 0 ;;
esac

if [ -d /run/systemd/system ] && command -v systemctl >/dev/null 2>&1; then
    systemctl stop deathplanswitch-web.service deathplanswitch-scheduler.service || true
    systemctl disable deathplanswitch-web.service deathplanswitch-scheduler.service || true
fi
exit 0
