#!/bin/bash

set -u

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

PID_FILE="$PROJECT_ROOT/examples/.php-server.pid"
LOG_FILE="$PROJECT_ROOT/examples/php-error.log"

if [ -f "$PID_FILE" ]; then
    PID=$(cat "$PID_FILE")

    if kill -0 "$PID" 2>/dev/null; then
        echo "PHP server is already running (PID: $PID)."
        exit 1
    fi

    rm -f "$PID_FILE"
fi

php \
    -d opcache.enable=1 \
    -d opcache.enable_cli=1 \
    -d opcache.validate_timestamps=1 \
    -d log_errors=1 \
    -d error_log="$LOG_FILE" \
    -S localhost:8080 \
    -t "$PROJECT_ROOT/examples" \
    > /dev/null 2>/dev/null &

echo $! > "$PID_FILE"

echo "PHP server started (PID: $!)."
echo "http://localhost:8080"