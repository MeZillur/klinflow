#!/usr/bin/env bash
# module-report.sh – scans modules/ and prints a JSON report per module.
set -euo pipefail

MODULES_DIR="${1:-modules}"

echo "["
first=true
for manifest in "$MODULES_DIR"/*/manifest.php; do
  [ -f "$manifest" ] || continue
  dir="$(dirname "$manifest")"
  name="$(basename "$dir")"

  has_migrations=$( [ -d "$dir/database/migrations" ] && echo true || echo false )
  has_routes=$( [ -f "$dir/routes.php" ] && echo true || echo false )
  has_src=$( [ -d "$dir/src" ] && echo true || echo false )

  providers=$(find "$dir" -type f -name '*Provider.php' 2>/dev/null | wc -l | tr -d ' ')
  controllers=$(find "$dir" -type f -name '*Controller.php' 2>/dev/null | wc -l | tr -d ' ')
  models=$(find "$dir" -type f \( -name '*.php' \) -path '*/Models/*' 2>/dev/null | wc -l | tr -d ' ')

  composer_name=""
  if [ -f "$dir/composer.json" ]; then
    composer_name=$(sed -n 's/.*"name"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' "$dir/composer.json" | head -1)
  fi

  $first || echo ","
  first=false

  cat <<EOF
  {
    "module": "$name",
    "composer_name": "$composer_name",
    "has_migrations": $has_migrations,
    "has_routes": $has_routes,
    "has_src": $has_src,
    "providers": $providers,
    "controllers": $controllers,
    "models": $models
  }
EOF
done
echo "]"
