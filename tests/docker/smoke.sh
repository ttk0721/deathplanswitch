#!/usr/bin/env bash
# Smoke test: builds the image, starts web + scheduler + a heartbeat catcher,
# and checks the web rules and the scheduler loop. Run: bash tests/docker/smoke.sh
set -eu

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
export SMOKE_DATA
SMOKE_DATA="$(mktemp -d /tmp/dps-smoke.XXXXXX)"
mkdir -p "${SMOKE_DATA}-out"
chmod 755 "$SMOKE_DATA" "${SMOKE_DATA}-out"

compose() {
  docker compose -p dps-smoke --project-directory "$ROOT" \
    -f "$ROOT/docker-compose.yml" -f "$ROOT/docker-compose.build.yml" \
    -f "$ROOT/tests/docker/compose.smoke.yml" "$@"
}

# Files in the data dirs end up owned by www-data (uid 33) or root, so the
# host user cannot read or delete them. A throwaway container can.
inspect() { docker run --rm -v "$1:/x:ro" alpine cat "/x/$2" 2>/dev/null || true; }

cleanup() {
  compose down -v --remove-orphans >/dev/null 2>&1 || true
  # /tmp is sticky and the dirs are owned by uid 33, so remove them from the parent.
  docker run --rm -v "$(dirname "$SMOKE_DATA"):/t" alpine \
    rm -rf "/t/$(basename "$SMOKE_DATA")" "/t/$(basename "$SMOKE_DATA")-out" >/dev/null 2>&1 || true
}
trap cleanup EXIT

failed=0
pass() { echo "ok    $1"; }
fail() { echo "FAIL  $1"; failed=1; }

# Retry a command until it succeeds or the time (seconds) runs out.
wait_for() {
  local limit=$1; shift
  local end=$((SECONDS + limit))
  while [ "$SECONDS" -lt "$end" ]; do
    if "$@"; then return 0; fi
    sleep 2
  done
  return 1
}

code() { curl -s --path-as-is -o /dev/null -w '%{http_code}' "http://127.0.0.1:18080$1" || true; }
is_up() { [ "$(code /install.php)" = 200 ]; }
has_tick() { inspect "$SMOKE_DATA" state.json | grep -q last_tick_at; }
has_ping() { inspect "${SMOKE_DATA}-out" requests.log | grep -q '^POST /ping'; }
sched_healthy() {
  [ "$(docker inspect --format '{{.State.Health.Status}}' "$(compose ps -q scheduler)" 2>/dev/null)" = healthy ]
}

# Configured but not installed: the scheduler ticks, nothing is mailed.
printf '{"owner_name":"Smoke","timezone":"UTC"}\n' > "$SMOKE_DATA/config.json"
chmod 644 "$SMOKE_DATA/config.json"

compose up -d --build

wait_for 60 is_up || true
if is_up; then pass "install.php answers 200"; else fail "install.php answers 200"; fi

if [ "$(code /app/bootstrap.php)" = 403 ] && [ "$(code /data/)" = 403 ]; then
  pass "/app/bootstrap.php and /data/ answer 403"
else
  fail "/app/bootstrap.php and /data/ answer 403"
fi

# Nothing private is served: project files, dotfiles, case/encoding tricks.
leaks=""
for u in /Dockerfile /docker-compose.yml /.env /.env.example /.git/config /config.local.php /README.md \
         /DATA/ /Data/config.json /data/config.json /data/..%2Fconfig.json /data%2Fconfig.json /data./ /app./Web.php /APP/Web.php \
         //app/Web.php /cli/status.php /bin/healthcheck.php /docker/entrypoint.sh /docs/ /tests/; do
  c="$(code "$u")"
  case "$c" in 403|404|400) ;; *) leaks="$leaks $u=$c" ;; esac
done
if [ -z "$leaks" ]; then pass "private paths are not served"; else fail "private paths served:$leaks"; fi

t="$(code /tests/)"
if [ "$t" = 403 ] || [ "$t" = 404 ]; then pass "/tests/ is not served ($t)"; else fail "/tests/ is not served ($t)"; fi

if wait_for 60 has_tick; then pass "scheduler wrote last_tick_at"; else fail "scheduler wrote last_tick_at"; fi

if wait_for 60 has_ping && ! inspect "${SMOKE_DATA}-out" requests.log | grep -q '/fail'; then
  pass "heartbeat POST /ping, no /fail"
else
  fail "heartbeat POST /ping, no /fail"
fi

if wait_for 90 sched_healthy; then pass "scheduler container healthy"; else fail "scheduler container healthy"; fi

# Scheduler php process uid, read from /proc (works without procps).
sched_uid() {
  compose exec -T scheduler sh -c 'for d in /proc/[0-9]*; do [ "${d#/proc/}" = "$$" ] && continue;
    if tr "\0" " " < $d/cmdline 2>/dev/null | grep -q "bin/scheduler.php"; then
      awk "/^Uid:/{print \$2}" $d/status; exit 0
    fi
  done; exit 1' 2>/dev/null | head -n1 | tr -d '\r'
}
tick_val() { inspect "$SMOKE_DATA" state.json | grep -o '"last_tick_at"[^,}]*' | head -n1 | tr -dc '0-9'; }
tick_gt() { local v; v="$(tick_val)"; [ -n "$v" ] && [ "$v" -gt "$1" ]; }

compose restart >/dev/null 2>&1 || true
wait_for 90 sched_healthy || true
owner="$(compose exec -T web stat -c %U /data/state.json 2>/dev/null | tr -d '\r')"
suid="$(sched_uid)"
if [ "$owner" = www-data ] && [ "$suid" = 33 ]; then
  pass "data stays owned by www-data after a restart"
else
  fail "data stays owned by www-data after a restart (owner=$owner scheduler uid=$suid)"
fi

before="$(tick_val)"; before="${before:-0}"
# last_tick_at may be an ISO string; fall back to a changed-value check.
compose up -d --force-recreate >/dev/null 2>&1 || true
if wait_for 60 tick_gt "$before" && wait_for 90 sched_healthy; then
  pass "recreated containers keep working"
else
  fail "recreated containers keep working (before=$before now=$(tick_val))"
fi

if [ "$(docker inspect -f '{{.Image}}' "$(compose ps -q web)")" = "$(docker inspect -f '{{.Image}}' "$(compose ps -q scheduler)")" ]; then
  pass "both services run the same image"
else
  fail "both services run the same image"
fi

apprise_up() { compose exec -T web php -r 'exit(@file_get_contents("http://apprise:8000/status") === false ? 1 : 0);' >/dev/null 2>&1; }
if wait_for 90 apprise_up; then
  pass "apprise answers from the web container"
else
  fail "apprise answers from the web container"
fi

if [ "$failed" -ne 0 ]; then
  compose logs --no-color --tail 30 scheduler web >&2 || true
  exit 1
fi
