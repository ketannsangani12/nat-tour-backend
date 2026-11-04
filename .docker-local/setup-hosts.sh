#!/bin/bash

# Script to add local Docker domains to /etc/hosts
# Run with: sudo ./.docker-local/setup-hosts.sh

set -e

LOCAL_DOMAIN="tour-backend.local"

# Define the domains to add
DOMAINS=(
    "${LOCAL_DOMAIN}"
    "kens.${LOCAL_DOMAIN}"
    "dixies.${LOCAL_DOMAIN}"
    "apm.${LOCAL_DOMAIN}"
)

HOSTS_FILE="/etc/hosts"
MARKER="# NAT Local Docker Environment"

echo "========================================"
echo "Setting up /etc/hosts for Local Docker"
echo "========================================"

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo "Please run as root: sudo $0"
    exit 1
fi

# Remove old entries if they exist
if grep -q "$MARKER" "$HOSTS_FILE"; then
    echo "Removing old NAT Docker entries..."
    sed -i "/$MARKER/,/# End NAT/d" "$HOSTS_FILE"
fi

# Add new entries
echo "" >> "$HOSTS_FILE"
echo "$MARKER" >> "$HOSTS_FILE"
for domain in "${DOMAINS[@]}"; do
    echo "127.0.0.1 ${domain}" >> "$HOSTS_FILE"
    echo "Added: 127.0.0.1 ${domain}"
done
echo "# End NAT" >> "$HOSTS_FILE"

echo ""
echo "========================================"
echo "Hosts file updated successfully!"
echo "========================================"
echo ""
echo "You can now access:"
echo "  - https://${LOCAL_DOMAIN} (central app)"
echo "  - https://kens.${LOCAL_DOMAIN}"
echo "  - https://dixies.${LOCAL_DOMAIN}"
echo "  - https://apm.${LOCAL_DOMAIN}"
echo ""
