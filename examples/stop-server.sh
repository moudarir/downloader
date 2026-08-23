#!/bin/bash

set -u

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

PID_FILE="$PROJECT_ROOT/examples/resources/.php-server.pid"

if [ ! -f "$PID_FILE" ]; then
    echo "PHP server is not running."
    exit 0
fi

PID=$(cat "$PID_FILE")

if kill -0 "$PID" 2>/dev/null; then
    kill "$PID"
    echo "PHP server stopped (PID: $PID)."
else
    echo "PHP server is not running."
fi

rm -f "$PID_FILE"