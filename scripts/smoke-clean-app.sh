#!/usr/bin/env bash

set -euo pipefail

laravel_constraint="${1:-^13.0}"
package_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
application_dir="$(mktemp -d "${TMPDIR:-/tmp}/latentkit-laravel-smoke.XXXXXX")"
server_log="${application_dir}/fixture-server.log"
server_pid=""

cleanup() {
  if [[ -n "${server_pid}" ]] && kill -0 "${server_pid}" 2>/dev/null; then
    kill "${server_pid}" 2>/dev/null || true
  fi
  rm -rf "${application_dir}"
}
trap cleanup EXIT

composer create-project "laravel/laravel:${laravel_constraint}" "${application_dir}" \
  --no-interaction --prefer-dist --no-progress

composer --working-dir="${application_dir}" config repositories.latentkit path "${package_root}"
composer --working-dir="${application_dir}" require \
  "latentkit/latentkit-php:0.1.0-beta.3" \
  "latentkit/laravel:@dev" \
  --no-interaction --prefer-dist --no-progress

database_path="${application_dir}/database/database.sqlite"
touch "${database_path}"

port="$(php -r '
  $socket = stream_socket_server("tcp://127.0.0.1:0", $errno, $error);
  if ($socket === false) { fwrite(STDERR, $error); exit(1); }
  $address = stream_socket_get_name($socket, false);
  fclose($socket);
  echo substr(strrchr($address, ":"), 1);
')"
base_url="http://127.0.0.1:${port}"

php -S "127.0.0.1:${port}" "${package_root}/tests/smoke/router.php" >"${server_log}" 2>&1 &
server_pid="$!"

for _ in {1..30}; do
  if curl --fail --silent "${base_url}/health" >/dev/null; then
    break
  fi
  sleep 0.1
done
curl --fail --silent "${base_url}/health" >/dev/null

export APP_ENV=testing
export LATENTKIT_API_KEY=lk_clean_application_smoke
export LATENTKIT_BASE_URL="${base_url}"
export LATENTKIT_TIMEOUT=10
export DB_CONNECTION=sqlite
export DB_DATABASE="${database_path}"
export QUEUE_CONNECTION=database

php "${application_dir}/artisan" package:discover --ansi
php "${application_dir}/artisan" config:cache --ansi
php "${application_dir}/artisan" migrate --force --ansi
php "${application_dir}/artisan" list --raw | grep -q '^latentkit:check'
php "${application_dir}/artisan" latentkit:check --no-interaction
php "${package_root}/tests/smoke/application.php" "${application_dir}"
php "${application_dir}/artisan" queue:work database --once --tries=1 --timeout=30 --no-interaction
php "${package_root}/tests/smoke/assert-queue.php" "${application_dir}"

echo "Laravel ${laravel_constraint} clean application smoke passed."
