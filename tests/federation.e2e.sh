#!/usr/bin/env bash
# End-to-end federation test: two nodes (A = primary, B = secondary) talk over
# real HTTP. Run: bash tests/federation.e2e.sh
set -eu

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SUFFIX="$(head -c 4 /dev/urandom | od -An -tx1 | tr -d ' \n')"
PA=$((18800 + RANDOM % 50))
PB=$((PA + 50))
NA="dps-fed-a-$SUFFIX"
NB="dps-fed-b-$SUFFIX"
DA="$(mktemp -d /tmp/dps-fed-a.XXXXXX)"
DB="$(mktemp -d /tmp/dps-fed-b.XXXXXX)"
PHP_IMAGE="${PHP_IMAGE:-php:8.4-cli}"

cleanup() {
  docker rm -f "$NA" "$NB" >/dev/null 2>&1 || true
  # Files written by the php containers are not ours to delete.
  for d in "$DA" "$DB"; do
    docker run --rm -v "$(dirname "$d"):/t" alpine rm -rf "/t/$(basename "$d")" >/dev/null 2>&1 || true
  done
}
trap cleanup EXIT

fail() { echo "FAIL  $1" >&2; exit 1; }
ok() { echo "ok    $1"; }

for pair in "$NA:$DA:$PA" "$NB:$DB:$PB"; do
  IFS=: read -r name dir port <<<"$pair"
  chmod 777 "$dir"
  docker run -d --name "$name" --network host -e DPS_DATA=/data \
    -v "$dir:/data" -v "$ROOT:/app" -w /app "$PHP_IMAGE" \
    php -S "127.0.0.1:$port" -t /app >/dev/null
done

# Run a PHP script (from stdin) inside a node's container. Usage: php_in NAME <<'EOF' ... EOF
php_in() { docker exec -i -e DPS_DATA=/data "$@" php; }
node_cli() { local n="$1"; shift; docker exec -e DPS_DATA=/data "$n" php /app/cli/node.php "$@"; }
sync_b() { docker exec -e DPS_DATA=/data "$NB" php /app/cli/sync.php; }

for _ in $(seq 1 30); do
  if curl -s -o /dev/null "http://127.0.0.1:$PA/node-api.php?a=state" \
    && curl -s -o /dev/null "http://127.0.0.1:$PB/node-api.php?a=state"; then break; fi
  sleep 1
done

# Installed configs. A carries the contact and the owner details; B starts with its own.
write_config() { # name owner port
  docker exec -i -e DPS_DATA=/data -e OWNER="$2" -e PORT="$3" "$1" php <<'EOF'
<?php
require "/app/app/bootstrap.php";
$s = DPS\store();
$s->write("config", [
  "owner_name" => getenv("OWNER"), "timezone" => "UTC", "installed_at" => 1700000000,
  "password_hash" => password_hash("e2e-password-123", PASSWORD_DEFAULT),
  "base_url" => "http://127.0.0.1:" . getenv("PORT"), "tick_key" => "tick-" . getenv("PORT"),
  "health_key" => "health-" . getenv("PORT"), "slot_dow" => 1, "slot_hour" => 9,
]);
$s->write("state", [
  "armed" => true, "armed_at" => time() - 3600, "last_checkin_at" => time() - 3600,
  "last_checkin_via" => "arming the switch", "next_due_at" => time() + 5 * 86400, "cycle" => null,
]);
EOF
}
write_config "$NA" "Alpha Owner" "$PA"
write_config "$NB" "Beta Placeholder" "$PB"
docker exec -i -e DPS_DATA=/data "$NA" php <<'EOF'
<?php
require "/app/app/bootstrap.php";
DPS\store()->write("contacts", ["contacts" => [[
  "id" => "c_0123456789abcdef", "kind" => "nok", "name" => "Zofia Kontakt",
  "email" => "zofia@example.test", "status" => "confirmed", "created_at" => 1700000000,
]]]);
EOF

node_cli "$NA" init --name=alpha >/dev/null
node_cli "$NB" init --name=beta >/dev/null
IDA="$(node_cli "$NA" show)"
IDB="$(node_cli "$NB" show)"
node_cli "$NB" join --primary-url="http://127.0.0.1:$PA" --primary="$IDA" --url="http://127.0.0.1:$PB" >/dev/null
ok "B joined A"

# The primary adds B to the cluster the way the Nodes page does: through Cluster::save.
docker exec -i -e DPS_DATA=/data -e IDA="$IDA" -e IDB="$IDB" -e PA="$PA" -e PB="$PB" "$NA" php <<'EOF'
<?php
require "/app/app/bootstrap.php";
$a = json_decode(getenv("IDA"), true);
$b = json_decode(getenv("IDB"), true);
DPS\Cluster::save(DPS\store(), [
  "k" => 1, "primary" => $a["id"], "isolation_hours" => 48, "step_delay_min" => 30,
  "nodes" => [
    $a + ["url" => "http://127.0.0.1:" . getenv("PA"), "rank" => 0],
    $b + ["url" => "http://127.0.0.1:" . getenv("PB"), "rank" => 1],
  ],
]);
EOF
ok "A lists both nodes"

# 1. B copies A's settings and contacts, keeping its own addresses and keys.
OUT="$(sync_b)" || fail "sync on B exited non-zero: $OUT"
echo "$OUT" | grep -q "Copied the settings, contacts and vault" || fail "B did not copy the bundle: $OUT"
docker exec -i -e DPS_DATA=/data "$NB" php <<EOF || fail "B does not mirror A"
<?php
require "/app/app/bootstrap.php";
\$s = DPS\store();
\$c = \$s->read("config");
if (\$c["owner_name"] !== "Alpha Owner") { fwrite(STDERR, "owner_name not copied\n"); exit(1); }
if (\$c["base_url"] !== "http://127.0.0.1:$PB") { fwrite(STDERR, "base_url not kept: " . \$c["base_url"] . "\n"); exit(1); }
if (\$c["tick_key"] !== "tick-$PB" || \$c["health_key"] !== "health-$PB") { fwrite(STDERR, "keys not kept\n"); exit(1); }
if ((\$s->read("contacts")["contacts"][0]["name"] ?? "") !== "Zofia Kontakt") { fwrite(STDERR, "contacts not copied\n"); exit(1); }
EOF
ok "B has A's config and contacts, and keeps its own base_url and keys"

# 2. A check-in on B is pushed to A.
BTIME="$(docker exec -i -e DPS_DATA=/data "$NB" php <<'EOF'
<?php
require "/app/app/bootstrap.php";
require_once DPS_ROOT . "/app/Sync.php";
DPS\federatedEngine()->checkIn("the dashboard");
echo DPS\store()->read("state")["last_checkin_at"];
EOF
)"
OUT="$(sync_b)" || fail "second sync on B exited non-zero: $OUT"
echo "$OUT" | grep -q "Sent the check-in" || fail "check-in was not pushed: $OUT"
ATIME="$(docker exec -i -e DPS_DATA=/data "$NA" php <<'EOF'
<?php
require "/app/app/bootstrap.php";
echo DPS\store()->read("state")["last_checkin_at"];
EOF
)"
# A records the check-in when it arrives, so its time is B's time or a moment later.
if [ "$ATIME" -lt "$BTIME" ] || [ "$ATIME" -gt $((BTIME + 30)) ]; then
  fail "A last_checkin_at ($ATIME) does not match B's ($BTIME)"
fi
ok "a check-in on B reached A (A=$ATIME, B=$BTIME)"

# 3. Unsigned requests are refused.
CODE="$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:$PA/node-api.php?a=state")"
[ "$CODE" = "401" ] || fail "unsigned state call returned $CODE, expected 401"
ok "an unsigned call to A's node-api gets 401"

# 4. A goes down: B's sync still exits 0 and says why it could not read A.
docker stop "$NA" >/dev/null
OUT="$(sync_b)" || fail "sync on B exited non-zero with A down: $OUT"
echo "$OUT" | grep -q "Could not read alpha" || fail "peer failure not logged: $OUT"
ok "with A down, B's sync exits 0 and logs the failure"

echo "ok    federation e2e passed"
