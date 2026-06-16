#!/bin/bash
# Deploy Toxic Market to Strato via SFTP
set -euo pipefail

HOST="${STRATO_HOST:-}"
USER="${STRATO_USER:-}"
PASS="${STRATO_PASS:-}"
REMOTE_ROOT="/public/toxic-market"

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"

if [ -z "$PASS" ]; then
    echo "Fehler: STRATO_PASS nicht gesetzt." >&2
    echo "Hinweis: STRATO_HOST und STRATO_USER haben Defaults, aber das Passwort muss aus der Umgebung kommen." >&2
    exit 1
fi

# Required directories on server
REMOTE_DIRS=(
    /public/toxic-market
    /public/toxic-market/api
    /public/toxic-market/includes
    /public/toxic-market/css
    /public/toxic-market/js
    /public/toxic-market/cards
    /public/toxic-market/uploads
    /public/toxic-market/data
    /public/toxic-market/scripts
)

# Create a batch file for SFTP uploads
BATCH=$(mktemp)

for d in "${REMOTE_DIRS[@]}"; do
    echo "mkdir $d" >> "$BATCH"
done

# Core PHP files in repo root (the deployable site)
PHP_FILES=(
    index.html 404.html
    card.php create.php create-auction.php listing.php seller.php auction.php dashboard.php set-builder.php sitemap.php
    favicon.svg robots.txt llms.txt
    .htaccess
)
for f in "${PHP_FILES[@]}"; do
    if [ -f "$SCRIPT_DIR/$f" ]; then
        echo "put $SCRIPT_DIR/$f $REMOTE_ROOT/$f" >> "$BATCH"
    else
        echo "Warnung: $f fehlt im lokalen Root, übersprungen." >&2
    fi
done

# API and includes
for f in api/api.php includes/auth.php includes/db.php includes/payments.php includes/email.php; do
    echo "put $SCRIPT_DIR/$f $REMOTE_ROOT/$f" >> "$BATCH"
done

# CSS
for f in public/css/*.css; do
    basename=$(basename "$f")
    echo "put $f $REMOTE_ROOT/css/$basename" >> "$BATCH"
done

# JS
for f in public/js/*.js; do
    basename=$(basename "$f")
    echo "put $f $REMOTE_ROOT/js/$basename" >> "$BATCH"
done

# Cards
for f in public/cards/*.php; do
    basename=$(basename "$f")
    echo "put $f $REMOTE_ROOT/cards/$basename" >> "$BATCH"
done

# Data / uploads access control
echo "put $SCRIPT_DIR/data/.htaccess $REMOTE_ROOT/data/.htaccess" >> "$BATCH"
echo "put $SCRIPT_DIR/data/.gitkeep $REMOTE_ROOT/data/.gitkeep" >> "$BATCH"
echo "put $SCRIPT_DIR/uploads/.htaccess $REMOTE_ROOT/uploads/.htaccess" >> "$BATCH"
echo "put $SCRIPT_DIR/uploads/.gitkeep $REMOTE_ROOT/uploads/.gitkeep" >> "$BATCH"

echo "Uploading ${#PHP_FILES[@]} root files + api/includes/css/js/cards to Strato..."
if command -v sshpass >/dev/null 2>&1; then
    sshpass -p "$PASS" sftp -b "$BATCH" "$USER@$HOST" 2>&1 || true
else
    echo "Fehler: sshpass nicht installiert." >&2
    rm -f "$BATCH"
    exit 1
fi

rm -f "$BATCH"
echo "Deploy complete!"
