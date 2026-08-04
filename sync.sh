#!/bin/bash
sudo rsync -a --delete \
    --exclude='.git' \
    --exclude='auth_system_OLD_BACKUP' \
    --exclude='sync.sh' \
    ./ /opt/lampp/htdocs/
sudo chmod -R 755 /opt/lampp/htdocs/
echo "Synced ✓"
