#!/usr/bin/env bash
# Share boxes: sealed in JS (WebCrypto) and in PHP, opened by PHP. Run: bash tests/sharebox.e2e.sh
set -eu

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PHP_IMAGE="${PHP_IMAGE:-php:8.4-cli}"
DATA="$(mktemp -d /tmp/dps-sharebox-e2e.XXXXXX)"
chmod 777 "$DATA"
cleanup() {
  # Files written by the php container are not ours to delete.
  docker run --rm -v "$(dirname "$DATA"):/t" alpine rm -rf "/t/$(basename "$DATA")" >/dev/null 2>&1 || true
}
trap cleanup EXIT

php_run() { # php_run <code> [VAR=value ...]
  local code="$1"; shift
  local envs=()
  local e
  for e in "$@"; do envs+=(-e "$e"); done
  docker run --rm "${envs[@]}" -v "$ROOT:/app:ro" -v "$DATA:/data" -w /app "$PHP_IMAGE" \
    php -r "require '/app/app/bootstrap.php'; \$s = new DPS\\Store(getenv('SD')); $code"
}

mkdir -p "$DATA/a" "$DATA/b"
chmod 777 "$DATA/a" "$DATA/b"
INIT='echo DPS\Node::init($s, "x")["id"];'
A_ID="$(php_run "$INIT" SD=/data/a)"
php_run "$INIT" SD=/data/b >/dev/null
PK_A="$(php_run 'echo $s->read("node")["share_pk"];' SD=/data/a)"
if [ -z "$A_ID" ] || [ -z "$PK_A" ]; then echo "FAIL init"; exit 1; fi

HEX="01$(head -c 32 /dev/urandom | od -An -tx1 | tr -d ' \n')"

OPEN='try { echo bin2hex(DPS\ShareBox::open($s, getenv("NID"), json_decode(getenv("BOX"), true))); } catch (\RuntimeException $e) { echo "THROWS"; }'
open_box() { php_run "$OPEN" SD="$1" NID="$2" BOX="$3"; }

# JS seal, PHP open.
BOX="$(docker run --rm -v "$ROOT:/app:ro" -w /app node:22-alpine node tests/js/sharebox_seal.mjs "$PK_A" "$A_ID" "$HEX")"
if [ "$(open_box /data/a "$A_ID" "$BOX")" != "$HEX" ]; then echo "FAIL js seal, php open"; exit 1; fi
echo "ok js seal, php open"

# Tamper: flip the first char of ct.
BAD="$(docker run --rm -i node:22-alpine node -e 'const b=JSON.parse(require("fs").readFileSync(0,"utf8"));const c=b.ct;b.ct=(c[0]==="A"?"B":"A")+c.slice(1);console.log(JSON.stringify(b))' <<<"$BOX")"
if [ "$(open_box /data/a "$A_ID" "$BAD")" != "THROWS" ]; then echo "FAIL tampered ct"; exit 1; fi
echo "ok tampered ct throws"
if [ "$(open_box /data/a "n_00000000" "$BOX")" != "THROWS" ]; then echo "FAIL wrong node id"; exit 1; fi
echo "ok wrong node id throws"
if [ "$(open_box /data/b "$A_ID" "$BOX")" != "THROWS" ]; then echo "FAIL other node key"; exit 1; fi
echo "ok other node key throws"
if [ "$(open_box /data/a "$A_ID" '{"epk":"AA","iv":"AA","ct":"AA"}')" != "THROWS" ]; then echo "FAIL malformed box"; exit 1; fi
echo "ok malformed box throws"

# PHP seal, PHP open.
SEAL='echo json_encode(DPS\ShareBox::sealForTests(getenv("PK"), getenv("NID"), hex2bin(getenv("HEX"))));'
PBOX="$(php_run "$SEAL" SD=/data/a PK="$PK_A" NID="$A_ID" HEX="$HEX")"
if [ "$(open_box /data/a "$A_ID" "$PBOX")" != "$HEX" ]; then echo "FAIL php seal, php open"; exit 1; fi
echo "ok php seal, php open"
echo "all sharebox checks passed"
