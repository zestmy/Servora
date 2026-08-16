#!/usr/bin/env bash
#
# Builds the Windows install zip: the agent exe, a PINNED SumatraPDF, its
# license (GPLv3 — shipped BESIDE the agent as a separate executable, which
# is aggregation, not linking), and the setup notes.
#
# Sumatra is pinned, never taken from the building machine or the outlet PC:
# `-print-settings paper=` needs >= 3.4, and version drift in the print path
# is a support ticket with no reproduction.
set -euo pipefail

cd "$(dirname "$0")"

VERSION=$(grep -oP 'const Version = "\K[^"]+' main.go)
SUMATRA_VERSION="3.5.2"
SUMATRA_URL="https://www.sumatrapdfreader.org/dl/rel/${SUMATRA_VERSION}/SumatraPDF-${SUMATRA_VERSION}-64.zip"

DIST="dist"
STAGE="${DIST}/servora-print-agent-${VERSION}"

rm -rf "$STAGE"
mkdir -p "$STAGE"

echo "Building agent v${VERSION} for windows/amd64..."
GOOS=windows GOARCH=amd64 CGO_ENABLED=0 \
    go build -trimpath -ldflags="-s -w" -o "${STAGE}/servora-print-agent.exe" .

echo "Fetching SumatraPDF ${SUMATRA_VERSION}..."
curl -fsSL "$SUMATRA_URL" -o "${DIST}/sumatra.zip"
unzip -jo "${DIST}/sumatra.zip" -d "$STAGE" >/dev/null
rm "${DIST}/sumatra.zip"
# The portable zip ships the exe under a versioned name on some releases.
if [ ! -f "${STAGE}/SumatraPDF.exe" ]; then
    mv "${STAGE}"/SumatraPDF*.exe "${STAGE}/SumatraPDF.exe"
fi

cat > "${STAGE}/SUMATRA-LICENSE.txt" <<'EOF'
SumatraPDF is distributed with this package as a separate program, unmodified.
It is licensed under the GNU General Public License v3 (GPLv3).

Source code and license text: https://github.com/sumatrapdfreader/sumatrapdf
EOF

cp README.md "${STAGE}/README.md"

( cd "$DIST" && zip -qr "servora-print-agent-${VERSION}-windows-amd64.zip" "$(basename "$STAGE")" )

echo "Built ${DIST}/servora-print-agent-${VERSION}-windows-amd64.zip"
