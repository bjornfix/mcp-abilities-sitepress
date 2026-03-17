#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
PLUGIN_DIR="/media/bjorn/Stuff/Prosjekter/plugins/wordpress-plugins/mcp-abilities-wpml"
ZIP_PATH="${ROOT_DIR}/mcp-abilities-wpml.zip"
STAGE_DIR="/tmp/mcp-abilities-wpml-package"

if [ ! -d "${PLUGIN_DIR}" ]; then
  echo "Missing plugin dir: ${PLUGIN_DIR}" >&2
  exit 1
fi

rm -rf "${STAGE_DIR}"
mkdir -p "${STAGE_DIR}/mcp-abilities-wpml"

for file in mcp-abilities-wpml.php README.md; do
  if [ -f "${PLUGIN_DIR}/${file}" ]; then
    cp "${PLUGIN_DIR}/${file}" "${STAGE_DIR}/mcp-abilities-wpml/${file}"
  fi
done

if [ ! -f "${STAGE_DIR}/mcp-abilities-wpml/mcp-abilities-wpml.php" ]; then
  echo "Missing main plugin file in staging." >&2
  exit 1
fi

rm -f "${ZIP_PATH}"
(
  cd "${STAGE_DIR}"
  zip -r "${ZIP_PATH}" "mcp-abilities-wpml" >/dev/null
)

echo "${ZIP_PATH}"
