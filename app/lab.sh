#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")"

test_config="${TEST_DB_CONFIG:-$(pwd)/.local/test-db.json}"

require_test_config() {
  if [[ ! -f "$test_config" ]]; then
    echo "Thiếu .local/test-db.json. Hãy tạo schema volunteer_test rồi chạy: php tools/configure.php test" >&2
    exit 1
  fi
}

case "${1:-}" in
  check)
    php tools/check.php
    ;;
  configure)
    php tools/configure.php "${2:-app}"
    ;;
  migrate)
    php tools/migrate.php
    ;;
  seed)
    php tools/seed.php
    ;;
  test)
    php tests/run.php
    ;;
  integration)
    require_test_config
    APP_ENV=test APP_DB_CONFIG="$test_config" php tests/integration.php
    ;;
  fixtures)
    php tools/generate_fixtures.php
    php tools/validate_fixtures.php
    ;;
  compare)
    php tools/compare.php --dataset DU_NGUOI_V1
    php tools/compare.php --dataset THIEU_NGUOI_V1
    php tools/compare.php --dataset TRUNG_LICH_V1
    ;;
  extend)
    require_test_config
    php tests/run.php
    php tools/validate_fixtures.php
    APP_ENV=test APP_DB_CONFIG="$test_config" php tests/integration.php
    APP_ENV=test APP_DB_CONFIG="$test_config" php tests/event_management.php
    APP_ENV=test APP_DB_CONFIG="$test_config" php tests/stale_plan.php
    APP_ENV=test APP_DB_CONFIG="$test_config" php tests/concurrency.php
    ;;
  db-info)
    php tools/db_info.php
    ;;
  http-test)
    require_test_config
    test_port="${TEST_HTTP_PORT:-18106}"
    test_url="http://127.0.0.1:${test_port}"
    test_log="$(mktemp /tmp/volunteer-http-test.XXXXXX.log)"
    APP_ENV=test APP_DB_CONFIG="$test_config" php -S "127.0.0.1:${test_port}" -t public >"$test_log" 2>&1 &
    server_pid=$!
    cleanup_server() {
      kill "$server_pid" 2>/dev/null || true
      wait "$server_pid" 2>/dev/null || true
      rm -f "$test_log"
    }
    trap cleanup_server EXIT INT TERM
    ready=0
    for _ in {1..30}; do
      if curl --silent --fail --output /dev/null "$test_url/?page=login"; then
        ready=1
        break
      fi
      sleep 0.1
    done
    if [[ "$ready" -ne 1 ]]; then
      echo "HTTP test server không khởi động được:" >&2
      tail -n 20 "$test_log" >&2
      exit 1
    fi
    APP_ENV=test APP_DB_CONFIG="$test_config" php tests/http_smoke.php "$test_url"
    ;;
  concurrency)
    require_test_config
    APP_ENV=test APP_DB_CONFIG="$test_config" php tests/concurrency.php
    ;;
  stale)
    require_test_config
    APP_ENV=test APP_DB_CONFIG="$test_config" php tests/stale_plan.php
    ;;
  test-prepare)
    require_test_config
    MIGRATION_DB_CONFIG="$test_config" php tools/migrate.php
    MIGRATION_DB_CONFIG="$test_config" php tools/seed.php
    ;;
  run)
    php -S 127.0.0.1:8106 -t public
    ;;
  *)
    echo "Cách dùng: bash lab.sh {check|configure [app|migration|test]|migrate|seed|test-prepare|test|integration|fixtures|compare|extend|concurrency|stale|db-info|http-test|run}"
    exit 2
    ;;
esac
