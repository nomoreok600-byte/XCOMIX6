#!/usr/bin/env bash
# =====================================================================
#  Build the three ready-to-upload cPanel artifacts into deploy/:
#    deploy/xcomix-database.sql   schema + app-schema + demo seed (phpMyAdmin)
#    deploy/xcomix-backend.zip    the backend/ Node app (no node_modules)
#    deploy/xcomix-frontend.zip   the static frontend (out/) for public_html
#
#  Usage:
#    scripts/build-deploy.sh                       # API base = production domain
#    API_BASE=https://www.a3555bet.com scripts/build-deploy.sh
#
#  The frontend is baked to talk to $API_BASE at build time, so keep the API on
#  that domain and the uploaded site needs no edits or rebuilds.
# =====================================================================
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DEPLOY="$ROOT/deploy"
API_BASE="${API_BASE:-https://www.a3555bet.com}"

mkdir -p "$DEPLOY"

echo "==> [1/3] Combined database SQL"
{
  echo "-- XCOMIX one-shot database import (schema + social schema + demo seed)."
  echo "-- In phpMyAdmin: select your DB, open the Import tab, choose this file, Go."
  echo
  cat "$ROOT/database/schema.sql"
  echo
  cat "$ROOT/database/app-schema.sql"
  echo
  cat "$ROOT/database/seed.sql"
} > "$DEPLOY/xcomix-database.sql"
echo "    wrote deploy/xcomix-database.sql ($(wc -l < "$DEPLOY/xcomix-database.sql") lines)"

echo "==> [2/3] Backend zip"
rm -f "$DEPLOY/xcomix-backend.zip"
( cd "$ROOT/backend" && zip -rq "$DEPLOY/xcomix-backend.zip" . \
    -x 'node_modules/*' '.env' '.next/*' 'npm-debug.log*' )
echo "    wrote deploy/xcomix-backend.zip"

echo "==> [3/3] Frontend static export (NEXT_PUBLIC_API_BASE=$API_BASE)"
( cd "$ROOT/frontend" && NEXT_PUBLIC_API_BASE="$API_BASE" npx --yes next build >/dev/null )
rm -f "$DEPLOY/xcomix-frontend.zip"
( cd "$ROOT/frontend/out" && zip -rq "$DEPLOY/xcomix-frontend.zip" . )
echo "    wrote deploy/xcomix-frontend.zip"

echo
echo "Done. Upload artifacts from deploy/ as described in QUICKSTART.md."
ls -lh "$DEPLOY"
