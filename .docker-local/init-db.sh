#!/bin/bash
set -e

# =============================================================================
# CONFIGURATION
# =============================================================================
# Add or remove tenants from this array as needed.
# Each tenant must have a corresponding data file: tenant_<name>.sql.gz
# in the .docker-local/data/ directory.

TENANTS=(
    "kens"
    "dixies"
    "apm"
    "lc"
)

# =============================================================================
# DO NOT EDIT BELOW THIS LINE (unless you know what you're doing)
# =============================================================================

DATA_DIR="/docker-entrypoint-initdb.d/data"
MYSQL_ROOT="mysql -u root -proot_password"

echo "========================================"
echo "Initializing Multi-Tenant Databases"
echo "========================================"
echo "Tenants to initialize: ${TENANTS[*]}"
echo ""

# Wait for MySQL to be ready
until mysqladmin ping -h localhost -u root -proot_password --silent; do
    echo "Waiting for MySQL..."
    sleep 2
done
echo "MySQL is ready!"

# Function to create database and grant privileges
create_database() {
    local db_name=$1
    echo "Creating database: $db_name"
    $MYSQL_ROOT -e "CREATE DATABASE IF NOT EXISTS $db_name CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    $MYSQL_ROOT -e "GRANT ALL PRIVILEGES ON $db_name.* TO 'nat_user'@'%';"
}

# Function to import database from gzipped SQL file
import_database() {
    local db_name=$1
    local sql_file=$2

    if [ -f "$sql_file" ]; then
        echo "Importing $db_name from $sql_file..."
        gunzip -c "$sql_file" | $MYSQL_ROOT "$db_name"
        echo "$db_name imported successfully!"
    else
        echo "Warning: $sql_file not found, skipping import for $db_name"
    fi
}

# Create and import central database
echo ""
echo "Setting up central database..."
create_database "nat_central"
import_database "nat_central" "$DATA_DIR/nat_central.sql.gz"

# Create and import tenant databases
echo ""
echo "Setting up tenant databases..."
for tenant in "${TENANTS[@]}"; do
    db_name="tenant_$tenant"
    sql_file="$DATA_DIR/${db_name}.sql.gz"
    create_database "$db_name"
    import_database "$db_name" "$sql_file"
done

# Grant global privileges to nat_user
$MYSQL_ROOT -e "GRANT ALL PRIVILEGES ON *.* TO 'nat_user'@'%' WITH GRANT OPTION; FLUSH PRIVILEGES;"

# Build SQL for tenant cleanup
TENANT_LIST=$(printf "'%s'," "${TENANTS[@]}")
TENANT_LIST=${TENANT_LIST%,}  # Remove trailing comma

# Update domains table for local Docker environment
echo ""
echo "Updating domains for local Docker environment..."
$MYSQL_ROOT nat_central <<EOF
-- Clean up: keep only configured tenants
DELETE FROM domains WHERE tenant_id NOT IN ($TENANT_LIST);
DELETE FROM tenants WHERE id NOT IN ($TENANT_LIST);

-- Update domains from dev/staging environments to local Docker
UPDATE domains SET domain = REPLACE(domain, '.ntv.tours', '.tour-backend.local') WHERE domain LIKE '%.ntv.tours';
UPDATE domains SET domain = REPLACE(domain, '.testingnat.com', '.tour-backend.local') WHERE domain LIKE '%.testingnat.com';

-- Show final domains
SELECT id, domain, tenant_id FROM domains;
EOF

echo ""
echo "========================================"
echo "Database initialization complete!"
echo "========================================"
echo ""
echo "Configured tenants:"
for tenant in "${TENANTS[@]}"; do
    echo "  - https://$tenant.tour-backend.local:8443"
done
echo ""
echo "Add these to your /etc/hosts file:"
echo "  127.0.0.1 tour-backend.local"
for tenant in "${TENANTS[@]}"; do
    echo "  127.0.0.1 $tenant.tour-backend.local"
done
echo ""
