#!/usr/bin/env bash
# Builds every release asset.
# Usage: bash packaging/build.sh X.Y.Z <outdir>
# Needs: bash, tar, gzip, zip, sha256sum (or shasum), and docker for the .deb/.rpm.
set -euo pipefail

# nfpm, pinned by digest (tag v2.46.0).
NFPM_IMAGE="goreleaser/nfpm@sha256:45f7960a35853a93118af90555df85471c3b5c0d218ca18526671182de1656f7"

die() { echo "build.sh: $*" >&2; exit 1; }

[ "$#" -eq 2 ] || die "usage: build.sh X.Y.Z <outdir>"
VERSION="$1"
OUT="$2"
[[ "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+(-[A-Za-z0-9.]+)?$ ]] || die "bad version: $VERSION (want X.Y.Z)"

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

# Reproducible timestamps: last commit, else now.
if [ -z "${SOURCE_DATE_EPOCH:-}" ]; then
    SOURCE_DATE_EPOCH="$(git -C "$ROOT" log -1 --format=%ct 2>/dev/null || true)"
    [[ "$SOURCE_DATE_EPOCH" =~ ^[0-9]+$ ]] || SOURCE_DATE_EPOCH="$(date +%s)"
fi
export SOURCE_DATE_EPOCH VERSION

# Fail early, naming the missing path, when a platform's install files are absent.
need() {
    local p
    for p in "$@"; do
        [ -e "$ROOT/$p" ] || die "missing $p (needed for this release)"
    done
}
need packaging/stage-list.txt packaging/nfpm.yaml packaging/release-notes.sh \
     packaging/solaris/INSTALL-solaris.txt packaging/docker/README.txt \
     docker-compose.yml .env.example
need packaging/linux/install.sh packaging/linux/postinstall.sh packaging/linux/preremove.sh \
     packaging/linux/deathplanswitch-web.service packaging/linux/deathplanswitch-scheduler.service \
     packaging/linux/deathplanswitch.default packaging/openrc
need packaging/freebsd/install.sh
need packaging/macos/install.sh
need packaging/windows/install.ps1

mkdir -p "$OUT"
OUT="$(cd "$OUT" && pwd)"
[ -z "$(ls -A "$OUT")" ] || die "output directory is not empty: $OUT"

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT
APP="$WORK/stage/deathplanswitch-$VERSION"
mkdir -p "$APP"

# 1. Stage the app.
while IFS= read -r line || [ -n "$line" ]; do
    case "$line" in ''|'#'*) continue ;; esac
    need "$line"
    mkdir -p "$APP/$(dirname "$line")"
    cp -R "$ROOT/$line" "$APP/$line"
done < "$ROOT/packaging/stage-list.txt"
mkdir -p "$APP/data"

# Secrets and data never ship: drop dotfiles (except data/.htaccess) and data/*.json.
find "$APP" -name '.*' ! -name '.' ! -path "$APP/data/.htaccess" -prune -exec rm -rf {} +
find "$APP/data" -maxdepth 1 -name '*.json' -delete
leak="$(find "$APP" \( -name '.*' ! -name '.' ! -path "$APP/data/.htaccess" \) -o -path "$APP/data/*.json" -o -path "$APP/tests" -o -path "$APP/.github" | head -1)"
[ -z "$leak" ] || die "staged tree holds a private path: $leak"

printf '%s\n' "$VERSION" > "$APP/VERSION"
find "$APP" -type d -exec chmod 755 {} +
find "$APP" -type f -exec chmod 644 {} +
find "$APP" -exec touch -h -d "@$SOURCE_DATE_EPOCH" {} +

# 2. Archives.
tar_gz() { # tar_gz <dir holding deathplanswitch-X.Y.Z> <outfile>
    ( cd "$1" && find "deathplanswitch-$VERSION" -print | LC_ALL=C sort |
        tar --no-recursion --owner=0 --group=0 --numeric-owner --mtime="@$SOURCE_DATE_EPOCH" \
            -cf - -T - | gzip -9n > "$2" )
}
zip_it() { # zip_it <dir holding deathplanswitch-X.Y.Z> <outfile>
    ( cd "$1" && find "deathplanswitch-$VERSION" -print | LC_ALL=C sort | zip -X -q -9 "$2" -@ )
}
# variant <name>: copy the staged app; VDIR is the copy.
variant() {
    local d="$WORK/v-$1"
    rm -rf "$d"; mkdir -p "$d"
    cp -R "$WORK/stage/deathplanswitch-$VERSION" "$d/"
    VDIR="$d/deathplanswitch-$VERSION"
}
add_dir() { # add_dir <repo dir>: lands in packaging/ of the variant
    mkdir -p "$VDIR/packaging"
    cp -R "$ROOT/$1" "$VDIR/packaging/"
}
finish_tree() {
    find "$VDIR" -type d -exec chmod 755 {} +
    find "$VDIR" -type f ! -name '*.sh' -exec chmod 644 {} +
    find "$VDIR" -type f -name '*.sh' -exec chmod 755 {} +
    find "$VDIR" -exec touch -h -d "@$SOURCE_DATE_EPOCH" {} +
}

variant linux-other
add_dir packaging/linux; add_dir packaging/openrc
cp "$ROOT/packaging/linux/install.sh" "$VDIR/install.sh"
finish_tree
tar_gz "$WORK/v-linux-other" "$OUT/deathplanswitch-$VERSION-linux-other.tar.gz"

variant freebsd
add_dir packaging/freebsd
cp "$ROOT/packaging/freebsd/install.sh" "$VDIR/install.sh"
finish_tree
tar_gz "$WORK/v-freebsd" "$OUT/deathplanswitch-$VERSION-freebsd.tar.gz"

variant macos
add_dir packaging/macos
cp "$ROOT/packaging/macos/install.sh" "$VDIR/install.sh"
finish_tree
tar_gz "$WORK/v-macos" "$OUT/deathplanswitch-$VERSION-macos.tar.gz"

variant windows
add_dir packaging/windows
cp "$ROOT/packaging/windows/install.ps1" "$VDIR/install.ps1"
finish_tree
zip_it "$WORK/v-windows" "$OUT/deathplanswitch-$VERSION-windows.zip"

variant solaris
cp "$ROOT/packaging/solaris/INSTALL-solaris.txt" "$VDIR/INSTALL-solaris.txt"
finish_tree
tar_gz "$WORK/v-solaris" "$OUT/deathplanswitch-$VERSION-solaris-illumos.tar.gz"

# Compose bundle: no app code, the image pinned to this version.
d="$WORK/v-compose/deathplanswitch-$VERSION"
mkdir -p "$d"
sed "s#\${DPS_IMAGE:-ghcr.io/ttk0721/deathplanswitch:latest}#\${DPS_IMAGE:-ghcr.io/ttk0721/deathplanswitch:$VERSION}#" \
    "$ROOT/docker-compose.yml" > "$d/docker-compose.yml"
grep -q "deathplanswitch:$VERSION}" "$d/docker-compose.yml" || die "could not pin the image in docker-compose.yml"
cp "$ROOT/.env.example" "$d/.env.example"
cp "$ROOT/packaging/docker/README.txt" "$d/README.txt"
find "$d" -type f -exec chmod 644 {} +
find "$d" -exec touch -h -d "@$SOURCE_DATE_EPOCH" {} +
zip_it "$WORK/v-compose" "$OUT/deathplanswitch-$VERSION-docker-compose.zip"

# 3. .deb and .rpm, from nfpm in its pinned container (runs as the calling user).
command -v docker >/dev/null 2>&1 || die "docker is required for the .deb and .rpm"
NF="$WORK/nfpm"
mkdir -p "$NF/out" "$NF/linux"
cp -R "$WORK/stage/deathplanswitch-$VERSION" "$NF/stage"
cp "$ROOT/packaging/linux/postinstall.sh" "$ROOT/packaging/linux/preremove.sh" \
   "$ROOT/packaging/linux/deathplanswitch-web.service" "$ROOT/packaging/linux/deathplanswitch-scheduler.service" \
   "$ROOT/packaging/linux/deathplanswitch.default" "$NF/linux/"
chmod 755 "$NF/linux/postinstall.sh" "$NF/linux/preremove.sh"
cp "$ROOT/packaging/nfpm.yaml" "$NF/nfpm.yaml"
run_nfpm() { # run_nfpm <packager> <outfile name>
    docker run --rm --user "$(id -u):$(id -g)" \
        -e VERSION -e SOURCE_DATE_EPOCH -v "$NF:/work" -w /work \
        "$NFPM_IMAGE" package --packager "$1" --config /work/nfpm.yaml --target "/work/out/$2"
}
run_nfpm deb "deathplanswitch-$VERSION-linux-debian-ubuntu.deb"
run_nfpm rpm "deathplanswitch-$VERSION-linux-fedora-rhel-suse.rpm"
cp "$NF/out/deathplanswitch-$VERSION-linux-debian-ubuntu.deb" \
   "$NF/out/deathplanswitch-$VERSION-linux-fedora-rhel-suse.rpm" "$OUT/"

# 4. Checksums.
if command -v sha256sum >/dev/null 2>&1; then SUM=(sha256sum); else SUM=(shasum -a 256); fi
( cd "$OUT" && "${SUM[@]}" $(ls | LC_ALL=C sort | grep -v '^SHA256SUMS$') > SHA256SUMS )
echo "built $(ls "$OUT" | wc -l | tr -d ' ') files in $OUT"
