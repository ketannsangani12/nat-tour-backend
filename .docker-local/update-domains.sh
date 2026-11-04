#!/bin/bash

# Script to update tenant domains in the database
# Run this after importing fresh data from dev/staging

set -e

MYSQL_HOST="${MYSQL_HOST:-localhost}"
MYSQL_PORT="${MYSQL_PORT:-3307}"
MYSQL_USER="${MYSQL_USER:-nat_user}"
MYSQL_PASSWORD="${MYSQL_PASSWORD:-nat_password}"
MYSQL_DATABASE="${MYSQL_DATABASE:-nat_central}"

# Default local domain suffix
LOCAL_DOMAIN="${LOCAL_DOMAIN:-tour-backend.local}"

echo "========================================"
echo "Updating Tenant Domains for Local Docker"
echo "========================================"
echo ""
echo "Connecting to MySQL at ${MYSQL_HOST}:${MYSQL_PORT}"
echo "Target domain suffix: ${LOCAL_DOMAIN}"
echo ""

mysql -h ${MYSQL_HOST} -P ${MYSQL_PORT} -u ${MYSQL_USER} -p${MYSQL_PASSWORD} ${MYSQL_DATABASE} <<EOF
-- Show current domains
SELECT 'Current domains:' AS '';
SELECT id, domain, tenant_id FROM domains;

-- Update domains from various dev/staging environments to local
UPDATE domains SET domain = CONCAT(
    SUBSTRING_INDEX(domain, '.', 1),
    '.${LOCAL_DOMAIN}'
) WHERE domain NOT LIKE '%.${LOCAL_DOMAIN}';

-- Show updated domains
SELECT '' AS '';
SELECT 'Updated domains:' AS '';
SELECT id, domain, tenant_id FROM domains;
EOF

echo ""
echo "========================================"
echo "Domain update complete!"
echo "========================================"
echo ""
echo "Make sure your /etc/hosts file includes:"
echo "  127.0.0.1 ${LOCAL_DOMAIN}"

# Get unique subdomains and list them
mysql -h ${MYSQL_HOST} -P ${MYSQL_PORT} -u ${MYSQL_USER} -p${MYSQL_PASSWORD} ${MYSQL_DATABASE} -N -e "SELECT domain FROM domains" 2>/dev/null | while read domain; do
    echo "  127.0.0.1 ${domain}"
done

echo ""
