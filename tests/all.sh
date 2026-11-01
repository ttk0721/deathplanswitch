#!/usr/bin/env bash
# Runs the test suites. Usage: bash tests/all.sh [files]
# PHP suites run in php:8.4-cli with the repo at /app. Shell suites run on the host.
set -u
cd "$(dirname "$0")/.." || exit 1

if [ "$#" -gt 0 ]; then
    files=("$@")
else
    files=(tests/run.php tests/*_test.php tests/docker/*.sh)
fi

status=0
for f in "${files[@]}"; do
    if [ ! -f "$f" ]; then
        continue
    fi
    case "$f" in
        *.php)
            docker run --rm -v "$PWD":/app -w /app "${PHP_IMAGE:-php:8.4-cli}" php "$f" || status=1
            ;;
        *.sh)
            bash "$f" || status=1
            ;;
    esac
done
exit "$status"
