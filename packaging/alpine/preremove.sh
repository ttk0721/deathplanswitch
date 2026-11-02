#!/bin/sh
# Alpine package pre-deinstall. Stops the services; the data stays.
set -eu
PATH=/usr/sbin:/usr/bin:/sbin:/bin
export PATH

if [ -d /run/openrc ] && command -v rc-service >/dev/null 2>&1; then
    rc-service deathplanswitch-scheduler stop || true
    rc-service deathplanswitch-web stop || true
    rc-update del deathplanswitch-scheduler default || true
    rc-update del deathplanswitch-web default || true
fi
exit 0
