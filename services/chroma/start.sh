#!/bin/bash

# ChromaDB Knowledge Base Service Startup Script
# Usage: ./start.sh

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$(dirname "$SCRIPT_DIR")")"
ENV_FILE="$PROJECT_ROOT/.env"

resolve_python_command() {
    local candidate="$1"
    if [ -z "$candidate" ]; then
        return 1
    fi

    if [[ "$candidate" == */* ]]; then
        if [ -x "$candidate" ]; then
            printf '%s\n' "$candidate"
            return 0
        fi
        return 1
    fi

    command -v "$candidate" 2>/dev/null
}

python_supports_service() {
    local python_bin="$1"
    "$python_bin" -c 'import chromadb, fastapi, pymysql' >/dev/null 2>&1
}

# Read Python path from .env if exists
ENV_PYTHON=""
if [ -f "$ENV_FILE" ]; then
    ENV_PYTHON=$(grep -E "^CHROMA_PYTHON_PATH=" "$ENV_FILE" | cut -d'=' -f2 | tr -d '"' | tr -d "'")
fi

PYTHON=""
WARNINGS=()

for candidate in \
    "$ENV_PYTHON" \
    "python3" \
    "python" \
    "$PROJECT_ROOT/.venv/bin/python" \
    "$PROJECT_ROOT/venv/bin/python" \
    "${VIRTUAL_ENV:+$VIRTUAL_ENV/bin/python}" \
    "${CONDA_PREFIX:+$CONDA_PREFIX/bin/python}" \
    "/opt/homebrew/bin/python3" \
    "/usr/local/bin/python3" \
    "/usr/bin/python3" \
    "/opt/anaconda3/bin/python"
do
    [ -n "$candidate" ] || continue

    resolved="$(resolve_python_command "$candidate")" || {
        if [ "$candidate" = "$ENV_PYTHON" ] && [ -n "$ENV_PYTHON" ]; then
            WARNINGS+=("Configured CHROMA_PYTHON_PATH not found or not executable: $ENV_PYTHON")
        fi
        continue
    }

    if python_supports_service "$resolved"; then
        PYTHON="$resolved"
        break
    fi

    if [ "$candidate" = "$ENV_PYTHON" ] && [ -n "$ENV_PYTHON" ]; then
        WARNINGS+=("Configured CHROMA_PYTHON_PATH is missing chromadb/fastapi/pymysql: $resolved")
    fi
done

cd "$SCRIPT_DIR"

echo "============================================"
echo "  ChromaDB Knowledge Base Service"
echo "============================================"
echo "Python: $PYTHON"
echo "Working directory: $SCRIPT_DIR"
echo "Project root: $PROJECT_ROOT"
echo ""

if [ "${#WARNINGS[@]}" -gt 0 ]; then
    for warning in "${WARNINGS[@]}"; do
        echo "Warning: $warning"
    done
    echo ""
fi

if [ -z "$PYTHON" ]; then
    echo "Error: no compatible Python runtime found."
    echo "Set CHROMA_PYTHON_PATH in $ENV_FILE, or install chromadb/fastapi/pymysql into python3."
    exit 1
fi

echo "Starting service..."
echo "Press Ctrl+C to stop"
echo ""

# Start the service
exec "$PYTHON" main.py
