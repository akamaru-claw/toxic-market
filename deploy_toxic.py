#!/usr/bin/env python3
"""
Toxic Market — Deploy Script
Uploads all project files to Strato via SFTP (paramiko)
Usage: python3 deploy_toxic.py [--verify] [--dry-run]
"""
import paramiko
import os
import sys

HOST = os.environ.get('STRATO_HOST', '')
USER = os.environ.get('STRATO_USER', '')
PASS = os.environ.get('STRATO_PASS', '')
REMOTE_BASE = '/public/toxic-market'
LOCAL_BASE = '/home/jordy/.openclaw/workspace/toxic-market'

# All files to deploy (local_path -> remote_path)
FILES = {
    # HTML
    'public/index.html': 'index.html',
    'public/404.html': '404.html',
    'public/auction.html': 'auction.html',
    'public/card.html': 'card.html',
    'public/create.html': 'create.html',
    'public/create-auction.html': 'create-auction.html',
    'public/dashboard.html': 'dashboard.html',
    'public/listing.html': 'listing.html',
    'public/seller.html': 'seller.html',
    # CSS
    'public/css/toxic.css': 'css/toxic.css',
    # JS
    'public/js/app.js': 'js/app.js',
    # API
    'api/api.php': 'api/api.php',
    # Includes
    'includes/auth.php': 'includes/auth.php',
    'includes/payments.php': 'includes/payments.php',
    'includes/db.php': 'includes/db.php',
    # Cards
    'public/cards/card.svg.php': 'cards/card.svg.php',
    # Auth API
    'public/auth_api.php': 'auth_api.php',
    # .htaccess
    'public/.htaccess': '.htaccess',
}

def deploy(dry_run=False, verify=False):
    transport = paramiko.Transport((HOST, 22))
    transport.connect(username=USER, password=PASS)
    sftp = paramiko.SFTPClient.from_transport(transport)
    
    if verify:
        print("Verifying files on server...")
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
        sftp.close()
        transport.close()
        return
    
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
        
        # Ensure remote directory exists
        remote_dir = os.path.dirname(remote_path)
        try:
            sftp.stat(remote_dir)
        except FileNotFoundError:
            sftp.mkdir(remote_dir)
        
        sftp.put(local_path, remote_path)
        
        # Verify
        remote_stat = sftp.stat(remote_path)
        local_size = os.path.getsize(local_path)
        match = '✅' if remote_stat.st_size == local_size else '❌'
        print(f'  {match} {remote_rel}: {remote_stat.st_size} bytes')
    
    sftp.close()
    transport.close()
    print("Deploy complete!")

if __name__ == '__main__':
    args = sys.argv[1:]
    if '--dry-run' in args:
        deploy(dry_run=True)
    elif '--verify' in args:
        deploy(verify=True)
    else:
        deploy()