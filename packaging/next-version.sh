#!/usr/bin/env bash
# Prints the next release version, or "skip".
# Usage: next-version.sh <last-tag-or-empty> <base VERSION> <labels csv> <title> <body-file>
# Exit 2 on malformed input.
set -u

[ "$#" -eq 5 ] || { echo "usage: next-version.sh <last-tag> <base> <labels> <title> <body-file>" >&2; exit 2; }
last="$1"; base="$2"; labels="$3"; title="$4"; bodyfile="$5"

re='^([0-9]+)\.([0-9]+)\.([0-9]+)$'
[[ "$base" =~ $re ]] || { echo "bad base version: $base" >&2; exit 2; }
cur="$base"
if [ -n "$last" ]; then
    t="${last#v}"
    [[ "$t" =~ $re ]] || { echo "bad last tag: $last" >&2; exit 2; }
    # The newer of the tag and VERSION is the starting point.
    cur="$(printf '%s\n%s\n' "$t" "$base" | sort -t. -k1,1n -k2,2n -k3,3n | tail -1)"
fi
[[ "$cur" =~ $re ]]
major="${BASH_REMATCH[1]}"; minor="${BASH_REMATCH[2]}"; patch="${BASH_REMATCH[3]}"

kind=""
IFS=',' read -r -a lab <<< "$labels"
for l in "${lab[@]+"${lab[@]}"}"; do
    l="${l#"${l%%[![:space:]]*}"}"; l="${l%"${l##*[![:space:]]}"}"
    case "$l" in
        release:skip) echo skip; exit 0 ;;
        release:major|release:minor|release:patch) [ -n "$kind" ] || kind="${l#release:}" ;;
    esac
done

if [ -z "$kind" ]; then
    body=""
    [ -r "$bodyfile" ] && body="$(cat "$bodyfile")"
    if [[ "$title" =~ ^[A-Za-z]+(\([^\)]*\))?!: ]] || [[ "$title" == *"BREAKING CHANGE"* ]] || [[ "$body" == *"BREAKING CHANGE"* ]]; then
        kind=major
    elif [[ "$title" =~ ^feat(\([^\)]*\))?: ]]; then
        kind=minor
    else
        kind=patch
    fi
fi

case "$kind" in
    major) echo "$((major + 1)).0.0" ;;
    minor) echo "$major.$((minor + 1)).0" ;;
    *)     echo "$major.$minor.$((patch + 1))" ;;
esac
