#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

echo "[1/3] Validando PHP..."
while IFS= read -r -d '' file; do
  php -l "$file" >/dev/null
done < <(find "$ROOT" -name '*.php' -print0)

echo "[2/3] Validando JavaScript..."
for file in "$ROOT"/assets/js/*.js; do
  node --check "$file"
done

echo "[3/3] Conferindo configs locais..."
for file in "$ROOT/config/app.local.php" "$ROOT/config/database.local.php"; do
  if [[ -f "$file" ]]; then
    echo "Aviso: arquivo local encontrado: $file"
  fi
done

echo "Projeto validado."
