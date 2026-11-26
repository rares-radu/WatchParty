#!/usr/bin/env bash
set -euo pipefail

port=${1:-8000}
ip_address=${2:-0.0.0.0}
script_dir=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)

if ! php_path=$(type -P php); then
  echo "PHP was not found. Add PHP 8.2 or later to PATH." >&2
  exit 1
fi
if [[ $php_path != /* ]]; then
  php_path=$PWD/$php_path
fi

runtime_path=$script_dir/.runtime
mkdir -p -- "$runtime_path"

php_args=()
modules=$("$php_path" -m)
modules=${modules//$'\r'/}
for module in pdo_mysql fileinfo; do
  if ! grep -Fqx -- "$module" <<<"$modules"; then
    php_args+=(-d "extension=$module")
  fi
done
php_args+=(-d "session.save_path=$runtime_path" -d display_errors=0 -d log_errors=1)

cd -- "$script_dir"
exec "$php_path" "${php_args[@]}" -S "$ip_address:$port" router.php
