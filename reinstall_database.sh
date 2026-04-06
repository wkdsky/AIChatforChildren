#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")" && pwd)"
XAMPP_SOCKET="/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock"

if [[ -n "${PHP_BIN:-}" ]]; then
  PHP_EXECUTABLE="$PHP_BIN"
elif [[ -x "/Applications/XAMPP/xamppfiles/bin/php" ]]; then
  PHP_EXECUTABLE="/Applications/XAMPP/xamppfiles/bin/php"
elif command -v php >/dev/null 2>&1; then
  PHP_EXECUTABLE="$(command -v php)"
else
  echo "Unable to find PHP. Set PHP_BIN or install XAMPP PHP first." >&2
  exit 1
fi

if [[ -z "${DB_SOCKET:-}" && -S "$XAMPP_SOCKET" ]]; then
  export DB_HOST="${DB_HOST:-localhost}"
  export DB_PORT="${DB_PORT:-3306}"
  export DB_NAME="${DB_NAME:-starter}"
  export DB_USERNAME="${DB_USERNAME:-root}"
  export DB_PASS="${DB_PASS:-}"
  export DB_SOCKET="$XAMPP_SOCKET"
  echo "Detected XAMPP MySQL socket. Using Mac XAMPP database settings from the project README."
fi

echo "Using PHP: $PHP_EXECUTABLE"
"$PHP_EXECUTABLE" "$ROOT_DIR/reinstall_database.php" "$@"
