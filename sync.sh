#!/bin/bash
sudo rm -rf /opt/lampp/htdocs/auth_system
sudo mkdir -p /opt/lampp/htdocs/auth_system
sudo cp -r auth_system/* /opt/lampp/htdocs/auth_system/
sudo chmod -R 755 /opt/lampp/htdocs/auth_system
echo "Synced ✓"
