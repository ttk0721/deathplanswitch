#!/usr/bin/env bash
# End-to-end vault test: owner fills the vault in a browser-like client, the
# switch fires, a recipient opens it with the code. Run: bash tests/vault.e2e.sh
set -eu

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SUFFIX="$(head -c 4 /dev/urandom | od -An -tx1 | tr -d ' \n')"
PORT=$((18600 + RANDOM % 300))
NAME="dps-vault-e2e-$SUFFIX"
DATA="$(mktemp -d /tmp/dps-vault-e2e.XXXXXX)"
PHP_IMAGE="${PHP_IMAGE:-php:8.4-cli}"

cleanup() {
  docker rm -f "$NAME" >/dev/null 2>&1 || true
  # Files written by the php container are not ours to delete.
  docker run --rm -v "$(dirname "$DATA"):/t" alpine rm -rf "/t/$(basename "$DATA")" >/dev/null 2>&1 || true
  docker run --rm -v "$ROOT:/r" alpine rm -f /r/.e2e-state.json >/dev/null 2>&1 || true
}
trap cleanup EXIT

HASH="$(docker run --rm "$PHP_IMAGE" php -r 'echo password_hash("e2e-password-123", PASSWORD_DEFAULT);')"
cat > "$DATA/config.json" <<EOF
{"owner_name":"E2E","timezone":"UTC","installed_at":"2026-01-01T00:00:00+00:00","password_hash":"$HASH","base_url":"http://127.0.0.1:$PORT"}
EOF
chmod 755 "$DATA"
chmod 644 "$DATA/config.json"

docker run -d --name "$NAME" --network host -e DPS_DATA=/data \
  -v "$DATA:/data" -v "$ROOT:/app" "$PHP_IMAGE" \
  php -S "127.0.0.1:$PORT" -t /app >/dev/null

for _ in $(seq 1 30); do
  if curl -s -o /dev/null "http://127.0.0.1:$PORT/vault-api.php?a=status"; then break; fi
  sleep 1
done

node_step() {
  docker run --rm --network host -v "$ROOT:/app" -w /app \
    -e E2E_BASE="http://127.0.0.1:$PORT" -e E2E_STEP="$1" \
    node:22-alpine node /app/tests/js/vault_e2e.mjs
}

node_step owner

# The switch fires: keep the other state keys, set the cycle stage.
docker exec "$NAME" php -r '
require "/app/app/bootstrap.php";
$s = DPS\store();
$state = $s->read("state");
$state["cycle"] = array_merge(is_array($state["cycle"] ?? null) ? $state["cycle"] : [], ["stage" => "fired"]);
$s->write("state", $state);
'
echo "ok    switch fired"

node_step recipient
echo "ok    vault e2e passed"
