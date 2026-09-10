#!/usr/bin/env bash
# ==============================================================================
# DTC Migration & Alter Runner (Root Shortcut)
# ==============================================================================
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
exec "$SCRIPT_DIR/database/migrate.sh" "$@"
