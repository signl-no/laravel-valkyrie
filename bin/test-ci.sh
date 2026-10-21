#!/usr/bin/env bash
set -euo pipefail

# Mirrors the tests workflow (.github/workflows/tests.yml) locally: runs the
# full validation suite from a clean clone in both stability lanes. A clone
# only contains committed files, so this also catches files that exist on
# disk but are missing from git — exactly what a CI checkout would see.

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

if [[ -n "$(git -C "$repo_root" status --porcelain)" ]]; then
    echo "Warning: working tree has uncommitted changes; test:ci runs against HEAD only." >&2
fi

workdir="$(mktemp -d)"
trap 'rm -rf "$workdir"' EXIT

for stability in prefer-lowest prefer-stable; do
    clone="$workdir/$stability"
    echo
    echo "==> Lane: $stability"
    git clone --quiet "$repo_root" "$clone"
    (
        cd "$clone"
        composer update --"$stability" --prefer-dist --no-interaction --no-progress --no-scripts
        composer run prepare
        composer test
    )
done

echo
echo "All lanes passed."
