#!/usr/bin/env bash
# End-to-end key shares: two nodes, K=2 of N=2. The owner splits the release key,
# the recipient needs both nodes. Run: bash tests/shares.e2e.sh
set -eu

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SUFFIX="$(head -c 4 /dev/urandom | od -An -tx1 | tr -d ' \n')"
PA=$((19100 + RANDOM % 50))
PB=$((PA + 50))
NA="dps-shr-a-$SUFFIX"
NB="dps-shr-b-$SUFFIX"
DA="$(mktemp -d /tmp/dps-shr-a.XXXXXX)"
DB="$(mktemp -d /tmp/dps-shr-b.XXXXXX)"
DS="$(mktemp -d /tmp/dps-shr-s.XXXXXX)"
PHP_IMAGE="${PHP_IMAGE:-php:8.4-cli}"

cleanup() {
  docker rm -f "$NA" "$NB" >/dev/null 2>&1 || true
  # Files written by the php containers are not ours to delete.
  for d in "$DA" "$DB" "$DS"; do
    docker run --rm -v "$(dirname "$d"):/t" alpine rm -rf "/t/$(basename "$d")" >/dev/null 2>&1 || true
  done
}
trap cleanup EXIT

fail() { echo "FAIL  $1" >&2; exit 1; }
ok() { echo "ok    $1"; }

chmod 777 "$DS"
for pair in "$NA:$DA:$PA" "$NB:$DB:$PB"; do
  IFS=: read -r name dir port <<<"$pair"
  chmod 777 "$dir"
  docker run -d --name "$name" --network host -e DPS_DATA=/data -e DPS_TEST_ALLOW_HTTP_PUBLIC=1 \
    -v "$dir:/data" -v "$ROOT:/app:ro" -w /app "$PHP_IMAGE" \
    php -S "127.0.0.1:$port" -t /app >/dev/null
done

node_cli() { local n="$1"; shift; docker exec -e DPS_DATA=/data -e DPS_TEST_ALLOW_HTTP_PUBLIC=1 "$n" php /app/cli/node.php "$@"; }
sync_b() { docker exec -e DPS_DATA=/data -e DPS_TEST_ALLOW_HTTP_PUBLIC=1 "$NB" php /app/cli/sync.php; }

for _ in $(seq 1 30); do
  if curl -s -o /dev/null "http://127.0.0.1:$PA/node-api.php?a=state" \
    && curl -s -o /dev/null "http://127.0.0.1:$PB/node-api.php?a=state"; then break; fi
  sleep 1
done

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

node_cli "$NA" init --name=alpha >/dev/null
node_cli "$NB" init --name=beta >/dev/null
IDA="$(node_cli "$NA" show)"
IDB="$(node_cli "$NB" show)"
node_cli "$NB" join --primary-url="http://127.0.0.1:$PA" --primary="$IDA" --url="http://127.0.0.1:$PB" >/dev/null

# Two nodes, K=2, each with a public address (its loopback URL; http only in test mode).
docker exec -i -e DPS_DATA=/data -e DPS_TEST_ALLOW_HTTP_PUBLIC=1 -e IDA="$IDA" -e IDB="$IDB" -e PA="$PA" -e PB="$PB" "$NA" php <<'EOF'
<?php
require "/app/app/bootstrap.php";
$a = json_decode(getenv("IDA"), true);
$b = json_decode(getenv("IDB"), true);
DPS\Cluster::save(DPS\store(), [
  "k" => 2, "primary" => $a["id"], "isolation_hours" => 48, "step_delay_min" => 30,
  "nodes" => [
    $a + ["url" => "http://127.0.0.1:" . getenv("PA"), "public_url" => "http://127.0.0.1:" . getenv("PA"), "rank" => 0],
    $b + ["url" => "http://127.0.0.1:" . getenv("PB"), "public_url" => "http://127.0.0.1:" . getenv("PB"), "rank" => 1],
  ],
]);
EOF
ok "cluster of two nodes, k=2, with public addresses"

id_of() { printf '%s' "$1" | tr -d ' \n' | sed -n 's/.*"id":"\([^"]*\)".*/\1/p'; }
pk_of() { docker exec -e DPS_DATA=/data "$1" php -r 'require "/app/app/bootstrap.php"; echo DPS\store()->read("node")["share_pk"];'; }
ID_A="$(id_of "$IDA")"; ID_B="$(id_of "$IDB")"
PK_A="$(pk_of "$NA")"; PK_B="$(pk_of "$NB")"
[ -n "$ID_A" ] && [ -n "$ID_B" ] && [ -n "$PK_A" ] && [ -n "$PK_B" ] || fail "could not read node identities"

node_step() {
  docker run --rm --network host -v "$ROOT:/app:ro" -v "$DS:/state" -w /app \
    -e E2E_A="http://127.0.0.1:$PA" -e E2E_B="http://127.0.0.1:$PB" \
    -e E2E_ID_A="$ID_A" -e E2E_PK_A="$PK_A" -e E2E_ID_B="$ID_B" -e E2E_PK_B="$PK_B" \
    -e E2E_STATE=/state/state.json -e E2E_STEP="$1" \
    node:22-alpine node /app/tests/js/shares_e2e.mjs
}

node_step owner || fail "owner step"

if docker exec "$NA" test -f /data/release.json || docker exec "$NA" test -f /data/release.json.bak; then
  fail "A still has the plain release key"
fi
ok "A's release.json is gone"

# Before the switch fires, neither node hands out a share.
[ "$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:$PA/vault-api.php?a=release")" = "403" ] || fail "release before firing is not 403"
ok "release on A before firing is 403"

OUT="$(sync_b)" || fail "sync on B: $OUT"
docker exec -i -e DPS_DATA=/data "$NB" php <<'EOF' || fail "B did not receive the shares"
<?php
require "/app/app/bootstrap.php";
$v = (new DPS\Vault(DPS\store()))->shares();
if ($v === null || $v["k"] !== 2 || count($v["boxes"]) !== 2) { fwrite(STDERR, "no shares on B\n"); exit(1); }
EOF
ok "B received the shares"

FIRE='
require "/app/app/bootstrap.php";
$s = DPS\store();
$state = $s->read("state");
$state["cycle"] = array_merge(is_array($state["cycle"] ?? null) ? $state["cycle"] : [], ["stage" => "fired"]);
$s->write("state", $state);
'
for n in "$NA" "$NB"; do docker exec -e DPS_DATA=/data "$n" php -r "$FIRE"; done
ok "both nodes fired"

node_step recipient || fail "recipient step"

docker stop "$NB" >/dev/null
node_step recipient_b_down || fail "recipient step with B stopped"

echo "ok    shares e2e passed"
