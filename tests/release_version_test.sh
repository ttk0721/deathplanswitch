#!/usr/bin/env bash
# Tests packaging/next-version.sh.
set -u
cd "$(dirname "$0")/.." || exit 1
S=packaging/next-version.sh
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
fail=0
printf 'Details.\nBREAKING CHANGE: the API moved\n' > "$TMP/breaking"
printf 'Nothing special.\n' > "$TMP/plain"

t() { # t <description> <expected> <args...>
    local d="$1" want="$2"; shift 2
    local got
    got="$(bash "$S" "$@" 2>/dev/null)"
    if [ "$got" = "$want" ]; then echo "ok - $d"; else echo "not ok - $d (got '$got', want '$want')"; fail=1; fi
}

t "feat after a tag bumps minor"        1.2.0 v1.1.0 1.0.0 "" "feat: add vault" /dev/null
t "no tag: base then minor"             1.1.0 ""     1.0.0 "" "feat: x" /dev/null
t "label release:skip skips"            skip  v1.2.3 1.0.0 "release:skip" "fix: y" /dev/null
t "fix bumps patch"                     1.2.4 v1.2.3 1.0.0 "" "fix: y" /dev/null
t "other title bumps patch"             1.2.4 v1.2.3 1.0.0 "" "Update docs" /dev/null
t "scoped feat bumps minor"             1.3.0 v1.2.3 1.0.0 "" "feat(ui): y" /dev/null
t "fix! is major"                       2.0.0 v1.2.3 1.0.0 "" "fix!: z" /dev/null
t "BREAKING CHANGE in body is major"    2.0.0 v1.2.3 1.0.0 "" "fix: z" "$TMP/breaking"
t "label release:patch beats feat"      1.2.4 v1.2.3 1.0.0 "release:patch" "feat: a" /dev/null
t "label release:major"                 2.0.0 v1.2.3 1.0.0 "bug,release:major" "fix: a" /dev/null
t "label release:minor"                 1.3.0 v1.2.3 1.0.0 "release:minor" "fix: a" "$TMP/plain"
t "base above the tag wins"             2.1.0 v1.2.3 2.0.0 "" "feat: a" /dev/null

bash "$S" vX 1.0.0 "" "feat: a" /dev/null >/dev/null 2>&1
rc=$?
if [ "$rc" -eq 2 ]; then echo "ok - malformed tag exits 2"; else echo "not ok - malformed tag exit $rc"; fail=1; fi
bash "$S" "" 1.x "" "feat: a" /dev/null >/dev/null 2>&1
rc=$?
if [ "$rc" -eq 2 ]; then echo "ok - malformed base exits 2"; else echo "not ok - malformed base exit $rc"; fail=1; fi

exit "$fail"
