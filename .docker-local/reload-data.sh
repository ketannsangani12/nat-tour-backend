#!/bin/bash

# Script to reload database data from SQL dumps
# Use this when you want to refresh data from dev/staging dumps

set -e

MYSQL_HOST="${MYSQL_HOST:-localhost}"
MYSQL_PORT="${MYSQL_PORT:-3307}"
MYSQL_USER="${MYSQL_USER:-nat_user}"
MYSQL_PASSWORD="${MYSQL_PASSWORD:-nat_password}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DATA_DIR="${DATA_DIR:-${SCRIPT_DIR}/data}"

echo "========================================"
echo "Reloading Database Data"
echo "========================================"
echo ""
echo "Data directory: ${DATA_DIR}"
echo "MySQL: ${MYSQL_HOST}:${MYSQL_PORT}"
echo ""

# Function to import a database
import_db() {
    local db_name=$1
    local sql_file=$2

    if [ -f "${sql_file}" ]; then
        echo "Importing ${db_name} from ${sql_file}..."

        # Drop and recreate database
        mysql -h ${MYSQL_HOST} -P ${MYSQL_PORT} -u ${MYSQL_USER} -p${MYSQL_PASSWORD} -e "DROP DATABASE IF EXISTS ${db_name}; CREATE DATABASE ${db_name} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

        # Import data
        if [[ "${sql_file}" == *.gz ]]; then
            gunzip -c "${sql_file}" | mysql -h ${MYSQL_HOST} -P ${MYSQL_PORT} -u ${MYSQL_USER} -p${MYSQL_PASSWORD} ${db_name}
        else
            mysql -h ${MYSQL_HOST} -P ${MYSQL_PORT} -u ${MYSQL_USER} -p${MYSQL_PASSWORD} ${db_name} < "${sql_file}"
        fi

        echo "${db_name} imported successfully!"
    else
        echo "Warning: ${sql_file} not found, skipping ${db_name}"
    fi
}

# Import databases
import_db "nat_central" "${DATA_DIR}/nat_central.sql.gz"
import_db "tenant_kens" "${DATA_DIR}/tenant_kens.sql.gz"
import_db "tenant_dixies" "${DATA_DIR}/tenant_dixies.sql.gz"
import_db "tenant_apm" "${DATA_DIR}/tenant_apm.sql.gz"

echo ""
echo "Updating domains for local environment..."

# Update domains
LOCAL_DOMAIN="tour-backend.local"
mysql -h ${MYSQL_HOST} -P ${MYSQL_PORT} -u ${MYSQL_USER} -p${MYSQL_PASSWORD} nat_central <<EOF
UPDATE domains SET domain = CONCAT(
    SUBSTRING_INDEX(domain, '.', 1),
    '.${LOCAL_DOMAIN}'
) WHERE domain NOT LIKE '%.${LOCAL_DOMAIN}';

SELECT 'Updated domains:' AS '';
SELECT id, domain, tenant_id FROM domains;
EOF

echo ""
echo "========================================"
echo "Data reload complete!"
echo "========================================"
