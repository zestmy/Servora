#!/usr/bin/env bash
#
# Builds the Windows zips. Both architectures every time: the POS fleet is
# old and mixed, and discovering a terminal is 32-bit AFTER shipping only an
# amd64 zip means a second trip to the outlet.
set -euo pipefail

cd "$(dirname "$0")"

VERSION=$(grep -oP 'const Version = "\K[^"]+' main.go)

DIST="dist"

for ARCH in amd64 386; do
    STAGE="${DIST}/servora-pos-agent-${VERSION}-windows-${ARCH}"
    rm -rf "$STAGE"
    mkdir -p "$STAGE"

    echo "Building agent v${VERSION} for windows/${ARCH}..."
    GOOS=windows GOARCH="$ARCH" CGO_ENABLED=0 \
        go build -trimpath -ldflags="-s -w" -o "${STAGE}/servora-pos-agent.exe" .

    cp README.md "${STAGE}/README.md"

    ( cd "$DIST" && zip -qr "$(basename "$STAGE").zip" "$(basename "$STAGE")" )
    echo "Built ${STAGE}.zip"
done
