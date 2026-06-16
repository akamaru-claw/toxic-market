#!/usr/bin/env python3
"""
Toxic Market — Deploy Script
Uploads all project files to Strato via SFTP (paramiko)
Usage: python3 deploy_toxic.py [--verify] [--dry-run]

The repo root contains the deployable PHP files (card.php, create.php, etc.).
Static assets (css, js, cards) are taken from public/.
"""
import paramiko
import os
import sys

HOST = os.environ.get('STRATO_HOST', '')
USER = os.environ.get('STRATO_USER', '')
PASS = os.environ.get('STRATO_PASS', '')
REMOTE_BASE = '/public/toxic-market'
LOCAL_BASE = os.path.dirname(os.path.abspath(__file__))

if not PASS:
    print('Fehler: STRATO_PASS nicht gesetzt.', file=sys.stderr)
    sys.exit(1)

# All files to deploy (local_path -> remote_path)
FILES = {
    # Site root (deployable PHP/HTML)
    'index.html': 'index.html',
    '404.html': '404.html',
    'card.php': 'card.php',
    'create.php': 'create.php',
    'create-auction.php': 'create-auction.php',
    'listing.php': 'listing.php',
    'seller.php': 'seller.php',
    'auction.php': 'auction.php',
    'dashboard.php': 'dashboard.php',
    'set-builder.php': 'set-builder.php',
    'sitemap.php': 'sitemap.php',
    'favicon.svg': 'favicon.svg',
    'robots.txt': 'robots.txt',
    'llms.txt': 'llms.txt',
    '.htaccess': '.htaccess',
    # API
    'api/api.php': 'api/api.php',
    # Includes
    'includes/auth.php': 'includes/auth.php',
    'includes/db.php': 'includes/db.php',
    'includes/payments.php': 'includes/payments.php',
    'includes/email.php': 'includes/email.php',
    # CSS
    'public/css/toxic.css': 'css/toxic.css',
    'public/css/toxic-card.css': 'css/toxic-card.css',
    # JS
    'public/js/toxic.js': 'js/toxic.js',
    'public/js/nostr.js': 'js/nostr.js',
    'public/js/noble-curves-bundle.js': 'js/noble-curves-bundle.js',
    # Cards
    'public/cards/card.svg.php': 'cards/card.svg.php',
    # Data / uploads access control
    'data/.htaccess': 'data/.htaccess',
    'data/.gitkeep': 'data/.gitkeep',
    'uploads/.htaccess': 'uploads/.htaccess',
    'uploads/.gitkeep': 'uploads/.gitkeep',
}


def ensure_remote_dir(sftp, path):
    try:
        sftp.stat(path)
    except FileNotFoundError:
        try:
            sftp.mkdir(path)
        except IOError as e:
            # Parent might not exist
            parent = os.path.dirname(path)
            if parent and parent != '/':
                ensure_remote_dir(sftp, parent)
                sftp.mkdir(path)
            else:
                raise e


def deploy(dry_run=False, verify=False):
    transport = paramiko.Transport((HOST, 22))
    transport.connect(username=USER, password=PASS)
    sftp = paramiko.SFTPClient.from_transport(transport)

    if verify:
        print("Verifying files on server...")
        errors = 0
        for local_rel, remote_rel in FILES.items():
            remote_path = REMOTE_BASE + '/' + remote_rel
            local_path = os.path.join(LOCAL_BASE, local_rel)
            try:
                remote_stat = sftp.stat(remote_path)
                if os.path.exists(local_path):
                    local_size = os.path.getsize(local_path)
                    match = '✅' if remote_stat.st_size == local_size else '⚠️'
                    print(f'  {match} {remote_rel}: server={remote_stat.st_size} local={local_size}')
                else:
                    print(f'  ⚠️ {remote_rel}: local file missing')
            except FileNotFoundError:
                print(f'  ❌ {remote_rel}: NOT FOUND on server')
                errors += 1
        sftp.close()
        transport.close()
        return errors

    print(f"Deploying {len(FILES)} files to {HOST}...")
    for local_rel, remote_rel in FILES.items():
        local_path = os.path.join(LOCAL_BASE, local_rel)
        remote_path = REMOTE_BASE + '/' + remote_rel

        if not os.path.exists(local_path):
            print(f'  ⚠️ SKIP {local_rel}: local file not found')
            continue

        if dry_run:
            print(f'  Would upload {local_rel} ({os.path.getsize(local_path)} bytes)')
            continue

        ensure_remote_dir(sftp, os.path.dirname(remote_path))
        sftp.put(local_path, remote_path)

        # Verify
        remote_stat = sftp.stat(remote_path)
        local_size = os.path.getsize(local_path)
        match = '✅' if remote_stat.st_size == local_size else '❌'
        print(f'  {match} {remote_rel}: {remote_stat.st_size} bytes')

    sftp.close()
    transport.close()
    print("Deploy complete!")
    return 0


if __name__ == '__main__':
    args = sys.argv[1:]
    if '--dry-run' in args:
        sys.exit(deploy(dry_run=True))
    elif '--verify' in args:
        sys.exit(deploy(verify=True))
    else:
        sys.exit(deploy())
