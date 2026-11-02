#!/bin/sh
# Starts as root only to fix ownership of the /data volume, then runs as www-data.
set -eu

mkdir -p /data
chown -R www-data:www-data /data

case "${1:-web}" in
  web)
    # Before the first install, make a fresh one-time setup code on every start and print it into
    # the container log (only its hash is stored; the newest code in the log is the valid one).
    setpriv --reuid=www-data --regid=www-data --init-groups php /var/www/html/cli/setup-code.php --if-needed || true
    # Apache drops to www-data itself after binding port 80.
    exec apache2-foreground
    ;;
  scheduler)
    exec setpriv --reuid=www-data --regid=www-data --init-groups php /var/www/html/bin/scheduler.php
    ;;
  *)
    exec "$@"
    ;;
esac
