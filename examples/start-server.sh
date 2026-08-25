#!/bin/bash

set -u

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

RESOURCE_DIR="$PROJECT_ROOT/examples/resources"
PID_FILE="$RESOURCE_DIR/.php-server.pid"
LOG_FILE="$RESOURCE_DIR/php-error.log"

mkdir -p "$RESOURCE_DIR"

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
    -S 127.0.0.1:8080 \
    -t "$PROJECT_ROOT/examples" \
    > /dev/null 2>&1 &

SERVER_PID=$!
echo $SERVER_PID > "$PID_FILE"

# Attente que le port 8080 soit prêt (max 5 secondes)
PORT_READY=0
for i in {1..25}; do
    if (echo > /dev/tcp/127.0.0.1/8080) >/dev/null 2>&1; then
        PORT_READY=1
        break
    fi
    sleep 0.2
done

if [ $PORT_READY -eq 1 ]; then
    echo "PHP server started (PID: $SERVER_PID) on http://127.0.0.1:8080"
else
    echo "Failed to start PHP server on port 8080."
    kill "$SERVER_PID" 2>/dev/null
    rm -f "$PID_FILE"
    exit 1
fi