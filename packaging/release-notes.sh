#!/usr/bin/env bash
# Prints the "Which file do I need?" table for a release.
# Usage: bash packaging/release-notes.sh X.Y.Z
set -eu

v="${1:-}"
if [ -z "$v" ]; then
    echo "usage: release-notes.sh X.Y.Z" >&2
    exit 2
fi

guide="https://github.com/ttk0721/deathplanswitch/blob/main/docs/pl/instalacja.md"

cat <<NOTES
## Which file do I need?

| I use | Download | Guide |
|---|---|---|
| Docker, Synology Container Manager, Portainer, TrueNAS, Unraid, CasaOS | \`deathplanswitch-${v}-docker-compose.zip\` | [Docker](${guide}) |
| Debian, Ubuntu, Mint, Raspberry Pi OS | \`deathplanswitch-${v}-linux-debian-ubuntu.deb\` | [.deb](${guide}) |
| Fedora, RHEL, Alma, Rocky, openSUSE | \`deathplanswitch-${v}-linux-fedora-rhel-suse.rpm\` | [.rpm](${guide}) |
| Arch, Alpine, other Linux | \`deathplanswitch-${v}-linux-other.tar.gz\` | [tarball](${guide}) |
| FreeBSD | \`deathplanswitch-${v}-freebsd.tar.gz\` | [FreeBSD](${guide}) |
| macOS | \`deathplanswitch-${v}-macos.tar.gz\` | [macOS](${guide}) |
| Windows 10/11, Server | \`deathplanswitch-${v}-windows.zip\` | [Windows](${guide}) |
| Solaris, OpenIndiana, OmniOS | \`deathplanswitch-${v}-solaris-illumos.tar.gz\` | [Solaris](${guide}) |

Check downloads against \`SHA256SUMS\`. The Docker image is
\`ghcr.io/ttk0721/deathplanswitch:${v}\` (linux/amd64, linux/arm64).
NOTES
