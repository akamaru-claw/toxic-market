#!/bin/bash
# Deploy Toxic Market to Strato via SFTP
set -e

HOST="${STRATO_HOST:-}"
USER="${STRATO_USER:-}"
PASS="${STRATO_PASS:-}"
REMOTE_ROOT="/public/toxic-market"

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"

# Create a batch file for SFTP uploads
BATCH=$(mktemp)

cat > "$BATCH" << 'SFTP_BATCH'
mkdir /public/toxic-market
mkdir /public/toxic-market/api
mkdir /public/toxic-market/includes
mkdir /public/toxic-market/css
mkdir /public/toxic-market/js
mkdir /public/toxic-market/cards
mkdir /public/toxic-market/uploads
mkdir /public/toxic-market/data
SFTP_BATCH

# Upload PHP files
for f in api/api.php includes/auth.php includes/db.php includes/payments.php; do
    echo "put $SCRIPT_DIR/$f $REMOTE_ROOT/$f" >> "$BATCH"
done

# Upload HTML files
for f in public/*.html; do
    basename=$(basename "$f")
    echo "put $f $REMOTE_ROOT/public/$basename" >> "$BATCH"
done

# Upload CSS
for f in public/css/*.css; do
    basename=$(basename "$f")
    echo "put $f $REMOTE_ROOT/public/css/$basename" >> "$BATCH"
done

# Upload JS
for f in public/js/*.js; do
    basename=$(basename "$f")
    echo "put $f $REMOTE_ROOT/public/js/$basename" >> "$BATCH"
done

# Upload cards
for f in public/cards/*.php public/cards/*.svg; do
    basename=$(basename "$f")
    echo "put $f $REMOTE_ROOT/public/cards/$basename" >> "$BATCH"
done

# Upload data directory .htaccess and .gitkeep
echo "put $SCRIPT_DIR/data/.htaccess $REMOTE_ROOT/data/.htaccess" >> "$BATCH"
echo "put $SCRIPT_DIR/data/.gitkeep $REMOTE_ROOT/data/.gitkeep" >> "$BATCH"

# Upload uploads .htaccess
echo "put $SCRIPT_DIR/uploads/.htaccess $REMOTE_ROOT/uploads/.htaccess" >> "$BATCH"

# Upload llms.txt
echo "put $SCRIPT_DIR/public/llms.txt $REMOTE_ROOT/public/llms.txt" >> "$BATCH"

# Upload favicon
if [ -f "$SCRIPT_DIR/public/favicon.svg" ]; then
    echo "put $SCRIPT_DIR/public/favicon.svg $REMOTE_ROOT/public/favicon.svg" >> "$BATCH"
fi

echo "Uploading to Strato..."
sshpass -p "$PASS" sftp -b "$BATCH" "$USER@$HOST" 2>&1 || true

rm -f "$BATCH"
echo "Deploy complete!"