#!/bin/bash

set -u

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

cleanup() {
    bash "$PROJECT_ROOT/examples/stop-server.sh"
}

trap cleanup EXIT INT TERM

bash "$PROJECT_ROOT/examples/start-server.sh"

cd "$PROJECT_ROOT" || exit 1

"$PROJECT_ROOT/vendor/bin/phpunit" tests/Integration